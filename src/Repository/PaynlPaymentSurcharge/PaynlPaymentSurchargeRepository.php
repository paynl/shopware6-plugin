<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Repository\PaynlPaymentSurcharge;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class PaynlPaymentSurchargeRepository implements PaynlPaymentSurchargeRepositoryInterface
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $paynlPaymentSurchargeRepository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $paynlPaymentSurchargeRepository
     */
    public function __construct($paynlPaymentSurchargeRepository)
    {
        $this->paynlPaymentSurchargeRepository = $paynlPaymentSurchargeRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->paynlPaymentSurchargeRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->paynlPaymentSurchargeRepository->create($data, $context);
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->paynlPaymentSurchargeRepository->search($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->paynlPaymentSurchargeRepository->update($data, $context);
    }
}
