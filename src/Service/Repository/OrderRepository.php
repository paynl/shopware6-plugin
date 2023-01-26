<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Repository;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderRepository
{
    private EntityRepositoryInterface $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function getOrder(string $orderId, Context $context, array $associations = []) : ?OrderEntity
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $orderId));

            /** @var OrderEntity $order */
            return $this->getOrderByCriteria($criteria, $context, $associations);
        } catch (\Exception $e) {
            return null;
        }

    }

    public function getOrderByCriteria(Criteria $criteria, Context $context, array $associations = []): ?OrderEntity
    {
        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }
        return $this->orderRepository->search($criteria, $context)->first();
    }

    public function getOrderByOrderNumber(string $orderNumber, Context $context, array $associations = []): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));

        return $this->getOrderByCriteria($criteria, $context, $associations);
    }

    public function update(string $orderId, array $data, Context $context)
    {
        $data['id'] = $orderId;
        $this->orderRepository->update([$data], $context);
    }
}
