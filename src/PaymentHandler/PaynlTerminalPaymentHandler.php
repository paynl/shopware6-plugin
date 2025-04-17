<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use PaynlPayment\Shopware6\Components\Payment\Terminal\DeprecatedPaymentHandlerTrait;
use PaynlPayment\Shopware6\Components\Payment\Terminal\PaymentHandlerTrait;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;

if (class_exists(AbstractPaymentHandler::class)) {
    class PaynlTerminalPaymentHandler extends AbstractPaymentHandler
    {
        use PaymentHandlerTrait;
    }

    return;
}

/** @phpstan-ignore-next-line  */
if (interface_exists(SynchronousPaymentHandlerInterface::class) && !class_exists(AbstractPaymentHandler::class)) {
    class PaynlTerminalPaymentHandler implements SynchronousPaymentHandlerInterface
    {
        use DeprecatedPaymentHandlerTrait;
    }
}
