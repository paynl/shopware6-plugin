<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Enums;

class PayLaterPaymentMethodsEnum
{
    const BILLINK = 1672;
    const KLARNA_ACHTERAF_BETALEN = 1717;
    const IN3 = 1813;
    const AFTERPAY = 739;
    const SPRAYPAY = 1987;
    const CREDITCLICK = 2107;
    const KLARNA_KP = 2265;

    const PAY_LATER_PAYMENT_METHODS = [
       self::AFTERPAY,
       self::BILLINK,
       self::KLARNA_ACHTERAF_BETALEN,
       self::IN3,
       self::SPRAYPAY,
       self::CREDITCLICK,
       self::KLARNA_KP,
    ];
}
