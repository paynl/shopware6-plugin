<?php

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use PaynlPayment\Shopware6\PaymentHandler\PaynlTerminalPaymentHandler;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\PageLoadedEvent;

class PaymentMethodCustomFields
{
    const PAYNL_PAYMENT_FIELD = 'paynl_payment';
    const DISPLAY_BANKS_FIELD = 'displayBanks';
    const IS_PAY_LATER_FIELD = 'isPayLater';
    const HAS_ADDITIONAL_INFO_INPUT_FIELD = 'hasAdditionalInfoInput';
    const TERMINALS = 'terminals';
    const PAYMENT_TERMINALS_CACHE_TAG = 'paymentTerminals';
    const PAYNL_ID = 'paynlId';
    const CSE_ENABLED = 'cseEnabled';

    /** @var Api */
    private $paynlApi;

    /** @var Config */
    private $config;

    /** @var CacheItemPoolInterface */
    private $cache;

    private $customFields;

    public function __construct(Api $api, Config $config, CacheItemPoolInterface $cache)
    {
        $this->paynlApi = $api;
        $this->config = $config;
        $this->cache = $cache;
    }

    public function getCustomField(string $name)
    {
        return $this->customFields[$name] ?? null;
    }

    /**
     * @return mixed[]
     */
    public function getCustomFields()
    {
        return (array)$this->customFields;
    }

    private function setCustomField(string $name, $data): void
    {
        $this->customFields[$name] = $data;
    }

    public function generateCustomFields(PageLoadedEvent $event, PaymentMethodEntity $paymentMethod): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        $this->customFields = $paymentMethod->getTranslation('customFields');

        $isPaynlPaymentMethod = $this->getCustomField(self::PAYNL_PAYMENT_FIELD);
        if (!$isPaynlPaymentMethod) {
            return;
        }

        $pageData = $event->getPage()->getVars();
        $isBirthdayExists = $pageData['isBirthdayExists'] ?? true;
        $isPhoneNumberExists = $pageData['isPhoneNumberExists'] ?? true;

        $isPaymentPayLater = $this->getCustomField(self::IS_PAY_LATER_FIELD);
        $hasPaymentLaterInputs = $isPaymentPayLater && (($isBirthdayExists && $isPhoneNumberExists) === false);

        $this->setCustomField(self::HAS_ADDITIONAL_INFO_INPUT_FIELD, $hasPaymentLaterInputs);

        $this->generatePaymentTerminals($paymentMethod, $salesChannelId);

        $paynlPaymentId = $this->getCustomField(self::PAYNL_ID);
        $isVisaMastercardPayment = $paynlPaymentId == PaynlPaymentMethodsIdsEnum::VISA_MASTERCARD_PAYMENT;
        $configCseEnabled = $this->config->cseEnabled($salesChannelId);
        $paymentCseEnabled = $isVisaMastercardPayment && $configCseEnabled;

        $this->setCustomField(self::CSE_ENABLED, $paymentCseEnabled);
    }

    private function generatePaymentTerminals(PaymentMethodEntity $paymentMethod, string $salesChannelId): void
    {
        if ($paymentMethod->getHandlerIdentifier() !== PaynlTerminalPaymentHandler::class) {
            return;
        }

        $paymentTerminalConfig = $this->config->getPaymentPinTerminal($salesChannelId);

        if (empty($paymentTerminalConfig) || in_array($paymentTerminalConfig, SettingsHelper::TERMINAL_DEFAULT_OPTIONS)) {
            $terminals = $this->getPaymentTerminalsCache($salesChannelId);
            $this->setCustomField(self::TERMINALS, $terminals);
        }
    }

    private function getPaymentTerminalsCache(string $salesChannelId): array
    {
        $terminalsCacheItem = $this->cache->getItem(self::PAYMENT_TERMINALS_CACHE_TAG);

        if (!$terminalsCacheItem->isHit() || !$terminalsCacheItem->get()) {
            $terminals = $this->paynlApi->getInstoreTerminals($salesChannelId);
            $terminalsCacheItem->set(json_encode($terminals));

            $this->cache->save($terminalsCacheItem);
        } else {
            $terminals = json_decode($terminalsCacheItem->get(), true);
        }

        return $terminals;
    }
}
