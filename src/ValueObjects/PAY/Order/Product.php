<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Order;

class Product
{
    private ?Amount $price;
    private ?string $id;
    private ?string $description;
    private ?string $type;
    private ?float $quantity;
    private ?float $vatPercentage;

    public function __construct(
        ?Amount $price,
        ?string $id,
        ?string $description,
        ?string $type,
        ?float $quantity,
        ?float $vatPercentage
    ) {
        $this->price = $price;
        $this->id = $id;
        $this->description = $description;
        $this->type = $type;
        $this->quantity = $quantity;
        $this->vatPercentage = $vatPercentage;
    }

    public function getPrice(): ?Amount
    {
        return $this->price;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function getVatPercentage(): ?float
    {
        return $this->vatPercentage;
    }

    public function toArray()
    {
        return array_filter([
            'price' => $this->price ? $this->price->toArray() : null,
            'id' => $this->id,
            'description' => $this->description,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'vatPercentage' => $this->vatPercentage,
        ]);
    }
}