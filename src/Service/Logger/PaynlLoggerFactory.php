<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Logger;

use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use PaynlPayment\Shopware6\Components\Config;
use Psr\Log\LoggerInterface;

class PaynlLoggerFactory
{
    const CHANNEL = 'PAY';

    /** @var Config */
    private $config;
    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $retentionDays;

    public function __construct(Config $config, string $filename, string $retentionDays)
    {
        $this->config = $config;
        $this->filename = $filename;
        $this->retentionDays = $retentionDays;
    }

    /**
     * @return LoggerInterface
     */
    public function createLogger(): LoggerInterface
    {
        $minLevel = 100;// DEBUG

        $loggerHandler = new NullHandler();

        if ($this->config->isLoggingEnabled()) {
            $loggerHandler = new RotatingFileHandler($this->filename, (int)$this->retentionDays, $minLevel);
            $loggerHandler->pushProcessor(new IntrospectionProcessor());
        }

        return new Logger(self::CHANNEL, [$loggerHandler]);
    }
}
