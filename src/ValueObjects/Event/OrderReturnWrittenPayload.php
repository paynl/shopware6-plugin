<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\Event;

class OrderReturnWrittenPayload
{
    private string $id;
    private string $orderId;
    private string $stateId;
    private float $amountTotal;
    private float $amountNet;
    private ?string $createdById;
    private ?string $createdAt;
    private ?string $returnNumber;
    private ?string $requestedAt;
    private ?string $internalComment;

    public function __construct(string $id, string $orderId, string $stateId, float $amountTotal, float $amountNet, ?string $createdById, ?string $createdAt, ?string $returnNumber, ?string $requestedAt, ?string $internalComment)
    {
        $this->id = $id;
        $this->orderId = $orderId;
        $this->stateId = $stateId;
        $this->amountTotal = $amountTotal;
        $this->amountNet = $amountNet;
        $this->createdById = $createdById;
        $this->createdAt = $createdAt;
        $this->returnNumber = $returnNumber;
        $this->requestedAt = $requestedAt;
        $this->internalComment = $internalComment;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function getAmountTotal(): float
    {
        return $this->amountTotal;
    }

    public function getAmountNet(): float
    {
        return $this->amountNet;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getReturnNumber(): ?string
    {
        return $this->returnNumber;
    }

    public function getRequestedAt(): ?string
    {
        return $this->requestedAt;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }
}