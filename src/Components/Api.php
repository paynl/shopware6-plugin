<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use Paynl\Config as SDKConfig;
use Paynl\Instore;
use Paynl\Payment;
use Paynl\Paymentmethods;
use Paynl\Result\Payment\Authenticate;
use Paynl\Result\Payment\AuthenticateMethod;
use Paynl\Result\Transaction\Start;
use Paynl\Transaction;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use Paynl\Api\Payment\Model;
use PaynlPayment\Shopware6\Enums\CustomerCustomFieldsEnum;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\TransactionLanguageHelper;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Paynl\Result\Transaction as Result;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\Session;

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
    /** @var EntityRepositoryInterface */
    private $productRepository;
    /** @var EntityRepositoryInterface */
    private $orderRepository;
    /** @var TranslatorInterface */
    private $translator;
    /** @var Session */
    private $session;

    public function __construct(
        Config $config,
        CustomerHelper $customerHelper,
        TransactionLanguageHelper $transactionLanguageHelper,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $orderRepository,
        TranslatorInterface $translator,
        Session $session
    ) {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
        $this->transactionLanguageHelper = $transactionLanguageHelper;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->translator = $translator;
        $this->session = $session;
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

    public function startEncryptedTransaction(
        OrderEntity $order,
        array $payload,
        SalesChannelContext $salesChannelContext,
        string $returnUrl,
        string $exchangeUrl,
        string $shopwareVersion,
        string $pluginVersion
    ): Authenticate {
        $transaction = $this->getTransactionInitialData(
            $order,
            $salesChannelContext,
            $returnUrl,
            $exchangeUrl,
            $shopwareVersion,
            $pluginVersion
        );

        $this->setCredentials($salesChannelContext->getSalesChannel()->getId());

        $objTransaction = new Model\Authenticate\Transaction();
        $objTransaction
            ->setServiceId(\Paynl\Config::getServiceId())
            ->setDescription($transaction['description'])
            ->setExchangeUrl($transaction['exchangeUrl'])
            ->setReference($transaction['orderNumber'])
            ->setAmount($transaction['amount'] * 100)
            ->setCurrency($transaction['currency'])
//            ->setIpAddress($transaction['ipaddress'])
            ->setLanguage($transaction['address']['country']);

        $address = new Model\Address();
        $address
            ->setStreetName($transaction['invoiceAddress']['streetName'])
            ->setStreetNumber($transaction['invoiceAddress']['houseNumber'])
            ->setZipCode($transaction['invoiceAddress']['zipCode'])
            ->setCity($transaction['invoiceAddress']['city'])
            ->setCountryCode($transaction['invoiceAddress']['country']);

        $invoice = new Model\Invoice();
        $invoice
            ->setFirstName($transaction['invoiceAddress']['initials'])
            ->setLastName($transaction['invoiceAddress']['lastName'])
            ->setGender($transaction['enduser']['gender'] ?? null)
            ->setAddress($address);

        $customer = new Model\Customer();
        $customer
            ->setFirstName($transaction['enduser']['initials'])
            ->setLastName($transaction['enduser']['lastName'])
            ->setAddress($address)
            ->setInvoice($invoice);

        $cse = new Model\CSE();
        $cse->setIdentifier($payload['identifier']);
        $cse->setData($payload['data']);

        $statistics = new Model\Statistics();
        $statistics->setObject($transaction['object']);

        $browser = new Model\Browser();
        $paymentOrder = new Model\Order();

        if(!empty($transaction['products']) && is_array($transaction['products'])) {
            foreach ($transaction['products'] as $arrProduct) {
                $product = new Model\Product();
                $product->setId($arrProduct['id']);
                $product->setType($arrProduct['type']);
                $product->setDescription($arrProduct['name']);
                $product->setAmount($arrProduct['price'] * 100);
                $product->setQuantity($arrProduct['qty']);
                $product->setVat($arrProduct['vatPercentage']);
                $paymentOrder->addProduct($product);
            }
        }

        return Payment::authenticate(
            $objTransaction,
            $customer,
            $cse,
            $browser,
            $statistics,
            $paymentOrder
        );
    }

    public function status(string $transactionId, string $salesChannelId)
    {
        $this->setCredentials($salesChannelId);

        return Payment::authenticationStatus($transactionId);
    }

    /**
     * @throws \Paynl\Error\Error
     * @throws \Paynl\Error\Api
     * @throws \Paynl\Error\Required\ApiToken
     */
    public function authentication(array $params, string $salesChannelId): AuthenticateMethod
    {
        $ped = $params['pay_encrypted_data'] ?? null;
        $transId = $params['transaction_id'] ?? null;
        $ecode = $params['entrance_code'] ?? null;
        $acquirerId = $params['acquirer_id'] ?? null;
        $tdsTransactionId = $params['threeds_transaction_id'] ?? null;

        $payload = json_decode($ped, true);

        $transaction = new Model\Authenticate\TransactionMethod();
        $transaction->setOrderId($transId)->setEntranceCode($ecode);


        $cse = new Model\CSE();
        $cse->setIdentifier($payload['identifier'])->setData($payload['data']);

        $payment = new Model\Payment();
        $payment->setMethod(Model\Payment::METHOD_CSE)->setCse($cse);

        if (!empty($tdsTransactionId)) {
            $auth = new Model\Auth();
            $auth->setPayTdsAcquirerId($acquirerId)->setPayTdsTransactionId($tdsTransactionId);
            $payment->setAuth($auth);
        }

        $browser = new Model\Browser();
        $browser
            ->setJavaEnabled('false')
            ->setJavascriptEnabled('false')
            ->setLanguage('nl-NL')
            ->setColorDepth('24')
            ->setScreenWidth('1920')
            ->setScreenHeight('1080')
            ->setTz('-120');

        $payment->setBrowser($browser);

        $this->setCredentials($salesChannelId);

        return Payment::authenticateMethod($transaction, $payment);
    }

    /**
     * @return \Paynl\Result\Payment\Authorize
     * @throws \Paynl\Error\Api
     * @throws \Paynl\Error\Error
     * @throws \Paynl\Error\Required\ApiToken
     */
    public function authorization(array $params, string $salesChannelId)
    {
        $ped = $params['pay_encrypted_data'] ?? null;
        $transId = $params['transaction_id'] ?? null;
        $ecode = $params['entrance_code'] ?? null;
        $acquirerId = $params['acquirer_id'] ?? null;
        $tdsTransactionId = $params['threeds_transaction_id'] ?? null;

        $payload = json_decode($ped, true);

        $transaction = new Model\Authorize\Transaction();
        $transaction->setOrderId($transId)->setEntranceCode($ecode);

        $cse = new Model\CSE();
        $cse->setIdentifier($payload['identifier']);
        $cse->setData($payload['data']);

        $auth = new Model\Auth();
        $auth->setPayTdsAcquirerId($acquirerId);
        $auth->setPayTdsTransactionId($tdsTransactionId);

        $payment = new Model\Payment();
        $payment->setMethod(Model\Payment::METHOD_CSE);
        $payment->setCse($cse);
        $payment->setAuth($auth);

        $this->setCredentials($salesChannelId);

        return Payment::authorize($transaction, $payment);
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
        $bank = (int)($paymentSelectedData[$shopwarePaymentMethodId]['issuer'] ?? $this->session->get('paynlIssuer'));

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

    public function getPublicKeys(string $salesChannelId): array
    {
        $this->setCredentials($salesChannelId);

        return Payment::paymentEncryptionKeys()->getKeys();
    }
}
