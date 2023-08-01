<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PaymentSurchargeEntity $entity)
 * @method void set(string $key, PaymentSurchargeEntity $entity)
 * @method PaymentSurchargeEntity[] getIterator()
 * @method PaymentSurchargeEntity[] getElements()
 * @method PaymentSurchargeEntity|null get(string $key)
 * @method PaymentSurchargeEntity|null first()
 * @method PaymentSurchargeEntity|null last()
 */
class PaymentSurchargeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaymentSurchargeEntity::class;
    }
}
