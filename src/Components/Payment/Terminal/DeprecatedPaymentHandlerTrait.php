<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\Payment\Terminal;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;

trait DeprecatedPaymentHandlerTrait
{
    private InitiatePaymentAction $initiatePaymentAction;

    public function __construct(InitiatePaymentAction $initiatePaymentAction)
    {
        $this->initiatePaymentAction = $initiatePaymentAction;
    }

    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $this->initiatePaymentAction->pay($transaction, $salesChannelContext->getContext());
    }
}