<?php

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\PaymentHandler\PaynlInstorePaymentHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\PageLoadedEvent;

class PaymentMethodCustomFields
{
    const PAYNL_PAYMENT_FIELD = 'paynl_payment';
    const DISPLAY_BANKS_FIELD = 'displayBanks';
    const IS_PAY_LATER_FIELD = 'isPayLater';
    const HAS_ADDITIONAL_INFO_INPUT_FIELD = 'hasAdditionalInfoInput';
    const TERMINALS = 'terminals';

    private $paynlApi;

    private $customFields;

    public function __construct(Api $api)
    {
        $this->paynlApi = $api;
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
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
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

        $this->generateInstoreTerminals($paymentMethod, $salesChannelId);
    }

    private function generateInstoreTerminals(PaymentMethodEntity $paymentMethod, string $salesChannelId): void
    {
        if ($paymentMethod->getHandlerIdentifier() !== PaynlInstorePaymentHandler::class) {
            return;
        }

        $terminals = $this->paynlApi->getInstoreTerminals($salesChannelId);
        $this->setCustomField(self::TERMINALS, $terminals);
    }
}
