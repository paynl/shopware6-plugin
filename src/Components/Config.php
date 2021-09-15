<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use PaynlPayment\Shopware6\Components\ConfigReader\ConfigReaderInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config
{
    const CONFIG_TEMPLATE = 'PaynlPaymentShopware6.settings.%s';
    const CONFIG_DOMAIN = 'PaynlPaymentShopware6.settings.';
    const FEMALE_SALUTATIONS = 'mrs, ms, miss, ma\'am, frau, mevrouw, mevr';

    const SHOW_PHONE_FIELD_CONFIG_KEY = 'core.loginRegistration.showPhoneNumberField';
    const SHOW_DOB_FIELD_CONFIG_KEY = 'core.loginRegistration.showBirthdayField';

    private $config;
    private $configReader;

    public function __construct(SystemConfigService $systemConfigService, ConfigReaderInterface $configReader)
    {
        $this->config = $systemConfigService;
        $this->configReader = $configReader;
    }

    private function get(string $salesChannel, string $key)
    {
        $configuration = $this->configReader->read($salesChannel);

        return $configuration->get(sprintf(self::CONFIG_TEMPLATE, $key), $configuration->get($key));
    }

    public function getTokenCode(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'tokenCode');
    }

    public function getApiToken(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'apiToken');
    }

    public function getServiceId(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'serviceId');
    }

    public function getSinglePaymentMethodInd(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId,'useSinglePaymentMethod');
    }

    public function getTestMode(string $salesChannelId): int
    {
        return (int)$this->get($salesChannelId, 'testMode');
    }

    public function isRefundAllowed(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'allowRefunds');
    }

    /**
     * @return string[]
     */
    public function getFemaleSalutations(string $salesChannelId): array
    {
        $salutations = $this->get($salesChannelId, 'femaleSalutations') ?: self::FEMALE_SALUTATIONS;
        $arrSalutations = explode(',', $salutations);

        return array_map('trim', $arrSalutations);
    }

    public function getUseAdditionalAddressFields(string $salesChannelId): int
    {
        return (int)$this->get($salesChannelId, 'additionalAddressFields');
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

    public function getShowDescription(string $salesChannelId): string
    {
        return $this->get($salesChannelId, 'showDescription') ?: 'show_payment_method_info';
    }

    public function getPaymentScreenLanguage(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'paymentScreenLanguage');
    }
}
