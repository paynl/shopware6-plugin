<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PayPartsLinkException extends Exception
{
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_CONFLICT  = 409;

    private int $statusCode;

    public function __construct(string $message, int $statusCode = self::HTTP_FORBIDDEN)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function accessDenied(): self
    {
        return new self('Transaction link denied.', self::HTTP_FORBIDDEN);
    }

    public static function transactionNotOpen(): self
    {
        return new self('Order transaction is not in an open state.', self::HTTP_CONFLICT);
    }

    public static function invalidPaymentMethod(): self
    {
        return new self('Invalid payment method for PAY.Parts linking.', self::HTTP_CONFLICT);
    }
}
