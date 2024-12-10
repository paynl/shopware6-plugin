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

    private LoggerInterface $logger;
    private ExpressCheckoutDataServiceInterface $expressCheckoutDataService;
    private CartBackupService $cartBackupService;

    public function __construct(
        LoggerInterface $logger,
        ExpressCheckoutDataServiceInterface $service,
        CartBackupService $cartBackupService
    ) {
        $this->logger = $logger;
        $this->expressCheckoutDataService = $service;
        $this->cartBackupService = $cartBackupService;
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
        $expressCheckoutButtonData = $this->getExpressCheckoutButtonData($event->getSalesChannelContext());

        $this->cartBackupService->restoreCartFromOrder('0193b0c5c89a72afa918d9f7d2262302', $event->getSalesChannelContext());

        if ($expressCheckoutButtonData === null) {
            return;
        }

        $event->getPage()->addExtension(
            self::PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID,
            $expressCheckoutButtonData
        );

        $this->logger->debug('Added data to page {page}', ['page' => \get_class($event)]);
    }

    private function getExpressCheckoutButtonData(
        SalesChannelContext $salesChannelContext,
        bool $addProductToCart = false
    ): ?ExpressCheckoutButtonData {
        return $this->expressCheckoutDataService->buildExpressCheckoutButtonData(
            $salesChannelContext,
            $addProductToCart
        );
    }
}
