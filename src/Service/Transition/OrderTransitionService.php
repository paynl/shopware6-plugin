<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Transition;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class OrderTransitionService implements OrderTransitionServiceInterface
{
    /** @var TransitionServiceInterface */
    private $transitionService;

    public function __construct(TransitionServiceInterface $transitionService)
    {
        $this->transitionService = $transitionService;
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     */
    public function openOrder(OrderEntity $order, Context $context): void
    {
        if ($this->isOrderStateSameAs($order, OrderStates::STATE_OPEN)) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REOPEN, $availableTransitions)) {
            $this->performTransition($order, StateMachineTransitionActions::ACTION_COMPLETE, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_REOPEN, $context);
    }

    /*
     * @param OrderEntity $order
     * @param Context $context
     */
    public function processOrder(OrderEntity $order, Context $context): void
    {
        if ($this->isOrderStateSameAs($order, OrderStates::STATE_IN_PROGRESS)) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_PROCESS, $availableTransitions)) {
            $this->performTransition($order, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_PROCESS, $context);
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     */
    public function completeOrder(OrderEntity $order, Context $context): void
    {
        if ($this->isOrderStateSameAs($order, OrderStates::STATE_COMPLETED)) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_COMPLETE, $availableTransitions)) {
            $this->processOrder($order, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_COMPLETE, $context);
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     */
    public function cancelOrder(OrderEntity $order, Context $context): void
    {
        if ($this->isOrderStateSameAs($order, OrderStates::STATE_CANCELLED)) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($order, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_CANCEL, $availableTransitions)) {
            $this->performTransition($order, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($order, StateMachineTransitionActions::ACTION_CANCEL, $context);
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return array<string>
     */
    public function getAvailableTransitions(OrderEntity $order, Context $context): array
    {
        return $this->transitionService->getAvailableTransitions(OrderDefinition::ENTITY_NAME, $order->getId(), $context);
    }

    /**
     * @param string $transition
     * @param array $availableTransitions
     * @return bool
     */
    private function transitionIsAllowed(string $transition, array $availableTransitions): bool
    {
        return $this->transitionService->transitionIsAllowed($transition, $availableTransitions);
    }

    /**
     * @param OrderEntity $order
     * @param string $transitionName
     * @param Context $context
     */
    private function performTransition(OrderEntity $order, string $transitionName, Context $context): void
    {
        $this->transitionService->performTransition(OrderDefinition::ENTITY_NAME, $order->getId(), $transitionName, $context);
    }

    /**
     * @param OrderEntity $order
     * @param string $orderState
     * @return bool
     */
    private function isOrderStateSameAs(OrderEntity $order, string $orderState): bool
    {
        if ($order->getStateMachineState() === null
            || $order->getStateMachineState()->getTechnicalName() === $orderState
        ) {
            return true;
        }

        return false;
    }
}
