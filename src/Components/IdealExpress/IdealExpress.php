<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\IdealExpress;

use PayNL\Sdk\Exception\PayException;
use PayNL\Sdk\Model;
use PayNL\Sdk\Model\Pay\PayOrder;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\ExpressCheckoutUtil;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Repository\OrderDelivery\OrderDeliveryRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Throwable;
use Exception;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\Repository\Order\OrderAddressRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderCustomer\OrderCustomerRepositoryInterface;
use PaynlPayment\Shopware6\Service\CustomerService;
use PaynlPayment\Shopware6\Service\OrderService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class IdealExpress
{
    private CustomerService $customerService;
    private OrderService $orderService;
    private Api $payAPI;
    private ProcessingHelper $processingHelper;
    private ExpressCheckoutUtil $expressCheckoutUtil;
    private OrderAddressRepositoryInterface $repoOrderAddresses;
    private OrderCustomerRepositoryInterface $orderCustomerRepository;
    private OrderDeliveryRepositoryInterface $orderDeliveryRepository;

    public function __construct(
        CustomerService $customerService,
        OrderService $orderService,
        Api $payAPI,
        ProcessingHelper $processingHelper,
        ExpressCheckoutUtil $expressCheckoutUtil,
        OrderAddressRepositoryInterface $repoOrderAddresses,
        OrderCustomerRepositoryInterface $orderCustomerRepository,
        OrderDeliveryRepositoryInterface $orderDeliveryRepository
    ) {
        $this->customerService = $customerService;
        $this->orderService = $orderService;
        $this->payAPI = $payAPI;
        $this->processingHelper = $processingHelper;
        $this->expressCheckoutUtil = $expressCheckoutUtil;
        $this->repoOrderAddresses = $repoOrderAddresses;
        $this->orderCustomerRepository = $orderCustomerRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /** @throws Exception */
    public function updateOrder(OrderEntity $order, array $customerData, SalesChannelContext $context): ?OrderEntity
    {
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
        array $customerData,
        SalesChannelContext $context
    ): void {
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
        array $customerWebhookData,
        SalesChannelContext $context
    ): ?CustomerEntity {
        if (empty($customerWebhookData)) {
            return $customer;
        }

        $shippingAddressData = $this->getAddressData($customerWebhookData, $context);
        $invoiceAddressData = $this->getAddressData($customerWebhookData, $context, null, 'billingAddress');

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

    /**
     * @throws PaynlPaymentException
     * @throws Throwable
     */
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

            $this->processingHelper->storePayTransactionData(
                $orderTransaction,
                $orderResponse->getOrderId(),
                $context->getContext()
            );
        } catch (Throwable $exception) {
            $this->processingHelper->storePayTransactionData(
                $orderTransaction,
                '',
                $context->getContext(),
                $exception
            );

            throw $exception;
        }

        return $orderResponse->getPaymentUrl();
    }

    /** @throws Exception */
    public function processNotify(PayOrder $payOrder): string
    {
        return $this->processingHelper->processNotify($payOrder->getOrderId());
    }

    /** @throws PayException */
    public function getPayTransactionByID(string $transactionId, SalesChannelContext $salesChannelContext): PayOrder
    {
        return $this->payAPI->getOrderStatus($transactionId, $salesChannelContext->getSalesChannel()->getId());
    }

    /**
     * @throws PaynlPaymentException
     * @throws Exception
     */
    private function createPayIdealExpressOrder(
        string $orderTransactionId,
        string $shopwareReturnUrl,
        SalesChannelContext $salesChannelContext
    ): Model\Pay\PayOrder {
        $orderCreateRequest = $this->expressCheckoutUtil->buildOrderCreateRequest($orderTransactionId, $shopwareReturnUrl, $salesChannelContext);
        $orderCreateRequest->setPaymentMethodId(Model\Method::IDEAL);

        return $orderCreateRequest->start();
    }

    /**
     * @return array<string, string|null>
     */
    private function getAddressData(
        array $customerWebhookData,
        SalesChannelContext $context,
        ?string $salutationId = null,
        string $addressType = 'shippingAddress'
    ): array {
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
}
