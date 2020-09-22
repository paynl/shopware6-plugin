<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config
{
    const CONFIG_TEMPLATE = 'PaynlPaymentShopware6.settings.%s';
    const FEMALE_SALUTATIONS = 'mrs, ms, miss, ma\'am, frau, mevrouw, mevr';

    private $config;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->config = $systemConfigService;
    }

    /**
     * @param string $key
     * @param mixed $defaultValue
     * @return array|mixed|null
     */
    private function get(string $key, $defaultValue = null)
    {
        $value = $this->config->get(sprintf(self::CONFIG_TEMPLATE, $key));
        if (is_null($value) && !is_null($defaultValue)) {
            return $defaultValue;
        }

        return $value;
    }

    public function getTokenCode(): string
    {
        return (string)$this->get('tokenCode');
    }

    public function getApiToken(): string
    {
        return (string)$this->get('apiToken');
    }

    public function getServiceId(): string
    {
        return (string)$this->get('serviceId');
    }

    public function getTestMode(): int
    {
        return (int)$this->get('testMode');
    }

    public function isRefundAllowed(): bool
    {
        return (bool)$this->get('allowRefunds', false);
    }

    /**
     * @return string[]
     */
    public function getFemaleSalutations(): array
    {
        $salutations = $this->get('femaleSalutations', self::FEMALE_SALUTATIONS);
        $arrSalutations = explode(',', $salutations);

        return array_map('trim', $arrSalutations);
    }

    public function getUseAdditionalAddressFields(): int
    {
        return (int)$this->get('additionalAddressFields');
    }

    /**
     * @param mixed[] $config
     */
    public function storeConfigData(array $config): void
    {
        foreach ($config as $configKey => $configValue) {
            $this->config->set(sprintf(self::CONFIG_TEMPLATE, $configKey), $configValue);
        }
    }

    public function getShowDescription(): string
    {
        return $this->get('showDescription', 'show_payment_method_info');
    }
}
