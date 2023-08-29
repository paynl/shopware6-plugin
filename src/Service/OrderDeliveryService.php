<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Repository\OrderDelivery\OrderDeliveryRepositoryInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderDeliveryService
{
    /** @var OrderDeliveryRepositoryInterface */
    private $orderDeliveryRepository;

    public function __construct(OrderDeliveryRepositoryInterface $orderDeliveryRepository)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    public function getDelivery(string $orderDeliveryId, Context $context): ?OrderDeliveryEntity
    {
        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order.transactions.paymentMethod');
        $result = $this->orderDeliveryRepository->search($criteria, $context);

        return $result->first();
    }
}
