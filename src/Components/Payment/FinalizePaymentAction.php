<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\Payment;

use Exception;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;

class FinalizePaymentAction
{
    private LoggerInterface $logger;
    private ProcessingHelper $processingHelper;

    public function __construct(
        LoggerInterface $logger,
        ProcessingHelper $processingHelper
    ) {
        $this->logger = $logger;
        $this->processingHelper = $processingHelper;
    }

    /**
     * @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction
     *
     * @throws Exception
     */
    public function finalize($transaction, Context $context): void
    {
        $orderTransactionId = '';
        if ($transaction instanceof PaymentTransactionStruct) {
            $orderTransactionId = $transaction->getOrderTransactionId();
        }
        if ($transaction instanceof AsyncPaymentTransactionStruct) {
            $orderTransactionId = $transaction->getOrderTransaction()->getId();
        }

        $orderTransaction = $this->processingHelper->getOrderTransaction($orderTransactionId, $context);
        $paymentMethod = $orderTransaction->getPaymentMethod();
        $paymentMethodName = $paymentMethod ? $paymentMethod->getName() : '';
        $order = $orderTransaction->getOrder();

        $this->logger->info(
            'Finalizing PAY. payment for order ' . $order->getOrderNumber() . ' with payment: ' . $paymentMethodName,
            [
                'salesChannel' => $order->getSalesChannel()->getName(),
            ]
        );

        $this->processingHelper->returnUrlActionUpdateTransactionByOrderId($order->getId());
    }
}