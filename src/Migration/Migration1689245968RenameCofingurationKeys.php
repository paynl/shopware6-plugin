<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1689245968RenameCofingurationKeys extends MigrationStep
{

    public function getCreationTimestamp(): int
    {
        return 1689245968;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
            UPDATE `system_config` 
            SET `configuration_key` = REPLACE(`configuration_key`, 'settings', 'config') 
            WHERE `configuration_key` LIKE 'PaynlPaymentShopware6.settings%';
SQL;
        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        $query = <<<SQL
            UPDATE `system_config` 
            SET `configuration_key` = REPLACE(`configuration_key`, 'config', 'settings') 
            WHERE `configuration_key` LIKE 'PaynlPaymentShopware6.config%';
SQL;
        $connection->executeStatement($query);
    }
}