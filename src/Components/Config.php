<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components;

use PaynlPayment\Shopware6\Components\ConfigReader\ConfigReaderInterface;

class Config
{
    const CONFIG_TEMPLATE = 'PaynlPaymentShopware6.config.%s';
    const CONFIG_DOMAIN = 'PaynlPaymentShopware6.config.';
    const FEMALE_SALUTATIONS = 'mrs, ms, miss, ma\'am, frau, mevrouw, mevr';

    const SHOW_PHONE_FIELD_CONFIG_KEY = 'core.loginRegistration.showPhoneNumberField';
    const SHOW_DOB_FIELD_CONFIG_KEY = 'core.loginRegistration.showBirthdayField';

    private $configReader;

    public function __construct(ConfigReaderInterface $configReader)
    {
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

    public function getFailoverGateway(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'failOverGateway');
    }

    public function getSinglePaymentMethodInd(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'useSinglePaymentMethod');
    }

    public function getTestMode(string $salesChannelId): int
    {
        return (int)$this->get($salesChannelId, 'testMode');
    }

    public function isRefundAllowed(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'allowRefunds');
    }

    public function isSurchargePaymentMethods(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'surchargePaymentMethods');
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

    public function getShowDescription(string $salesChannelId): string
    {
        return $this->get($salesChannelId, 'showDescription') ?: 'show_payment_method_info';
    }

    public function getPaymentScreenLanguage(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'paymentScreenLanguage');
    }

    public function isTransferGoogleAnalytics(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'transferGoogleAnalytics');
    }

    public function isLoggingEnabled(string $salesChannelId = ''): bool
    {
        return (bool)$this->get($salesChannelId, 'logging');
    }

    public function getIpSettings(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'ipSettings');
    }

    public function isAutomaticShipping(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'automaticShipping');
    }

    public function isAutomaticCancellation(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'automaticCancellation');
    }

    public function isRestoreShippingCart(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'restoreShoppingCart');
    }

    public function getOrderStateWithPaidTransaction(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'orderStateWithPaidTransaction');
    }

    public function getOrderStateWithFailedTransaction(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'orderStateWithFailedTransaction');
    }

    public function getOrderStateWithCancelledTransaction(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'orderStateWithCancelledTransaction');
    }

    public function getOrderStateWithAuthorizedTransaction(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'orderStateWithAuthorizedTransaction');
    }

    public function getPaymentIdealBankDropdownEnabled(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'paymentIdealBankDropdownEnabled');
    }

    public function getPaymentIdealExpressCheckoutEnabled(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'paymentIdealExpressCheckoutEnabled');
    }

    public function getPaymentPayPalExpressCheckoutEnabled(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'paymentPaypalExpressCheckoutEnabled');
    }

    public function getPaymentPayPalExpressShoppingCartEnabled(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'paymentPaypalExpressShoppingCartEnabled');
    }

    public function getPaymentPayPalExpressLoggedInCustomerEnabled(string $salesChannelId): bool
    {
        return (bool)$this->get($salesChannelId, 'paymentPaypalExpressCheckoutLoggedInCustomerEnabled');
    }

    public function getPaymentPayPalClientIdSandbox(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'paymentPaypalClientIdSandbox');
    }

    public function getPaymentPayPalSecretKeySandbox(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'paymentPaypalSecretKeySandbox');
    }

    public function getPaymentPayPalClientIdProduction(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'paymentPaypalClientIdProduction');
    }

    public function getPaymentPayPalSecretKeyProduction(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'paymentPaypalSecretKeyProduction');
    }

    public function getPaymentPinTerminal(string $salesChannelId): string
    {
        return (string)$this->get($salesChannelId, 'paymentPinTerminal');
    }
}
