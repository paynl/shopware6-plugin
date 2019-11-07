<?php

declare(strict_types=1);

namespace PaynlPayment\Helper;

use Paynl\Result\Transaction\Status;
use Paynl\Result\Transaction\Transaction;
use PaynlPayment\Components\Api;
use PaynlPayment\Exceptions\PaynlPaymentException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;

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

    public function __construct(Api $api)
    {
        $this->paynlApi = $api;
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
}
