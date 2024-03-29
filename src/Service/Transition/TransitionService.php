<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Transition;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class TransitionService implements TransitionServiceInterface
{
    /** @var StateMachineRegistry */
    private $stateMachineRegistry;

    /**
     * @param StateMachineRegistry $stateMachineRegistry
     */
    public function __construct(StateMachineRegistry $stateMachineRegistry)
    {
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @param string $transition
     * @param array $availableTransitions
     * @return bool
     */
    public function transitionIsAllowed(string $transition, array $availableTransitions): bool
    {
        return in_array($transition, $availableTransitions);
    }

    /**
     * @param string $definitionName
     * @param string $entityId
     * @param Context $context
     * @return mixed[]
     */
    public function getAvailableTransitions(string $definitionName, string $entityId, Context $context): array
    {
        /** @var array<StateMachineTransitionEntity> $availableTransitions */
        $availableTransitions = $this->stateMachineRegistry->getAvailableTransitions(
            $definitionName,
            $entityId,
            'stateId',
            $context
        );

        return array_map(function (StateMachineTransitionEntity $transition) {
            return $transition->getActionName();
        }, $availableTransitions);
    }

    /**
     * @param string $definitionName
     * @param string $entityId
     * @param string $transitionName
     * @param Context $context
     * @return void
     */
    public function performTransition(
        string $definitionName,
        string $entityId,
        string $transitionName,
        Context $context
    ): void {
        $this->stateMachineRegistry->transition(
            new Transition(
                $definitionName,
                $entityId,
                $transitionName,
                'stateId'
            ),
            $context
        );
    }
}
