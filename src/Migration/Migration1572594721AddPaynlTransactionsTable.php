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
                
                PRIMARY KEY (`id`),
                
                KEY `fk.paynl_transaction.customer_id` (`customer_id`),
                KEY `fk.paynl_transaction.order_id` (`order_id`),
               
                CONSTRAINT `fk.paynl_transaction.customer_id` 
                    FOREIGN KEY (`customer_id`) 
                    REFERENCES `customer` (`id`) 
                    ON DELETE RESTRICT ON UPDATE CASCADE,
               
                CONSTRAINT `fk.paynl_transaction.order_id` 
                    FOREIGN KEY (`order_id`) 
                    REFERENCES `order` (`id`) 
                    ON DELETE RESTRICT ON UPDATE CASCADE
                    
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ';

        $connection->executeQuery($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
