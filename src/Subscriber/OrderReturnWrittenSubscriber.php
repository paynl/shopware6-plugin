<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\ValueObjects\Event\OrderReturnPayloadMapper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderReturnWrittenSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private ProcessingHelper $processingHelper;
    private OrderReturnPayloadMapper $orderReturnPayloadMapper;

    public function __construct(
        LoggerInterface $logger,
        ProcessingHelper $processingHelper
    ) {
        $this->logger = $logger;
        $this->processingHelper = $processingHelper;

        $this->orderReturnPayloadMapper = new OrderReturnPayloadMapper();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'order_return.written' => ['onOrderReturnWritten', 10],
            'state_enter.order_return.state.done' => ['onOrderReturnFinished', 10],
        ];
    }

    public function onOrderReturnFinished(OrderStateMachineStateChangeEvent $event): void
    {
        $this->logger->info('Order return state done event', [
            'orderNumber' => $event->getOrder()->getOrderNumber()
        ]);
    }

    public function onOrderReturnWritten(EntityWrittenEvent $event): void
    {
        $this->logger->info('Order return written event', [
            'event' => $event,
            'entityName' => $event->getEntityName()
        ]);

        $writeResults = $event->getWriteResults();

        if (empty($writeResults)) {
            return;
        }

        if ($writeResults[0]->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
            return;
        }

        $this->logger->info('Order return: getting payload', [
            'payload' => $writeResults[0]->getPayload(),
        ]);

        $orderReturnPayload = $this->orderReturnPayloadMapper->mapArray($writeResults[0]->getPayload());

        $this->processingHelper->refund($orderReturnPayload, $event->getContext());
    }
}
