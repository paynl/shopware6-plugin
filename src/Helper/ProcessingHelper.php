<?php

declare(strict_types=1);

namespace PaynlPayment\Helper;

use Paynl\Result\Transaction\Transaction;
use PaynlPayment\Components\Api;
use PaynlPayment\Entity\PaynlTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProcessingHelper
{
    const STATUS_PENDING = 17;
    const STATUS_CANCEL = 35;
    const STATUS_PAID = 12;
    const STATUS_NEEDS_REVIEW = 21;
    const STATUS_REFUND = 20;
    const STATUS_AUTHORIZED = 18;

    /** @var Api */
    private $paynlApi;
    /** @var EntityRepositoryInterface */
    private $paynlTransactionRepository;

    public function __construct(Api $api, EntityRepositoryInterface $paynlTransactionRepository)
    {
        $this->paynlApi = $api;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    public function processPayment($transactionId, $isExchange = false): ?bool
    {
        /** @var Transaction $apiTransaction */
        $apiTransaction = $this->paynlApi->getTransaction($transactionId);
        $apiTransaction->getStatus();

        if (!$isExchange && $apiTransaction->isCanceled()) {
            throw new CustomerCanceledAsyncPaymentException(
                $transactionId,
                'Customer canceled the payment on the PayPal page'
            );
        }

        return $apiTransaction->isAuthorized();
    }

    public function createPaynlTransactionInfo(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ) {
        $transactionData = [
            'customerId' => $salesChannelContext->getCustomer()->getId(),
            'orderId' => $transaction->getOrder()->getId(),
            'paymentId' => $this->paynlApi->getPaynlPaymentMethodFromContext($salesChannelContext),
            'amount' => $transaction->getOrder()->getAmountTotal(),
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
        ];
        $this->paynlTransactionRepository->create([$transactionData], $salesChannelContext->getContext());
    }

    public function setPaynlTransactionId(
        string $orderId,
        string $paynlTransactionId,
        SalesChannelContext $salesChannelContext
    ) {
        /** @var PaynlTransactionEntity $paynlTransaction */
        $paynlTransaction = $this->paynlTransactionRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId)),
            $salesChannelContext->getContext()
        );
        $paynlTransaction->paynlTransactionId = $paynlTransactionId;
        $paynlTransaction->setId();
    }
}
