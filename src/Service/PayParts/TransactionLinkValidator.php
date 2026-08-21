<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Exceptions\PayPartsLinkException;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;

class TransactionLinkValidator
{
    private const LINKABLE_STATUSES = [
        PaynlTransactionStatusesEnum::STATUS_PAID,
        PaynlTransactionStatusesEnum::STATUS_AUTHORIZE,
        PaynlTransactionStatusesEnum::STATUS_VERIFY,
        PaynlTransactionStatusesEnum::STATUS_PENDING_20,
        PaynlTransactionStatusesEnum::STATUS_PENDING_25,
        PaynlTransactionStatusesEnum::STATUS_PENDING_50,
        PaynlTransactionStatusesEnum::STATUS_PENDING_90,
    ];

    private PaynlTransactionsRepositoryInterface $paynlTransactionRepository;
    private Api $api;

    public function __construct(
        PaynlTransactionsRepositoryInterface $paynlTransactionRepository,
        Api $api
    ) {
        $this->paynlTransactionRepository = $paynlTransactionRepository;
        $this->api = $api;
    }

    /**
     * Hard security checks — failures must block linking entirely.
     *
     * @throws PayPartsLinkException
     */
    public function validateSecurity(
        OrderTransactionEntity $orderTransaction,
        SalesChannelContext $salesChannelContext
    ): void {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            throw PayPartsLinkException::accessDenied();
        }

        $orderCustomerId = $orderTransaction->getOrder()?->getOrderCustomer()?->getCustomerId();
        if ($orderCustomerId !== $customer->getId()) {
            throw PayPartsLinkException::accessDenied();
        }
    }

    /**
     * Verifies the Pay.nl transaction exists at Pay.nl, is in a linkable state,
     * its amount matches the Shopware order, and it is not already linked to a
     * different Shopware order transaction.
     *
     * @throws PayPartsLinkException
     */
    public function validatePaynlTransaction(
        string $paynlTransactionId,
        OrderTransactionEntity $orderTransaction,
        SalesChannelContext $salesChannelContext
    ): void {
        $order = $orderTransaction->getOrder();
        if ($order === null) {
            throw PayPartsLinkException::accessDenied();
        }

        $this->checkPaynlIdNotUsedByOtherOrder(
            $paynlTransactionId,
            $orderTransaction->getId(),
            $salesChannelContext->getContext()
        );

        try {
            $payOrder = $this->api->getOrderStatus(
                $paynlTransactionId,
                $order->getSalesChannelId()
            );
        } catch (Throwable $exception) {
            throw PayPartsLinkException::accessDenied();
        }

        $this->checkPaynlStatus($payOrder->getStatusCode());
        $this->checkPaynlCurrency($payOrder->getCurrency(), $order);
        $this->checkPaynlAmount($payOrder->getAmount(), $order);
    }

    public function isAlreadyLinked(string $orderTransactionId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId));
        $criteria->setLimit(1);

        return $this->paynlTransactionRepository->search($criteria, $context)->first() !== null;
    }

    /**
     * Soft business-rule warnings — stored in paynl_transactions.exception, never block storage.
     */
    public function getStorageWarning(OrderTransactionEntity $orderTransaction): ?Throwable
    {
        $state = $orderTransaction->getStateMachineState()?->getTechnicalName() ?? '';
        if ($state !== OrderTransactionStates::STATE_OPEN) {
            return PayPartsLinkException::transactionNotOpen();
        }

        $paynlId = $this->api->getPaynlPaymentMethodIdFromShopware($orderTransaction);
        if ($paynlId !== PaynlPaymentMethodsIdsEnum::CREDIT_CARD_PAYMENT) {
            return PayPartsLinkException::invalidPaymentMethod();
        }

        return null;
    }

    /** @throws PayPartsLinkException */
    private function checkPaynlIdNotUsedByOtherOrder(
        string  $paynlTransactionId,
        string  $orderTransactionId,
        Context $context
    ): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $paynlTransactionId));
        $criteria->setLimit(1);

        $existing = $this->paynlTransactionRepository->search($criteria, $context)->first();
        if ($existing !== null && $existing->getOrderTransactionId() !== $orderTransactionId) {
            throw PayPartsLinkException::accessDenied();
        }
    }

    /** @throws PayPartsLinkException */
    private function checkPaynlStatus(int $statusCode): void
    {
        if (!in_array($statusCode, self::LINKABLE_STATUSES, true)) {
            throw PayPartsLinkException::accessDenied();
        }
    }

    /** @throws PayPartsLinkException */
    private function checkPaynlCurrency(string $paynlCurrency, OrderEntity $order): void
    {
        $orderCurrency = $order->getCurrency()?->getIsoCode();
        if ($orderCurrency === null || $orderCurrency === '') {
            return;
        }

        if (strtoupper($paynlCurrency) !== strtoupper($orderCurrency)) {
            throw PayPartsLinkException::accessDenied();
        }
    }

    /** @throws PayPartsLinkException */
    private function checkPaynlAmount(float $paynlAmount, OrderEntity $order): void
    {
        $expectedCents = (int)round($order->getAmountTotal() * 100);
        $actualCents = (int)round($paynlAmount * 100);

        if ($expectedCents !== $actualCents) {
            throw PayPartsLinkException::accessDenied();
        }
    }
}
