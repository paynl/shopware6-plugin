<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PaymentMethod;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\CustomerCustomFieldsEnum;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Service\PaymentMethodCustomFields;
use PaynlPayment\Shopware6\ValueObjects\IssuerValueObject;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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
        $bank = $this->findBank($paymentMethod, $issuerId, $salesChannelId);

        return $bank !== null ? $bank->getIssuerId() : null;
    }

    /**
     * The bank the customer saved for the payment method, when it is still safe to send to PAY.
     */
    public function resolveSavedBank(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): ?IssuerValueObject
    {
        return $this->findBank(
            $paymentMethod,
            $this->getSavedIssuerId($paymentMethod, $salesChannelContext),
            $salesChannelContext->getSalesChannel()->getId()
        );
    }

    /**
     * Data for the storefront bank list, or null when the bank list must not be shown.
     *
     * @return array{display: string, banks: array<int, array<string, string>>, selected: ?array<string, string>}|null
     */
    public function getBankListData(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): ?array
    {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        if (!$this->isBankListEnabled($paymentMethod, $salesChannelId)) {
            return null;
        }

        $selected = $this->findBank(
            $paymentMethod,
            $this->getSavedIssuerId($paymentMethod, $salesChannelContext),
            $salesChannelId
        );

        return [
            'display' => $this->getDisplayType($salesChannelId),
            'banks' => array_map(
                static fn(IssuerValueObject $bank): array => $bank->toArray(),
                $this->getBanks($paymentMethod)
            ),
            'selected' => $selected !== null ? $selected->toArray() : null,
        ];
    }

    private function findBank(PaymentMethodEntity $paymentMethod, ?string $issuerId, string $salesChannelId): ?IssuerValueObject
    {
        $issuerId = trim((string) $issuerId);

        if ($issuerId === '' || !$this->isBankListEnabled($paymentMethod, $salesChannelId)) {
            return null;
        }

        foreach ($this->getBanks($paymentMethod) as $bank) {
            if ($bank->getIssuerId() === $issuerId) {
                return $bank;
            }
        }

        return null;
    }

    private function getSavedIssuerId(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): ?string
    {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            return null;
        }

        $customFields = (array) $customer->getCustomFields();
        $selectedData = (array) ($customFields[CustomerCustomFieldsEnum::PAYMENT_METHODS_SELECTED_DATA] ?? []);

        $issuer = $selectedData[$paymentMethod->getId()]['issuer'] ?? null;

        return is_string($issuer) ? $issuer : null;
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
