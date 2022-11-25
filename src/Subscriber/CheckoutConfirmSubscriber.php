<?php

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\PublicKeysHelper;
use PaynlPayment\Shopware6\Service\PaymentMethodCustomFields;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;

class CheckoutConfirmSubscriber implements EventSubscriberInterface
{
    const PAYNL_DATA_EXTENSION_ID = 'paynlFrontendData';

    private $router;

    private $paymentMethodCustomFields;

    private $publicKeysHelper;

    public function __construct(
        RouterInterface $router,
        PaymentMethodCustomFields $paymentMethodCustomFields,
        PublicKeysHelper $publicKeysHelper
    ) {
        $this->router = $router;
        $this->paymentMethodCustomFields = $paymentMethodCustomFields;
        $this->publicKeysHelper = $publicKeysHelper;
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
        $this->addCheckoutOptions($event);
    }

    /**
     * @param AccountEditOrderPageLoadedEvent $event
     */
    public function onEditOrderPageLoaded(AccountEditOrderPageLoadedEvent $event)
    {
        $this->checkCustomerData($event);
        $this->addPaymentMethodsCustomFields($event);
        $this->addCheckoutOptions($event);
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

    private function addCheckoutOptions(PageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $page = $event->getPage();
        $orderId = '';
        if (method_exists($page, 'getOrder')) {
            $orderId = $page->getOrder()->getId();
        }

        $page->addExtension(
            self::PAYNL_DATA_EXTENSION_ID,
            new ArrayEntity(
                [
                    'checkoutOrderUrl' => $this->router->generate(
                        'store-api.checkout.cart.order'
                    ),
                    'paymentHandleUrl' => $this->router->generate(
                        'store-api.payment.handle'
                    ),
                    'paymentFinishUrl' => $this->router->generate(
                        'frontend.checkout.finish.page',
                        ['orderId' => '']
                    ),
                    'paymentErrorUrl' => $this->router->generate(
                        'frontend.checkout.finish.page',
                        [
                            'orderId' => '',
                            'changedPayment' => false,
                            'paymentFailed' => true,
                        ]
                    ),
                    'languageId' => $salesChannelContext->getContext()->getLanguageId(),
                    'orderId' => $orderId,
                    'publicEncryptionKeys' => json_encode($this->publicKeysHelper->getKeys($salesChannelId))
                ]
            )
        );
    }
}
