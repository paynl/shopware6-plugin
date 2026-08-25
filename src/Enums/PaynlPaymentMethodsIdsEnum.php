<?php

namespace PaynlPayment\Shopware6\Enums;

class PaynlPaymentMethodsIdsEnum
{
    const PIN_PAYMENT = 1927;
    const IDEAL_PAYMENT = 10;
    /** Grouped credit card (PG_2) */
    const CREDIT_CARD_PAYMENT = 11;
    const PAYPAL_PAYMENT = 138;

    // Ungrouped card networks (Pay.Parts)
    const VISA = 3141;
    const MASTERCARD = 3138;
    const VISA_MASTERCARD = 706;
    const POSTEPAY = 708;
    const CARTE_BANCAIRE = 2268;
    const CARTE_BLEUE = 710;
    const CARTE_BLEUE_VPAY = 711;   // Carte Bleue V PAY variant
    const MAESTRO = 712;            // Maestro (card not present)
    const MAESTRO_CARD_PRESENT = 715; // Maestro (card present)
    const AMEX = 1705;
    const DANKORT = 1939;
    const NEXI = 1945;

    /**
     * Preference order when resolving which Shopware payment method to use for Pay.Parts.
     * Grouped first, then combined Visa/Mastercard, then individual brands.
     *
     * @return int[]
     */
    public static function getPayPartsCardPaymentIds(): array
    {
        return [
            self::CREDIT_CARD_PAYMENT,
            self::VISA_MASTERCARD,
            self::VISA,
            self::MASTERCARD,
            self::POSTEPAY,
            self::CARTE_BANCAIRE,
            self::CARTE_BLEUE,
            self::CARTE_BLEUE_VPAY,
            self::MAESTRO,
            self::MAESTRO_CARD_PRESENT,
            self::AMEX,
            self::DANKORT,
            self::NEXI,
        ];
    }

    public static function isPayPartsCardPayment(int $paynlId): bool
    {
        return in_array($paynlId, self::getPayPartsCardPaymentIds(), true);
    }
}
