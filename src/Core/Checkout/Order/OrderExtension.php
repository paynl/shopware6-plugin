<?php

namespace PaynlPayment\Core\Checkout\Order;

use PaynlPayment\Entity\PaynlTransactionEntityDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
        // TODO: modify to use one to one relation
        new OneToManyAssociationField(
                'paynlTransactions',
                PaynlTransactionEntityDefinition::class,
                'order_id'
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }
}
