<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Logger;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class PaynlLogger implements LoggerInterface
{
    const CHANNEL = 'Paynl';

    private string $sessionId;

    private Logger $logger;

    public function __construct(string $filename, string $retentionDays, string $logLevel, string $sessionId)
    {
        $this->sessionId = $sessionId;

        $fileHandler = new RotatingFileHandler($filename, (int)$retentionDays, $logLevel);

        $this->logger = new Logger(self::CHANNEL, [$fileHandler]);
    }

    public function log($level, $message, array $context = array())
    {
    }

    public function debug($message, array $context = [])
    {
        $this->logger->debug(
            $this->modifyMessage($message),
            $context
        );
    }

    public function info($message, array $context = [])
    {
        $this->logger->info(
            $this->modifyMessage($message),
            $context
        );
    }

    public function notice($message, array $context = [])
    {
        $this->logger->notice(
            $this->modifyMessage($message),
            $context
        );
    }

    public function warning($message, array $context = [])
    {
        $this->logger->warning(
            $this->modifyMessage($message),
            $context
        );
    }

    public function error($message, array $context = [])
    {
        $this->logger->error(
            $this->modifyMessage($message),
            $context
        );
    }

    public function critical($message, array $context = [])
    {
        $this->logger->critical(
            $this->modifyMessage($message),
            $context
        );
    }

    public function alert($message, array $context = [])
    {
        $this->logger->alert(
            $this->modifyMessage($message),
            $context
        );
    }

    public function emergency($message, array $context = [])
    {
        $this->logger->emergency(
            $this->modifyMessage($message),
            $context
        );
    }

    private function modifyMessage(string $message): string
    {
        $sessionPart = substr($this->sessionId, 0, 4) . '...';

        return $message . ' (Session: ' . $sessionPart . ')';
    }
}
