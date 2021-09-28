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
        $this->customFields = $paymentMethod->getTranslation('customFields');

        $isPaynlPaymentMethod = $this->getCustomField(self::PAYNL_PAYMENT_FIELD);
        if (!$isPaynlPaymentMethod) {
            return;
        }


        $pageData = $event->getPage()->getVars();
        $isBirthdayExists = $pageData['isBirthdayExists'] ?? true;
        $isPhoneNumberExists = $pageData['isPhoneNumberExists'] ?? true;

        $isPaymentDisplayBanks = $this->getCustomField(self::DISPLAY_BANKS_FIELD);
        $isPaymentPayLater = $this->getCustomField(self::IS_PAY_LATER_FIELD);
        $hasPaymentLaterInputs = $isPaymentPayLater && (($isBirthdayExists && $isPhoneNumberExists) === false);

        $hasAdditionalInfoInput = $isPaymentDisplayBanks || $hasPaymentLaterInputs;
        $this->setCustomField(self::HAS_ADDITIONAL_INFO_INPUT_FIELD, $hasAdditionalInfoInput);

        $this->generateInstoreTerminals($paymentMethod);
    }

    private function generateInstoreTerminals(PaymentMethodEntity $paymentMethod): void
    {
        if ($paymentMethod->getHandlerIdentifier() !== PaynlInstorePaymentHandler::class) {
            return;
        }

        $terminals = $this->paynlApi->getTerminals();
        $this->setCustomField(self::TERMINALS, $terminals);
    }
}
