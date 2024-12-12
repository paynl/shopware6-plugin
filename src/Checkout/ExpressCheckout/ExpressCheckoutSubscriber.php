<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout;

use PaynlPayment\Shopware6\Checkout\ExpressCheckout\Service\ExpressCheckoutDataServiceInterface;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExpressCheckoutSubscriber implements EventSubscriberInterface
{
    public const PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID = 'payPalEcsButtonData';
    public const IDEAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID = 'idealEcsButtonData';

    private LoggerInterface $logger;
    private ExpressCheckoutDataServiceInterface $expressCheckoutDataService;

    public function __construct(
        LoggerInterface $logger,
        ExpressCheckoutDataServiceInterface $service
    ) {
        $this->logger = $logger;
        $this->expressCheckoutDataService = $service;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            CheckoutRegisterPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            NavigationPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            OffcanvasCartPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            ProductPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            SearchPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
        ];
    }

    public function addExpressCheckoutDataToPage(PageLoadedEvent $event): void
    {
        $paypalExpressCheckoutButtonData = $this->getPayPalExpressCheckoutButtonData($event->getSalesChannelContext());

        if ($paypalExpressCheckoutButtonData) {
            $event->getPage()->addExtension(
                self::PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID,
                $paypalExpressCheckoutButtonData
            );
        }

        $idealExpressCheckoutButtonData = $this->getIdealExpressCheckoutButtonData($event->getSalesChannelContext());

        if ($idealExpressCheckoutButtonData) {
            $event->getPage()->addExtension(
                self::IDEAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID,
                $idealExpressCheckoutButtonData
            );
        }

        $this->logger->debug('Added data to page {page}', ['page' => \get_class($event)]);
    }

    private function getPayPalExpressCheckoutButtonData(
        SalesChannelContext $salesChannelContext,
        bool $addProductToCart = false
    ): ?PayPalExpressCheckoutButtonData {
        return $this->expressCheckoutDataService->buildPayPalExpressCheckoutButtonData(
            $salesChannelContext,
            $addProductToCart
        );
    }

    private function getIdealExpressCheckoutButtonData(
        SalesChannelContext $salesChannelContext,
        bool $addProductToCart = false
    ): ?IdealExpressCheckoutButtonData {
        return $this->expressCheckoutDataService->buildIdealExpressCheckoutButtonData(
            $salesChannelContext,
            $addProductToCart
        );
    }
}
