<?php

namespace PaynlPayment;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class PaynlPayment extends Plugin
{
    const MYSQL_DROP_TABLE = 'DROP TABLE IF EXISTS %s';
    const TABLE_PAYNL_TRANSACTIONS = 'paynl_transactions';

    public function install(InstallContext $installContext): void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
    }

    public function activate(ActivateContext $activateContext): void
    {
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->dropTable(self::TABLE_PAYNL_TRANSACTIONS);
    }

    private function dropTable(string $tableName)
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $connection->exec(sprintf(self::MYSQL_DROP_TABLE, $tableName));
    }
}
