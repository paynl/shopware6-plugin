<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Order;

class Shipping
{
    private ?string $fullName;
    private ?Address $address;

    public function __construct(?string $fullName, ?Address $address)
    {
        $this->fullName = $fullName;
        $this->address = $address;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }
}