<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Transition;

use Shopware\Core\Framework\Context;

interface TransitionServiceInterface
{
    /**
     * @param string $transition
     * @param array $availableTransitions
     * @return bool
     */
    public function transitionIsAllowed(string $transition, array $availableTransitions): bool;

    /**
     * @param string $definitionName
     * @param string $entityId
     * @param Context $context
     * @return array<string>
     */
    public function getAvailableTransitions(string $definitionName, string $entityId, Context $context): array;

    /**
     * @param string $definitionName
     * @param string $entityId
     * @param string $transitionName
     * @param Context $context
     */
    public function performTransition(string $definitionName, string $entityId, string $transitionName, Context $context): void;
}
