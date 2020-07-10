<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Entity;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class PaynlTransactionEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'paynl_transactions';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaynlTransactionEntityCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaynlTransactionEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))
                ->addFlags(new Required()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))
                ->addFlags(new Required()),
            (new FkField('order_transaction_id', 'orderTransactionId', OrderTransactionDefinition::class))
                ->addFlags(new Required()),
            (new FkField('order_state_id', 'orderStateId', StateMachineStateDefinition::class))
                ->addFlags(new Required()),

            (new StringField('paynl_transaction_id', 'paynlTransactionId', 16)),
            (new IntField('payment_id', 'paymentId')),
            (new FloatField('amount', 'amount'))->setFlags(new Required()),
            (new StringField('currency', 'currency', 3))->setFlags(new Required()),
            (new StringField('latest_action_name', 'latestActionName'))->setFlags(new Required()),
            (new LongTextField('exception', 'exception')),
            (new StringField('comment', 'comment')),
            (new StringField('dispatch', 'dispatch')),
            (new IntField('state_id', 'stateId')),

            new ManyToOneAssociationField(
                'order',
                'order_id',
                OrderDefinition::class,
                'id',
                false
            ),
            new ManyToOneAssociationField(
                'customer',
                'customer_id',
                CustomerDefinition::class,
                'id',
                true
            ),
            new ManyToOneAssociationField(
                'orderStateMachine',
                'order_state_id',
                StateMachineStateDefinition::class,
                'id',
                true
            ),
            new OneToOneAssociationField(
                'orderTransaction',
                'order_transaction_id',
                'id',
                OrderTransactionDefinition::class,
                true
            ),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
