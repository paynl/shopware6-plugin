<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PAY\Response;

class Links
{
    private string $abort;
    private string $status;
    private string $redirect;

    public function __construct(string $abort, string $status, string $redirect)
    {
        $this->abort = $abort;
        $this->status = $status;
        $this->redirect = $redirect;
    }

    public function getAbort(): string
    {
        return $this->abort;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRedirect(): string
    {
        return $this->redirect;
    }
}