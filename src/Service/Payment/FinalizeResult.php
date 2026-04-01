<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Payment;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FinalizeResult
{
    private RedirectResponse $response;

    private ?OrderTransactionEntity $orderTransaction;

    public function __construct(RedirectResponse $response, ?OrderTransactionEntity $orderTransaction = null)
    {
        $this->response = $response;
        $this->orderTransaction = $orderTransaction;
    }

    public function getResponse(): RedirectResponse
    {
        return $this->response;
    }

    public function getOrderTransaction(): ?OrderTransactionEntity
    {
        return $this->orderTransaction;
    }
}
