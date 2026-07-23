<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Request;

class SessionCustomer
{
    public function __construct(
        private string $locale,
        private ?string $firstName = null,
        private ?string $lastName = null,
        private ?string $email = null,
        private ?string $phone = null,
        private ?SessionAddress $address = null
    ) {}

    public function toArray(): array
    {
        $data = ['locale' => $this->locale];

        if ($this->firstName !== null) $data['firstName'] = $this->firstName;
        if ($this->lastName !== null) $data['lastName'] = $this->lastName;
        if ($this->email !== null) $data['email'] = $this->email;
        if ($this->phone !== null) $data['phone'] = $this->phone;
        if ($this->address !== null) $data['address'] = $this->address->toArray();

        return $data;
    }
}