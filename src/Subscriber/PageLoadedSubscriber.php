<?php

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Config;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PageLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent' => 'onCheckoutConfirmPageLoaded',
            'Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent' => 'onAccountOrderEditPageLoaded',
            'Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent' => 'onAccountPaymentMethodPageLoaded'
        ];
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $checkoutConfirmPageLoadedEvent)
    {
        $salesChannelId = $checkoutConfirmPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getId();

        $checkoutConfirmPageLoadedEvent->getPage()->assign([
            'showDescription' => $this->config->getShowDescription($salesChannelId)
        ]);
    }

    public function onAccountPaymentMethodPageLoaded(AccountPaymentMethodPageLoadedEvent $accountPaymentMethodPageLoadedEvent)
    {
        $salesChannelId = $accountPaymentMethodPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getId();

        $accountPaymentMethodPageLoadedEvent->getPage()->assign([
            'showDescription' => $this->config->getShowDescription($salesChannelId)
        ]);
    }

    public function onAccountOrderEditPageLoaded(AccountEditOrderPageLoadedEvent $accountEditOrderPageLoadedEvent)
    {
        $salesChannelId = $accountEditOrderPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getId();

        $accountEditOrderPageLoadedEvent->getPage()->assign([
            'showDescription' => $this->config->getShowDescription($salesChannelId)
        ]);
    }
}
