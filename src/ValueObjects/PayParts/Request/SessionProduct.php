<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Request;

class SessionProduct
{
    public function __construct(
        private string $description,
        private int $quantity,
        private int $unitPrice,   // cents
        private int $total,       // cents
        private string $productId,
        private float $vatPercentage
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'total' => $this->total,
            'productId' => $this->productId,
            'vatPercentage' => $this->vatPercentage,
        ];
    }
}