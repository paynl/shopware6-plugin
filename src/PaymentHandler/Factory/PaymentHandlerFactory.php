<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\PaymentHandler\Factory;

use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\PaymentHandler\PaynlPaymentHandler;
use PaynlPayment\Shopware6\PaymentHandler\PaynlTerminalPaymentHandler;

class PaymentHandlerFactory
{
    public function get(int $paymentMethodId = 0): string
    {
        switch ($paymentMethodId) {
            case PaynlPaymentMethodsIdsEnum::PIN_PAYMENT:
                return PaynlTerminalPaymentHandler::class;
            default:
                return PaynlPaymentHandler::class;
        }
    }
}
