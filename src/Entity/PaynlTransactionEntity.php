<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Entity;

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
     * @var int
     */
    protected $paymentId;

    /**
     * @var float
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

    /**
     * @var string
     */
    protected $orderTransactionId;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $latestActionName;

    public function getTechnicalName(): ?string
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

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function setOrderTransactionId(string $orderTransactionId): void
    {
        $this->orderTransactionId = $orderTransactionId;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getPaymentId(): ?int
    {
        return $this->paymentId;
    }

    public function setPaymentId(int $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
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

    public function getLatestActionName(): ?string
    {
        return $this->latestActionName;
    }

    public function setLatestActionName(string $latestActionName): void
    {
        $this->latestActionName = $latestActionName;
    }

    public function getData(): array
    {
        return [
            'technicalName' => $this->getTechnicalName(),
            'paynlTransactionId' => $this->getPaynlTransactionId(),
            'orderTransactionId' => $this->getOrderTransactionId(),
            'orderId' => $this->getOrderId(),
            'paymentId' => $this->getPaymentId(),
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrency(),
            'exception' => $this->getException(),
            'latestActionName' => $this->getLatestActionName()
        ];
    }
}
