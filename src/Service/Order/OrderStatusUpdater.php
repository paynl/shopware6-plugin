<?php

namespace PaynlPayment\Shopware6\Service\Order;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class OrderStatusUpdater
{
    /** @var Config */
    private $config;

    /** @var OrderStateService */
    private $orderHandler;

    /**
     * @param OrderStateService $orderHandler
     * @param StateMachineRegistry $stateMachineRegistry
     */
    public function __construct(
        Config $config,
        OrderStateService $orderHandler
    ) {
        $this->config = $config;
        $this->orderHandler = $orderHandler;
    }

    /**
     * @param OrderEntity $order
     * @param string $status
     * @param string $salesChannelId
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function updateOrderStatus(OrderEntity $order, int $status, string $salesChannelId, Context $context): void
    {
        switch ($status) {

            case PaynlTransactionStatusesEnum::STATUS_REFUND:
            case PaynlTransactionStatusesEnum::STATUS_REFUNDING:
            case PaynlTransactionStatusesEnum::STATUS_PARTIAL_REFUND:
            case PaynlTransactionStatusesEnum::STATUS_VERIFY:
            case PaynlTransactionStatusesEnum::STATUS_PARTLY_CAPTURED:
            case PaynlTransactionStatusesEnum::STATUS_PARTIAL_PAYMENT:
                break;

            case PaynlTransactionStatusesEnum::STATUS_AUTHORIZE:
                $this->orderHandler->setOrderState(
                    $order,
                    $this->config->getOrderStateWithAuthorizedTransaction($salesChannelId),
                    $context
                );
                break;

            case PaynlTransactionStatusesEnum::STATUS_PAID:
                $this->orderHandler->setOrderState(
                    $order,
                    $this->config->getOrderStateWithPaidTransaction($salesChannelId),
                    $context
                );
                break;

            case PaynlTransactionStatusesEnum::STATUS_FAILURE:
            case PaynlTransactionStatusesEnum::STATUS_EXPIRED:
            case PaynlTransactionStatusesEnum::STATUS_PAID_CHECKAMOUNT:
            case PaynlTransactionStatusesEnum::STATUS_DENIED_63:
            case PaynlTransactionStatusesEnum::STATUS_DENIED_64:
                $this->orderHandler->setOrderState(
                    $order,
                    $this->config->getOrderStateWithFailedTransaction($salesChannelId),
                    $context
                );
                break;

            case PaynlTransactionStatusesEnum::STATUS_CANCEL:
                $this->orderHandler->setOrderState(
                    $order,
                    $this->config->getOrderStateWithCancelledTransaction($salesChannelId),
                    $context
                );
                break;
        }
    }

}
