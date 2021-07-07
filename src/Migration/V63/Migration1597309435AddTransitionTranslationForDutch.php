<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration\V63;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597309435AddTransitionTranslationForDutch extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597309435;
    }

    public function update(Connection $connection): void
    {
        $languageSQL = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'language',
            'WHERE',
            'name = :name',
            'LIMIT 1'
        ]);

        $stateMachineStateSQL = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'state_machine_state',
            'WHERE',
            'technical_name = :technical_name',
            'AND',
            'state_machine_id = :state_machine_id'
        ]);

        $orderTransactionStateSQL = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'state_machine',
            'WHERE',
            'technical_name = :technical_name',
            'LIMIT 1'
        ]);

        $stateMachineStateTranslation = join(' ', [
            'INSERT INTO',
            'state_machine_state_translation',
            '(`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)',
            'VALUES',
            '(:language_id, :state_machine_state_id, :name, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `updated_at` = CURRENT_TIME();'
        ]);

        $languageDutchId = $connection->fetchColumn($languageSQL, [
            'name' => 'Dutch'
        ]);

        $orderTransactionStateId = $connection->fetchColumn($orderTransactionStateSQL, [
            'technical_name' => 'order_transaction.state'
        ]);

        $statusesArray = [
            'verify' => [
                'dutch' => [
                    'id' => $languageDutchId,
                    'name' => 'Verifiëren',
                ]
            ],
            'authorize' => [
                'dutch' => [
                    'id' => $languageDutchId,
                    'name' => 'Toestemming geven',
                ]
            ],
            'partly_captured' => [
                'dutch' => [
                    'id' => $languageDutchId,
                    'name' => 'Gedeeltelijk gevangen',
                ]
            ]
        ];

        if (!empty($languageDutchId)) {
            foreach ($statusesArray as $status => $translations) {
                $stateMachineStateId = $connection->fetchColumn($stateMachineStateSQL, [
                    'technical_name' => $status,
                    'state_machine_id' => $orderTransactionStateId
                ]);

                $connection->executeQuery($stateMachineStateTranslation, [
                    'language_id' => $translations['dutch']['id'],
                    'state_machine_state_id' => $stateMachineStateId,
                    'name' => $translations['dutch']['name'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {

    }
}
