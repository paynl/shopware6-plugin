<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use Paynl\Config as SDKConfig;
use Paynl\Instore;
use Paynl\Paymentmethods;
use PayNL\Sdk\Config\Config as PayNLConfig;
use PayNL\Sdk\Model\CreateTransactionResponse;
use PayNL\Sdk\Model\Request\TransactionCreateRequest;
use PayNL\Sdk\Model;
use Paynl\Transaction;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Shopware6\Enums\CustomerCustomFieldsEnum;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\StringHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Product\ProductRepositoryInterface;
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
use Throwable;

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
    /** @var StringHelper */
    private $stringHelper;
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
        StringHelper $stringHelper,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        RequestStack $requestStack
    ) {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
        $this->stringHelper = $stringHelper;
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

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $salesChannelContext
     * @param string $returnUrl
     * @param string $exchangeUrl
     * @param string $shopwareVersion
     * @param string $pluginVersion
     * @param string|null $terminalId
     * @return CreateTransactionResponse
     * @throws \PayNL\Sdk\Exception\PayException
     */
    public function startTransaction(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext,
        string $returnUrl,
        string $exchangeUrl,
        string $shopwareVersion,
        string $pluginVersion,
        ?string $terminalId = null
    ): CreateTransactionResponse {
        $transactionRequest = $this->getTransactionRequest(
            $order,
            $salesChannelContext,
            $returnUrl,
            $exchangeUrl,
            $shopwareVersion,
            $pluginVersion,
            $terminalId
        );

        $config = $this->getConfig($salesChannelContext->getSalesChannel()->getId(), true);

        $transactionRequest->setConfig($config);

        return $transactionRequest->start();
    }

    public function getTransaction(string $transactionId, string $salesChannelId): ResultTransaction
    {
        $this->setCredentials($salesChannelId, true);

        // Temporary hack which should fixed when we will use SDK v2
        if (substr($transactionId, 0, 2) == '51') {
            SDKConfig::setApiBase('https://rest.achterelkebetaling.nl');
        } elseif (substr($transactionId, 0, 2) == '52') {
            SDKConfig::setApiBase('https://rest.payments.nl');
        }

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
     * @return TransactionCreateRequest
     * @throws Exception
     */
    private function getTransactionRequest(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext,
        string $returnUrl,
        string $exchangeUrl,
        string $shopwareVersion,
        string $pluginVersion,
        ?string $terminalId = null
    ): TransactionCreateRequest {
        $shopwarePaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();
        $shopwarePaymentMethodCustomFields = $salesChannelContext->getPaymentMethod()->getCustomFields();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $paynlPaymentMethodId = $shopwarePaymentMethodCustomFields['paynlId'] ?? '';
        $amount = $order->getAmountTotal();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();
        $testMode = $this->config->getTestMode($salesChannelId);
        $orderNumber = $order->getOrderNumber();

        $customer = $salesChannelContext->getCustomer();
        $customerCustomFields = $customer->getCustomFields();
        $paymentSelectedData = $customerCustomFields[CustomerCustomFieldsEnum::PAYMENT_METHODS_SELECTED_DATA] ?? [];
        $bank = (int)($paymentSelectedData[$shopwarePaymentMethodId]['issuer'] ?? $this->requestStack->getSession()->get('paynlIssuer'));

        $request = new TransactionCreateRequest();
        $request->setServiceId($this->config->getServiceId($salesChannelId));
        $request->setDescription(sprintf(
            '%s %s',
            $this->translator->trans('transactionLabels.order'),
            $orderNumber
        ));
        $request->setReference($orderNumber);
//$request->setExpire(date('Y-m-d H:i:s', strtotime('+1 DAY')));
        $request->setReturnurl($returnUrl);
        $request->setExchangeUrl($exchangeUrl);
        $request->setAmount($amount);
        $request->setCurrency($currency);

        if (!$this->config->getSinglePaymentMethodInd($salesChannelId)) {
            $request->setPaymentMethodId((int) $paynlPaymentMethodId);
        }
        if ($bank) {
            $request->setIssuerId($bank);

            $orderCustomFields = (array)$order->getCustomFields();
            $orderCustomFields['paynlIssuer'] = $bank;

            $data[] = [
                'id' => $order->getId(),
                'customFields' => $orderCustomFields
            ];

            $this->orderRepository->upsert($data, $salesChannelContext->getContext());
        }
        if ($paynlPaymentMethodId === PaynlPaymentMethodsIdsEnum::PIN_PAYMENT) {
            $request->setTerminal($terminalId);

        }
        $request->setTestmode((bool) $testMode);

        $request->setCustomer($this->customerHelper->getCustomer($customer, $order, $salesChannelId));

        $payNLOrder = new Model\Order();
//        $payNLOrder->setCountryCode('NL');
//        $payNLOrder->setDeliveryDate('2023-10-28 14:11:01');
//        $payNLOrder->setInvoiceDate('2023-10-29 14:05:00');

        $payNLOrder->setDeliveryAddress($this->customerHelper->getDeliveryAddress($customer, $salesChannelId));

        $payNLOrder->setInvoiceAddress($this->customerHelper->getInvoiceAddress($customer, $salesChannelId));

        $payNLOrder->setProducts($this->getOrderProducts($order, $salesChannelContext->getContext()));

        $request->setOrder($payNLOrder);

        $request->setStats((new Model\Stats())
            ->setObject(sprintf('Shopware v%s %s', $shopwareVersion, $pluginVersion))
        );

        if ($this->getTransferData($salesChannelId)) {
            $request->setTransferData($this->getTransferData($salesChannelId));
        }

        return $request;
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
     * @return \PayNL\Sdk\Model\Products
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderProducts(OrderEntity $order, Context $context): \PayNL\Sdk\Model\Products
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
        $products = new Model\Products();

        /** @var OrderLineItemEntity $item */
        foreach ($productsItems as $item) {
            $vatPercentage = 0;
            if ($item->getPrice()->getCalculatedTaxes()->first() !== null) {
                $vatPercentage = $item->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
            }

            $product = new Model\Product();
            $product->setId($elements[$item->getReferencedId()]->get('autoIncrement'));
            $product->setDescription($item->getLabel());
            $product->setType(Model\Product::TYPE_ARTICLE);
            $product->setAmount($item->getUnitPrice());
            $product->setCurrency($order->getCurrency()->getIsoCode());
            $product->setQuantity($item->getPrice()->getQuantity());
            $product->setVatCode(paynl_determine_vat_class_by_percentage($vatPercentage));

            $products->addProduct($product);
        }

        $surchargeItems = $orderLineItems->filterByProperty('type', 'payment_surcharge');
        /** @var OrderLineItemEntity $item */
        foreach ($surchargeItems as $item) {
            $vatPercentage = 0;
            if ($item->getPrice()->getCalculatedTaxes()->first() !== null) {
                $vatPercentage = $item->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
            }

            $product = new Model\Product();
            $product->setId('payment');
            $product->setDescription($item->getLabel());
            $product->setType(Model\Product::TYPE_PAYMENT);
            $product->setAmount($item->getUnitPrice());
            $product->setCurrency($order->getCurrency()->getIsoCode());
            $product->setQuantity($item->getPrice()->getQuantity());
            $product->setVatCode(paynl_determine_vat_class_by_percentage($vatPercentage));

            $products->addProduct($product);
        }

        $product = new Model\Product();
        $product->setId('shipping');
        $product->setDescription('Shipping');
        $product->setType(Model\Product::TYPE_SHIPPING);
        $product->setAmount($order->getShippingTotal());
        $product->setCurrency($order->getCurrency()->getIsoCode());
        $product->setQuantity(1);
        $product->setVatCode(
            paynl_determine_vat_class_by_percentage(
                $order->getShippingCosts()->getCalculatedTaxes()->getAmount()
            )
        );

        $products->addProduct($product);

        return $products;
    }

    /** @return mixed[] */
    private function getTransferData(string $salesChannelId): array
    {
        $transferData = [];

        if ($this->config->isTransferGoogleAnalytics($salesChannelId)) {
            $transferData['gaClientId'] = $this->getGoogleAnalyticsClientId();
        }

        return $transferData;
    }

    private function getGoogleAnalyticsClientId(): string
    {
        $allCookies = $this->requestStack->getCurrentRequest()->cookies->all();
        $gaCookies = array_filter($allCookies, function ($key) {
            return $this->stringHelper->endsWith($key, '_ga');
        }, ARRAY_FILTER_USE_KEY);

        if (empty($gaCookies)) {
            return '';
        }

        $gaCookie = reset($gaCookies);
        $gaSplit = explode('.', $gaCookie);
        if (isset($gaSplit[2]) && isset($gaSplit[3])) {
            return $gaSplit[2] . '.' . $gaSplit[3];
        }

        return '';
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

    /** @throws PaynlTransactionException */
    public function capture(string $transactionID, $amount, string $salesChannelId): bool
    {
        $this->setCredentials($salesChannelId);

        try {
            return Transaction::capture($transactionID, $amount);
        } catch (Throwable $exception) {
            throw PaynlTransactionException::captureError($exception->getMessage());
        }
    }

    /** @throws PaynlTransactionException */
    public function void(string $transactionID, string $salesChannelId): bool
    {
        $this->setCredentials($salesChannelId);

        try {
            return Transaction::void($transactionID);
        } catch (Throwable $exception) {
            throw PaynlTransactionException::captureError($exception->getMessage());
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

    private function setCredentials(string $salesChannelId, bool $useGateway = false): void
    {
        SDKConfig::setTokenCode($this->config->getTokenCode($salesChannelId));
        SDKConfig::setApiToken($this->config->getApiToken($salesChannelId));
        SDKConfig::setServiceId($this->config->getServiceId($salesChannelId));

        $gateway = $this->config->getFailoverGateway($salesChannelId);
        $gateway = $gateway ? 'https://' . $gateway : '';
        if ($useGateway && $gateway && substr(trim($gateway), 0, 4) === "http") {
            SDKConfig::setApiBase(trim($gateway));
        }
    }

    private function getConfig(string $salesChannelId, bool $useGateway = false): PayNLConfig
    {
        $sdkConfig = new PayNLConfig();
        $sdkConfig->setUsername($this->config->getTokenCode($salesChannelId));
        $sdkConfig->setPassword($this->config->getApiToken($salesChannelId));

        $gateway = $this->config->getFailoverGateway($salesChannelId);
        $gateway = $gateway ? 'https://' . $gateway : '';
        if ($useGateway && $gateway && substr(trim($gateway), 0, 4) === "http") {
            $sdkConfig->setCore(trim($gateway));
        }

        return $sdkConfig;
    }
}
