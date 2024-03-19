<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Response;

use PaynlPayment\Shopware6\ValueObjects\PAY\Order\Amount;

class CreateOrderResponse
{
    private string $id;
    private string $serviceId;
    private string $reference;
    private string $orderId;
    private string $uuid;
    private Status $status;
    private Amount $amount;
    private Amount $authorizedAmount;
    private Amount $capturedAmount;
    private Links $links;

    public function __construct(string $id, string $serviceId, string $reference, string $orderId, string $uuid, Status $status, Amount $amount, Amount $authorizedAmount, Amount $capturedAmount, Links $links)
    {
        $this->id = $id;
        $this->serviceId = $serviceId;
        $this->reference = $reference;
        $this->orderId = $orderId;
        $this->uuid = $uuid;
        $this->status = $status;
        $this->amount = $amount;
        $this->authorizedAmount = $authorizedAmount;
        $this->capturedAmount = $capturedAmount;
        $this->links = $links;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }

    public function getAuthorizedAmount(): Amount
    {
        return $this->authorizedAmount;
    }

    public function getCapturedAmount(): Amount
    {
        return $this->capturedAmount;
    }

    public function getLinks(): Links
    {
        return $this->links;
    }
}