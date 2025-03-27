<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use PaynlPayment\Shopware6\ValueObjects\Event\OrderReturnPayloadMapper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturn\OrderReturnEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderReturnWrittenSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private Config $config;
    private ProcessingHelper $processingHelper;
    private OrderReturnPayloadMapper $orderReturnPayloadMapper;
    /** @var EntityRepository */
    private ?EntityRepository $orderReturnRepository;
    private bool $featureDisabled = false;

    public function __construct(
        LoggerInterface $logger,
        Config $config,
        ProcessingHelper $processingHelper,
        ?EntityRepository $orderReturnRepository
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->processingHelper = $processingHelper;
        $this->orderReturnRepository = $orderReturnRepository;

        $this->orderReturnPayloadMapper = new OrderReturnPayloadMapper();
        $this->featureDisabled = $orderReturnRepository === null;
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
        if ($this->featureDisabled) {
            return;
        }

        $this->logger->info('Order return state done event', [
            'orderNumber' => $event->getOrder()->getOrderNumber()
        ]);

        if (!$this->config->isNativeShopwareRefundAllowed($event->getOrder()->getSalesChannelId())) {
            return;
        }

        $orderReturn = $this->findReturnByOrder($event->getOrder(), $event->getContext());
        if ($orderReturn === null) {
            return;
        }

        $orderReturnPayload = $this->orderReturnPayloadMapper->mapOrderReturnEntity($orderReturn);

        $this->processingHelper->refund($orderReturnPayload, $event->getContext());
    }

    public function onOrderReturnWritten(EntityWrittenEvent $event): void
    {
        if ($this->featureDisabled) {
            return;
        }

        $this->logger->info('Order return written event', [
            'entityName' => $event->getEntityName(),
            'contextSource' => $event->getContext()->getSource(),
            'event' => $event,
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

    /** @return OrderReturnEntity|null */
    private function findReturnByOrder(OrderEntity $order, Context $context)
    {
        if ($this->orderReturnRepository === null) {
            return null;
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
        $criteria->addFilter(new EqualsFilter('orderVersionId', $order->getVersionId()));
        $criteria->addAssociation('lineItems');

        $orderReturnSearchResult = $this->orderReturnRepository->search($criteria, $context);

        if ($orderReturnSearchResult->getTotal() === 0) {
            $this->logger->warning('Failed to find order return for order {{orderNumber}}', [
                'orderNumber' => $order->getOrderNumber(),
            ]);
            return null;
        }

        /** @var OrderReturnEntity $orderReturn */
        $orderReturn = $orderReturnSearchResult->first();
        return $orderReturn;
    }
}
