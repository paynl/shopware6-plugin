<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PaymentMethod;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Service\PaymentMethodCustomFields;
use PaynlPayment\Shopware6\ValueObjects\IssuerValueObject;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

/**
 * Single place for the bank list rules: whether it is shown, how it is shown and which bank may be used.
 */
class PaymentMethodIssuerService
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function isBankListEnabled(PaymentMethodEntity $paymentMethod, string $salesChannelId): bool
    {
        return $this->getPayMethodId($paymentMethod) === PaynlPaymentMethodsIdsEnum::METJEBANK_PAYMENT
            && $this->config->getPaymentMetjebankBankListEnabled($salesChannelId)
            && $this->getBanks($paymentMethod) !== [];
    }

    /**
     * @return string one of the BankListDisplayEnum values
     */
    public function getDisplayType(string $salesChannelId): string
    {
        return $this->config->getPaymentMetjebankBankListDisplay($salesChannelId);
    }

    /**
     * @return IssuerValueObject[]
     */
    public function getBanks(PaymentMethodEntity $paymentMethod): array
    {
        $banks = $this->getCustomFields($paymentMethod)[PaymentMethodCustomFields::BANKS_FIELD] ?? [];

        return array_values(array_filter(array_map(
            static fn($bank): ?IssuerValueObject => is_array($bank) ? IssuerValueObject::fromArray($bank) : null,
            is_array($banks) ? $banks : []
        )));
    }

    /**
     * Returns the issuer id only when it is safe to send to PAY.: the bank list is enabled and the bank is one of
     * the banks stored for the payment method. Anything else (request input included) resolves to null, which
     * means the customer picks the bank on the PAY. payment page.
     */
    public function resolveIssuer(PaymentMethodEntity $paymentMethod, ?string $issuerId, string $salesChannelId): ?string
    {
        $issuerId = trim((string) $issuerId);

        if ($issuerId === '' || !$this->isBankListEnabled($paymentMethod, $salesChannelId)) {
            return null;
        }

        foreach ($this->getBanks($paymentMethod) as $bank) {
            if ($bank->getIssuerId() === $issuerId) {
                return $issuerId;
            }
        }

        return null;
    }

    private function getPayMethodId(PaymentMethodEntity $paymentMethod): int
    {
        return (int) ($this->getCustomFields($paymentMethod)[PaymentMethodCustomFields::PAY_NL_ID_FIELD] ?? 0);
    }

    private function getCustomFields(PaymentMethodEntity $paymentMethod): array
    {
        return (array) $paymentMethod->getTranslation('customFields');
    }
}
