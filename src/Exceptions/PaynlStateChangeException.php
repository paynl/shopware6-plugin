<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PaynlStateChangeException extends ShopwareHttpException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'PAYNL__PAYMENT_STATE_CHANGE_REJECTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
