<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Exceptions\PayPartsLinkException;
use PaynlPayment\Shopware6\Exceptions\PayPartsOrderException;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractSetPaymentOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Edit-order retry: when the customer presses Pay, guarantees the order has an OPEN transaction with a
 * PAY.Parts card method, so the PAY.nl payment is always linked to a matching transaction.
 */
class EditOrderTransactionService
{
    private AbstractSetPaymentOrderRoute $setPaymentOrderRoute;
    private OrderRepositoryInterface $orderRepository;
    private PaymentMethodRepositoryInterface $paymentMethodRepository;

    public function __construct(
        AbstractSetPaymentOrderRoute $setPaymentOrderRoute,
        OrderRepositoryInterface $orderRepository,
        PaymentMethodRepositoryInterface $paymentMethodRepository
    ) {
        $this->setPaymentOrderRoute = $setPaymentOrderRoute;
        $this->orderRepository = $orderRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * Returns the id of an open card transaction of the customer's order. Reuses the latest transaction
     * when it is open and already a card method; otherwise lets Shopware create/reopen one (also covers
     * failed and cancelled transactions) via the core "set order payment" route.
     *
     * Called when the customer presses Pay (not on page load), so just viewing the page never changes the order.
     * The order total (after a possible method switch, e.g. surcharges) must equal the session amount.
     *
     * @throws PayPartsOrderException
     * @throws PayPartsLinkException when the order is not found or not owned by the customer
     */
    public function ensureOpenCardTransaction(
        string $orderId,
        SalesChannelContext $context,
        string $paymentMethodId,
        ?int $expectedAmountCents,
        ?string $expectedCurrency
    ): string {
        if (!$this->isCardMethod($paymentMethodId, $context)) {
            throw PayPartsOrderException::invalidPaymentMethod();
        }

        $order = $this->loadOrder($orderId, $context);
        $latest = $order->getTransactions()?->first();

        if ($latest === null
            || $latest->getStateMachineState()?->getTechnicalName() !== OrderTransactionStates::STATE_OPEN
            || !$this->isCardMethod($latest->getPaymentMethodId(), $context)
        ) {
            try {
                $this->setPaymentOrderRoute->setPayment(
                    new Request([], ['orderId' => $orderId, 'paymentMethodId' => $paymentMethodId]),
                    $context
                );
            } catch (Throwable $exception) {
                throw PayPartsOrderException::paymentChangeFailed($exception);
            }

            $order = $this->loadOrder($orderId, $context);
            $latest = $order->getTransactions()?->first();
        }

        if ($latest === null) {
            throw PayPartsOrderException::transactionNotFound($orderId);
        }

        PayPartsOrderException::assertAmountMatches(
            (int) round($order->getAmountTotal() * 100),
            $order->getCurrency()?->getIsoCode() ?? $context->getCurrency()->getIsoCode(),
            $expectedAmountCents,
            $expectedCurrency
        );

        return $latest->getId();
    }

    private function isCardMethod(string $paymentMethodId, SalesChannelContext $context): bool
    {
        try {
            $method = $this->paymentMethodRepository->getPaymentMethodById($paymentMethodId, $context->getContext());
        } catch (EntityNotFoundException $exception) {
            return false;
        }

        return $this->paymentMethodRepository->isPayPartsCardPaymentMethod($method);
    }

    /**
     * The single place that loads an order for the storefront PAY.Parts flow: only orders of the
     * logged-in customer (guest or registered). Pass a Criteria to add the associations you need.
     *
     * @throws PayPartsLinkException when the customer is not logged in or the order is not theirs
     */
    public function loadCustomerOrder(string $orderId, SalesChannelContext $context, ?Criteria $criteria = null): OrderEntity
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw PayPartsLinkException::accessDenied();
        }

        $criteria ??= new Criteria();
        $criteria->setIds([$orderId]);
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()));

        $order = $this->orderRepository->search($criteria, $context->getContext())->first();
        if (!$order instanceof OrderEntity) {
            throw PayPartsLinkException::accessDenied();
        }

        return $order;
    }

    /** The customer's order with only its latest transaction. */
    private function loadOrder(string $orderId, SalesChannelContext $context): OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('currency');
        $criteria->getAssociation('transactions')
            ->addAssociation('stateMachineState')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1);

        return $this->loadCustomerOrder($orderId, $context, $criteria);
    }
}
