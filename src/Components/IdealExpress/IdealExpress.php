<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\IdealExpress;

use PaynlPayment\Shopware6\Components\ExpressCheckoutUtil;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Repository\OrderDelivery\OrderDeliveryRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Integration;
use PaynlPayment\Shopware6\ValueObjects\PAY\OrderDataMapper;
use PaynlPayment\Shopware6\ValueObjects\PAY\Response\CreateOrderResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Throwable;
use Exception;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderAddressRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderCustomer\OrderCustomerRepositoryInterface;
use PaynlPayment\Shopware6\Service\CustomerService;
use PaynlPayment\Shopware6\Service\OrderService;
use PaynlPayment\Shopware6\Service\PAY\v1\OrderService as PayOrderService;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Amount;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\CreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Optimize;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Order;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class IdealExpress
{
    private Config $config;
    private RouterInterface $router;
    private TranslatorInterface $translator;
    private CustomerService $customerService;
    private OrderService $orderService;
    private PayOrderService $payOrderService;
    private ProcessingHelper $processingHelper;
    private ExpressCheckoutUtil $expressCheckoutUtil;
    private OrderAddressRepositoryInterface $repoOrderAddresses;
    private OrderCustomerRepositoryInterface $orderCustomerRepository;
    private OrderDeliveryRepositoryInterface $orderDeliveryRepository;

    public function __construct(
        Config $config,
        RouterInterface $router,
        TranslatorInterface $translator,
        CustomerService $customerService,
        OrderService $orderService,
        PayOrderService $payOrderService,
        ProcessingHelper $processingHelper,
        ExpressCheckoutUtil $expressCheckoutUtil,
        OrderAddressRepositoryInterface $repoOrderAddresses,
        OrderCustomerRepositoryInterface $orderCustomerRepository,
        OrderDeliveryRepositoryInterface $orderDeliveryRepository
    ) {
        $this->config = $config;
        $this->router = $router;
        $this->translator = $translator;
        $this->customerService = $customerService;
        $this->orderService = $orderService;
        $this->payOrderService = $payOrderService;
        $this->processingHelper = $processingHelper;
        $this->expressCheckoutUtil = $expressCheckoutUtil;
        $this->repoOrderAddresses = $repoOrderAddresses;
        $this->orderCustomerRepository = $orderCustomerRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /** @throws Exception */
    public function updateOrder(OrderEntity $order, array $webhookData, SalesChannelContext $context): ?OrderEntity
    {
        $customerData = $this->getCustomerWebhookData($webhookData);
        if (empty($customerData)) {
            return $order;
        }

        $countryCode = strtoupper($customerData['billingAddress']['countryCode']);
        $countryId = $this->expressCheckoutUtil->getCountryIdByCode($countryCode, $context->getContext());
        if (empty($countryId)) {
            $countryId = $context->getSalesChannel()->getCountryId();
        }

        if ($order->getAddresses() instanceof OrderAddressCollection) {
            foreach ($order->getAddresses() as $address) {
                $this->repoOrderAddresses->updateAddress(
                    $address->getId(),
                    $customerData['customer']['firstName'],
                    $customerData['customer']['lastName'],
                    '',
                    '',
                    '',
                    sprintf(
                        "%s %s",
                        $customerData['billingAddress']['streetName'],
                        $customerData['billingAddress']['streetNumber'],
                    ),
                    $customerData['billingAddress']['zipCode'],
                    $customerData['billingAddress']['city'],
                    $countryId,
                    $context->getContext()
                );
            }

            $shippingAddressId = Uuid::randomHex();
            $addressData = [
                'id' => $shippingAddressId,
                'countryId' => $countryId,
                'orderId' => $order->getId(),
                'salutationId' => $this->expressCheckoutUtil->getSalutationId($context->getContext()),
                'firstName' => $customerData['customer']['firstName'],
                'lastName' => $customerData['customer']['lastName'],
                'street' => sprintf(
                    "%s %s",
                    $customerData['shippingAddress']['streetName'],
                    $customerData['shippingAddress']['streetNumber'],
                ),
                'zipcode' => $customerData['shippingAddress']['zipCode'],
                'city' => $customerData['shippingAddress']['city'],
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

    public function updateOrderCustomer(
        OrderCustomerEntity $customer,
        array $webhookData,
        SalesChannelContext $context
    ): void {
        $customerData = $this->getCustomerWebhookData($webhookData);
        if (empty($customerData)) {
            return;
        }

        $this->orderCustomerRepository->update([
            [
                'id' => $customer->getId(),
                'email' => $customerData['customer']['email'],
                'firstName' => $customerData['customer']['firstName'],
                'lastName' => $customerData['customer']['lastName'],
            ]
        ], $context->getContext());
    }

    public function updateCustomer(
        CustomerEntity $customer,
        array $webhookData,
        SalesChannelContext $context
    ): ?CustomerEntity {
        $customerWebhookData = $this->getCustomerWebhookData($webhookData);
        if (empty($customerWebhookData)) {
            return $customer;
        }

        $shippingAddressData = $this->getAddressData($webhookData, $context);
        $invoiceAddressData = $this->getAddressData($webhookData, $context, null, 'billingAddress');

        $shippingAddressId = $this->expressCheckoutUtil->getCustomerAddressId($customer, $shippingAddressData);
        $billingAddressId = $this->expressCheckoutUtil->getCustomerAddressId($customer, $invoiceAddressData);

        $salutationId = $this->expressCheckoutUtil->getSalutationId($context->getContext());

        $customerData = [
            'id' => $customer->getId(),
            'email' => $customerWebhookData['customer']['email'],
            'defaultShippingAddressId' => $shippingAddressId,
            'defaultBillingAddressId' => $billingAddressId,
            'firstName' => $customerWebhookData['customer']['firstName'],
            'lastName' => $customerWebhookData['customer']['lastName'],
            'salutationId' => $salutationId,
            'addresses' => [
                array_merge($shippingAddressData, [
                    'id' => $shippingAddressId,
                    'salutationId' => $salutationId,
                ]),
                array_merge($invoiceAddressData, [
                    'id' => $billingAddressId,
                    'salutationId' => $salutationId,
                ]),
            ],
        ];

        return $this->customerService->updateCustomer($customerData, $context);
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
        $countryID = (string) $this->customerService->getCountryId($countryCode, $context->getContext());

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
            throw new PaynlPaymentException('Created IDEAL Express Direct order has not OrderTransaction!');
        }

        try {
            $orderResponse = $this->createPayIdealExpressOrder($transaction->getId(), $shopwareReturnUrl, $context);

            $orderTransaction = $this->processingHelper->getOrderTransaction($transaction->getId(), $context->getContext());

            $this->processingHelper->storePaynlTransactionData(
                $orderTransaction,
                $orderResponse->getOrderId(),
                $context->getContext()
            );
        } catch (Throwable $exception) {
            $this->processingHelper->storePaynlTransactionData(
                $orderTransaction,
                '',
                $context->getContext(),
                $exception
            );

            throw $exception;
        }

        return $orderResponse->getLinks()->getRedirect();
    }

    /** @throws Exception */
    public function processNotify(array $orderData): string
    {
        $orderDataMapper = new OrderDataMapper();
        $order = $orderDataMapper->mapArray($orderData);

        return $this->processingHelper->processNotify($order->getOrderId());
    }

    private function createPayIdealExpressOrder(
        string $orderTransactionId,
        string $shopwareReturnUrl,
        SalesChannelContext $salesChannelContext
    ): CreateOrderResponse {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $orderTransaction = $this->processingHelper->getOrderTransaction($orderTransactionId, $salesChannelContext->getContext());
        $orderNumber = $orderTransaction->getOrder()->getOrderNumber();

        $exchangeUrl = $this->router->generate(
            'frontend.account.PaynlPayment.ideal-express.finish-payment',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $amount = (int) round($orderTransaction->getOrder()->getAmountTotal() * 100);
        $currency = $salesChannelContext->getCurrency()->getIsoCode();

        $amount = new Amount($amount, $currency);

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

        $paymentMethod = new PaymentMethod(PaynlPaymentMethodsIdsEnum::IDEAL_PAYMENT, null, null);
        $products = $this->expressCheckoutUtil->getOrderProducts($orderTransaction->getOrder(), $salesChannelContext);
        $order = new Order($products);

        $integration = $this->config->getTestMode($salesChannelId) ? new Integration(true) : null;

        $createOrder = new CreateOrder(
            $this->config->getServiceId($salesChannelId),
            $amount,
            $description,
            $orderNumber,
            $shopwareReturnUrl,
            $exchangeUrl,
            $optimize,
            $paymentMethod,
            $integration,
            $order
        );

        return $this->payOrderService->create($createOrder, $salesChannelId);
    }

    /**
     * @return array<string, string|null>
     */
    private function getAddressData(
        array $webhookData,
        SalesChannelContext $context,
        ?string $salutationId = null,
        string $addressType = 'shippingAddress'
    ): array {
        $customerWebhookData = $this->getCustomerWebhookData($webhookData);
        $countryCode = strtoupper($customerWebhookData[$addressType]['countryCode']);
        $countryId = $this->expressCheckoutUtil->getCountryIdByCode($countryCode, $context->getContext());
        if (empty($countryId)) {
            $countryId = $context->getSalesChannel()->getCountryId();
        }

        return [
            'firstName' => $customerWebhookData['customer']['firstName'],
            'lastName' => $customerWebhookData['customer']['lastName'],
            'salutationId' => $salutationId,
            'street' => sprintf(
                "%s %s",
                $customerWebhookData[$addressType]['streetName'],
                $customerWebhookData[$addressType]['streetNumber'],
            ),
            'zipcode' => $customerWebhookData[$addressType]['zipCode'],
            'countryId' => $countryId,
            'phoneNumber' => $customerWebhookData['customer']['phone'],
            'city' => $customerWebhookData[$addressType]['city'],
            'additionalAddressLine1' => null,
        ];
    }

    private function getCustomerWebhookData(array $webhookData): ?array
    {
        if (empty($webhookData['checkoutData'])) {
            return null;
        }

        return (array) $webhookData['checkoutData'] ?? null;
    }
}
