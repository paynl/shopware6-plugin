<?php

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\ValueObjects\IssuerValueObject;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface OrderServiceInterface
{
    /**
     * @param DataBag $data
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity;

    /**
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrder(string $orderId, Context $context): OrderEntity;

    /**
     * @param string $orderNumber
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity;

    /**
     * Keep the bank chosen for the payment on the order, or remove an earlier one when no bank is given.
     */
    public function saveIssuer(OrderEntity $order, ?IssuerValueObject $bank, Context $context): void;
}
