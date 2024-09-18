<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Order;

class PurchaseUnit
{
    private Amount $amount;
    private ?string $referenceId;
    private ?Shipping $shipping;

    public function __construct(Amount $amount, ?string $referenceId = null, ?Shipping $shipping = null)
    {
        $this->amount = $amount;
        $this->referenceId = $referenceId;
        $this->shipping = $shipping;
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function getShipping(): ?Shipping
    {
        return $this->shipping;
    }

    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount->toArray(),
            'reference_id' => $this->referenceId,
        ]);
    }
}