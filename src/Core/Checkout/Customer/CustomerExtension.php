<?php

namespace PaynlPayment\Shopware6\Core\Checkout\Customer;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomerExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        // TODO: modify to use one to one relation
        $collection->add(
            new OneToManyAssociationField(
                'paynlTransactions',
                PaynlTransactionEntityDefinition::class,
                'customer_id'
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return CustomerDefinition::class;
    }
}
