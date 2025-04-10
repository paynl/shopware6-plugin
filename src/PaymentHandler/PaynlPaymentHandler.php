<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler;

use PaynlPayment\Shopware6\Components\Payment\DeprecatedPaymentHandlerTrait;
use PaynlPayment\Shopware6\Components\Payment\PaymentHandlerTrait;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;

if (class_exists(AbstractPaymentHandler::class)) {
    class PaynlPaymentHandler extends AbstractPaymentHandler
    {
        use PaymentHandlerTrait;
    }

    return;
} else if (interface_exists(AsynchronousPaymentHandlerInterface::class)) {
    class PaynlPaymentHandler implements AsynchronousPaymentHandlerInterface
    {
        use DeprecatedPaymentHandlerTrait;
    }
}