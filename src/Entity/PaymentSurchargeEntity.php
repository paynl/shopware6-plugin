<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Entity;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PaymentSurchargeEntity extends Entity
{
    use EntityIdTrait;

    const TYPE_ABSOLUTE = 'absolute';
    const TYPE_PERCENTAGE = 'percentage';

    protected float $amount;
    protected ?float $orderValueLimit = null;
    protected ?string $type = null;
    protected string $paymentMethodId;
    protected PaymentMethodEntity $paymentMethod;

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getPaymentMethod(): PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getOrderValueLimit(): float
    {
        return $this->orderValueLimit ?? 0;
    }

    public function setOrderValueLimit(?float $orderValueLimit): void
    {
        $this->orderValueLimit = $orderValueLimit;
    }
}
