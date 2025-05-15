<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PaynlTransactionException extends Exception
{
    public const TRANSACTION_NOT_FOUND_BY_ORDER = 'Transaction was not found in PAY transaction table. OrderID: %s';
    public const TRANSACTION_NOT_FOUND_BY_TRANSACTION = 'Transaction was not found in PAY transaction table. PAY transaction ID: %s';

    public static function captureError(string $message = ''): PaynlTransactionException
    {
        return new PaynlTransactionException($message ?: 'Unknown PAY. transaction capture error');
    }

    public static function notFoundByOrderError(string $orderId): PaynlTransactionException
    {
        return new PaynlTransactionException(sprintf(self::TRANSACTION_NOT_FOUND_BY_ORDER, $orderId));
    }

    public static function notFoundByPayTransactionError(string $payTransactionId): PaynlTransactionException
    {
        return new PaynlTransactionException(sprintf(self::TRANSACTION_NOT_FOUND_BY_TRANSACTION, $payTransactionId));
    }
}
