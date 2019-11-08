<?php declare(strict_types=1);

namespace PaynlPayment\Entity;

use PaynlPayment\Entity\PaynlTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(PaynlTransactionEntity $entity)
 * @method void              set(string $key, PaynlTransactionEntity $entity)
 * @method PaynlTransactionEntity[]    getIterator()
 * @method PaynlTransactionEntity[]    getElements()
 * @method PaynlTransactionEntity|null get(string $key)
 * @method PaynlTransactionEntity|null first()
 * @method PaynlTransactionEntity|null last()
 */
class PaynlTransactionEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PaynlTransactionEntity::class;
    }
}
