<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Order;

class PurchaseUnit
{
    private Amount $amount;

    public function __construct(Amount $amount)
    {
        $this->amount = $amount;
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount->toArray()
        ];
    }
}