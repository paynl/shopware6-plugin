<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PayPalPaymentApi extends Exception
{
    public static function paymentResponseError(string $message, int $code): PayPalPaymentApi
    {
        return new PayPalPaymentApi('PayPal API response error: ' . $message, $code);
    }
}
