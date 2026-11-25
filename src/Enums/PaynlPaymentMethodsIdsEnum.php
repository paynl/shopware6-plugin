<?php

namespace PaynlPayment\Shopware6\Enums;

class PaynlPaymentMethodsIdsEnum
{
    const PIN_PAYMENT = 1927;
    const IDEAL_PAYMENT = 10;
    /** Grouped credit card (PG_2) */
    const CREDIT_CARD_PAYMENT = 11;
    const PAYPAL_PAYMENT = 138;
    const METJEBANK_PAYMENT = 5358;

    /**
     * Payment methods the PAY.Parts card widget may be booked under. Only the grouped credit card:
     * the widget accepts every card brand, so tying it to a single brand method (e.g. Visa) would
     * record an Amex payment as Visa, with Visa's surcharge. Shops with only brand methods keep
     * the normal redirect flow.
     *
     * @return int[]
     */
    public static function getPayPartsCardPaymentIds(): array
    {
        return [self::CREDIT_CARD_PAYMENT];
    }

    public static function isPayPartsCardPayment(int $paynlId): bool
    {
        return in_array($paynlId, static::getPayPartsCardPaymentIds(), true);
    }
}
