<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Order;

class Address
{
    private ?string $addressLine1;
    private ?string $adminArea1;
    private ?string $adminArea2;
    private ?string $postalCode;
    private ?string $countryCode;

    public function __construct(?string $addressLine1, ?string $adminArea1, ?string $adminArea2, ?string $postalCode, ?string $countryCode)
    {
        $this->addressLine1 = $addressLine1;
        $this->adminArea1 = $adminArea1;
        $this->adminArea2 = $adminArea2;
        $this->postalCode = $postalCode;
        $this->countryCode = $countryCode;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function getAdminArea1(): ?string
    {
        return $this->adminArea1;
    }

    public function getAdminArea2(): ?string
    {
        return $this->adminArea2;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }
}