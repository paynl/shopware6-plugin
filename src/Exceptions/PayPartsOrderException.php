<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;
use Throwable;

class PayPartsOrderException extends Exception
{
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_CONFLICT  = 409;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    private int $statusCode;

    public function __construct(string $message, int $statusCode = self::HTTP_INTERNAL_SERVER_ERROR, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function invalidPaymentMethod(): self
    {
        return new self('Invalid payment method for Pay.Parts order creation.', self::HTTP_CONFLICT);
    }

    public static function cardMethodNotFound(): self
    {
        return new self('No active Pay.Parts card payment method found.', self::HTTP_NOT_FOUND);
    }

    public static function paymentMethodNotFound(string $paymentMethodId): self
    {
        return new self(
            sprintf('Payment method "%s" not found.', $paymentMethodId),
            self::HTTP_NOT_FOUND
        );
    }

    public static function transactionNotFound(string $orderId): self
    {
        return new self(
            sprintf('No transaction found for order "%s".', $orderId),
            self::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    public static function orderCreationFailed(Throwable $previous): self
    {
        return new self('Pay.Parts order creation failed.', self::HTTP_INTERNAL_SERVER_ERROR, $previous);
    }
}
