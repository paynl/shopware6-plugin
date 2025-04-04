<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use Exception;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Helper\PluginHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\ValueObjects\AdditionalTransactionInfo;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class PaynlPaymentHandler extends AbstractPaymentHandler
{
    /** @var OrderTransactionStateHandler */
    private $transactionStateHandler;
    /** @var UrlGeneratorInterface */
    private $router;
    /** @var Api */
    private $paynlApi;
    /** @var LoggerInterface */
    private $logger;
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
        LoggerInterface $logger,
        ProcessingHelper $processingHelper,
        PluginHelper $pluginHelper,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        string $shopwareVersion
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->router = $router;
        $this->paynlApi = $api;
        $this->logger = $logger;
        $this->processingHelper = $processingHelper;
        $this->pluginHelper = $pluginHelper;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    /**
     * @throws PaymentException
     * @throws Throwable
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): RedirectResponse {
        $orderTransaction = $this->processingHelper->getOrderTransaction($transaction->getOrderTransactionId(), $context);
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
            $redirectUrl = $this->sendReturnUrlToExternalGateway($transaction, $orderTransaction, $context);
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
                    $transaction->getOrderTransactionId(),
                    'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
                );
            }

            /** @phpstan-ignore-next-line */
            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        return new RedirectResponse($redirectUrl);
    }

    /** @throws Exception */
    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $orderTransaction = $this->processingHelper->getOrderTransaction($transaction->getOrderTransactionId(), $context);
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

    private function sendReturnUrlToExternalGateway(
        PaymentTransactionStruct $paymentTransaction,
        OrderTransactionEntity $orderTransaction,
        Context $context
    ): string {
        $paynlTransactionId = '';
        $exchangeUrl =
            $this->router->generate('frontend.PaynlPayment.notify', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $order = $orderTransaction->getOrder();

        $parameterUrl = parse_url($paymentTransaction->getReturnUrl())['query'];
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
            if (!$order->getSalesChannel()) {
                throw new PaynlPaymentException();
            }

            $paynlTransaction = $this->paynlApi->startTransaction(
                $orderTransaction,
                $order,
                $context,
                $additionalTransactionInfo
            );

            $paynlTransactionId = $paynlTransaction->getTransactionId();

            $this->logger->info('PAY. transaction was successfully created: ' . $paynlTransactionId);
        } catch (Throwable $exception) {
            $this->logger->error('Error on starting transaction', [
                'exception' => $exception
            ]);

            $this->processingHelper->storePaynlTransactionData(
                $order,
                $orderTransaction,
                $paynlTransactionId,
                $context,
                $exception
            );
            throw $exception;
        }

        $this->processingHelper->storePaynlTransactionData(
            $order,
            $orderTransaction,
            $paynlTransactionId,
            $context
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
