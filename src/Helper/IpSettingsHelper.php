<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\Components\Config;

class IpSettingsHelper
{
    public const HTTP_FORWARDED_SETTING = 'httpForwarded';
    public const REMOTE_ADDRESS_SETTING = 'remoteAddress';

    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getIp(string $salesChannelId): string
    {
        if ($this->isHttpForwardedSetting($salesChannelId)) {
            return $this->getHttpForwarded();
        }

        if ($this->isRemoteAddressSetting($salesChannelId)) {
            return $this->getRemoteAddress();
        }

        return '';
    }

    private function isHttpForwardedSetting(string $salesChannelId): bool
    {
        return $this->config->getIpSettings($salesChannelId) === self::HTTP_FORWARDED_SETTING;
    }

    private function isRemoteAddressSetting(string $salesChannelId): bool
    {
        return $this->config->getIpSettings($salesChannelId) === self::REMOTE_ADDRESS_SETTING;
    }

    private function getHttpForwarded(): string
    {
        $headers = $_SERVER;
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        }

        $ip = '';
        if (array_key_exists('X-Forwarded-For', $headers)) {
            $ip = $headers['X-Forwarded-For'];
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $headers)) {
            $ip = $headers['HTTP_X_FORWARDED_FOR'];
        }

        return $this->getValidatedIp((string) $ip);
    }

    private function getRemoteAddress(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return $this->getValidatedIp((string) $ip);
    }

    private function getValidatedIp(string $ip): string
    {
        $arrIp = explode(',', $ip);

        return (string) filter_var(trim(trim($arrIp[0]), '[]'), FILTER_VALIDATE_IP);
    }
}
