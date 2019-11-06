<?php

declare(strict_types=1);

namespace PaynlPayment\Components;

use Exception;
use Paynl\Config as SDKConfig;
use Paynl\Paymentmethods;
use Paynl\Transaction;
use PaynlPayment\Exceptions\PaynlPaymentException;
use PaynlPayment\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Api
{
    const PAYMENT_METHOD_ID = 'id';
    const PAYMENT_METHOD_NAME = 'name';
    const PAYMENT_METHOD_VISIBLE_NAME = 'visibleName';

    /** @var Config */
    private $config;
    /** @var CustomerHelper */
    private $customerHelper;

    public function __construct(Config $config, CustomerHelper $customerHelper)
    {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
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

        $this->setAccessData();

        return Paymentmethods::getList();
    }

    private function setAccessData()
    {
        SDKConfig::setTokenCode($this->config->getTokenCode());
        SDKConfig::setApiToken($this->config->getApiToken());
        SDKConfig::setServiceId($this->config->getServiceId());
    }

    public function startPayment(AsyncPaymentTransactionStruct $transaction, SalesChannelContext $salesChannelContext)
    {
        // TODO: create custom transaction
        // TODO: set initial data to custom transaction
        $transactionInitialData = $this->getTransactionInitialData($transaction, $salesChannelContext);
        try {
            $this->setAccessData();
            // TODO: store transaction ID to custom transaction
            return Transaction::start($transactionInitialData);
        } catch (Exception $exception) {
            // TODO: store exception to custom transaction
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
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ) {
        $paymentMethodId = $this->getPaymentMethodFromContext($salesChannelContext);
        $amount = (string)$transaction->getOrder()->getAmountTotal();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();
        // TODO: implement payment id increment
        $paymentId = time();
        $testMode = $this->config->getTestMode();
        $exchangeUrl = '';
        $returnUrl = $transaction->getReturnUrl();
        $transactionInitialData = [
            // Basic data
            'paymentMethod' => $paymentMethodId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $paymentId,
            'orderNumber' => $paymentId,
            // TODO: store 'extra1'
            'testmode' => $testMode,

            // Urls
            'returnUrl' => $returnUrl,
            'exchangeUrl' => $exchangeUrl,

            // Products
            // TODO: store 'products'
        ];

        $customer = $salesChannelContext->getCustomer();
        if ($customer instanceof CustomerEntity) {
            $addresses = $this->customerHelper->formatAddresses($customer);
            $transactionInitialData = array_merge($transactionInitialData, $addresses);
        }

        file_put_contents('debug.txt', json_encode($transactionInitialData) . PHP_EOL, FILE_APPEND);

        return $transactionInitialData;
    }

    private function getPaymentMethodFromContext(SalesChannelContext $salesChannelContext): string
    {
        $shopwarePaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();
        $paymentMethod = $this->findPaymentMethod($shopwarePaymentMethodId);
        if (is_null($paymentMethod)) {
            throw new PaynlPaymentException('Could not detect payment method.');
        }
        if (empty($paymentMethod) || !is_array($paymentMethod)) {
            throw new PaynlPaymentException('Wrong payment method data.');
        }

        return $paymentMethod[self::PAYMENT_METHOD_ID];
    }
}
