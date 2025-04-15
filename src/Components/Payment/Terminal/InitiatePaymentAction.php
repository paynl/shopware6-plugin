<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\Payment\Terminal;

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
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class InitiatePaymentAction
{
    const TERMINAL = 'terminal';
    const HASH = 'hash';
    const COOKIE_PAYNL_PIN_TERMINAL_ID = 'paynl_pin_terminal_id';

    const MAX_EXECUTION_TIME = 65;
    const ONE_YEAR_IN_SEC = 60 * 60 * 24 * 365;

    private RouterInterface $router;
    private RequestStack $requestStack;
    private Config $config;
    private Api $payAPI;
    private LoggerInterface $logger;
    private CustomerHelper $customerHelper;
    private ProcessingHelper $processingHelper;
    private PluginHelper $pluginHelper;
    private RequestDataBagHelper $requestDataBagHelper;
    private OrderTransactionRepositoryInterface $orderTransactionRepository;
    private string $shopwareVersion;

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
        $this->payAPI = $api;
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

    /** @param SyncPaymentTransactionStruct|PaymentTransactionStruct $transaction */
    public function pay($transaction, Context $context): ?RedirectResponse
    {
        $orderTransactionId = '';
        if ($transaction instanceof PaymentTransactionStruct) {
            $orderTransactionId = $transaction->getOrderTransactionId();
        }
        if ($transaction instanceof SyncPaymentTransactionStruct) {
            $orderTransactionId = $transaction->getOrderTransaction()->getId();
        }

        $orderTransaction = $this->processingHelper->getOrderTransaction($orderTransactionId, $context);
        $paymentMethod = $orderTransaction->getPaymentMethod();
        $paymentMethodName = (string) ($paymentMethod ? $paymentMethod->getName() : '');

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

            $payTransaction = $this->startTransaction($orderTransaction, $terminal, $context);
            $payTransactionId = $payTransaction->getTransactionId();
            $payTransactionData = $payTransaction->getData();

            $this->logger->info('PAY. terminal transaction was successfully created: ' . $payTransactionId);

            $hash = (string)($payTransactionData[self::TERMINAL][self::HASH] ?? '');
            $this->processTerminalState($orderTransaction, $payTransactionId, $hash, $context);

        } catch (Throwable $e) {
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
        $payTransactionId = '';

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
            $payTransaction = $this->payAPI->startTransaction(
                $orderTransaction,
                $context,
                $additionalTransactionInfo
            );

            $payTransactionId = $payTransaction->getTransactionId();
        } catch (Throwable $exception) {
            $this->logger->error('Error on starting terminal transaction with terminal ' . $terminalId, [
                'exception' => $exception
            ]);

            $this->processingHelper->storePaynlTransactionData(
                $orderTransaction,
                $payTransactionId,
                $context,
                $exception
            );
            throw $exception;
        }

        $this->processingHelper->storePaynlTransactionData(
            $orderTransaction,
            $payTransactionId,
            $context
        );

        return $payTransaction;
    }

    private function processTerminalState(
        OrderTransactionEntity $orderTransaction,
        string $payTransactionId,
        string $instoreHash,
        Context $context
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
                    $this->saveTransactionReceiptApprovalId($orderTransaction, $instoreHash, $context);

                    $this->processingHelper->instorePaymentUpdateState(
                        $payTransactionId,
                        StateMachineTransitionActions::ACTION_PAID,
                        PaynlTransactionStatusesEnum::STATUS_PAID
                    );

                    return;
                case PaynlInstoreTransactionStatusesEnum::EXPIRED:
                    $this->processingHelper->instorePaymentUpdateState(
                        $payTransactionId,
                        StateMachineTransitionActions::ACTION_CANCEL,
                        PaynlTransactionStatusesEnum::STATUS_EXPIRED
                    );

                    return;

                case PaynlInstoreTransactionStatusesEnum::CANCELLED:
                case PaynlInstoreTransactionStatusesEnum::ERROR:
                    $this->processingHelper->instorePaymentUpdateState(
                        $payTransactionId,
                        StateMachineTransitionActions::ACTION_CANCEL,
                        PaynlTransactionStatusesEnum::STATUS_CANCEL
                    );

                    return;
            }

            usleep(1000000);
        }

        $this->processingHelper->instorePaymentUpdateState(
            $payTransactionId,
            StateMachineTransitionActions::ACTION_CANCEL,
            PaynlTransactionStatusesEnum::STATUS_EXPIRED
        );
    }

    private function saveTransactionReceiptApprovalId(OrderTransactionEntity $orderTransaction, string $instoreHash, Context $context): void
    {
        $receipt = Instore::getReceipt(['hash' => $instoreHash]);
        if (empty($receipt->getApprovalId())) {
            return;
        }

        $this->orderTransactionRepository->update([[
            'id' => $orderTransaction->getId(),
            'customFields' => [
                OrderTransactionCustomFieldsEnum::PAYNL_PAYMENTS => [
                    OrderTransactionCustomFieldsEnum::APPROVAL_ID => $receipt->getApprovalId() ?? ''
                ]
            ]
        ]], $context);
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