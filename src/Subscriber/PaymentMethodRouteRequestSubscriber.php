<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderLineItem\OrderLineItemRepositoryInterface;
use PaynlPayment\Shopware6\Service\PaymentMethod\PaymentMethodSurchargeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Event\RouteRequest\SetPaymentOrderRouteRequestEvent;

class PaymentMethodRouteRequestSubscriber implements EventSubscriberInterface
{
    /** @var Config */
    protected $config;

    /** @var PaymentMethodSurchargeService */
    protected $surchargeService;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var OrderLineItemRepositoryInterface */
    protected $orderLineItemRepository;

    public function __construct(
        Config $config,
        PaymentMethodSurchargeService $surchargeService,
        OrderRepositoryInterface $orderRepository,
        OrderLineItemRepositoryInterface $orderLineItemRepository
    ) {
        $this->config = $config;
        $this->surchargeService = $surchargeService;
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
    }

    /** @return string[] */
    public static function getSubscribedEvents(): array
    {
        return [SetPaymentOrderRouteRequestEvent::class => 'onHandlePaymentMethodRouteRequest'];
    }

    public function onHandlePaymentMethodRouteRequest(SetPaymentOrderRouteRequestEvent $event): void
    {
        if (!$this->config->isSurchargePaymentMethods($event->getSalesChannelContext()->getSalesChannel()->getId())) {
            return;
        }

        /** @var string $orderId */
        $orderId = $event->getStorefrontRequest()->attributes->get('orderId');

        $salesChannelContext = $event->getSalesChannelContext();

        $order = $this->orderRepository->getOrderById($orderId, $salesChannelContext->getContext());

        $this->surchargeService->updateOrderTotal($order, $salesChannelContext);
    }
}
