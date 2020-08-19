<?php

namespace PaynlPayment\Shopware6\Subscriber;

use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class CheckoutConfirmSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded',
            AccountPaymentMethodPageLoadedEvent::class => 'onAccountPageLoaded'
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
}
