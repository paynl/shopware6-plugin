<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Response;

class OrderCreationResult
{
    public function __construct(
        private string $orderId,
        private string $orderTransactionId,
        private string $finishUrl,
        private string $editOrderUrl
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function getFinishUrl(): string
    {
        return $this->finishUrl;
    }

    public function getEditOrderUrl(): string
    {
        return $this->editOrderUrl;
    }
}
