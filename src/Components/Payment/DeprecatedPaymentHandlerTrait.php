<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\Payment;

use Exception;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

trait DeprecatedPaymentHandlerTrait
{
    private InitiatePaymentAction $initiatePaymentAction;
    private FinalizePaymentAction $finalizePaymentAction;

    public function __construct(InitiatePaymentAction $initiatePaymentAction, FinalizePaymentAction $finalizePaymentAction)
    {
        $this->initiatePaymentAction = $initiatePaymentAction;
        $this->finalizePaymentAction = $finalizePaymentAction;
    }

    /** @throws Throwable */
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        return $this->initiatePaymentAction->pay($transaction, $salesChannelContext);
    }

    /** @throws Exception */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $this->finalizePaymentAction->finalize($transaction, $salesChannelContext->getContext());
    }
}