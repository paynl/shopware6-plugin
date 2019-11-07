<?php declare(strict_types=1);

namespace PaynlPayment\Service;

use Exception;
use Paynl\Result\Transaction\Start;
use PaynlPayment\Components\Api;
use PaynlPayment\Entity\PaynlTransactionEntity;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class PaynlPaymentHandler implements AsynchronousPaymentHandlerInterface
{
    /** @var OrderTransactionStateHandler */
    private $transactionStateHandler;
    /** @var RouterInterface */
    private $router;
    /** @var Api */
    private $paynlApi;
    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        RouterInterface $router,
        Api $api,
        ProcessingHelper $processingHelper
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->router = $router;
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
        $context = $salesChannelContext->getContext();
        $orderId = $transaction->getOrder()->getId();
        $orderTransactionId = $transaction->getOrderTransaction()->getId();

        /** @var PaynlTransactionEntity $paynlTransaction */
        $paynlTransaction = $this->processingHelper->findTransactionByOrderId($orderId, $context);
        $apiTransaction = $this->processingHelper->getApiTransaction($paynlTransaction->getPaynlTransactionId());

        if ($apiTransaction->isCanceled()) {
            $this->transactionStateHandler->cancel($orderTransactionId, $context);
            throw new CustomerCanceledAsyncPaymentException(
                $orderId,
                'Customer canceled the payment on the PayPal page'
            );
        }

        // TODO: check other transaction statuses
    }

    private function sendReturnUrlToExternalGateway(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): string {
        $startTransactionException = null;
        $exchangeUrl = $this->router->generate('frontend.PaynlPayment.notify', [], UrlGeneratorInterface::ABSOLUTE_URL);
        try {
            $paynlTransaction = $this->paynlApi->startTransaction($transaction, $salesChannelContext, $exchangeUrl);
        } catch (Throwable $exception) {
            $startTransactionException = $exception;
            throw $exception;
        }

        $paynlTransactionId = $paynlTransaction instanceof Start ? $paynlTransaction->getTransactionId() : '';
        $this->processingHelper->storePaynlTransactionData(
            $transaction,
            $salesChannelContext,
            $paynlTransactionId,
            $startTransactionException
        );

        if ($paynlTransaction instanceof Start && !empty($paynlTransaction->getRedirectUrl())) {
            return $paynlTransaction->getRedirectUrl();
        }

        return '';
    }
}
