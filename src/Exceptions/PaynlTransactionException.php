<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PaynlTransactionException extends Exception
{
    public static function captureError(string $message = ''): PaynlTransactionException
    {
        return new PaynlTransactionException($message ?: 'Unknown PAY. transaction capture error');
    }
}
