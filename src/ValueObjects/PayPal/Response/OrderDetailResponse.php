<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Response;

class OrderDetailResponse
{
    private string $id;
    private string $status;
    private string $intent;
    private array $data;

    public function __construct(string $id, string $status, string $intent, array $data)
    {
        $this->id = $id;
        $this->status = $status;
        $this->intent = $intent;
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

    public function getIntent(): string
    {
        return $this->intent;
    }

    public function getData(): array
    {
        return $this->data;
    }
}