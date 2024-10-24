<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Order;

class Payer
{
    private string $firstName;
    private string $lastName;
    private string $email;
    private ?string $phone;
    private ?Address $address;

    public function __construct(string $firstName, string $lastName, string $email, ?string $phone, ?Address $address = null)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phone = $phone;
        $this->address = $address;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }
}