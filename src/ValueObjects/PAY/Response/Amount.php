<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Response;

class Amount
{
    private int $value;
    private ?string $currency;

    public function __construct(int $value, ?string $currency)
    {
        $this->value = $value;
        $this->currency = $currency;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function toArray()
    {
        return [
            'value' => $this->value,
            'currency' => $this->currency
        ];
    }
}