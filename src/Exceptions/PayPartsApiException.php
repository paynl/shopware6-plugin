<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Exceptions;

use Exception;

class PayPartsApiException extends Exception
{
    public static function sessionCreateFailed(string $message, int $code = 0): self
    {
        return new self('PAY.Parts session create failed: ' . $message, $code);
    }

    public static function sessionGetFailed(string $message, int $code = 0): self
    {
        return new self('PAY.Parts session get failed: ' . $message, $code);
    }
}