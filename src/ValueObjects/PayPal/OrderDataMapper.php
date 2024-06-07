<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal;

use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse;

class OrderDataMapper
{
    public function mapArray(array $orderData): CreateOrderResponse
    {
        return new CreateOrderResponse(
            $orderData['id'],
            $orderData['status'],
            $orderData
        );
    }
}