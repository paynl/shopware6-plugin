<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Request;

class CreateSessionRequest
{
    public function __construct(
        private string $returnUrl,
        private string $exchangeUrl,
        private SessionAmount $amount,
        private ?string $reference = null,
        private ?string $description = null,
        private ?string $expire = null,
        private ?SessionCustomer $customer = null,
        private ?SessionOrder $order = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'returnUrl' => $this->returnUrl,
            'exchangeUrl' => $this->exchangeUrl,
            'amount' => $this->amount->toArray(),
        ];

        if ($this->reference !== null) $data['reference'] = $this->reference;
        if ($this->description !== null) $data['description'] = $this->description;
        if ($this->expire !== null) $data['expire'] = $this->expire;
        if ($this->customer !== null) $data['customer'] = $this->customer->toArray();
        if ($this->order !== null) $data['order'] = $this->order->toArray();

        return $data;
    }
}