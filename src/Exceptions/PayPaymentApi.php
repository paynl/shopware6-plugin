<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PayPaymentApi extends Exception
{
    public static function paymentResponseError(string $message, int $code): PayPaymentApi
    {
        return new PayPaymentApi('PAY API response error: ' . $message, $code);
    }
}
