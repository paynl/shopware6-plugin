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
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\IpSettingsHelper;
use PaynlPayment\Shopware6\Helper\StringHelper;
use PaynlPayment\Shopware6\Helper\TransactionLanguageHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Product\ProductRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\AdditionalTransactionInfo;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
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
    const PAYMENT_METHOD_PAY_NL_ID = 'paynlId';

    const ACTION_PENDING = 'pending';

    /** @var Config */
    private $config;
    /** @var CustomerHelper */
    private $customerHelper;
    /** @var TransactionLanguageHelper */
    private $transactionLanguageHelper;
    /** @var StringHelper */
    private $stringHelper;
    /** @var IpSettingsHelper */
    private $ipSettingsHelper;
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
        TransactionLanguageHelper $transactionLanguageHelper,
        StringHelper $stringHelper,
        IpSettingsHelper $ipSettingsHelper,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
        $this->transactionLanguageHelper = $transactionLanguageHelper;
        $this->stringHelper = $stringHelper;
        $this->ipSettingsHelper = $ipSettingsHelper;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
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

            $this->logger->warning('PAY. credentials are missing.');

            return [];
        }

        $this->setCredentials($salesChannelId);

        return Paymentmethods::getList();
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

    public function startTransaction(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        Context $context,
        AdditionalTransactionInfo $additionalTransactionInfo
    ): Start {
        $transactionInitialData = $this->getTransactionInitialData(
            $orderTransaction,
            $order,
            $context,
            $additionalTransactionInfo
        );

        $this->setCredentials($order->getSalesChannel()->getId(), true);

        return Transaction::start($transactionInitialData);
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

    /** @throws PaynlPaymentException */
    private function getTransactionInitialData(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        Context $context,
        AdditionalTransactionInfo $additionalTransactionInfo
    ): array {
        $salesChannelId = $order->getSalesChannelId();
        $paynlPaymentMethodId = $this->getPaynlPaymentMethodIdFromShopware($orderTransaction);
        $amount = $order->getAmountTotal();
        $currency = $order->getCurrency()->getIsoCode();
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
            'returnUrl' => $additionalTransactionInfo->getReturnUrl(),
            'exchangeUrl' => $additionalTransactionInfo->getExchangeUrl(),

            // Products
            'products' => $this->getOrderProducts($order, $context),
            'object' => sprintf(
                'Shopware v%s %s',
                $additionalTransactionInfo->getShopwareVersion(),
                $additionalTransactionInfo->getPluginVersion()
            ),
        ];

        $customer = $orderTransaction->getOrder()->getOrderCustomer()->getCustomer();

        if ($paynlPaymentMethodId === PaynlPaymentMethodsIdsEnum::PIN_PAYMENT) {
            $transactionInitialData['bank'] = $additionalTransactionInfo->getTerminalId();
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

        if ($this->getTransferData($salesChannelId)) {
            $transactionInitialData['transferData'] = $this->getTransferData($salesChannelId);
        }

        if ($this->ipSettingsHelper->getIp($salesChannelId)) {
            $transactionInitialData['ipaddress'] = $this->ipSettingsHelper->getIp($salesChannelId);
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

    /** @throws PaynlPaymentException */
    public function getPaynlPaymentMethodIdFromShopware(OrderTransactionEntity $orderTransaction): int
    {
        $salesChannelId = $orderTransaction->getOrder()->getSalesChannelId();
        if ($this->config->getSinglePaymentMethodInd($salesChannelId)) {
            return 0;
        }

        $paymentMethodTranslated = $orderTransaction->getPaymentMethod()->getTranslated();
        if (!isset($paymentMethodTranslated['customFields'])
            || !$paymentMethodTranslated['customFields'][self::PAYMENT_METHOD_PAY_NL_ID]
        ) {
            throw new PaynlPaymentException('Could not detect payment method.');
        }

        return (int)$paymentMethodTranslated['customFields'][self::PAYMENT_METHOD_PAY_NL_ID];
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

        $surchargeItems = $orderLineItems->filterByProperty('type', 'payment_surcharge');
        /** @var OrderLineItemEntity $item */
        foreach ($surchargeItems as $item) {
            $vatPercentage = 0;
            if ($item->getPrice()->getCalculatedTaxes()->first() !== null) {
                $vatPercentage = $item->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
            }

            $products[] = [
                'id' => 'payment',
                'name' => $item->getLabel(),
                'price' => $item->getUnitPrice(),
                'vatPercentage' => $vatPercentage,
                'qty' => $item->getPrice()->getQuantity(),
                'type' => Transaction::PRODUCT_TYPE_PAYMENT,
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
            $this->logger->error('Error while refunding process', [
                'transactionId' => $transactionID,
                'amount' => $amount,
                'exception' => $e
            ]);

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
            $this->logger->error('Error while capturing process', [
                'transactionId' => $transactionID,
                'amount' => $amount,
                'exception' => $exception
            ]);

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
}
