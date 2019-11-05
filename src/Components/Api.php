<?php

declare(strict_types=1);

namespace PaynlPayment\Components;

use Exception;
use Paynl\Config as SDKConfig;
use Paynl\Paymentmethods;
use Paynl\Transaction;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

class Api
{
    const PAYMENT_METHOD_ID = 'id';
    const PAYMENT_METHOD_NAME = 'name';
    const PAYMENT_METHOD_VISIBLE_NAME = 'visibleName';

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return mixed[]
     */
    public function getPaymentMethods(): array
    {
        // plugin doesn't configured, nothing to do
        if (empty($this->config->getTokenCode())
            || empty($this->config->getApiToken())
            || empty($this->config->getServiceId())) {
            return [];
        }

        $this->loginSDK();

        return Paymentmethods::getList();
    }

    private function loginSDK()
    {
        SDKConfig::setTokenCode($this->config->getTokenCode());
        SDKConfig::setApiToken($this->config->getApiToken());
        SDKConfig::setServiceId($this->config->getServiceId());
    }

    public function startPayment(string $shopwarePaymentMethodId, AsyncPaymentTransactionStruct $transaction)
    {
        // TODO: create custom transaction
        // TODO: set initial data to custom transaction

        $paymentMethod = $this->findPaymentMethod($shopwarePaymentMethodId);
        file_put_contents('debug.txt', json_encode($paymentMethod) . PHP_EOL, FILE_APPEND);
        $paymentMethodId = $paymentMethod[self::PAYMENT_METHOD_ID];
        file_put_contents('debug.txt', json_encode($paymentMethodId) . PHP_EOL, FILE_APPEND);
        // TODO: throw exception if $paymentMethodId is null

        // TODO: implement payment id increment
        $paymentId = time();

        file_put_contents('debug.txt', $transaction->getOrder()->getAmountTotal() . PHP_EOL, FILE_APPEND);
        file_put_contents('debug.txt', $paymentMethodId . PHP_EOL, FILE_APPEND);
        file_put_contents('debug.txt', $transaction->getOrder()->getCurrencyId() . PHP_EOL, FILE_APPEND);
        file_put_contents('debug.txt', $paymentId . PHP_EOL, FILE_APPEND);
        file_put_contents('debug.txt', $transaction->getReturnUrl() . PHP_EOL, FILE_APPEND);

        $transactionInitialData = $this->getTransactionInitialData(
            (string)$transaction->getOrder()->getAmountTotal(),
            (string)$paymentMethodId,
            '978', // TODO: get currency
            (string)$paymentId,
            $transaction->getReturnUrl()
        );
        file_put_contents('debug.txt', json_encode($transactionInitialData) . PHP_EOL, FILE_APPEND);

        try {
            $this->loginSDK();
            // TODO: store transaction ID to custom transaction
            $result = Transaction::start($transactionInitialData);
            file_put_contents('debug.txt', json_encode($result) . PHP_EOL, FILE_APPEND);
            return $result;
        } catch (Exception $exception) {
            // TODO: store exception to custom transaction
            file_put_contents('debug.txt', json_encode($exception) . PHP_EOL, FILE_APPEND);
            throw $exception;
        }
    }

    /**
     * @param string $shopwarePaymentMethodId
     * @return mixed[]|null
     */
    private function findPaymentMethod(string $shopwarePaymentMethodId): ?array
    {
        $paymentMethods = $this->getPaymentMethods();
        foreach ($paymentMethods as $paymentMethod) {
            if ($shopwarePaymentMethodId === md5($paymentMethod[self::PAYMENT_METHOD_ID])) {
                return $paymentMethod;
            }
        }

        return null;
    }

    private function getTransactionInitialData(
        string $amount,
        string $paymentMethodId,
        string $currency,
        string $paymentId,
        string $returnUrl
    ) {
        $transactionInitialData = [
            // Basic data
            'amount' => $amount,
            //'paymentMethod' => $paymentMethodId,
            //'currency' => $currency,
            //'description' => $paymentId,
            //'orderNumber' => $paymentId,
            // TODO: store 'extra1'
            // TODO: store 'testmode'

            // Urls
            'returnUrl' => $returnUrl,
            //'exchangeUrl' => '',

            // Products
            // TODO: store 'products'
        ];

        // TODO: add user formatted address to data

        return $transactionInitialData;
    }
}
