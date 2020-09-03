<?php

namespace PaynlPayment\Shopware6\Core\System\StateMachine\Aggregation\StateMachineState;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class StateMachineStateExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField(
                'paynlTransactions',
                PaynlTransactionEntityDefinition::class,
                'order_state_id'
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return StateMachineStateDefinition::class;
    }
}
