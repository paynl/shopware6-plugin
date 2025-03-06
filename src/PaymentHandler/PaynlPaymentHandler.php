<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use Exception;
use LogicException;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Helper\PluginHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Helper\RequestDataBagHelper;
use PaynlPayment\Shopware6\Service\PaymentMethod\PaymentMethodTerminalService;
use PaynlPayment\Shopware6\ValueObjects\AdditionalTransactionInfo;
use Psr\Log\LoggerInterface;
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
    private UrlGeneratorInterface $router;
    private Api $payAPI;
    private LoggerInterface $logger;
    private PaymentMethodTerminalService $paymentMethodTerminalService;
    private ProcessingHelper $processingHelper;
    private PluginHelper $pluginHelper;
    private RequestDataBagHelper $requestDataBagHelper;
    private TranslatorInterface $translator;
    private RequestStack $requestStack;
    private string $shopwareVersion;

    public function __construct(
        RouterInterface $router,
        Api $api,
        LoggerInterface $logger,
        PaymentMethodTerminalService $paymentMethodTerminalService,
        ProcessingHelper $processingHelper,
        PluginHelper $pluginHelper,
        RequestDataBagHelper $requestDataBagHelper,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        string $shopwareVersion
    ) {
        $this->router = $router;
        $this->payAPI = $api;
        $this->logger = $logger;
        $this->paymentMethodTerminalService = $paymentMethodTerminalService;
        $this->processingHelper = $processingHelper;
        $this->pluginHelper = $pluginHelper;
        $this->requestDataBagHelper = $requestDataBagHelper;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        $paymentMethod = $transaction->getOrderTransaction()->getPaymentMethod();
        $paymentMethodName = $paymentMethod ? $paymentMethod->getName() : '';

        $this->logger->info(
            'Starting order ' . $transaction->getOrder()->getOrderNumber() . ' with payment: ' . $paymentMethodName,
            [
                'salesChannel' => $salesChannelContext->getSalesChannel()->getName(),
                'cart' => [
                    'amount' => $transaction->getOrder()->getAmountTotal(),
                ],
            ]
        );

        try {
            $redirectUrl = $this->sendReturnUrlToExternalGateway($transaction, $salesChannelContext);
        } catch (Exception $e) {
            $this->logger->error(
                'Error on starting PAY. payment: ' . $e->getMessage(),
                [
                    'exception' => $e
                ]
            );

            $this->displaySafeErrorMessages($e->getMessage());
            if (class_exists('Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException')) {
                throw new AsyncPaymentProcessException(
                    $transaction->getOrderTransaction()->getId(),
                    'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
                );
            }

            /** @phpstan-ignore-next-line */
            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        // Redirect to external gateway
        return new RedirectResponse($redirectUrl);
    }

    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $paymentMethod = $transaction->getOrderTransaction()->getPaymentMethod();
        $paymentMethodName = $paymentMethod ? $paymentMethod->getName() : '';
        $order = $transaction->getOrder();

        $this->logger->info(
            'Finalizing PAY. payment for order ' . $order->getOrderNumber() . ' with payment: ' . $paymentMethodName,
            [
                'salesChannel' => $salesChannelContext->getSalesChannel()->getName(),
            ]
        );

        $this->processingHelper->returnUrlActionUpdateTransactionByOrderId($order->getId());
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
        $terminal = $this->getRequestTerminal();

        $parameterUrl = parse_url($transaction->getReturnUrl())['query'];
        $paymentToken = explode('=', $parameterUrl)[1];
        $returnUrl = $this->router->generate('frontend.PaynlPayment.finalize-transaction', ['_sw_payment_token' => $paymentToken], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->paymentMethodTerminalService->storeCustomerTerminal(
            $orderTransaction->getPaymentMethod(),
            $salesChannelContext,
            $terminal
        );

        $additionalTransactionInfo = new AdditionalTransactionInfo(
            $returnUrl,
            $exchangeUrl,
            $this->shopwareVersion,
            $this->pluginHelper->getPluginVersionFromComposer(),
            $terminal ?: null
        );

        try {
            $paynlTransaction = $this->payAPI->startTransaction(
                $order,
                $salesChannelContext,
                $additionalTransactionInfo
            );

            $paynlTransactionId = $paynlTransaction->getOrderId();

            $this->logger->info('PAY. transaction was successfully created: ' . $paynlTransactionId);
        } catch (Throwable $exception) {
            $this->logger->error('Error on starting transaction', [
                'exception' => $exception
            ]);

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

        if (!empty($paynlTransaction->getPaymentUrl())) {
            return $paynlTransaction->getPaymentUrl();
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

    private function getRequestTerminal(): string
    {
        $requestData = $this->fetchRequestData();

        return (string) $this->requestDataBagHelper->getDataBagItem('paynlInstoreTerminal', $requestData);
    }

    private function fetchRequestData(): RequestDataBag
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            throw new LogicException('Missing current request');
        }

        return new RequestDataBag($request->request->all());
    }
}
