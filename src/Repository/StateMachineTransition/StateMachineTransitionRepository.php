<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Repository\StateMachineTransition;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class StateMachineTransitionRepository implements StateMachineTransitionRepositoryInterface
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $stateMachineTransitionRepository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $stateMachineTransitionRepository
     */
    public function __construct($stateMachineTransitionRepository)
    {
        $this->stateMachineTransitionRepository = $stateMachineTransitionRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->stateMachineTransitionRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->stateMachineTransitionRepository->create($data, $context);
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->stateMachineTransitionRepository->search($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->stateMachineTransitionRepository->update($data, $context);
    }
}
