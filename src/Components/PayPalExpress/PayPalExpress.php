<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\PayPalExpress;

use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Repository\OrderDelivery\OrderDeliveryRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\Repository\SalesChannel\SalesChannelRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Amount;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\CreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Input;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Integration;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Optimize;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Order;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\PaymentMethod;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Product;
use PaynlPayment\Shopware6\ValueObjects\PAY\OrderDataMapper;
use PaynlPayment\Shopware6\ValueObjects\PAY\Response\CreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse as PayPalCreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Amount as PayPalAmount;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\CreateOrder as PayPalCreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\PurchaseUnit;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\OrderDetailResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Throwable;
use Exception;
use RuntimeException;
use Paynl\Transaction;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\Country\CountryRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Order\OrderAddressRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderCustomer\OrderCustomerRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Repository\Product\ProductRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Salutation\SalutationRepositoryInterface;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartServiceInterface;
use PaynlPayment\Shopware6\Service\CustomerService;
use PaynlPayment\Shopware6\Service\OrderService;
use PaynlPayment\Shopware6\Service\PayPal\v2\OrderService as PayPalOrderService;
use PaynlPayment\Shopware6\Service\PAY\v1\OrderService as PayOrderService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PayPalExpress
{
    private const ADDRESS_KEYS = [
        'firstName',
        'lastName',
        'street',
        'zipcode',
        'countryId',
        'city',
        'phoneNumber',
        'additionalAddressLine1',
    ];

    /** @var CartServiceInterface */
    private $cartService;

    /** @var Config */
    private $config;

    /** @var RouterInterface */
    private $router;

    /** @var TranslatorInterface */
    private $translator;

    /** @var CustomerService */
    private $customerService;

    /** @var PaymentMethodRepository */
    private $repoPaymentMethods;

    /** @var CartBackupService */
    private $cartBackupService;

    /** @var OrderService */
    private $orderService;

    /** @var PayPalOrderService */
    private $paypalOrderService;

    /** @var PayOrderService */
    private $payOrderService;

    /** @var ProcessingHelper */
    private $processingHelper;

    /** @var OrderAddressRepositoryInterface */
    private $repoOrderAddresses;

    /** @var CountryRepositoryInterface */
    private $countryRepository;

    /** @var SalesChannelRepositoryInterface */
    private $salesChannelRepository;

    /** @var SalutationRepositoryInterface */
    private $salutationRepository;

    /** @var OrderCustomerRepositoryInterface */
    private $orderCustomerRepository;

    /** @var OrderDeliveryRepositoryInterface */
    private $orderDeliveryRepository;

    /** @var OrderTransactionRepositoryInterface */
    private $orderTransactionRepository;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    public function __construct(
        CartServiceInterface $cartService,
        Config $config,
        RouterInterface $router,
        TranslatorInterface $translator,
        CustomerService $customerService,
        CartBackupService $cartBackupService,
        OrderService $orderService,
        PayPalOrderService $paypalOrderService,
        PayOrderService $payOrderService,
        ProcessingHelper $processingHelper,
        OrderAddressRepositoryInterface $repoOrderAddresses,
        CountryRepositoryInterface $countryRepository,
        SalesChannelRepositoryInterface $salesChannelRepository,
        SalutationRepositoryInterface $salutationRepository,
        OrderCustomerRepositoryInterface $orderCustomerRepository,
        OrderDeliveryRepositoryInterface $orderDeliveryRepository,
        PaymentMethodRepository $repoPaymentMethods,
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->cartService = $cartService;
        $this->config = $config;
        $this->router = $router;
        $this->translator = $translator;
        $this->customerService = $customerService;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->cartBackupService = $cartBackupService;
        $this->orderService = $orderService;
        $this->paypalOrderService = $paypalOrderService;
        $this->payOrderService = $payOrderService;
        $this->processingHelper = $processingHelper;
        $this->repoOrderAddresses = $repoOrderAddresses;
        $this->countryRepository = $countryRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salutationRepository = $salutationRepository;
        $this->orderCustomerRepository = $orderCustomerRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->productRepository = $productRepository;
    }

    public function getActivePayPalID(SalesChannelContext $context): string
    {
        return $this->repoPaymentMethods->getActivePayPalID($context->getContext());
    }

    public function prepareCustomer(
        string $firstname,
        string $lastname,
        string $email,
        string $street,
        string $zipcode,
        string $city,
        SalesChannelContext $context
    ): SalesChannelContext {
        $this->cartBackupService->clearBackup($context);

        $paypalExpressID = $this->getActivePayPalID($context);

        if (!$this->customerService->isCustomerLoggedIn($context)) {
            $countryCode = $this->getSalesChannelCountryIso($context);

            $customer = $this->customerService->createPaymentExpressCustomer(
                $firstname,
                $lastname,
                $email,
                '',
                $street,
                $zipcode,
                $city,
                $countryCode,
                $paypalExpressID,
                $context
            );

            if (!$customer instanceof CustomerEntity) {
                throw new Exception('Error when creating customer!');
            }

            $this->customerService->customerLogin($customer, $context);
        }

        // update our payment method to use PayPal Express for our cart
        return $this->cartService->updatePaymentMethod($context, $paypalExpressID);
    }

    /** @throws Exception */
    private function updateOrder(OrderEntity $order, OrderDetailResponse $orderDetailResponse, SalesChannelContext $context): ?OrderEntity
    {
        $salutationId = $this->getSalutationId($context->getContext());
        $addressData = $this->getAddressData($orderDetailResponse, $context, $salutationId);

        if ($order->getAddresses() instanceof OrderAddressCollection) {
            foreach ($order->getAddresses() as $address) {
                $this->repoOrderAddresses->updateAddress(
                    $address->getId(),
                    $addressData['firstName'],
                    $addressData['lastName'],
                    '',
                    '',
                    '',
                    $addressData['street'],
                    $addressData['zipcode'],
                    $addressData['city'],
                    $addressData['countryId'],
                    $context->getContext()
                );
            }

            $shippingAddressId = Uuid::randomHex();
            $addressData = [
                'id' => $shippingAddressId,
                'countryId' => $addressData['countryId'],
                'orderId' => $order->getId(),
                'salutationId' => $salutationId,
                'firstName' => $addressData['firstName'],
                'lastName' => $addressData['lastName'],
                'street' => $addressData['street'],
                'zipcode' => $addressData['zipcode'],
                'city' => $addressData['city'],
                'additionalAddressLine1' => null,
            ];

            $this->repoOrderAddresses->create([$addressData], $context->getContext());

            /** @var OrderDeliveryEntity|null $delivery */
            $delivery = $order->getDeliveries() ? $order->getDeliveries()->first() : null;

            $this->orderDeliveryRepository->update([[
                'id' => $delivery->getId(),
                'shippingOrderAddressId' => $shippingAddressId
            ]], $context->getContext());
        }

        return $this->orderService->getOrder($order->getId(), $context->getContext());
    }

    private function updateOrderCustomer(
        OrderCustomerEntity $customer,
        OrderDetailResponse $orderDetailResponse,
        SalesChannelContext $context
    ): void {
        $payer = $orderDetailResponse->getPayer();
        if (empty($payer)) {
            return;
        }

        $this->orderCustomerRepository->update([
            [
                'id' => $customer->getId(),
                'email' => $payer->getEmail(),
                'firstName' => $payer->getFirstName(),
                'lastName' => $payer->getLastName(),
            ]
        ], $context->getContext());
    }

    private function updateCustomer(
        CustomerEntity $customer,
        OrderDetailResponse $orderDetailResponse,
        SalesChannelContext $context
    ): ?CustomerEntity {
        $salutationId = $this->getSalutationId($context->getContext());
        $addressData = $this->getAddressData($orderDetailResponse, $context, $salutationId);
        $payer = $orderDetailResponse->getPayer();
        if (empty($addressData) || empty($payer)) {
            return $customer;
        }

        $billingAddressId = $this->getCustomerAddressId($customer, $addressData);

        $customerData = [
            'id' => $customer->getId(),
            'email' => $payer->getEmail(),
            'defaultShippingAddressId' => $billingAddressId,
            'defaultBillingAddressId' => $billingAddressId,
            'firstName' => $addressData['firstName'],
            'lastName' => $addressData['lastName'],
            'salutationId' => $salutationId,
            'addresses' => [
                array_merge($addressData, [
                    'id' => $billingAddressId,
                    'salutationId' => $salutationId,
                ]),
            ],
        ];

        return $this->customerService->updateCustomer($customerData, $context);
    }

    public function createOrder(SalesChannelContext $context): OrderEntity
    {
        $data = new DataBag();

        # we have to agree to the terms of services
        # to avoid constraint violation checks
        $data->add(['tos' => true]);

        # create our new Order using the
        # Shopware function for it.
        return $this->orderService->createOrder($data, $context);
    }

    public function createPayment(
        OrderEntity $order,
        string $shopwareReturnUrl,
        string $firstname,
        string $lastname,
        string $street,
        string $zipcode,
        string $city,
        string $countryCode,
        SalesChannelContext $context
    ): string {
        $countryID = (string)$this->customerService->getCountryId($countryCode, $context->getContext());

        if ($order->getAddresses() instanceof OrderAddressCollection) {
            foreach ($order->getAddresses() as $address) {
                $this->repoOrderAddresses->updateAddress(
                    $address->getId(),
                    $firstname,
                    $lastname,
                    '',
                    '',
                    '',
                    $street,
                    $zipcode,
                    $city,
                    $countryID,
                    $context->getContext()
                );
            }
        }


        /** @var OrderTransactionCollection $transactions */
        $transactions = $order->getTransactions();
        $transaction = $transactions->last();

        if (!$transaction instanceof OrderTransactionEntity) {
            throw new Exception('Created PayPal Express Direct order has not OrderTransaction!');
        }

        $asyncPaymentTransition = new AsyncPaymentTransactionStruct($transaction, $order, $shopwareReturnUrl);

        try {
            $orderResponse = $this->createPayPalExpressOrder($asyncPaymentTransition, $context);

            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', 'Response:', FILE_APPEND);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', json_encode($orderResponse->getData()), FILE_APPEND);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', "\n\n", FILE_APPEND);
        } catch (Throwable $exception) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', 'Error:', FILE_APPEND);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', $exception->getMessage(), FILE_APPEND);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', "\n\n", FILE_APPEND);

            throw $exception;
        }

        return $orderResponse->getId();
    }

    /** @throws Exception */
    public function updatePaymentTransaction(CreateOrderResponse $orderResponse): void
    {
        $statusCode = $orderResponse->getStatus()->getCode();

        $stateActionName = PaynlTransactionStatusesEnum::STATUSES_ARRAY[$statusCode] ?? null;

        if (empty($stateActionName)) {
            throw new Exception('State action name was not defined');
        }

        $this->processingHelper->instorePaymentUpdateState($orderResponse->getOrderId(), $stateActionName, $statusCode);
    }

    public function isNotCompletedOrder(string $orderId, Context $context): bool
    {
        try {
            $order = $this->orderService->getOrder($orderId, $context);
            $billingAddress = $order->getBillingAddress();

            if (!$billingAddress) {
                return true;
            }

            return in_array('Temp', [
                $billingAddress->getFirstName(),
                $billingAddress->getLastName(),
            ]);
        } catch (Throwable $exception) {
            return true;
        }
    }

    private function createPayPalExpressOrder(
        AsyncPaymentTransactionStruct $asyncPaymentTransition,
        SalesChannelContext $salesChannelContext
    ): PayPalCreateOrderResponse {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $order = $asyncPaymentTransition->getOrder();

        $currency = $salesChannelContext->getCurrency()->getIsoCode();

        $paypalAmount = new PayPalAmount($currency, (string) $order->getAmountTotal());

        $purchaseUnit = new PurchaseUnit($paypalAmount, $asyncPaymentTransition->getOrderTransaction()->getId());

        $createOrder = new PayPalCreateOrder(
            'CAPTURE',
            [$purchaseUnit]
        );

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', 'Payment:', FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', json_encode($createOrder->toArray()), FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', "\n\n", FILE_APPEND);

        $paypalOrder = $this->paypalOrderService->create($createOrder, $salesChannelId);

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', 'PayPal Order Response:', FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', json_encode($paypalOrder->getData()), FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', "\n\n", FILE_APPEND);

        return $paypalOrder;
    }

    public function createPayPaymentTransaction(
        string $payPalOrderId,
        SalesChannelContext $salesChannelContext
    ): CreateOrderResponse {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();

        $payPalOrder = $this->paypalOrderService->getOrder($payPalOrderId, $salesChannelId);

        $purchaseUnits = $payPalOrder->getPurchaseUnits();
        /** @var PurchaseUnit $purchaseUnit */
        $purchaseUnit = reset($purchaseUnits);
        $purchaseUnitAmount = $purchaseUnit->getAmount();
        $amount = (string) round($purchaseUnitAmount->getValue() * 100);
        $referenceId = $purchaseUnit->getReferenceId() ?? '';

        if (!$referenceId || !$amount) {
            throw new Exception('PayPal: Amount or reference ID is empty');
        }

        $transactionCriteria = (new Criteria([$referenceId]))
            ->addAssociation('stateMachineState');

        $orderTransactions = $this->orderTransactionRepository->search($transactionCriteria, $salesChannelContext->getContext());
        /** @var OrderTransactionEntity $orderTransaction */
        $orderTransaction = $orderTransactions->first();
        $orderId = $orderTransaction->getOrderId();

        $order = $this->orderService->getOrder($orderId, $salesChannelContext->getContext());
        $orderNumber = $order->getOrderNumber();

        $exchangeUrl = $this->router->generate(
            'frontend.PaynlPayment.notify',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $payAmount = new Amount((int) $amount, $currency);

        $description = sprintf(
            '%s %s',
            $this->translator->trans('transactionLabels.order'),
            $orderNumber
        );

        $optimize = new Optimize(
            'fastCheckout',
            true,
            true,
            true
        );

        $input = new Input($payPalOrderId);
        $paymentMethod = new PaymentMethod(PaynlPaymentMethodsIdsEnum::PAYPAL_PAYMENT, null, $input);
        $products = $this->getOrderProducts($order, $salesChannelContext);
        $payOrder = new Order($products);

        $integration = $this->config->getTestMode($salesChannelId) ? new Integration(true) : null;

        $returnUrl = $this->router->generate(
            'frontend.account.PaynlPayment.paypal-express.finish-page',
            [
                'orderId' => $orderId,
            ],
            $this->router::ABSOLUTE_URL
        );

        $createOrder = new CreateOrder(
            $this->config->getServiceId($salesChannelId),
            $payAmount,
            $description,
            $orderNumber,
            $returnUrl,
            $exchangeUrl,
            $optimize,
            $paymentMethod,
            $integration,
            $payOrder
        );

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', 'Payment PAY Request:', FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', json_encode($createOrder->toArray()), FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', "\n\n", FILE_APPEND);

        $createOrderResponse = $this->payOrderService->create($createOrder, $salesChannelId);

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', 'Payment PAY Response:', FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', json_encode($createOrderResponse->getData()), FILE_APPEND);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/paypal-express.txt', "\n\n", FILE_APPEND);

        $this->processingHelper->storePaynlTransactionData(
            $order,
            $orderTransaction,
            $salesChannelContext,
            $createOrderResponse->getOrderId(),
        );

        $this->updateOrder($order, $payPalOrder, $salesChannelContext);

        $this->updateOrderCustomer($order->getOrderCustomer(), $payPalOrder, $salesChannelContext);

        $this->updateCustomer($order->getOrderCustomer()->getCustomer(), $payPalOrder, $salesChannelContext);

        $this->updatePaymentTransaction($createOrderResponse);

        return $createOrderResponse;
    }

    private function getOrderProducts(OrderEntity $order, SalesChannelContext $salesChannelContext): array
    {
        $currency = $salesChannelContext->getCurrency()->getIsoCode();
        $context = $salesChannelContext->getContext();

        /** @var OrderLineItemCollection $orderLineItems*/
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

            $products[] = new Product(
                new Amount(
                    (int) round($item->getUnitPrice() * 100),
                    $currency
                ),
                (string) $elements[$item->getReferencedId()]->get('autoIncrement'),
                $item->getLabel(),
                Transaction::PRODUCT_TYPE_ARTICLE,
                $item->getPrice()->getQuantity(),
                $vatPercentage
            );
        }

        $surchargeItems = $orderLineItems->filterByProperty('type', 'payment_surcharge');
        /** @var OrderLineItemEntity $item */
        foreach ($surchargeItems as $item) {
            $vatPercentage = 0;
            if ($item->getPrice()->getCalculatedTaxes()->first() !== null) {
                $vatPercentage = $item->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
            }

            $products[] = new Product(
                new Amount(
                    (int) round($item->getUnitPrice() * 100),
                    $currency
                ),
                'payment',
                $item->getLabel(),
                Transaction::PRODUCT_TYPE_PAYMENT,
                $item->getPrice()->getQuantity(),
                $vatPercentage
            );
        }

        $products[] = new Product(
            new Amount(
                (int) round($order->getShippingTotal() * 100),
                $currency
            ),
            'shipping',
            'Shipping',
            Transaction::PRODUCT_TYPE_SHIPPING,
            1,
            $order->getShippingCosts()->getCalculatedTaxes()->getAmount()
        );

        return $products;
    }

    /**
     * @return array<string, string|null>
     */
    private function getAddressData(
        OrderDetailResponse $orderDetailResponse,
        SalesChannelContext $context,
        ?string $salutationId = null
    ): array {
        $payer = $orderDetailResponse->getPayer();
        if (!empty($orderDetailResponse->getPurchaseUnits())) {
            $shipping = $orderDetailResponse->getPurchaseUnits()[0]->getShipping();
            $payerAddress = $shipping->getAddress();
            $names = explode(' ', $shipping->getFullName());
            $lastName = array_pop($names);
            $firstName = implode(' ', $names);
        } else {
            $payerAddress = $payer->getAddress();
            $firstName = $payer->getFirstName();
            $lastName = $payer->getLastName();
        }

        $countryCode = $payerAddress->getCountryCode();
        $phone = $payer->getPhone();

        $countryId = $this->getCountryIdByCode($countryCode, $context->getContext());
        if (empty($countryId)) {
            $countryId = $context->getSalesChannel()->getCountryId();
        }

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'salutationId' => $salutationId,
            'street' => $payerAddress->getAddressLine1(),
            'zipcode' => $payerAddress->getPostalCode(),
            'countryId' => $countryId,
            'phoneNumber' => $phone,
            'city' => $payerAddress->getAdminArea1(),
            'additionalAddressLine1' => null,
        ];
    }

    private function getCountryIdByCode(string $code, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('iso', $code)
        );

        /** @var CountryEntity|null $country */
        $country = $this->countryRepository->search($criteria, $context)->first();

        if (!$country instanceof CountryEntity) {
            return null;
        }

        return $country->getId();
    }

    private function getSalutationId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('salutationKey', 'not_specified')
        );

        /** @var SalutationEntity|null $salutation */
        $salutation = $this->salutationRepository->search($criteria, $context)->first();

        if ($salutation === null) {
            throw new RuntimeException();
        }

        return $salutation->getId();
    }

    /**
     * @param array<string, string|null> $addressData
     */
    private function isIdenticalAddress(CustomerAddressEntity $address, array $addressData): bool
    {
        foreach (self::ADDRESS_KEYS as $key) {
            if ($address->get($key) !== ($addressData[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function getCustomerWebhookData(array $webhookData): ?array
    {
        if (empty($webhookData['checkoutData'])) {
            return null;
        }

        return (array) $webhookData['checkoutData'] ?? null;
    }

    private function getPaymentWebhookData(array $webhookData): ?array
    {
        if (empty($webhookData['payments'])) {
            return null;
        }

        return (array) reset($webhookData['payments']);
    }

    private function getCustomerAddressId(CustomerEntity $customer, array $addressData)
    {
        $matchingAddress = null;

        $addresses = $customer->getAddresses();
        if ($addresses !== null) {
            foreach ($addresses as $address) {
                if ($this->isIdenticalAddress($address, $addressData)) {
                    $matchingAddress = $address;

                    break;
                }
            }
        }

        return $matchingAddress === null ? Uuid::randomHex() : $matchingAddress->getId();
    }

    private function getSalesChannelCountryIso(SalesChannelContext $salesChannelContext): ?string
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('country');

        $salesChannel = $this->salesChannelRepository->search(
            $criteria,
            $salesChannelContext->getContext()
        )->first();

        if (!$salesChannel->getCountry()) {
            return null;
        }

        return (string) $salesChannel->getCountry()->getIso();
    }
}
