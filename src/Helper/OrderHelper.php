<?php

namespace PaynlPayment\Shopware6\Helper;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Context;

class OrderHelper
{
    /** @var EntityRepositoryInterface $orderRepository */
    private $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param Context $context
     * @param mixed[] $data
     */
    public function updateOrderCustomFields(Context $context, array $data): void
    {
        $this->orderRepository->upsert($data, $context);
    }
}
