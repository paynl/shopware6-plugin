<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Order;

class PurchaseUnit
{
    private Amount $amount;
    private ?string $referenceId;

    public function __construct(Amount $amount, ?string $referenceId = null)
    {
        $this->amount = $amount;
        $this->referenceId = $referenceId;
    }

    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount->toArray(),
            'reference_id' => $this->referenceId,
        ]);
    }
}