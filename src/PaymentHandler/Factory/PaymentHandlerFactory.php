<?php

namespace PaynlPayment\Shopware6\PaymentHandler\Factory;

use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\PaymentHandler\PaynlInstorePaymentHandler;
use PaynlPayment\Shopware6\Service\PaynlPaymentHandler;

class PaymentHandlerFactory
{
    public function get(int $paymentMethodId = 0): string
    {
        switch ($paymentMethodId) {
            case PaynlPaymentMethodsIdsEnum::INSTORE_PAYMENT:
                return PaynlInstorePaymentHandler::class;
            default:
                return PaynlPaymentHandler::class;
        }
    }
}
