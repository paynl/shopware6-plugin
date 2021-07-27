<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use Exception;
use Paynl\Result\Instore\Payment;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class PaynlInstorePaymentHandler implements SynchronousPaymentHandlerInterface
{
    /** @var RouterInterface */
    private $router;
    /** @var Api */
    private $paynlApi;
    /** @var ProcessingHelper */
    private $processingHelper;
    private $shopwareVersion;

    public function __construct(
        RouterInterface $router,
        Api $api,
        ProcessingHelper $processingHelper,
        string $shopwareVersion
    ) {
        $this->router = $router;
        $this->paynlApi = $api;
        $this->processingHelper = $processingHelper;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     * @throws AsyncPaymentProcessException
     * @throws Throwable
     */
    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {
        try {
            $paynlTransactionId = $this->startTransaction($transaction, $salesChannelContext);

            $instoreResult = $this->doInstorePayment($paynlTransactionId, $_COOKIE['terminal_id'] ?? '1');
            $hash = $instoreResult->getHash();

            for ($i = 0; $i < 60; $i++) {
                $status = \Paynl\Instore::status(['hash' => $hash]);
                if ($status->getTransactionState() != 'init') {
                    switch ($status->getTransactionState()) {
                        case 'approved':
                            break;
                        case 'cancelled':
                        case 'expired':
                        case 'error':
                            break;
                    }
                }

                dump($status->getData());

                sleep(1);
            }
            dd('test');

        } catch (Exception $e) {
            dd($e);
            throw new SyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway' . PHP_EOL . $e->getMessage()
            );
        }
    }

    private function startTransaction(
        SyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): string {
        $paynlTransactionId = '';
        $exchangeUrl =
            $this->router->generate('frontend.PaynlPayment.notify', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $order = $transaction->getOrder();
        $orderTransaction = $transaction->getOrderTransaction();

        try {
            $paynlTransaction = $this->paynlApi->startTransaction(
                $order,
                $salesChannelContext,
                $exchangeUrl,
                $exchangeUrl,
                $this->shopwareVersion,
                $this->getPluginVersionFromComposer(),
            );
            dump($paynlTransaction);

            $paynlTransactionId = $paynlTransaction->getTransactionId();
        } catch (Throwable $exception) {
            dump($exception);
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

    private function doInstorePayment(string $transactionId, string $terminalId): Payment
    {
        return \Paynl\Instore::payment(['transactionId' => $transactionId, 'terminalId' => $terminalId]);
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
