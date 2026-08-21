<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Request;

class SessionOrder
{
    /** @param SessionProduct[] $products */
    public function __construct(
        private array $products,
        private int $shippingCosts, // cents
        private int $totalVat       // cents
    ) {}

    public function toArray(): array
    {
        return [
            'products' => array_map(fn(SessionProduct $p) => $p->toArray(), $this->products),
            'shippingCosts' => $this->shippingCosts,
            'totalVat' => $this->totalVat,
        ];
    }
}