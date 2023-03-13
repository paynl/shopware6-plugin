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
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class PaynlTerminalPaymentHandler implements SynchronousPaymentHandlerInterface
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

    /** @var CustomerHelper */
    private $customerHelper;

    /** @var ProcessingHelper */
    private $processingHelper;

    /** @var PluginHelper */
    private $pluginHelper;

    /** @var RequestDataBagHelper */
    private $requestDataBagHelper;

    /** @var EntityRepositoryInterface */
    private $orderTransactionRepository;

    /** @var string */
    private $shopwareVersion;

    public function __construct(
        RouterInterface $router,
        RequestStack $requestStack,
        Config $config,
        Api $api,
        CustomerHelper $customerHelper,
        ProcessingHelper $processingHelper,
        PluginHelper $pluginHelper,
        RequestDataBagHelper $requestDataBagHelper,
        EntityRepositoryInterface $orderTransactionRepository,
        string $shopwareVersion
    ) {
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->config = $config;
        $this->paynlApi = $api;
        $this->customerHelper = $customerHelper;
        $this->processingHelper = $processingHelper;
        $this->pluginHelper = $pluginHelper;
        $this->requestDataBagHelper = $requestDataBagHelper;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return void
     * @throws Throwable
     */
    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {

        try {
            $requestData = $this->fetchRequestData();
            $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
            $paymentMethod = $transaction->getOrderTransaction()->getPaymentMethod();
            $terminal = $this->getRequestTerminal($requestData, $salesChannelId);
            if (empty($terminal) || $paymentMethod === null) {
                return;
            }

            $this->saveUsedTerminal($paymentMethod, $terminal, $salesChannelContext, $salesChannelId);

            $paynlTransaction = $this->startTransaction($transaction, $salesChannelContext, $terminal);
            $paynlTransactionId = $paynlTransaction->getTransactionId();
            $paynlTransactionData = $paynlTransaction->getData();

            $hash = (string)($paynlTransactionData[self::TERMINAL][self::HASH] ?? '');
            $this->processTerminalState($transaction, $paynlTransactionId, $hash);

        } catch (Exception $e) {
            throw new SyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }
    }

    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     * @param string $terminalId
     * @return Start
     * @throws Throwable
     */
    private function startTransaction(
        SyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $terminalId
    ): Start {
        $paynlTransactionId = '';
        $order = $transaction->getOrder();
        $orderTransaction = $transaction->getOrderTransaction();

        $returnUrl = $this->router->generate(
            'frontend.PaynlPayment.notify',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $paynlTransaction = $this->paynlApi->startTransaction(
                $order,
                $salesChannelContext,
                $returnUrl,
                '',
                $this->shopwareVersion,
                $this->pluginHelper->getPluginVersionFromComposer(),
                $terminalId
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

        return $paynlTransaction;
    }

    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param string $paynlTransactionId
     * @param string $instoreHash
     */
    private function processTerminalState(
        SyncPaymentTransactionStruct $transaction,
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
                    $this->saveTransactionReceiptApprovalId($transaction->getOrderTransaction()->getId(), $instoreHash);

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

    /**
     * @param PaymentMethodEntity $paymentMethod
     * @param string $terminalId
     * @param SalesChannelContext $salesChannelContext
     * @param string $salesChannelId
     * @return void
     */
    private function saveUsedTerminal(
        PaymentMethodEntity $paymentMethod,
        string $terminalId,
        SalesChannelContext $salesChannelContext,
        string $salesChannelId
    ): void {
        $configTerminal = $this->config->getPaymentPinTerminal($salesChannelId);
        $customer = $salesChannelContext->getCustomer();
        $context = $salesChannelContext->getContext();

        if ($customer === null) {
            return;
        }

        if (SettingsHelper::TERMINAL_CHECKOUT_SAVE_OPTION === $configTerminal) {
            $this->customerHelper->savePaynlInstoreTerminal($customer, $paymentMethod->getId(), $terminalId, $context);

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
