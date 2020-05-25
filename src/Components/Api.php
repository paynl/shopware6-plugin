<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use Paynl\Config as SDKConfig;
use Paynl\Paymentmethods;
use Paynl\Result\Transaction\Start;
use Paynl\Transaction;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Paynl\Result\Transaction as Result;
use Exception;

class Api
{
    const PAYMENT_METHOD_ID = 'id';
    const PAYMENT_METHOD_NAME = 'name';
    const PAYMENT_METHOD_VISIBLE_NAME = 'visibleName';
    const PAYMENT_METHOD_BANKS = 'banks';

    const ACTION_PENDING = 'pending';

    /** @var Config */
    private $config;
    /** @var CustomerHelper */
    private $customerHelper;
    /** @var EntityRepositoryInterface */
    private $productRepository;

    public function __construct(
        Config $config,
        CustomerHelper $customerHelper,
        EntityRepositoryInterface $productRepository
    ) {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
        $this->productRepository = $productRepository;
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

        $this->setCredentials();

        return Paymentmethods::getList();
    }

    private function setCredentials(): void
    {
        SDKConfig::setTokenCode($this->config->getTokenCode());
        SDKConfig::setApiToken($this->config->getApiToken());
        SDKConfig::setServiceId($this->config->getServiceId());
    }

    public function startTransaction(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $exchangeUrl
    ): Start {
        $transactionInitialData = $this->getTransactionInitialData($transaction, $salesChannelContext, $exchangeUrl);
        $this->setCredentials();

        return Transaction::start($transactionInitialData);
    }

    public function getTransaction(string $transactionId): ResultTransaction
    {
        $this->setCredentials();

        return Transaction::get($transactionId);
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     * @param string $exchangeUrl
     * @return mixed[]
     * @throws PaynlPaymentException
     * @throws InconsistentCriteriaIdsException
     */
    private function getTransactionInitialData(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $exchangeUrl
    ): array {
        $shopwarePaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();
        $paynlPaymentMethodId = $this->getPaynlPaymentMethodId($shopwarePaymentMethodId);
        $amount = $transaction->getOrder()->getAmountTotal();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();
        $extra1 = $transaction->getOrder()->getId();
        $testMode = $this->config->getTestMode();
        $returnUrl = $transaction->getReturnUrl();
        $transactionInitialData = [
            // Basic data
            'paymentMethod' => $paynlPaymentMethodId,
            'amount' => $amount,
            'currency' => $currency,
            'extra1' => $extra1,
            'testmode' => $testMode,
            'orderNumber' => $transaction->getOrder()->getOrderNumber(),

            // Urls
            'returnUrl' => $returnUrl,
            'exchangeUrl' => $exchangeUrl,

            // Products
            'products' => $this->getOrderProducts($transaction, $salesChannelContext->getContext()),
        ];

        $customer = $salesChannelContext->getCustomer();
        if ($customer instanceof CustomerEntity) {
            $addresses = $this->customerHelper->formatAddresses($customer);
            $transactionInitialData = array_merge($transactionInitialData, $addresses);
        }

        return $transactionInitialData;
    }

    public function getPaynlPaymentMethodId(string $shopwarePaymentMethodId): int
    {
        $paynlPaymentMethod = $this->findPaynlPaymentMethod($shopwarePaymentMethodId);
        if (is_null($paynlPaymentMethod)) {
            throw new PaynlPaymentException('Could not detect payment method.');
        }
        if (empty($paynlPaymentMethod) || !is_array($paynlPaymentMethod)) {
            throw new PaynlPaymentException('Wrong payment method data.');
        }

        return (int)$paynlPaymentMethod[self::PAYMENT_METHOD_ID];
    }

    /**
     * @param string $shopwarePaymentMethodId
     * @return mixed[]|null
     */
    private function findPaynlPaymentMethod(string $shopwarePaymentMethodId): ?array
    {
        $paymentMethods = $this->getPaymentMethods();
        foreach ($paymentMethods as $paymentMethod) {
            if ($shopwarePaymentMethodId === md5($paymentMethod[self::PAYMENT_METHOD_ID])) { //NOSONAR
                return $paymentMethod;
            }
        }

        return null;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Context $context
     * @return mixed[]
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderProducts(AsyncPaymentTransactionStruct $transaction, Context $context): array
    {
        /** @var OrderLineItemCollection*/
        $orderLineItems = $transaction->getOrder()->getLineItems();
        $productsItems = $orderLineItems->filterByProperty('type', 'product');
        $productsIds = [];

        foreach ($productsItems as $product) {
            $productsIds[] = $product->getReferencedId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.id', $productsIds));
        $entities = $this->productRepository->search($criteria, $context);
        $elements = $entities->getElements();

        foreach ($productsItems as $item) {
            $products[] = [
                'id' => $elements[$item->getReferencedId()]->get('autoIncrement'),
                'name' => $item->getLabel(),
                'price' => $item->getUnitPrice(),
                'vatPercentage' => $item->getPrice()->getCalculatedTaxes()->first()->getTaxRate(),
                'qty' => $item->getPrice()->getQuantity(),
                'type' => Transaction::PRODUCT_TYPE_ARTICLE,
            ];
        }

        $products[] = [
            'id' => 'shipping',
            'name' => 'Shipping',
            'price' => $transaction->getOrder()->getShippingTotal(),
            'vatPercentage' => $transaction->getOrder()->getShippingCosts()->getCalculatedTaxes()->getAmount(),
            'qty' => 1,
            'type' => Transaction::PRODUCT_TYPE_SHIPPING,
        ];

        return $products;
    }

    /**
     * @param string $transactionID
     * @param int|float|null $amount
     * @param string $description
     * @return Result\Refund
     * @throws \Exception
     */
    public function refund(string $transactionID, $amount, string $description = ''): Result\Refund
    {
        if (!$this->config->isRefundAllowed()) {
            $message = 'PAY-PLUGIN-001: Your did not activate refund option in plugin, check %s';
            $url = sprintf(
                '<a target="_blank" href="https://docs.pay.nl/plugins?language=en#shopware-six-errordefinitions">
%s</a>',
                'docs.pay.nl/shopware6/instructions'
            );

            throw new \Exception(sprintf($message, $url));
        }
        $this->setCredentials();

        try {
            return \Paynl\Transaction::refund($transactionID, $amount, $description);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function isValidCredentials($tokenCode, $apiToken, $serviceId)
    {
        try {
            SDKConfig::setTokenCode($tokenCode);
            SDKConfig::setApiToken($apiToken);
            SDKConfig::setServiceId($serviceId);

            $paymentMethods = Paymentmethods::getList();

            return !empty($paymentMethods);
        } catch (Exception $exception) {
            return false;
        }
    }
}
