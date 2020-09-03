<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Entity;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

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

    /**
     * @var string
     */
    protected $customerId;

    /**
     * @var string
     */
    protected $orderStateId;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var string
     */
    protected $dispatch;

    /**
     * @var string
     */
    protected $stateId;

    /**
     * @var OrderEntity
     */
    protected $order;

    /**
     * @var CustomerEntity
     */
    protected $customer;

    /**
     * @var StateMachineStateEntity
     */
    protected $stateMachineState;

    /**
     * @var OrderTransactionEntity
     */
    protected $orderTransaction;

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

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    /**
     * @param string $customerId
     */
    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return string
     */
    public function getOrderStateId(): string
    {
        return $this->orderStateId;
    }

    /**
     * @param string $orderStateId
     */
    public function setOrderStateId(string $orderStateId): void
    {
        $this->orderStateId = $orderStateId;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     */
    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * @return string
     */
    public function getDispatch(): string
    {
        return $this->dispatch;
    }

    /**
     * @param string $dispatch
     */
    public function setDispatch(string $dispatch): void
    {
        $this->dispatch = $dispatch;
    }

    /**
     * @return string
     */
    public function getStateId(): string
    {
        return $this->stateId;
    }

    /**
     * @param string $stateId
     */
    public function setStateId(string $stateId): void
    {
        $this->stateId = $stateId;
    }

    /**
     * @return OrderEntity
     */
    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    /**
     * @param OrderEntity $order
     */
    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    /**
     * @return CustomerEntity
     */
    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    /**
     * @param CustomerEntity $customer
     */
    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * @return OrderTransactionEntity
     */
    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    /**
     * @param OrderTransactionEntity $orderTransaction
     */
    public function setOrderTransaction(OrderTransactionEntity $orderTransaction): void
    {
        $this->orderTransaction = $orderTransaction;
    }

    /**
     * @return StateMachineStateEntity
     */
    public function getStateMachineState(): StateMachineStateEntity
    {
        return $this->stateMachineState;
    }

    /**
     * @param StateMachineStateEntity $stateMachineState
     */
    public function setStateMachineState(StateMachineStateEntity $stateMachineState): void
    {
        $this->stateMachineState = $stateMachineState;
    }
}
