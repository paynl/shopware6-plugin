<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

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
            CREATE TABLE IF NOT EXISTS `paynl_transactions` (
                `id` BINARY(16) NOT NULL,

                `customer_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NULL,
                `order_transaction_id` BINARY(16) NULL,

                `paynl_transaction_id` VARCHAR(16),
                `payment_id` INT(11) NOT NULL,
                `amount` FLOAT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `exception` TEXT,
                `comment` VARCHAR(255),
                `dispatch` VARCHAR(255),
                `state_id` INT(11) NULL,
                `order_state_id` BINARY(16) NULL,

                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,

                PRIMARY KEY (`id`),

                KEY `fk.paynl_transaction.customer_id` (`customer_id`),
                KEY `fk.paynl_transaction.order_id` (`order_id`),
                KEY `fk.paynl_transaction.order_state_id` (`order_state_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';

        $connection->executeQuery($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
