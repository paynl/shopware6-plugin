<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Service\PaymentMethodCustomFields;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class CheckoutConfirmSubscriber implements EventSubscriberInterface
{
    private $paymentMethodCustomFields;

    public function __construct(PaymentMethodCustomFields $paymentMethodCustomFields)
    {
        $this->paymentMethodCustomFields = $paymentMethodCustomFields;
    }

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
        $this->checkCustomerData($event);
        $this->addPaymentMethodsCustomFields($event);
    }

    /**
     * @param AccountEditOrderPageLoadedEvent $event
     */
    public function onEditOrderPageLoaded(AccountEditOrderPageLoadedEvent $event)
    {
        $this->checkCustomerData($event);
        $this->addPaymentMethodsCustomFields($event);
    }

    /**
     * @param AccountPaymentMethodPageLoadedEvent $event
     */
    public function onAccountPageLoaded(AccountPaymentMethodPageLoadedEvent $event)
    {
        $this->checkCustomerData($event);
        $this->addPaymentMethodsCustomFields($event);
    }

    private function checkCustomerData(PageLoadedEvent $event): void
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
        $paymentMethods = $event->getPage()->getPaymentMethods();
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            $this->paymentMethodCustomFields->generateCustomFields($event, $paymentMethod);

            $paymentMethod->setCustomFields($this->paymentMethodCustomFields->getCustomFields());
        }
    }
}
