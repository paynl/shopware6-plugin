<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Order;

class PaymentMethod
{
    private int $id;
    private ?Optimize $optimize;
    private ?Input $input;

    public function __construct(int $id, ?Optimize $optimize, ?Input $input)
    {
        $this->id = $id;
        $this->optimize = $optimize;
        $this->input = $input;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOptimize(): ?Optimize
    {
        return $this->optimize;
    }

    public function getInput(): ?Input
    {
        return $this->input;
    }

    public function toArray()
    {
        return array_filter([
            'id' => $this->id,
            'optimize' => $this->optimize ? $this->optimize->toArray() : null,
            'input' => $this->input ? $this->input->toArray() : null,
        ]);
    }
}