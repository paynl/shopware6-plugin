<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Request;

class SessionAmount
{
    public function __construct(
        private int $value,      // cents
        private string $currency // ISO 4217
    ) {}

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'currency' => $this->currency,
        ];
    }
}