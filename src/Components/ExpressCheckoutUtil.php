<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use Exception;
use PayNL\Sdk\Model;
use PayNL\Sdk\Model\Request\OrderCreateRequest;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Helper\PluginHelper;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\Country\CountryRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Repository\Product\ProductRepositoryInterface;
use PaynlPayment\Shopware6\Repository\SalesChannel\SalesChannelRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Salutation\SalutationRepositoryInterface;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartServiceInterface;
use PaynlPayment\Shopware6\Service\CustomerService;
use PaynlPayment\Shopware6\Service\OrderService;
use RuntimeException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class ExpressCheckoutUtil
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

    private CustomerService $customerService;
    private CartServiceInterface $cartService;
    private CartBackupService $cartBackupService;
    private OrderService $orderService;
    private RouterInterface $router;
    private TranslatorInterface $translator;
    private Api $payAPI;
    private Config $config;
    private PluginHelper $pluginHelper;
    private ProcessingHelper $processingHelper;
    private AbstractLogoutRoute $logoutRoute;
    private CountryRepositoryInterface $countryRepository;
    private PaymentMethodRepository $repoPaymentMethods;
    private ProductRepositoryInterface $productRepository;
    private SalutationRepositoryInterface $salutationRepository;
    private SalesChannelRepositoryInterface $salesChannelRepository;
    private string $shopwareVersion;

    public function __construct(
        CustomerService $customerService,
        CartServiceInterface $cartService,
        CartBackupService $cartBackupService,
        OrderService $orderService,
        RouterInterface $router,
        TranslatorInterface $translator,
        Api $payAPI,
        Config $config,
        PluginHelper $pluginHelper,
        ProcessingHelper $processingHelper,
        AbstractLogoutRoute $logoutRoute,
        CountryRepositoryInterface $countryRepository,
        PaymentMethodRepository $repoPaymentMethods,
        ProductRepositoryInterface $productRepository,
        SalutationRepositoryInterface $salutationRepository,
        SalesChannelRepositoryInterface $salesChannelRepository,
        string $shopwareVersion
    ) {
        $this->customerService = $customerService;
        $this->cartService = $cartService;
        $this->cartBackupService = $cartBackupService;
        $this->orderService = $orderService;
        $this->router = $router;
        $this->translator = $translator;
        $this->payAPI = $payAPI;
        $this->config = $config;
        $this->pluginHelper = $pluginHelper;
        $this->processingHelper = $processingHelper;
        $this->logoutRoute = $logoutRoute;
        $this->countryRepository = $countryRepository;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->productRepository = $productRepository;
        $this->salutationRepository = $salutationRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function getActiveIdealID(SalesChannelContext $context): string
    {
        return $this->repoPaymentMethods->getActiveIdealID($context->getContext());
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
        string $paymentMethodId,
        SalesChannelContext $context
    ): SalesChannelContext {
        $this->cartBackupService->clearBackup($context);

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
                $paymentMethodId,
                $context
            );

            if (!$customer instanceof CustomerEntity) {
                throw new Exception('Error when creating customer!');
            }

            $this->customerService->customerLogin($customer, $context);
        }

        // update our payment method to use PayPal Express for our cart
        return $this->cartService->updatePaymentMethod($context, $paymentMethodId);
    }

    public function createOrder(SalesChannelContext $context): OrderEntity
    {
        $data = new DataBag();

        $data->add(['tos' => true]);

        return $this->orderService->createOrder($data, $context);
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

    public function logoutCustomer(SalesChannelContext $salesChannelContext): void
    {
        $this->logoutRoute->logout($salesChannelContext, new RequestDataBag());
    }

    /** @throws PaynlPaymentException */
    public function buildOrderCreateRequest(
        string $orderTransactionId,
        string $shopwareReturnUrl,
        SalesChannelContext $salesChannelContext
    ): OrderCreateRequest {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $currency = $salesChannelContext->getCurrency()->getIsoCode();
        $orderTransaction = $this->processingHelper->getOrderTransaction($orderTransactionId, $salesChannelContext->getContext());
        $orderNumber = $orderTransaction->getOrder()->getOrderNumber();

        $exchangeUrl = $this->router->generate(
            'frontend.account.PaynlPayment.ideal-express.finish-payment',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $description = sprintf(
            '%s %s',
            $this->translator->trans('transactionLabels.order'),
            $orderNumber
        );

        $request = new OrderCreateRequest();
        $request->setServiceId($this->config->getServiceId($salesChannelId));
        $request->setDescription($description);
        $request->setReference($orderNumber);
        $request->setReturnurl($shopwareReturnUrl);
        $request->setExchangeUrl($exchangeUrl);
        $request->setAmount($orderTransaction->getOrder()->getAmountTotal());
        $request->setCurrency($currency);
        $request->setTestmode((bool) $this->config->getTestMode($salesChannelId));

        $request->enableFastCheckout();

        $payNLOrder = new Model\Order();

        $payNLOrder->setProducts($this->getOrderProducts($orderTransaction->getOrder(), $salesChannelContext));

        $request->setOrder($payNLOrder);

        $request->setStats($this->getOrderStats());

        $request->setConfig($this->payAPI->getConfig($salesChannelContext->getSalesChannel()->getId(), true));

        return $request;
    }

    public function getOrderProducts(OrderEntity $order, SalesChannelContext $salesChannelContext): Model\Products
    {
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
            $product->setVatPercentage($vatPercentage);

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
            $product->setVatPercentage($vatPercentage);

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
        $product->setVatPercentage($order->getShippingCosts()->getCalculatedTaxes()->getAmount());

        $products->addProduct($product);

        return $products;
    }

    public function getOrderStats(): Model\Stats
    {
        return (new Model\Stats())
            ->setObject(sprintf(
                'Shopware v%s %s',
                $this->shopwareVersion,
                $this->pluginHelper->getPluginVersionFromComposer(),
            ));
    }

    public function getCountryIdByCode(string $code, Context $context): ?string
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

    public function getSalutationId(Context $context): string
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
    public function isIdenticalAddress(CustomerAddressEntity $address, array $addressData): bool
    {
        foreach (self::ADDRESS_KEYS as $key) {
            if ($address->get($key) !== ($addressData[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function getCustomerAddressId(CustomerEntity $customer, array $addressData)
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

    public function getSalesChannelCountryIso(SalesChannelContext $salesChannelContext): ?string
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