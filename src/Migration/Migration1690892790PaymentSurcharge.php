<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Entity\PaymentSurchargeDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1690892790PaymentSurcharge extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1690892790;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `'.PaymentSurchargeDefinition::ENTITY_NAME.'` (
              `id` BINARY(16) NOT NULL,
              `amount` FLOAT NOT NULL,
              `order_value_limit` INT(8) NULL,
              `type` VARCHAR(25) NULL,
              `payment_method_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.paynl_payment_surcharge.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }

    public function updateDestructive(Connection $connection): void
    {

    }
}
