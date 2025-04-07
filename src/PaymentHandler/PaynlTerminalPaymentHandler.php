<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use Exception;
use LogicException;
use Paynl\Instore;
use Paynl\Result\Transaction\Start;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\OrderTransactionCustomFieldsEnum;
use PaynlPayment\Shopware6\Enums\PaynlInstoreTransactionStatusesEnum;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\PluginHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Helper\RequestDataBagHelper;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\AdditionalTransactionInfo;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class PaynlTerminalPaymentHandler extends AbstractPaymentHandler
{
    const TERMINAL = 'terminal';
    const HASH = 'hash';
    const COOKIE_PAYNL_PIN_TERMINAL_ID = 'paynl_pin_terminal_id';

    const MAX_EXECUTION_TIME = 65;
    const ONE_YEAR_IN_SEC = 60 * 60 * 24 * 365;

    /** @var RouterInterface */
    private $router;

    /** @var RequestStack */
    private $requestStack;

    /** @var Config */
    private $config;

    /** @var Api */
    private $paynlApi;

    /** @var LoggerInterface */
    private $logger;

    /** @var CustomerHelper */
    private $customerHelper;

    /** @var ProcessingHelper */
    private $processingHelper;

    /** @var PluginHelper */
    private $pluginHelper;

    /** @var RequestDataBagHelper */
    private $requestDataBagHelper;

    /** @var OrderTransactionRepositoryInterface */
    private $orderTransactionRepository;

    /** @var string */
    private $shopwareVersion;

    public function __construct(
        RouterInterface $router,
        RequestStack $requestStack,
        Config $config,
        Api $api,
        LoggerInterface $logger,
        CustomerHelper $customerHelper,
        ProcessingHelper $processingHelper,
        PluginHelper $pluginHelper,
        RequestDataBagHelper $requestDataBagHelper,
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        string $shopwareVersion
    ) {
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->config = $config;
        $this->paynlApi = $api;
        $this->logger = $logger;
        $this->customerHelper = $customerHelper;
        $this->processingHelper = $processingHelper;
        $this->pluginHelper = $pluginHelper;
        $this->requestDataBagHelper = $requestDataBagHelper;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->shopwareVersion = $shopwareVersion;
    }


    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse {
        $orderTransaction = $this->processingHelper->getOrderTransaction($transaction->getOrderTransactionId(), $context);
        $paymentMethod = $orderTransaction->getPaymentMethod();
        $paymentMethodName = (string) $paymentMethod?->getName();

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
            $requestData = $this->fetchRequestData();
            $salesChannelId = $orderTransaction->getOrder()->getSalesChannel()->getId();
            $terminal = $this->getRequestTerminal($requestData, $salesChannelId);
            if (empty($terminal) || $paymentMethod === null) {
                return null;
            }

            $this->saveUsedTerminal($orderTransaction, $terminal, $context);

            $paynlTransaction = $this->startTransaction($orderTransaction, $terminal, $context);
            $paynlTransactionId = $paynlTransaction->getTransactionId();
            $paynlTransactionData = $paynlTransaction->getData();

            $this->logger->info('PAY. terminal transaction was successfully created: ' . $paynlTransactionId);

            $hash = (string)($paynlTransactionData[self::TERMINAL][self::HASH] ?? '');
            $this->processTerminalState($orderTransaction, $paynlTransactionId, $hash);

        } catch (Exception $e) {
            $this->logger->error(
                'Error on starting PAY. payment: ' . $e->getMessage(),
                [
                    'exception' => $e
                ]
            );

            /** @phpstan-ignore-next-line */
            throw PaymentException::syncProcessInterrupted(
                $orderTransaction->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }

        return null;
    }

    /** @throws Throwable */
    private function startTransaction(
        OrderTransactionEntity $orderTransaction,
        string $terminalId,
        Context $context
    ): Start {
        $paynlTransactionId = '';

        $returnUrl = $this->router->generate(
            'frontend.PaynlPayment.notify',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $additionalTransactionInfo = new AdditionalTransactionInfo(
            $returnUrl,
            '',
            $this->shopwareVersion,
            $this->pluginHelper->getPluginVersionFromComposer(),
            $terminalId
        );

        $this->logger->info(
            'Starting terminal transaction with terminalId ' . $terminalId,
            [
                'salesChannel' => $orderTransaction->getOrder()->getSalesChannel()->getName(),
                'cart' => [
                    'amount' => $orderTransaction->getOrder()->getAmountTotal(),
                ],
            ]
        );

        try {
            $paynlTransaction = $this->paynlApi->startTransaction(
                $orderTransaction,
                $context,
                $additionalTransactionInfo
            );

            $paynlTransactionId = $paynlTransaction->getTransactionId();
        } catch (Throwable $exception) {
            $this->logger->error('Error on starting terminal transaction with terminal ' . $terminalId, [
                'exception' => $exception
            ]);

            $this->processingHelper->storePaynlTransactionData(
                $orderTransaction,
                $paynlTransactionId,
                $context,
                $exception
            );
            throw $exception;
        }

        $this->processingHelper->storePaynlTransactionData(
            $orderTransaction,
            $paynlTransactionId,
            $context
        );

        return $paynlTransaction;
    }

    private function processTerminalState(
        OrderTransactionEntity $orderTransaction,
        string $paynlTransactionId,
        string $instoreHash
    ): void {
        set_time_limit(self::MAX_EXECUTION_TIME);

        for ($i = 0; $i < 60; $i++) {
            $status = Instore::status(['hash' => $instoreHash]);
            if ($status->getTransactionState() == PaynlInstoreTransactionStatusesEnum::INIT) {
                usleep(1000000);

                continue;
            }

            switch ($status->getTransactionState()) {
                case PaynlInstoreTransactionStatusesEnum::APPROVED:
                    $this->saveTransactionReceiptApprovalId($orderTransaction->getId(), $instoreHash);

                    $this->processingHelper->instorePaymentUpdateState(
                        $paynlTransactionId,
                        StateMachineTransitionActions::ACTION_PAID,
                        PaynlTransactionStatusesEnum::STATUS_PAID
                    );

                    return;
                case PaynlInstoreTransactionStatusesEnum::EXPIRED:
                    $this->processingHelper->instorePaymentUpdateState(
                        $paynlTransactionId,
                        StateMachineTransitionActions::ACTION_CANCEL,
                        PaynlTransactionStatusesEnum::STATUS_EXPIRED
                    );

                    return;

                case PaynlInstoreTransactionStatusesEnum::CANCELLED:
                case PaynlInstoreTransactionStatusesEnum::ERROR:
                    $this->processingHelper->instorePaymentUpdateState(
                        $paynlTransactionId,
                        StateMachineTransitionActions::ACTION_CANCEL,
                        PaynlTransactionStatusesEnum::STATUS_CANCEL
                    );

                    return;
            }

            usleep(1000000);
        }

        $this->processingHelper->instorePaymentUpdateState(
            $paynlTransactionId,
            StateMachineTransitionActions::ACTION_CANCEL,
            PaynlTransactionStatusesEnum::STATUS_EXPIRED
        );
    }

    /**
     * @param string $orderTransactionId
     * @param string $instoreHash
     */
    private function saveTransactionReceiptApprovalId(string $orderTransactionId, string $instoreHash): void
    {
        $receipt = Instore::getReceipt(['hash' => $instoreHash]);
        if (empty($receipt->getApprovalId())) {
            return;
        }

        $this->orderTransactionRepository->update([[
            'id' => $orderTransactionId,
            'customFields' => [
                OrderTransactionCustomFieldsEnum::PAYNL_PAYMENTS => [
                    OrderTransactionCustomFieldsEnum::APPROVAL_ID => $receipt->getApprovalId() ?? ''
                ]
            ]
        ]], Context::createDefaultContext());
    }

    private function saveUsedTerminal(
        OrderTransactionEntity $orderTransaction,
        string $terminalId,
        Context $context
    ): void {
        $configTerminal = $this->config->getPaymentPinTerminal($orderTransaction->getOrder()->getSalesChannelId());
        $customer = $orderTransaction->getOrder()->getOrderCustomer()->getCustomer();

        if ($customer === null) {
            return;
        }

        if (SettingsHelper::TERMINAL_CHECKOUT_SAVE_OPTION === $configTerminal) {
            $this->customerHelper->savePaynlInstoreTerminal($customer, $orderTransaction->getPaymentMethod()->getId(), $terminalId, $context);

            setcookie(self::COOKIE_PAYNL_PIN_TERMINAL_ID, $terminalId, time() + self::ONE_YEAR_IN_SEC); //NOSONAR
        }
    }

    /**
     * @param RequestDataBag $dataBag
     * @param string $salesChannelId
     * @return string
     */
    private function getRequestTerminal(RequestDataBag $dataBag, string $salesChannelId): string
    {
        $configTerminal = $this->config->getPaymentPinTerminal($salesChannelId);

        if (empty($configTerminal) || in_array($configTerminal, SettingsHelper::TERMINAL_DEFAULT_OPTIONS)) {
            return (string) $this->requestDataBagHelper->getDataBagItem('paynlInstoreTerminal', $dataBag);
        }

        return $configTerminal;
    }

    /**
     * @return RequestDataBag
     */
    private function fetchRequestData(): RequestDataBag
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            throw new LogicException('missing current request');
        }

        return new RequestDataBag($request->request->all());
    }
}
