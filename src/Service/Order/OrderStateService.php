<?php

namespace PaynlPayment\Shopware6\Service\Order;

use Exception;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use PaynlPayment\Shopware6\Service\Transition\OrderTransitionServiceInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;

class OrderStateService
{
    /** @var OrderTransitionServiceInterface */
    protected $orderTransitionService;

    public function __construct(
        OrderTransitionServiceInterface $orderTransitionService
    ) {
        $this->orderTransitionService = $orderTransitionService;
    }

    /**
     * @param OrderEntity $order
     * @param string $orderState
     * @param Context $context
     * @return bool
     */
    public function setOrderState(OrderEntity $order, string $orderState, Context $context): bool
    {
        if ($orderState === '' || $orderState === SettingsHelper::ORDER_STATE_SKIP) {
            return false;
        }

        $currentStatus = $order->getStateMachineState()->getTechnicalName();

        if ($currentStatus === $orderState) {
            return false;
        }

        try {
            switch ($orderState) {
                case OrderStates::STATE_OPEN:
                    $this->orderTransitionService->openOrder($order, $context);
                    break;
                case OrderStates::STATE_IN_PROGRESS:
                    $this->orderTransitionService->processOrder($order, $context);
                    break;
                case OrderStates::STATE_COMPLETED:
                    $this->orderTransitionService->completeOrder($order, $context);
                    break;
                case OrderStates::STATE_CANCELLED:
                    $this->orderTransitionService->cancelOrder($order, $context);
                    break;
                default:
                    return false;
            }

            return true;
        } catch (Exception $e) {

        }

        return false;
    }
}
