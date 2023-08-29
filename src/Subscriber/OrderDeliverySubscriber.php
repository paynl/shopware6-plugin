<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Service\OrderDeliveryService;
use PaynlPayment\Shopware6\Service\PaynlTransaction\PaynlTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{
    /** @var Config */
    private $config;
    private $orderDeliveryService;
    private $paynlTransactionService;
    private $api;

    public function __construct(
        Config $config,
        OrderDeliveryService $orderDeliveryService,
        PaynlTransactionService $paynlTransactionService,
        Api $api
    ) {
        $this->config = $config;
        $this->orderDeliveryService = $orderDeliveryService;
        $this->paynlTransactionService = $paynlTransactionService;
        $this->api = $api;
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryChanged',
        ];
    }

    /**
     * @param StateMachineStateChangeEvent $event
     */
    public function onOrderDeliveryChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();
        if ($transitionName !== StateMachineTransitionActions::ACTION_SHIP) {
            return;
        }

        if (!$this->config->isAutomaticShipping((string) $event->getSalesChannelId())) {
            return;
        }

        $context = $event->getContext();
        $orderDeliveryId = $event->getTransition()->getEntityId();
        $paynlTransaction = $this->isPaynlOrder($orderDeliveryId, $context);

        if (!$paynlTransaction instanceof PaynlTransactionEntity) {
            return;
        }

        if ($paynlTransaction->getStateId() != PaynlTransactionStatusesEnum::STATUS_AUTHORIZE) {
            return;
        }

        try {
            $this->api->capture(
                $paynlTransaction->getPaynlTransactionId(),
                $paynlTransaction->getAmount(),
                (string) $event->getSalesChannelId()
            );
        } catch (PaynlTransactionException $exception) {

        }
    }

    private function isPaynlOrder(string $orderDeliveryId, Context $context): ?PaynlTransactionEntity
    {
        $delivery = $this->orderDeliveryService->getDelivery($orderDeliveryId, $context);
        if (!$delivery instanceof OrderDeliveryEntity) {
            return null;
        }

        $order = $delivery->getOrder();
        if (!$order instanceof OrderEntity) {
            return null;
        }

        return $this->paynlTransactionService->getPayTransactionByOrderId($order->getId(), $context);
    }
}
