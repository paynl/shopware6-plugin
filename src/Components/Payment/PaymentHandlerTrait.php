<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\Payment;

use Exception;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

trait PaymentHandlerTrait
{
    private InitiatePaymentAction $initiatePaymentAction;
    private FinalizePaymentAction $finalizePaymentAction;

    public function __construct(InitiatePaymentAction $initiatePaymentAction, FinalizePaymentAction $finalizePaymentAction)
    {
        $this->initiatePaymentAction = $initiatePaymentAction;
        $this->finalizePaymentAction = $finalizePaymentAction;
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    /** @throws Throwable */
    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): RedirectResponse
    {
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $request->get('sw-sales-channel-context');

        return $this->initiatePaymentAction->pay($transaction, $salesChannelContext);
    }

    /** @throws Exception */
    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $this->finalizePaymentAction->finalize($transaction, $context);
    }
}