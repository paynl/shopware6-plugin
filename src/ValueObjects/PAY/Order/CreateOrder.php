<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Order;

class CreateOrder
{
    private string $serviceId;
    private Amount $amount;
    private ?string $description;
    private ?string $reference;
    private ?string $returnUrl;
    private ?string $exchangeUrl;
    private ?Optimize $optimize;
    private ?PaymentMethod $paymentMethod;
    private ?Integration $integration;
    private ?Order $order;

    public function __construct(
        string $serviceId,
        Amount $amount,
        ?string $description,
        ?string $reference,
        ?string $returnUrl,
        ?string $exchangeUrl,
        ?Optimize $optimize,
        ?PaymentMethod $paymentMethod,
        ?Integration $integration,
        ?Order $order
    ) {
        $this->serviceId = $serviceId;
        $this->amount = $amount;
        $this->description = $description;
        $this->reference = $reference;
        $this->returnUrl = $returnUrl;
        $this->exchangeUrl = $exchangeUrl;
        $this->optimize = $optimize;
        $this->paymentMethod = $paymentMethod;
        $this->integration = $integration;
        $this->order = $order;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }

    public function getExchangeUrl(): ?string
    {
        return $this->exchangeUrl;
    }

    public function getOptimize(): ?Optimize
    {
        return $this->optimize;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getIntegration(): ?Integration
    {
        return $this->integration;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function toArray(): array
    {
        return array_filter([
            'serviceId' => $this->serviceId,
            'amount' => $this->amount->toArray(),
            'reference' => $this->reference,
            'description' => $this->description,
            'returnUrl' => $this->returnUrl,
            'exchangeUrl' => $this->exchangeUrl,
            'optimize' => $this->optimize ? $this->optimize->toArray() : null,
            'paymentMethod' => $this->paymentMethod ? $this->paymentMethod->toArray() : null,
            'integration' => $this->integration ? $this->integration->toArray() : null,
            'order' => $this->order ? $this->order->toArray() : null
        ]);
    }
}