<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration\V63;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1595341699RemoveConstraints extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595341699;
    }

    public function update(Connection $connection): void
    {
        $query = '
            ALTER TABLE `paynl_transactions`
            DROP FOREIGN key `fk.paynl_transaction.customer_id`,
            DROP INDEX `fk.paynl_transaction.customer_id`,
            DROP FOREIGN key `fk.paynl_transaction.order_id`,
            DROP INDEX `fk.paynl_transaction.order_id`,
            DROP FOREIGN key `fk.paynl_transaction.order_state_id`,
            DROP INDEX `fk.paynl_transaction.order_state_id`;';

        $connection->executeQuery($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
