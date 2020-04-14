<?php

namespace PaynlPayment\Shopware6\Core\Checkout\Order;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        // TODO: modify to use one to one relation
        $collection->add(
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
