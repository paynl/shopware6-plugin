<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Order;

class CreateOrder
{
    private string $intent;

    /** @var PurchaseUnit[] */
    private array $purchaseUnits;

    public function __construct(string $intent, array $purchaseUnits)
    {
        $this->intent = $intent;
        $this->purchaseUnits = $purchaseUnits;
    }

    public function getIntent(): string
    {
        return $this->intent;
    }

    public function getPurchaseUnits(): array
    {
        return $this->purchaseUnits;
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'purchase_units' => array_map(function (PurchaseUnit $purchaseUnit) {
                return $purchaseUnit->toArray();
            }, $this->purchaseUnits)
        ];
    }
}