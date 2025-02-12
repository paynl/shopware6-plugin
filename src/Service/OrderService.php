<?php

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService as ShopwareOrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderService implements OrderServiceInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ShopwareOrderService
     */
    private $swOrderService;

    public function __construct(OrderRepositoryInterface $orderRepository, ShopwareOrderService $swOrderService)
    {
        $this->orderRepository = $orderRepository;
        $this->swOrderService = $swOrderService;
    }

    public function getOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('addresses.country');     # required for FlowBuilder -> send confirm email option
        $criteria->addAssociation('billingAddress');    # important for subscription creation
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.positions.orderLineItem');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('transactions.stateMachineState');


        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order instanceof OrderEntity) {
            return $order;
        }

        throw new \Exception($orderId);
    }

    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));

        $orderId = $this->orderRepository->searchIds($criteria, $context)->firstId();

        if (is_string($orderId)) {
            return $this->getOrder($orderId, $context);
        }

        throw new \Exception($orderNumber);
    }

    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity
    {
        $orderId = $this->swOrderService->createOrder($data, $context);

        $order = $this->getOrder($orderId, $context->getContext());

        if (!$order instanceof OrderEntity) {
            throw new \Exception($orderId);
        }

        return $order;
    }
}
