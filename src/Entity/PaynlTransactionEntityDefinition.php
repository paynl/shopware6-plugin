<?php declare(strict_types=1);

namespace PaynlPayment\Entity;

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
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PaynlTransactionEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'paynl_transactions';
    public const STATUS_PENDING = 17;
    public const STATUS_CANCEL = 35;
    public const STATUS_PAID = 12;
    public const STATUS_NEEDS_REVIEW = 21;
    public const STATUS_REFUND = 20;
    public const STATUS_AUTHORIZED = 18;

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

            (new FkField('customer_id', 'customerId', PaynlTransactionEntityDefinition::class))
                ->addFlags(new Required()),
            (new FkField('order_id', 'orderId', PaynlTransactionEntityDefinition::class))
                ->addFlags(new Required()),
            (new FkField('order_transaction_id', 'orderTransactionId', PaynlTransactionEntityDefinition::class))
                ->addFlags(new Required()),

            (new StringField('paynl_transaction_id', 'paynlTransactionId', 16)),
            (new IntField('payment_id', 'paymentId')),
            (new FloatField('amount', 'amount'))->setFlags(new Required()),
            (new StringField('currency', 'currency', 3))->setFlags(new Required()),
            (new LongTextField('exception', 'exception')),
            (new StringField('comment', 'comment')),
            (new StringField('dispatch', 'dispatch')),
            (new IntField('state_id', 'stateId')),

            new ManyToOneAssociationField(
                'customer',
                'customer_id',
                PaynlTransactionEntityDefinition::class,
                'id',
                false
            ),
            // TODO: modify to use one to one relation
            new ManyToOneAssociationField(
                'order',
                'order_id',
                PaynlTransactionEntityDefinition::class,
                'id',
                false
            ),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
