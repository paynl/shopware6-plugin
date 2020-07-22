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

        $orderTransactionStateId = $connection->executeQuery($orderTransactionStateSQL, [
            'technical_name' => 'order_transaction.state'
        ])->fetchColumn();

        $authorizeStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => StateMachineStateEnum::ACTION_AUTHORIZE,
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();

        $verifyStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => StateMachineStateEnum::ACTION_VERIFY,
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();

        $inProgressStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'in_progress',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();

        $transitions = [
            [
                'action_name' => StateMachineStateEnum::ACTION_AUTHORIZE,
                'from_state_id' => $inProgressStateMachineStateId,
                'to_state_id' => $authorizeStateMachineStateId,
            ],
            [
                'action_name' => StateMachineStateEnum::ACTION_VERIFY,
                'from_state_id' => $inProgressStateMachineStateId,
                'to_state_id' => $verifyStateMachineStateId,
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
