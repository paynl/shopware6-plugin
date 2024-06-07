<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Order;

class Integration
{
    private ?bool $test;

    public function __construct(?bool $test)
    {
        $this->test = $test;
    }

    public function getTest(): ?bool
    {
        return $this->test;
    }

    public function toArray(): array
    {
        return array_filter([
            'test' => $this->test
        ]);
    }
}