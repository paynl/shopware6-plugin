<?php

namespace PaynlPayment\Shopware6\Subscriber;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class CheckoutConfirmSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded',
            AccountPaymentMethodPageLoadedEvent::class => 'onAccountPageLoaded',
            AccountEditOrderPageLoadedEvent::class => 'onEditOrderPageLoaded'
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $event
     */
    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event)
    {
        $birthday = $event->getSalesChannelContext()->getCustomer()->getBirthday();
        $phoneNumber = $event->getSalesChannelContext()->getCustomer()->getDefaultBillingAddress()->getPhoneNumber();

        $event->getPage()->assign([
            'isBirthdayExists' => !empty($birthday),
            'isPhoneNumberExists' => !empty($phoneNumber)
        ]);

        $this->addPaymentMethodsCustomFields($event);
    }

    /**
     * @param AccountEditOrderPageLoadedEvent $event
     */
    public function onEditOrderPageLoaded(AccountEditOrderPageLoadedEvent $event)
    {
        $birthday = $event->getSalesChannelContext()->getCustomer()->getBirthday();
        $phoneNumber = $event->getSalesChannelContext()->getCustomer()->getDefaultBillingAddress()->getPhoneNumber();

        $event->getPage()->assign([
            'isBirthdayExists' => !empty($birthday),
            'isPhoneNumberExists' => !empty($phoneNumber)
        ]);
    }

    /**
     * @param AccountPaymentMethodPageLoadedEvent $event
     */
    public function onAccountPageLoaded(AccountPaymentMethodPageLoadedEvent $event)
    {
        $birthday = $event->getSalesChannelContext()->getCustomer()->getBirthday();
        $phoneNumber = $event->getSalesChannelContext()->getCustomer()->getDefaultBillingAddress()->getPhoneNumber();

        $event->getPage()->assign([
            'isBirthdayExists' => !empty($birthday),
            'isPhoneNumberExists' => !empty($phoneNumber)
        ]);
    }

    private function addPaymentMethodsCustomFields(PageLoadedEvent $event): void
    {
        $pageData = $event->getPage()->getVars();
        $isBirthdayExists = $pageData['isBirthdayExists'] ?? true;
        $isPhoneNumberExists = $pageData['isPhoneNumberExists'] ?? true;

        $paymentMethods = $event->getPage()->getPaymentMethods();
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            $customFields = $paymentMethod->getCustomFields();
            $isPaynlPaymentMethod = $customFields['paynl_payment'] ?? false;
            if (!$isPaynlPaymentMethod) {
                continue;
            }

            $isPaymentDisplayBanks = $customFields['displayBanks'] ?? false;
            $isPaymentPayLater = $customFields['isPayLater'] ?? false;
            $hasPaymentLaterInputs = $isPaymentPayLater && (!$isBirthdayExists || !$isPhoneNumberExists);
            $customFields['hasAdditionalInfoInput'] = $isPaymentDisplayBanks || $hasPaymentLaterInputs;

            $paymentMethod->setCustomFields($customFields);
        }
    }
}
