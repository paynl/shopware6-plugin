<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use DateTime;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use PaynlPayment\Shopware6\Components\Config;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

class OrderSubscriber implements EventSubscriberInterface
{
    public const LAST_PLACED_ORDER_ID = 'paynl_last_placed_order_id';

    private Client $client;

    /** @var Config */
    private $config;

    /** @var RequestStack */
    private $requestStack;
    private string $shopwareVersion;
    private ?string $instanceId;

    public function __construct(Config $config, RequestStack $requestStack, string $shopwareVersion, ?string $instanceId)
    {
        $this->client = new Client();
        $this->config = $config;
        $this->requestStack = $requestStack;
        $this->shopwareVersion = $shopwareVersion;
        $this->instanceId = $instanceId;
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
        $this->sendCommissionReport($orderPlacedEvent);
    }

    public function sendCommissionReport(CheckoutOrderPlacedEvent $orderPlacedEvent): void
    {
        $shopwareApiIdentifier = $this->config->getShopwareApiIdentifier($orderPlacedEvent->getSalesChannelId());
        if (empty($shopwareApiIdentifier)) {
            return;
        }

        $now = new DateTime();
        $data = [
            'identifier' => $shopwareApiIdentifier,
            'reportDate' => $now->format(DateTimeInterface::ATOM),
            'instanceId' => $this->instanceId,
            'shopwareVersion' => $this->shopwareVersion,
            'reportDataKeys' => [
                'numberOfFulfilledOrders' => 1
            ],
        ];

        try {
            $request = new Request(
                'POST',
                'https://api.shopware.com/shopwarepartners/reports/technology',
                [],
                json_encode($data)
            );

            $this->client->send($request);
        } catch (Throwable $exception) {

        }
    }

    private function saveLastPlacedOrder(CheckoutOrderPlacedEvent $orderPlacedEvent): void
    {
        if (!$this->config->isRestoreShippingCart((string)$orderPlacedEvent->getSalesChannelId())) {
            return;
        }

        $this->requestStack->getSession()->set(self::LAST_PLACED_ORDER_ID, $orderPlacedEvent->getOrder()->getId());
    }
}
