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

    public function getRequestParameter(string $salesChannel, string $key)
    {
        $this->configuration = $this->configReader->read($salesChannel);

        return $this->configuration->get(sprintf(self::CONFIG_TEMPLATE, $key), $this->configuration->get($key));
    }

    public function getTokenCode(string $salesChannelId): string
    {
        //729ae7edd72f45c28035d0b08698623b
        return (string)$this->getRequestParameter($salesChannelId, 'tokenCode');

        return (string)$this->get('tokenCode');
    }

    public function getApiToken(string $salesChannelId): string
    {
        return (string)$this->getRequestParameter($salesChannelId, 'apiToken');

        return (string)$this->get('apiToken');
    }

    public function getServiceId(string $salesChannelId): string
    {
        return (string)$this->getRequestParameter($salesChannelId, 'serviceId');

        return (string)$this->get('serviceId');
    }

    public function getSinglePaymentMethodInd(string $salesChannelId): bool
    {
        return (bool)$this->get('useSinglePaymentMethod');
    }

    public function getTestMode(string $salesChannelId): int
    {
        return (int)$this->get('testMode');
    }

    public function isRefundAllowed(string $salesChannelId): bool
    {
        return (bool)$this->get('allowRefunds', false);
    }

    /**
     * @return string[]
     */
    public function getFemaleSalutations(string $salesChannelId): array
    {
        $salutations = $this->get('femaleSalutations', self::FEMALE_SALUTATIONS);
        $arrSalutations = explode(',', $salutations);

        return array_map('trim', $arrSalutations);
    }

    public function getUseAdditionalAddressFields(string $salesChannelId): int
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

    public function getShowDescription(string $salesChannelId): string
    {
        return $this->get('showDescription', 'show_payment_method_info');
    }

    public function getPaymentScreenLanguage(string $salesChannelId): string
    {
        return $this->get('paymentScreenLanguage', '');
    }
}
