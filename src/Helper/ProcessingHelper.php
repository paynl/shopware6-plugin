<?php

declare(strict_types=1);

namespace PaynlPayment\Helper;

use Exception;
use Paynl\Result\Transaction\Transaction;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Components\Api;
use PaynlPayment\Entity\PaynlTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Context;
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

    public function storePaynlTransactionData(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $paynlTransactionId,
        ?Exception $exception
    ) {
        $transactionData = [
            'paynlTransactionId' => $paynlTransactionId,
            'customerId' => $salesChannelContext->getCustomer()->getId(),
            'orderId' => $transaction->getOrder()->getId(),
            'paymentId' => $this->paynlApi->getPaynlPaymentMethodFromContext($salesChannelContext),
            'amount' => $transaction->getOrder()->getAmountTotal(),
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'exception' => json_encode($exception)
        ];
        $this->paynlTransactionRepository->create([$transactionData], $salesChannelContext->getContext());
    }

    public function findTransactionByOrderId(string $orderId, Context $context)
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId));
        return $this->paynlTransactionRepository->search($criteria, $context)->first();
    }

    public function getApiTransaction(string $transactionId): ResultTransaction
    {
        return $this->paynlApi->getTransaction($transactionId);
    }
}
