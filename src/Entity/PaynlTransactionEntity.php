<?php declare(strict_types=1);

namespace PaynlPayment\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PaynlTransactionEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var string
     */
    protected $paynlTransactionId;

    /**
     * @var string
     */
    protected $paymentId;

    /**
     * @var string
     */
    protected $amount;

    /**
     * @var string
     */
    protected $currency;

    /**
     * @var string
     */
    protected $exception;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getPaynlTransactionId(): string
    {
        return $this->paynlTransactionId;
    }

    public function setPaynlTransactionId(string $paynlTransactionId): void
    {
        $this->paynlTransactionId = $paynlTransactionId;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function setPaymentId(string $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getException(): ?string
    {
        return $this->exception;
    }

    public function setException(string $exception): void
    {
        $this->exception = $exception;
    }
}
