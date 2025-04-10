<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\Payment;

use Exception;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Helper\PluginHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\ValueObjects\AdditionalTransactionInfo;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class InitiatePaymentAction
{
    private UrlGeneratorInterface $router;
    private Api $payAPI;
    private LoggerInterface $logger;
    private ProcessingHelper $processingHelper;
    private PluginHelper $pluginHelper;
    private TranslatorInterface $translator;
    private RequestStack $requestStack;
    private string $shopwareVersion;

    public function __construct(
        RouterInterface $router,
        Api $api,
        LoggerInterface $logger,
        ProcessingHelper $processingHelper,
        PluginHelper $pluginHelper,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        string $shopwareVersion
    ) {
        $this->router = $router;
        $this->payAPI = $api;
        $this->logger = $logger;
        $this->processingHelper = $processingHelper;
        $this->pluginHelper = $pluginHelper;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param AsyncPaymentTransactionStruct|PaymentTransactionStruct $transaction
     *
     * @throws PaymentException
     * @throws Throwable
     */
    public function pay($transaction, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $orderTransactionId = '';
        if ($transaction instanceof PaymentTransactionStruct) {
            $orderTransactionId = $transaction->getOrderTransactionId();
        }
        if ($transaction instanceof AsyncPaymentTransactionStruct) {
            $orderTransactionId = $transaction->getOrderTransaction()->getId();
        }

        $orderTransaction = $this->processingHelper->getOrderTransaction($orderTransactionId, $salesChannelContext->getContext());
        $paymentMethod = $orderTransaction->getPaymentMethod();
        $paymentMethodName = $paymentMethod ? $paymentMethod->getName() : '';

        $this->logger->info(
            'Starting order ' . $orderTransaction->getOrder()->getOrderNumber() . ' with payment: ' . $paymentMethodName,
            [
                'salesChannel' => $orderTransaction->getOrder()->getSalesChannel()->getName(),
                'cart' => [
                    'amount' => $orderTransaction->getOrder()->getAmountTotal(),
                ],
            ]
        );

        try {
            $redirectUrl = $this->createPayPayment($orderTransaction, $transaction->getReturnUrl(), $salesChannelContext);
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
                    $orderTransactionId,
                    'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
                );
            }

            /** @phpstan-ignore-next-line */
            throw PaymentException::asyncProcessInterrupted(
                $orderTransactionId,
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        return new RedirectResponse($redirectUrl);
    }

    /** @throws Throwable */
    private function createPayPayment(
        OrderTransactionEntity $orderTransaction,
        string $returnUrl,
        SalesChannelContext $salesChannelContext
    ): string {
        $payTransactionId = '';
        $exchangeUrl = $this->router->generate('frontend.PaynlPayment.notify', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $parameterUrl = parse_url($returnUrl)['query'];
        $paymentToken = explode('=', $parameterUrl)[1];
        $returnUrl = $this->router->generate('frontend.PaynlPayment.finalize-transaction', ['_sw_payment_token' => $paymentToken], UrlGeneratorInterface::ABSOLUTE_URL);

        $additionalTransactionInfo = new AdditionalTransactionInfo(
            $returnUrl,
            $exchangeUrl,
            $this->shopwareVersion,
            $this->pluginHelper->getPluginVersionFromComposer(),
            null
        );

        try {
            $payTransaction = $this->payAPI->startTransaction(
                $orderTransaction,
                $salesChannelContext->getContext(),
                $additionalTransactionInfo
            );

            $payTransactionId = $payTransaction->getTransactionId();

            $this->logger->info('PAY. transaction was successfully created: ' . $payTransactionId);
        } catch (Throwable $exception) {
            $this->logger->error('Error on starting transaction', [
                'exception' => $exception
            ]);

            $this->processingHelper->storePaynlTransactionData(
                $orderTransaction,
                $payTransactionId,
                $salesChannelContext->getContext(),
                $exception
            );
            throw $exception;
        }

        $this->processingHelper->storePaynlTransactionData(
            $orderTransaction,
            $payTransactionId,
            $salesChannelContext->getContext()
        );

        if (empty($payTransaction->getRedirectUrl())) {
            return '';
        }

        return $payTransaction->getRedirectUrl();
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