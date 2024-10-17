<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Response;

class CreateOrderResponse
{
    private string $id;
    private string $status;
    private array $data;

    public function __construct(string $id, string $status, array $data)
    {
        $this->id = $id;
        $this->status = $status;
        $this->data = $data;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getData(): array
    {
        return $this->data;
    }
}