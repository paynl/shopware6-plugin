<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Order;

class Input
{
    private string $orderId;

    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function toArray(): array
    {
        return array_filter([
            'orderId' => $this->orderId,
        ]);
    }
}