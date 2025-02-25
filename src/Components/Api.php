<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use Paynl\Config as SDKConfig;
use Paynl\Instore;
use Paynl\Paymentmethods;
use PayNL\Sdk\Config\Config as PayNLConfig;
use PayNL\Sdk\Exception\PayException;
use PayNL\Sdk\Model\Pay\PayOrder;
use PayNL\Sdk\Model\Request\OrderCaptureRequest;
use PayNL\Sdk\Model\Request\OrderCreateRequest;
use PayNL\Sdk\Model;
use PayNL\Sdk\Model\Request\OrderVoidRequest;
use PayNL\Sdk\Model\Request\ServiceGetConfigRequest;
use PayNL\Sdk\Model\Request\TransactionRefundRequest;
use PayNL\Sdk\Model\Response\TransactionRefundResponse;
use Paynl\Transaction;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\StringHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Product\ProductRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\AdditionalTransactionInfo;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class Api
{
    const PAYMENT_METHOD_ID = 'id';
    const PAYMENT_METHOD_NAME = 'name';
    const PAYMENT_METHOD_VISIBLE_NAME = 'visibleName';
    const PAYMENT_METHOD_BRAND = 'brand';
    const PAYMENT_METHOD_BRAND_DESCRIPTION = 'public_description';
    const PAYMENT_METHOD_PAY_NL_ID = 'paynlId';

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
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Config $config,
        CustomerHelper $customerHelper,
        StringHelper $stringHelper,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
        $this->stringHelper = $stringHelper;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * @return Model\Method[]
     * @throws PayException
     */
    public function getPaymentMethods(string $salesChannelId): array
    {
        if (empty($this->config->getTokenCode($salesChannelId))
            || empty($this->config->getApiToken($salesChannelId))
            || empty($this->config->getServiceId($salesChannelId))) {

            $this->logger->warning('PAY. credentials are missing.');

            return [];
        }

        $config = $this->getConfig($salesChannelId);

        $serviceConfig = (new ServiceGetConfigRequest($this->config->getServiceId($salesChannelId)))
            ->setConfig($config)
            ->start();

        return $serviceConfig->getPaymentMethods();
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

    /**
     * @throws PayException
     * @throws PaynlPaymentException
     */
    public function startTransaction(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext,
        AdditionalTransactionInfo $additionalTransactionInfo
    ): PayOrder {
        $orderCreateRequest = $this->getOrderCreateRequest(
            $order,
            $salesChannelContext,
            $additionalTransactionInfo
        );

        $config = $this->getConfig($salesChannelContext->getSalesChannel()->getId(), true);

        $orderCreateRequest->setConfig($config);

        return $orderCreateRequest->start();
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
     * @throws PaynlPaymentException
     * @throws Exception
     */
    private function getOrderCreateRequest(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext,
        AdditionalTransactionInfo $additionalTransactionInfo
    ): OrderCreateRequest {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $paynlPaymentMethodId = $this->getPaynlPaymentMethodIdFromShopware($salesChannelContext);
        $amount = $order->getAmountTotal();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();
        $testMode = $this->config->getTestMode($salesChannelId);
        $orderNumber = $order->getOrderNumber();
        $customer = $salesChannelContext->getCustomer();

        $request = new OrderCreateRequest();
        $request->setServiceId($this->config->getServiceId($salesChannelId));
        $request->setDescription(sprintf(
            '%s %s',
            $this->translator->trans('transactionLabels.order'),
            $orderNumber
        ));
        $request->setReference($orderNumber);
        $request->setReturnurl($additionalTransactionInfo->getReturnUrl());
        $request->setExchangeUrl($additionalTransactionInfo->getExchangeUrl());
        $request->setAmount($amount);
        $request->setCurrency($currency);

        if ($paynlPaymentMethodId) {
            $request->setPaymentMethodId($paynlPaymentMethodId);
        }

        if ($paynlPaymentMethodId === PaynlPaymentMethodsIdsEnum::PIN_PAYMENT) {
            $request->setTerminal($additionalTransactionInfo->getTerminalId());
        }

        $request->setTestmode((bool) $testMode);

        $request->setCustomer($this->customerHelper->getCustomer($customer, $order, $salesChannelId));

        $payNLOrder = new Model\Order();

        $payNLOrder->setDeliveryAddress($this->customerHelper->getDeliveryAddress($customer, $salesChannelId));

        $payNLOrder->setInvoiceAddress($this->customerHelper->getInvoiceAddress($customer, $salesChannelId));

        $payNLOrder->setProducts($this->getOrderProducts($order, $salesChannelContext->getContext()));

        $request->setOrder($payNLOrder);

        $request->setStats((new Model\Stats())
            ->setObject(sprintf(
                'Shopware v%s %s',
                $additionalTransactionInfo->getShopwareVersion(),
                $additionalTransactionInfo->getPluginVersion()
            ))
        );

        if ($this->getTransferData($salesChannelId)) {
            $request->setTransferData($this->getTransferData($salesChannelId));
        }

        return $request;
    }

    /** @throws PaynlPaymentException */
    public function getPaynlPaymentMethodIdFromShopware(SalesChannelContext $salesChannelContext): int
    {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        if ($this->config->getSinglePaymentMethodInd($salesChannelId)) {
            return 0;
        }

        $paymentMethodTranslated = $salesChannelContext->getPaymentMethod()->getTranslated();
        if (!isset($paymentMethodTranslated['customFields'])
            || !$paymentMethodTranslated['customFields'][self::PAYMENT_METHOD_PAY_NL_ID]
        ) {
            throw new PaynlPaymentException('Could not detect payment method.');
        }

        return (int)$paymentMethodTranslated['customFields'][self::PAYMENT_METHOD_PAY_NL_ID];
    }

    /** @throws InconsistentCriteriaIdsException */
    private function getOrderProducts(OrderEntity $order, Context $context): Model\Products
    {
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
            $product->setId((string) $elements[$item->getReferencedId()]->get('autoIncrement'));
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

    /** @throws Exception */
    public function refund(
        string $transactionID,
        float $amount,
        string $salesChannelId,
        string $description = ''
    ): TransactionRefundResponse {
        if (!$this->config->isRefundAllowed($salesChannelId)) {
            $message = 'PAY-PLUGIN-001: You did not activate refund option in plugin, check %s';
            $url = sprintf(
                '<a target="_blank" href="https://docs.pay.nl/plugins?language=en#shopware-six-errordefinitions">
%s</a>',
                'docs.pay.nl/shopware6/instructions'
            );

            throw new Exception(sprintf($message, $url));
        }
        $this->setCredentials($salesChannelId);

        try {
            $config = $this->getConfig($salesChannelId);

            $transactionRefundRequest = new TransactionRefundRequest($transactionID);
            $transactionRefundRequest->setConfig($config);
            $transactionRefundRequest->setAmount($amount);
            $transactionRefundRequest->setDescription($description);

            return $transactionRefundRequest->start();
        } catch (\Throwable $e) {
            $this->logger->error('Error while refunding process', [
                'transactionId' => $transactionID,
                'amount' => $amount,
                'exception' => $e
            ]);

            throw new Exception($e->getMessage());
        }
    }

    /** @throws PaynlTransactionException */
    public function capture(string $transactionID, ?float $amount, string $salesChannelId): PayOrder
    {
        try {
            $config = $this->getConfig($salesChannelId);

            $orderCaptureRequest = new OrderCaptureRequest($transactionID);
            $orderCaptureRequest->setConfig($config);

            if ($amount) {
                $orderCaptureRequest->setAmount($amount);
            }

            return $orderCaptureRequest->start();
        } catch (Throwable $exception) {
            $this->logger->error('Error while capturing process', [
                'transactionId' => $transactionID,
                'amount' => $amount,
                'exception' => $exception
            ]);

            throw PaynlTransactionException::captureError($exception->getMessage());
        }
    }

    /** @throws PaynlTransactionException */
    public function void(string $transactionID, string $salesChannelId): PayOrder
    {
        try {
            $config = $this->getConfig($salesChannelId);

            $orderVoidRequest = new OrderVoidRequest($transactionID);
            $orderVoidRequest->setConfig($config);

            return $orderVoidRequest->start();
        } catch (Throwable $exception) {
            $this->logger->error('Error while voiding process', [
                'transactionId' => $transactionID,
                'exception' => $exception
            ]);

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
