<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StateMachineTransitionSubscriber implements EventSubscriberInterface
{
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'stateChanged',
            'order_transaction_capture_refund.written' => 'lineItemWritten',
            'order_return.written' => 'lineItemWritten',
            'Shopware\Commercial\ReturnManagement\Event\OrderReturnCreatedEvent' => 'orderReturn',
        ];
    }

    public function orderReturn($event): void
    {
        $this->logger->info('Return management order return event: ' . $event->getName(), [
            'event' => $event,
        ]);
    }

    public function lineItemWritten(EntityWrittenEvent $event): void
    {
        $this->logger->info('Order transaction capture refund event: ' . $event->getEntityName(), [
            'event' => $event,
        ]);
    }

    public function stateChanged(StateMachineTransitionEvent $event): void
    {
        $context = $event->getContext();

        $to = $event->getToPlace()->getTechnicalName();
        $from = $event->getFromPlace()->getTechnicalName();

        $this->logger->info('State machine transition event: ' . $event->getEntityName(), [
            'from' => $from,
            'to' => $to,
            'event' => $event,
        ]);

        if ($event->getEntityName() !== OrderTransactionCaptureRefundDefinition::ENTITY_NAME) {
            return;
        }

        if ($to !== OrderTransactionCaptureRefundStates::STATE_COMPLETED) {
            return;
        }
    }
}
