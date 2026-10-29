<?php

namespace PaynlPayment\Shopware6\Enums;

class PaynlPaymentMethodsIdsEnum
{
    const PIN_PAYMENT = 1927;
    const IDEAL_PAYMENT = 10;
    const PAYPAL_PAYMENT = 138;
    const METJEBANK_PAYMENT = 5358;

    const BANK_LIST_PAYMENT_METHODS = [
        self::IDEAL_PAYMENT,
        self::METJEBANK_PAYMENT,
    ];
}
