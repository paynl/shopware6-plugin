<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Order;

class Optimize
{
    private string $flow;
    private bool $contactDetails;
    private bool $shippingAddress;
    private bool $billingAddress;

    public function __construct(string $flow, bool $contactDetails, bool $shippingAddress, bool $billingAddress)
    {
        $this->flow = $flow;
        $this->contactDetails = $contactDetails;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
    }

    public function getFlow(): string
    {
        return $this->flow;
    }

    public function isContactDetails(): bool
    {
        return $this->contactDetails;
    }

    public function isShippingAddress(): bool
    {
        return $this->shippingAddress;
    }

    public function isBillingAddress(): bool
    {
        return $this->billingAddress;
    }

    public function toArray(): array
    {
        return array_filter([
            'flow' => $this->flow,
            'contactDetails' => $this->contactDetails,
            'shippingAddress' => $this->shippingAddress,
            'billingAddress' => $this->billingAddress,
        ]);
    }
}