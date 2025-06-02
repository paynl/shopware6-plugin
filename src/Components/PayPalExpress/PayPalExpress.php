<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\PayPalExpress;

use PayNL\Sdk\Model;
use PayNL\Sdk\Exception\PayException;
use PaynlPayment\Shopware6\Components\ExpressCheckoutUtil;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Exceptions\PayPalPaymentApi;
use PaynlPayment\Shopware6\Repository\OrderDelivery\OrderDeliveryRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse as PayPalCreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Amount as PayPalAmount;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\CreateOrder as PayPalCreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\PurchaseUnit;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\OrderDetailResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Exception;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderAddressRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderCustomer\OrderCustomerRepositoryInterface;
use PaynlPayment\Shopware6\Service\CustomerService;
use PaynlPayment\Shopware6\Service\OrderService;
use PaynlPayment\Shopware6\Service\PayPal\v2\OrderService as PayPalOrderService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class PayPalExpress
{
    private RouterInterface $router;
    private CustomerService $customerService;
    private OrderService $orderService;
    private PayPalOrderService $paypalOrderService;
    private ProcessingHelper $processingHelper;
    private ExpressCheckoutUtil $expressCheckoutUtil;
    private OrderAddressRepositoryInterface $repoOrderAddresses;
    private OrderCustomerRepositoryInterface $orderCustomerRepository;
    private OrderDeliveryRepositoryInterface $orderDeliveryRepository;

    public function __construct(
        RouterInterface $router,
        CustomerService $customerService,
        OrderService $orderService,
        PayPalOrderService $paypalOrderService,
        ProcessingHelper $processingHelper,
        ExpressCheckoutUtil $expressCheckoutUtil,
        OrderAddressRepositoryInterface $repoOrderAddresses,
        OrderCustomerRepositoryInterface $orderCustomerRepository,
        OrderDeliveryRepositoryInterface $orderDeliveryRepository
    ) {
        $this->router = $router;
        $this->customerService = $customerService;
        $this->orderService = $orderService;
        $this->paypalOrderService = $paypalOrderService;
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

    /**
     * @throws PayPalPaymentApi
     * @throws PaynlPaymentException
     */
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

        return $this->paypalOrderService->create($createOrder, $salesChannelId);
    }

    /**
     * @throws PaynlPaymentException
     * @throws PayException
     * @throws PayPalPaymentApi
     * @throws Exception
     */
    public function createPayPaymentTransaction(
        string $payPalOrderId,
        SalesChannelContext $salesChannelContext
    ): Model\Pay\PayOrder {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

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

        $returnUrl = $this->router->generate(
            'frontend.account.PaynlPayment.paypal-express.finish-page',
            [
                'orderId' => $orderId,
            ],
            $this->router::ABSOLUTE_URL
        );

        $orderCreateRequest = $this->expressCheckoutUtil->buildOrderCreateRequest($orderTransaction->getId(), $returnUrl, $salesChannelContext);
        $orderCreateRequest->setPaymentMethodId(Model\Method::PAYPAL);
        $orderCreateRequest->setPayPalOrderId($payPalOrder->getId());

        $orderCreateResponse = $orderCreateRequest->start();

        $this->processingHelper->storePayTransactionData(
            $orderTransaction,
            $orderCreateResponse->getOrderId(),
            $salesChannelContext->getContext()
        );

        $order = $this->orderService->getOrderByNumber($orderTransaction->getOrder()->getOrderNumber(), $salesChannelContext->getContext());

        $this->updateOrder($order, $payPalOrder, $salesChannelContext);

        $this->updateOrderCustomer($order->getOrderCustomer(), $payPalOrder, $salesChannelContext);

        $this->updateCustomer($order->getOrderCustomer()->getCustomer(), $payPalOrder, $salesChannelContext);

        $this->processingHelper->notifyActionUpdateTransactionByPayTransactionId($orderCreateResponse->getOrderId());

        return $orderCreateResponse;
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
