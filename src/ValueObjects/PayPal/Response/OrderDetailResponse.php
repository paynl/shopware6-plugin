<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal\Response;

use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Payer;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\PurchaseUnit;

class OrderDetailResponse
{
    private string $id;
    private string $status;
    private string $intent;
    private ?Payer $payer;
    /** @var PurchaseUnit[] */
    private array $purchaseUnits;
    private array $data;

    public function __construct(string $id, string $status, string $intent, ?Payer $payer, array $purchaseUnits, array $data)
    {
        $this->id = $id;
        $this->status = $status;
        $this->intent = $intent;
        $this->payer = $payer;
        $this->purchaseUnits = $purchaseUnits;
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

    public function getPayer(): ?Payer
    {
        return $this->payer;
    }

    public function getPurchaseUnits(): array
    {
        return $this->purchaseUnits;
    }

    public function getData(): array
    {
        return $this->data;
    }
}