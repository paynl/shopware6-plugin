<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Config;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderSubscriber implements EventSubscriberInterface
{
    public const LAST_PLACED_ORDER_ID = 'paynl_last_placed_order_id';

    /** @var Config */
    private $config;

    /** @var RequestStack */
    private $requestStack;

    public function __construct(Config $config, RequestStack $requestStack)
    {
        $this->config = $config;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => ['onOrderPlaced', -1000],
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $orderPlacedEvent): void
    {
        $this->saveLastPlacedOrder($orderPlacedEvent);
    }

    private function saveLastPlacedOrder(CheckoutOrderPlacedEvent $orderPlacedEvent): void
    {
        if (!$this->config->isRestoreShippingCart((string)$orderPlacedEvent->getSalesChannelId())) {
            return;
        }

        $this->requestStack->getSession()->set(self::LAST_PLACED_ORDER_ID, $orderPlacedEvent->getOrder()->getId());
    }
}
