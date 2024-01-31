<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use Exception;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Helper\PluginHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\ValueObjects\AdditionalTransactionInfo;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class PaynlPaymentHandler implements AsynchronousPaymentHandlerInterface
{
    /** @var OrderTransactionStateHandler */
    private $transactionStateHandler;
    /** @var UrlGeneratorInterface */
    private $router;
    /** @var Api */
    private $paynlApi;
    /** @var ProcessingHelper */
    private $processingHelper;
    /** @var PluginHelper */
    private $pluginHelper;
    /** @var TranslatorInterface */
    private $translator;
    /** @var RequestStack */
    private $requestStack;
    /** @var string */
    private $shopwareVersion;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        RouterInterface $router,
        Api $api,
        ProcessingHelper $processingHelper,
        PluginHelper $pluginHelper,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        string $shopwareVersion
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->router = $router;
        $this->paynlApi = $api;
        $this->processingHelper = $processingHelper;
        $this->pluginHelper = $pluginHelper;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     * @throws PaymentException
     * @throws Throwable
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        try {
            $redirectUrl = $this->sendReturnUrlToExternalGateway($transaction, $salesChannelContext);
        } catch (Exception $e) {
            $this->displaySafeErrorMessages($e->getMessage());
            if (class_exists('Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException')) {
                throw new AsyncPaymentProcessException(
                    $transaction->getOrderTransaction()->getId(),
                    'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
                );
            }

            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     * @throws Exception
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $this->processingHelper->returnUrlActionUpdateTransactionByOrderId($transaction->getOrder()->getId());
    }

    private function sendReturnUrlToExternalGateway(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): string {
        $paynlTransactionId = '';
        $exchangeUrl =
            $this->router->generate('frontend.PaynlPayment.notify', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $order = $transaction->getOrder();
        $orderTransaction = $transaction->getOrderTransaction();

        $additionalTransactionInfo = new AdditionalTransactionInfo(
            $transaction->getReturnUrl(),
            $exchangeUrl,
            $this->shopwareVersion,
            $this->pluginHelper->getPluginVersionFromComposer(),
            null
        );

        try {
            $paynlTransaction = $this->paynlApi->startTransaction(
                $orderTransaction,
                $order,
                $salesChannelContext,
                $additionalTransactionInfo
            );

            $paynlTransactionId = $paynlTransaction->getTransactionId();
        } catch (Throwable $exception) {
            $this->processingHelper->storePaynlTransactionData(
                $order,
                $orderTransaction,
                $salesChannelContext,
                $paynlTransactionId,
                $exception
            );
            throw $exception;
        }

        $this->processingHelper->storePaynlTransactionData(
            $order,
            $orderTransaction,
            $salesChannelContext,
            $paynlTransactionId
        );

        if (!empty($paynlTransaction->getRedirectUrl())) {
            return $paynlTransaction->getRedirectUrl();
        }

        return '';
    }

    private function displaySafeErrorMessages(string $errorMessage)
    {
        if (strpos(strtolower($errorMessage), 'minimum amount') !== false) {
            $flashBagMessage = $this->translator->trans('checkout.messages.orderAmountPaymentError');
        } else {
            $flashBagMessage = $this->translator->trans('checkout.messages.orderDefaultError');
        }

        $this->requestStack->getSession()->getFlashBag()->add('warning', $flashBagMessage);
    }
}
