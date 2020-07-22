<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class Migration1595405770ChangePayActionNameToPaid extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595405770;
    }

    public function update(Connection $connection): void
    {
        $orderTransactionStateSQL = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'state_machine',
            'WHERE',
            'technical_name = :technical_name',
            'LIMIT 1'
        ]);

        $updateTransitionSQL = join(' ', [
            'UPDATE',
            '`state_machine_transition`',
            'SET',
            '`action_name` = :action_name, `updated_at` = :updated_at',
            'WHERE',
            '`from_state_id` = :from_state_id',
            'AND',
            '`to_state_id` = :to_state_id',
            'AND',
            '`state_machine_id` = :state_machine_id;'
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

        $orderTransactionStateId = $connection->executeQuery($orderTransactionStateSQL, [
            'technical_name' => 'order_transaction.state'
        ])->fetchColumn();

        $authorizeStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => StateMachineStateEnum::ACTION_AUTHORIZE,
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();

        $paidStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'paid',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();

        $verifyStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => StateMachineStateEnum::ACTION_VERIFY,
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();

        $transitions = [
            ['from_state_id' => $authorizeStateMachineStateId],
            ['from_state_id' => $verifyStateMachineStateId]
        ];

        $defaultData = [
            'action_name' => StateMachineTransitionActions::ACTION_PAID,
            'state_machine_id' => bin2hex($orderTransactionStateId),
            'to_state_id' => bin2hex($paidStateMachineStateId),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        foreach ($transitions as $transition) {
            $connection->executeUpdate(
                $updateTransitionSQL,
                array_merge($transition, $defaultData)
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
