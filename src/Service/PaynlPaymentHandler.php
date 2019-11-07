<?php

declare(strict_types=1);

namespace PaynlPayment\Service;

use Exception;
use PaynlPayment\Components\Api;
use PaynlPayment\Helper\ProcessingHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaynlPaymentHandler implements AsynchronousPaymentHandlerInterface
{
    /** @var OrderTransactionStateHandler */
    private $transactionStateHandler;
    /** @var Api */
    private $paynlApi;
    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        Api $api,
        ProcessingHelper $processingHelper
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->paynlApi = $api;
        $this->processingHelper = $processingHelper;
    }

    /**
     * @throws AsyncPaymentProcessException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        try {
            $redirectUrl = $this->sendReturnUrlToExternalGateway($transaction, $salesChannelContext);
        } catch (Exception $e) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @throws CustomerCanceledAsyncPaymentException
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $transactionId = $transaction->getOrderTransaction()->getId();
        $paymentIsCompleted = $this->processingHelper->processPayment($transactionId, false);
        $context = $salesChannelContext->getContext();
        if ($paymentIsCompleted === true) {
            // Payment completed, set transaction status to "paid"
            $this->transactionStateHandler->pay($transaction->getOrderTransaction()->getId(), $context);
        } else {
            // Payment not completed, set transaction status to "open"
            // TODO: clarify using reopen method
            $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context);
        }
    }

    private function sendReturnUrlToExternalGateway(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): string {
        try {
            $result = $this->paynlApi->startPayment($transaction, $salesChannelContext);
            if (!empty($result->getRedirectUrl())) {
                return $result->getRedirectUrl();
            }
        } catch (Exception $exception) {
            // TODO: error handling
        }
    }
}
