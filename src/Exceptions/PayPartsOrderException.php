<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;
use Throwable;

class PayPartsOrderException extends Exception
{
    public const HTTP_NOT_FOUND = 404;
    // 409 is reserved for "cart total changed": the storefront shows "Your cart has changed" only for 409.
    public const HTTP_CONFLICT = 409;
    public const HTTP_UNPROCESSABLE = 422;
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
        return new self('Invalid payment method for Pay.Parts order creation.', self::HTTP_UNPROCESSABLE);
    }

    /**
     * The amount PAY.Parts will charge (session) must equal the order/cart total, in cents and currency.
     *
     * @throws self
     */
    public static function assertAmountMatches(
        int $actualCents,
        string $actualCurrency,
        ?int $expectedCents,
        ?string $expectedCurrency
    ): void {
        if ($actualCents !== $expectedCents
            || $expectedCurrency === null
            || strtoupper($actualCurrency) !== strtoupper($expectedCurrency)
        ) {
            throw self::amountMismatch();
        }
    }

    public static function amountMismatch(): self
    {
        return new self('Cart total no longer matches the Pay.Parts session amount.', self::HTTP_CONFLICT);
    }

    public static function paymentChangeFailed(Throwable $previous): self
    {
        return new self('Could not prepare an open card transaction for the order.', self::HTTP_UNPROCESSABLE, $previous);
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

    public static function orderDataInvalid(Throwable $previous): self
    {
        return new self('Order data rejected by Shopware validation (e.g. terms not accepted).', self::HTTP_UNPROCESSABLE, $previous);
    }

    public static function orderCreationFailed(Throwable $previous): self
    {
        return new self('Pay.Parts order creation failed.', self::HTTP_INTERNAL_SERVER_ERROR, $previous);
    }
}
