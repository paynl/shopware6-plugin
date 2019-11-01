<?php declare(strict_types=1);

namespace PaynlPayment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1572594721AddPaynlTransactionsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572594721;
    }

    public function update(Connection $connection): void
    {
        $query = '            
            CREATE TABLE `paynl_transactions` (
                `id` BINARY(16) NOT NULL,
                
                `customer_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NULL,

                `paynl_payment_id` INT NOT NULL,
                `signature` VARCHAR(70) NOT NULL,
                `amount` FLOAT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `exceptions` TEXT,
                `comment` VARCHAR(255),
                `dispatch` VARCHAR(255),
                
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                
                PRIMARY KEY (`id`)
                    
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ';

        $connection->executeQuery($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
