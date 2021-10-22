<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use Exception;
use Paynl\Instore;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class PaynlTerminalPaymentHandler implements SynchronousPaymentHandlerInterface
{
    /** @var RouterInterface */
    private $router;

    /** @var Config */
    private $config;

    /** @var Api */
    private $paynlApi;

    /** @var ProcessingHelper */
    private $processingHelper;

    /** @var CustomerHelper */
    private $customerHelper;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $shopwareVersion;

    public function __construct(
        RouterInterface $router,
        Config $config,
        Api $api,
        CustomerHelper $customerHelper,
        ProcessingHelper $processingHelper,
        LoggerInterface $logger,
        string $shopwareVersion
    ) {
        $this->router = $router;
        $this->config = $config;
        $this->paynlApi = $api;
        $this->customerHelper = $customerHelper;
        $this->processingHelper = $processingHelper;
        $this->logger = $logger;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @throws Throwable
     */
    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {
        $this->logger->debug('Start order processing', ['request' => $dataBag->all()]);

        try {
            $salesChannelId = $salesChannelContext->getSalesChannelId();
            $paymentMethod = $transaction->getOrderTransaction()->getPaymentMethod();
            $paymentMethodId = $paymentMethod->getId();
            $terminal = $this->getTerminalByPaymentMethod($paymentMethod, $dataBag, $salesChannelId);
            if (empty($terminal)) {
                return;
            }

            $this->saveUsedTerminal(
                $paymentMethodId,
                $terminal,
                $salesChannelContext->getCustomer(),
                $salesChannelId, $salesChannelContext->getContext()
            );

            $paynlTransactionId = $this->startTransaction($transaction, $salesChannelContext);
            $this->logger->debug('Paynl transaction was created', ['transactionId' => $paynlTransactionId]);

            $instoreResult = $this->paynlApi->doInstorePayment($paynlTransactionId, $terminal, $salesChannelId);
            $this->logger->debug('Instore payment was done', [
                'request' => [$paynlTransactionId, $terminal, $salesChannelId],
                'result' => $instoreResult->getData()
            ]);

            $hash = $instoreResult->getHash();

            $this->processTerminalState($paynlTransactionId, $hash);

        } catch (Exception $e) {
            $this->logger->error('Exception:' . $e->getMessage(), [
                'orderTransactionId' => $transaction->getOrderTransaction()->getId()
            ]);

            throw new SyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }
    }

    private function processTerminalState(string $paynlTransactionId, string $instoreHash): void
    {
        ini_set('max_execution_time', '65');

        for ($i = 0; $i < 60; $i++) {
            $status = Instore::status(['hash' => $instoreHash]);
            if ($status->getTransactionState() == 'init') {
                sleep(1);
            }

            switch ($status->getTransactionState()) {
                case 'approved':
                    $this->logger->debug('Instore payment status approved');
                    $this->processingHelper->instorePaymentUpdateState(
                        $paynlTransactionId,
                        StateMachineTransitionActions::ACTION_PAID,
                        PaynlTransactionStatusesEnum::STATUS_PAID
                    );

                    return;
                case 'cancelled':
                case 'expired':
                case 'error':
                    $this->logger->debug('Instore payment status cancelled');
                    $this->processingHelper->instorePaymentUpdateState(
                        $paynlTransactionId,
                        StateMachineTransitionActions::ACTION_CANCEL,
                        PaynlTransactionStatusesEnum::STATUS_CANCEL
                    );

                    return;
            }

            sleep(1);
        }
    }

    private function getTerminalByPaymentMethod(
        PaymentMethodEntity $paymentMethod,
        RequestDataBag $dataBag,
        string $salesChannelId
    ): string {
        $paymentMethodCustomFields = $paymentMethod->getTranslation('customFields');
        $paynlPaymentMethodId = $paymentMethodCustomFields['paynlId'] ?? null;

        if ($paynlPaymentMethodId === null) {
            return '';
        }

        if ((int)$paynlPaymentMethodId === PaynlPaymentMethodsIdsEnum::INSTORE_PAYMENT) {
            $configTerminal = $this->config->getPaymentInstoreTerminal($salesChannelId);
        }

        if ((int)$paynlPaymentMethodId === PaynlPaymentMethodsIdsEnum::PIN_PAYMENT) {
            $configTerminal = $this->config->getPaymentInstoreTerminal($salesChannelId);
        }

        if (empty($configTerminal) || in_array($configTerminal, SettingsHelper::TERMINAL_DEFAULT_OPTIONS)) {
            return (string)$dataBag->get("paynl-terminal-{$paynlPaymentMethodId}");
        }

        return $configTerminal;
    }

    /**
     * @param SyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     * @return string
     * @throws Throwable
     */
    private function startTransaction(
        SyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): string {
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
                $this->getPluginVersionFromComposer()
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

        return $paynlTransactionId;
    }

    /**
     * @param string $paymentMethodId
     * @param string $terminalId
     * @param CustomerEntity $customer
     * @param string $salesChannelId
     * @param Context $context
     * @return void
     */
    private function saveUsedTerminal(
        string $paymentMethodId,
        string $terminalId,
        CustomerEntity $customer,
        string $salesChannelId,
        Context $context
    ): void {
        $configTerminal = $this->config->getPaymentInstoreTerminal($salesChannelId);

        if (SettingsHelper::TERMINAL_CHECKOUT_SAVE_OPTION === $configTerminal) {
            $this->customerHelper->savePaynlInstoreTerminal($customer, $paymentMethodId, $terminalId, $context);
        }
    }

    /**
     * @param string $defaultValue
     * @return string
     */
    private function getPluginVersionFromComposer($defaultValue = ''): string
    {
        $composerFilePath = sprintf('%s/%s', rtrim(__DIR__, '/'), '../../composer.json');
        if (file_exists($composerFilePath)) {
            $composer = json_decode(file_get_contents($composerFilePath), true);
            return $composer['version'] ?? $defaultValue;
        }

        return $defaultValue;
    }
}
