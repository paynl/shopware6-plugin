<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\ConfigReader;

use PaynlPayment\Shopware6\Struct\Configuration;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigReader implements ConfigReaderInterface
{
    public const SYSTEM_CONFIG_DOMAIN = 'PaynlPaymentShopware6.settings.';

    /** @var SystemConfigService */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function read(?string $salesChannelId = null, bool $fallback = true): Configuration
    {
        $values = $this->systemConfigService->getDomain(
            self::SYSTEM_CONFIG_DOMAIN,
            $salesChannelId,
            $fallback
        );

        $config = [];

        foreach ($values as $key => $value) {
            $property = substr($key, strlen(self::SYSTEM_CONFIG_DOMAIN));

            $config[$property] = $value;
        }

        return new Configuration($config);
    }
}
