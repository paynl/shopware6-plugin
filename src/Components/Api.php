<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use Paynl\Config as SDKConfig;
use Paynl\Instore;
use Paynl\Paymentmethods;
use Paynl\Result\Transaction\Start;
use Paynl\Transaction;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Shopware6\Enums\CustomerCustomFieldsEnum;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\TransactionLanguageHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Product\ProductRepositoryInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Paynl\Result\Transaction as Result;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Api
{
    const PAYMENT_METHOD_ID = 'id';
    const PAYMENT_METHOD_NAME = 'name';
    const PAYMENT_METHOD_VISIBLE_NAME = 'visibleName';
    const PAYMENT_METHOD_BANKS = 'banks';
    const PAYMENT_METHOD_BRAND = 'brand';
    const PAYMENT_METHOD_BRAND_DESCRIPTION = 'public_description';
    const PAYMENT_METHOD_BRAND_ID = 'id';

    const ACTION_PENDING = 'pending';

    /** @var Config */
    private $config;
    /** @var CustomerHelper */
    private $customerHelper;
    /** @var TransactionLanguageHelper */
    private $transactionLanguageHelper;
    /** @var ProductRepositoryInterface */
    private $productRepository;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var TranslatorInterface */
    private $translator;
    /** @var RequestStack */
    private $requestStack;

    public function __construct(
        Config $config,
        CustomerHelper $customerHelper,
        TransactionLanguageHelper $transactionLanguageHelper,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        RequestStack $requestStack
    ) {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
        $this->transactionLanguageHelper = $transactionLanguageHelper;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
    }

    /**
     * @return mixed[]
     */
    public function getPaymentMethods(string $salesChannelId): array
    {
        // plugin doesn't configured, nothing to do
        if (empty($this->config->getTokenCode($salesChannelId))
            || empty($this->config->getApiToken($salesChannelId))
            || empty($this->config->getServiceId($salesChannelId))) {
            return [];
        }

        $this->setCredentials($salesChannelId);

        return Paymentmethods::getList();
    }

    private function setCredentials(string $salesChannelId): void
    {
        SDKConfig::setTokenCode($this->config->getTokenCode($salesChannelId));
        SDKConfig::setApiToken($this->config->getApiToken($salesChannelId));
        SDKConfig::setServiceId($this->config->getServiceId($salesChannelId));
    }

    public function startTransaction(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext,
        string $returnUrl,
        string $exchangeUrl,
        string $shopwareVersion,
        string $pluginVersion,
        ?string $terminalId = null
    ): Start {
        $transactionInitialData = $this->getTransactionInitialData(
            $order,
            $salesChannelContext,
            $returnUrl,
            $exchangeUrl,
            $shopwareVersion,
            $pluginVersion,
            $terminalId
        );

        $this->setCredentials($salesChannelContext->getSalesChannel()->getId());

        return Transaction::start($transactionInitialData);
    }

    public function getTransaction(string $transactionId, string $salesChannelId): ResultTransaction
    {
        $this->setCredentials($salesChannelId);

        return Transaction::get($transactionId);
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $salesChannelContext
     * @param string $returnUrl
     * @param string $exchangeUrl
     * @param string $shopwareVersion
     * @param string $pluginVersion
     * @param string|null $terminalId
     * @return array
     * @throws PaynlPaymentException
     */
    private function getTransactionInitialData(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext,
        string $returnUrl,
        string $exchangeUrl,
        string $shopwareVersion,
        string $pluginVersion,
        ?string $terminalId = null
    ): array {
        $shopwarePaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $paynlPaymentMethodId = $this->getPaynlPaymentMethodId($shopwarePaymentMethodId, $salesChannelId);
        $amount = $order->getAmountTotal();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();
        $testMode = $this->config->getTestMode($salesChannelId);
        $orderNumber = $order->getOrderNumber();
        $transactionInitialData = [
            // Basic data
            'paymentMethod' => $paynlPaymentMethodId,
            'amount' => $amount,
            'currency' => $currency,
            'testmode' => $testMode,
            'orderNumber' => $orderNumber,
            'description' => sprintf(
                '%s %s',
                $this->translator->trans('transactionLabels.order'),
                $orderNumber
            ),

            // Urls
            'returnUrl' => $returnUrl,
            'exchangeUrl' => $exchangeUrl,

            // Products
            'products' => $this->getOrderProducts($order, $salesChannelContext->getContext()),
            'object' => sprintf('Shopware v%s %s', $shopwareVersion, $pluginVersion),
        ];

        $customer = $salesChannelContext->getCustomer();
        $customerCustomFields = $customer->getCustomFields();
        $paymentSelectedData = $customerCustomFields[CustomerCustomFieldsEnum::PAYMENT_METHODS_SELECTED_DATA] ?? [];
        $bank = (int)($paymentSelectedData[$shopwarePaymentMethodId]['issuer'] ?? $this->requestStack->getSession()->get('paynlIssuer'));

        if (!empty($bank)) {
            $orderCustomFields = (array)$order->getCustomFields();
            $orderCustomFields['paynlIssuer'] = $bank;

            $data[] = [
                'id' => $order->getId(),
                'customFields' => $orderCustomFields
            ];

            $this->orderRepository->upsert($data, $salesChannelContext->getContext());

            $transactionInitialData['bank'] = $bank;
        }

        if ($paynlPaymentMethodId === PaynlPaymentMethodsIdsEnum::PIN_PAYMENT) {
            $transactionInitialData['bank'] = $terminalId;
        }

        if ($customer instanceof CustomerEntity) {
            $addresses = $this->customerHelper->formatAddresses($customer, $salesChannelId);
            $transactionInitialData = array_merge($transactionInitialData, $addresses);
        }

        if ($this->config->getSinglePaymentMethodInd($salesChannelId)) {
            unset($transactionInitialData['paymentMethod']);
        }

        if ($this->config->getPaymentScreenLanguage($salesChannelId)) {
            $transactionInitialData['enduser']['language'] = $this->transactionLanguageHelper->getLanguageForOrder($order);
        }

        return $transactionInitialData;
    }

    public function getPaynlPaymentMethodId(string $shopwarePaymentMethodId, string $salesChannelId): int
    {
        if ($this->config->getSinglePaymentMethodInd($salesChannelId)) {
            return 0;
        }

        $paynlPaymentMethod = $this->findPaynlPaymentMethod($shopwarePaymentMethodId, $salesChannelId);
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
    private function findPaynlPaymentMethod(string $shopwarePaymentMethodId, string $salesChannelId): ?array
    {
        $paymentMethods = $this->getPaymentMethods($salesChannelId);
        foreach ($paymentMethods as $paymentMethod) {
            if ($shopwarePaymentMethodId === md5($paymentMethod[self::PAYMENT_METHOD_ID])) { //NOSONAR
                return $paymentMethod;
            }
        }

        return null;
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return mixed[]
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderProducts(OrderEntity $order, Context $context): array
    {
        /** @var OrderLineItemCollection*/
        $orderLineItems = $order->getLineItems();
        $productsItems = $orderLineItems->filterByProperty('type', 'product');
        $productsIds = [];

        foreach ($productsItems as $product) {
            $productsIds[] = $product->getReferencedId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.id', $productsIds));
        $entities = $this->productRepository->search($criteria, $context);
        $elements = $entities->getElements();

        /** @var OrderLineItemEntity $item */
        foreach ($productsItems as $item) {
            $vatPercentage = 0;
            if ($item->getPrice()->getCalculatedTaxes()->first() !== null) {
                $vatPercentage = $item->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
            }

            $products[] = [
                'id' => $elements[$item->getReferencedId()]->get('autoIncrement'),
                'name' => $item->getLabel(),
                'price' => $item->getUnitPrice(),
                'vatPercentage' => $vatPercentage,
                'qty' => $item->getPrice()->getQuantity(),
                'type' => Transaction::PRODUCT_TYPE_ARTICLE,
            ];
        }

        $products[] = [
            'id' => 'shipping',
            'name' => 'Shipping',
            'price' => $order->getShippingTotal(),
            'vatPercentage' => $order->getShippingCosts()->getCalculatedTaxes()->getAmount(),
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
    public function refund(
        string $transactionID,
        $amount,
        string $salesChannelId,
        string $description = ''
    ): Result\Refund {
        if (!$this->config->isRefundAllowed($salesChannelId)) {
            $message = 'PAY-PLUGIN-001: You did not activate refund option in plugin, check %s';
            $url = sprintf(
                '<a target="_blank" href="https://docs.pay.nl/plugins?language=en#shopware-six-errordefinitions">
%s</a>',
                'docs.pay.nl/shopware6/instructions'
            );

            throw new \Exception(sprintf($message, $url));
        }
        $this->setCredentials($salesChannelId);

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

    public function isValidStoredCredentials(string $salesChannelId): bool
    {
        list($tokenCode, $apiToken, $serviceId) = [
            $this->config->getTokenCode($salesChannelId),
            $this->config->getApiToken($salesChannelId),
            $this->config->getServiceId($salesChannelId),
        ];

        if (empty($tokenCode) || empty($apiToken) || empty($serviceId)) {
            return false;
        }

        return $this->isValidCredentials($tokenCode, $apiToken, $serviceId);
    }

    /**
     * @param string $salesChannelId
     * @return array
     */
    public function getInstoreTerminals(string $salesChannelId): array
    {
        $this->setCredentials($salesChannelId);

        return (array)Instore::getAllTerminals()->getList();
    }
}
