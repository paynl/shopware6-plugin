<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1595341760AddPrefixesForOurStatuses extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595341760;
    }

    public function update(Connection $connection): void
    {
        $updateStatusTechnicalNameQuery = join(' ', [
            'UPDATE',
            'state_machine_state',
            'SET',
            'technical_name = :new_technical_name',
            'WHERE',
            'technical_name = :old_technical_name'
        ]);

        $statusesToChange = [
            [
                'new_technical_name' => 'paynl_verify',
                'old_technical_name' => 'verify',
            ],
            [
                'new_technical_name' => 'paynl_authorize',
                'old_technical_name' => 'authorize',
            ],
            [
                'new_technical_name' => 'paynl_partly_captured',
                'old_technical_name' => 'partly_captured',
            ],
        ];

        foreach ($statusesToChange as $statuseToChange) {
            $connection->executeQuery($updateStatusTechnicalNameQuery, $statuseToChange);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
