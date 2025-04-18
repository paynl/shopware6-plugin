<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1595341767AddLatestActionNameField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595341767;
    }

    public function update(Connection $connection): void
    {
        $query = 'ALTER TABLE paynl_transactions
            ADD COLUMN `latest_action_name` VARCHAR(255) NULL';

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
