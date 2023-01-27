<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Session\Session;

class PaynlLoggerFactory
{
    private Session $session;

    private string $filename;

    private string $retentionDays;

    public function __construct(Session $session, string $filename, string $retentionDays)
    {
        $this->session = $session;
        $this->filename = $filename;
        $this->retentionDays = $retentionDays;
    }

    /**
     * @return PaynlLogger
     */
    public function createLogger(): LoggerInterface
    {
        $sessionID = $this->session->getId();

        return new PaynlLogger(
            $this->filename,
            $this->retentionDays,
            LogLevel::INFO,
            $sessionID
        );
    }
}
