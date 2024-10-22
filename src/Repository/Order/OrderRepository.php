<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Repository\Order;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class OrderRepository implements OrderRepositoryInterface
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $orderRepository
     */
    public function __construct($orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderRepository->create($data, $context);
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->orderRepository->search($criteria, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->orderRepository->searchIds($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderRepository->update($data, $context);
    }

    public function getOrderById(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociations([
            'lineItems',
            'deliveries.shippingMethod',
            'deliveries.positions.orderLineItem',
            'deliveries.shippingOrderAddress.country',
            'transactions'
        ]);

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order === null) {
            throw new EntityNotFoundException(OrderDefinition::ENTITY_NAME, $orderId);
        }

        return $order;
    }
}
