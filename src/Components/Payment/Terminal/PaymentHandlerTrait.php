<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\Payment\Terminal;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

trait PaymentHandlerTrait
{
    private InitiatePaymentAction $initiatePaymentAction;
    public function __construct(InitiatePaymentAction $initiatePaymentAction)
    {
        $this->initiatePaymentAction = $initiatePaymentAction;
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): RedirectResponse
    {
        return $this->initiatePaymentAction->pay($transaction, $context);
    }
}