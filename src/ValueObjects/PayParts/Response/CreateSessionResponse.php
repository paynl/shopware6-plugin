<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Response;

class CreateSessionResponse
{
    public function __construct(
        private string $sessionId,
        private string $sessionToken
    ) {
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSessionToken(): string
    {
        return $this->sessionToken;
    }
}