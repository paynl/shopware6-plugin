<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Repository\OrderLineItem;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class OrderLineItemRepository implements OrderLineItemRepositoryInterface
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $orderLineItemRepository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $orderLineItemRepository
     */
    public function __construct($orderLineItemRepository)
    {
        $this->orderLineItemRepository = $orderLineItemRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderLineItemRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderLineItemRepository->create($data, $context);
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->orderLineItemRepository->search($criteria, $context);
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->orderLineItemRepository->searchIds($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderLineItemRepository->update($data, $context);
    }

    /**
     * @param array $ids
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        return $this->orderLineItemRepository->delete($ids, $context);
    }
}
