<?php

namespace PaynlPayment\Shopware6\Core\Checkout\Order\Aggregation\OrderTransaction;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderTransactionExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                'paynlTransactions',
                'id',
                'order_transaction_id',
                PaynlTransactionEntityDefinition::class
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderTransactionDefinition::class;
    }
}
