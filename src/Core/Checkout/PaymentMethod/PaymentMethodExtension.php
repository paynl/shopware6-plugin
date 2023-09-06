<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Core\Checkout\PaymentMethod;

use PaynlPayment\Shopware6\Entity\PaymentSurchargeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;

class PaymentMethodExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField(
                'paynlPaymentSurcharge',
                PaymentSurchargeDefinition::class,
                'payment_method_id',
                'id'
            )
        );
    }

    public function getDefinitionClass(): string
    {
        return PaymentMethodDefinition::class;
    }
}
