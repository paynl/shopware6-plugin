<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1595405748AddTransitionsForInProgress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595405748;
    }

    public function update(Connection $connection): void
    {
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

        $insertTransitionSQL = join(' ', [
            'INSERT INTO',
            'state_machine_transition',
            '(id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)',
            'VALUES',
            '(:id, :action_name, :state_machine_id, :from_state_id, :to_state_id, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `action_name` = :action_name, `updated_at` = CURRENT_TIME();'
        ]);

        $orderTransactionStateId = $connection->fetchColumn($orderTransactionStateSQL, [
            'technical_name' => 'order_transaction.state'
        ]);

        $authorizeStateMachineStateId = $connection->fetchColumn($stateMachineStateSQL, [
            'technical_name' => 'paynl_authorize',
            'state_machine_id' => $orderTransactionStateId,
        ]);

        $partlyCapturedStateMachineStateId = $connection->fetchColumn($stateMachineStateSQL, [
            'technical_name' => 'paynl_partly_captured',
            'state_machine_id' => $orderTransactionStateId,
        ]);

        $verifyStateMachineStateId = $connection->fetchColumn($stateMachineStateSQL, [
            'technical_name' => 'paynl_authorize',
            'state_machine_id' => $orderTransactionStateId,
        ]);

        $inProgressStateMachineStateId = $connection->fetchColumn($stateMachineStateSQL, [
            'technical_name' => 'in_progress',
            'state_machine_id' => $orderTransactionStateId,
        ]);

        $transitions = [
            [
                'action_name' => 'paynl_authorize',
                'from_state_id' => $inProgressStateMachineStateId,
                'to_state_id' => $authorizeStateMachineStateId,
            ],
            [
                'action_name' => 'paynl_verify',
                'from_state_id' => $inProgressStateMachineStateId,
                'to_state_id' => $verifyStateMachineStateId,
            ],
            [
                'action_name' => 'paynl_partly_captured',
                'from_state_id' => $inProgressStateMachineStateId,
                'to_state_id' => $partlyCapturedStateMachineStateId,
            ]
        ];

        $defaultData = [
            'state_machine_id' => $orderTransactionStateId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        foreach ($transitions as $transition) {
            $connection->executeQuery(
                $insertTransitionSQL,
                array_merge($transition, $defaultData, ['id' => Uuid::randomBytes()])
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
