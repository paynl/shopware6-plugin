<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal;

use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\OrderDetailResponse;

class OrderDataMapper
{
    public function mapCreateOrderArray(array $orderData): CreateOrderResponse
    {
        return new CreateOrderResponse(
            $orderData['id'] ?? '',
            $orderData['status'] ?? '',
            $orderData
        );
    }

    public function mapOrderDetailArray(array $orderData): OrderDetailResponse
    {
        return new OrderDetailResponse(
            $orderData['id'] ?? '',
            $orderData['status'] ?? '',
            $orderData['intent'] ?? '',
            $orderData
        );
    }
}