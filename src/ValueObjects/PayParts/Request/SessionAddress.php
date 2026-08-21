<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Request;

class SessionAddress
{
    public function __construct(
        private string $street,
        private string $houseNumber,
        private string $postalCode,
        private string $city,
        private string $country
    ) {}

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}