<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\PayPalExpress;

use PaynlPayment\Shopware6\Components\ExpressCheckoutUtil;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Repository\OrderDelivery\OrderDeliveryRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Amount;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\CreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Input;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Integration;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Optimize;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Order;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\PaymentMethod;
use PaynlPayment\Shopware6\ValueObjects\PAY\Response\CreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse as PayPalCreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Amount as PayPalAmount;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\CreateOrder as PayPalCreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\PurchaseUnit;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\OrderDetailResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Exception;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderAddressRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderCustomer\OrderCustomerRepositoryInterface;
use PaynlPayment\Shopware6\Service\CustomerService;
use PaynlPayment\Shopware6\Service\OrderService;
use PaynlPayment\Shopware6\Service\PayPal\v2\OrderService as PayPalOrderService;
use PaynlPayment\Shopware6\Service\PAY\v1\OrderService as PayOrderService;
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

class PayPalExpress
{
    private Config $config;
    private RouterInterface $router;
    private TranslatorInterface $translator;
    private CustomerService $customerService;
    private OrderService $orderService;
    private PayPalOrderService $paypalOrderService;
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
        PayPalOrderService $paypalOrderService,
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
        $this->paypalOrderService = $paypalOrderService;
        $this->payOrderService = $payOrderService;
        $this->processingHelper = $processingHelper;
        $this->expressCheckoutUtil = $expressCheckoutUtil;
        $this->repoOrderAddresses = $repoOrderAddresses;
        $this->orderCustomerRepository = $orderCustomerRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /** @throws Exception */
    private function updateOrder(OrderEntity $order, OrderDetailResponse $orderDetailResponse, SalesChannelContext $context): ?OrderEntity
    {
        $salutationId = $this->expressCheckoutUtil->getSalutationId($context->getContext());
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
        $salutationId = $this->expressCheckoutUtil->getSalutationId($context->getContext());
        $addressData = $this->getAddressData($orderDetailResponse, $context, $salutationId);
        $payer = $orderDetailResponse->getPayer();
        if (empty($addressData) || empty($payer)) {
            return $customer;
        }

        $billingAddressId = $this->expressCheckoutUtil->getCustomerAddressId($customer, $addressData);

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

    /** @throws PaynlPaymentException */
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
            throw new PaynlPaymentException('Created PayPal Express Direct order has not OrderTransaction!');
        }

        $orderResponse = $this->createPayPalExpressOrder($transaction->getId(), $context);

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

    private function createPayPalExpressOrder(
        string $orderTransactionId,
        SalesChannelContext $salesChannelContext
    ): PayPalCreateOrderResponse {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $orderTransaction = $this->processingHelper->getOrderTransaction($orderTransactionId, $salesChannelContext->getContext());

        $currency = $salesChannelContext->getCurrency()->getIsoCode();

        $paypalAmount = new PayPalAmount($currency, (string) $orderTransaction->getOrder()->getAmountTotal());

        $purchaseUnit = new PurchaseUnit($paypalAmount, $orderTransaction->getId());

        $createOrder = new PayPalCreateOrder(
            'CAPTURE',
            [$purchaseUnit]
        );

        $paypalOrder = $this->paypalOrderService->create($createOrder, $salesChannelId);

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
            throw new PaynlPaymentException('PayPal: Amount or reference ID is empty');
        }

        $orderTransaction = $this->processingHelper->getOrderTransaction($referenceId, $salesChannelContext->getContext());
        $orderId = $orderTransaction->getOrderId();

        $orderNumber = $orderTransaction->getOrder()->getOrderNumber();

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
        $products = $this->expressCheckoutUtil->getOrderProducts($orderTransaction->getOrder(), $salesChannelContext);
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

        $createOrderResponse = $this->payOrderService->create($createOrder, $salesChannelId);

        $this->processingHelper->storePaynlTransactionData(
            $orderTransaction,
            $createOrderResponse->getOrderId(),
            $salesChannelContext->getContext()
        );

        $this->updateOrder($orderTransaction->getOrder(), $payPalOrder, $salesChannelContext);

        $this->updateOrderCustomer($orderTransaction->getOrder()->getOrderCustomer(), $payPalOrder, $salesChannelContext);

        $this->updateCustomer($orderTransaction->getOrder()->getOrderCustomer()->getCustomer(), $payPalOrder, $salesChannelContext);

        $this->processingHelper->notifyActionUpdateTransactionByPayTransactionId($createOrderResponse->getOrderId());

        return $createOrderResponse;
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

        $countryId = $this->expressCheckoutUtil->getCountryIdByCode($countryCode, $context->getContext());
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
}
