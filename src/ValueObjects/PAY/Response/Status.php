<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Response;

class Status
{
    private int $code;
    private string $action;

    public function __construct(int $code, string $action)
    {
        $this->code = $code;
        $this->action = $action;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}