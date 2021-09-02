<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class Migration1630581261RevertCancelledTransitions extends MigrationStep
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    //Payment statuses
    const REFUNDED_STATUS = 'refunded';
    const REFUNDED_PARTIALLY_STATUS = 'refunded_partially';
    const CANCELLED_STATUS = 'cancelled';

    /** @var Connection */
    private $connection;

    public function getCreationTimestamp(): int
    {
        return 1630581261;
    }

    public function update(Connection $connection): void
    {
        $this->connection = $connection;

        $date = date(self::DATE_FORMAT);

        $orderTransactionStateId = $connection->executeQuery($this->getOrderTransactionStateSql(), [
            'technical_name' => 'order_transaction.state'
        ])->fetchColumn();


        $transitions = $this->getTransitions($orderTransactionStateId);

        $defaultData = [
            'state_machine_id' => $orderTransactionStateId,
            'created_at' => $date
        ];
        foreach ($transitions as $transition) {
            $connection->executeUpdate(
                $this->getInsertTransitionSql(),
                array_merge($transition, $defaultData, ['id' => Uuid::randomBytes()])
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getTransitions($stateMachineStateId): array
    {
        $cancelledStateMachineStateId = $this->getStateMachineStateId(self::CANCELLED_STATUS, $stateMachineStateId);
        $refundedStateMachineStateId = $this->getStateMachineStateId(self::REFUNDED_STATUS, $stateMachineStateId);
        $refundedPartiallyStateMachineStateId = $this->getStateMachineStateId(
            self::REFUNDED_PARTIALLY_STATUS,
            $stateMachineStateId
        );

        $transitions = [
            // From Cancelled to Refunded
            [
                'action_name' => StateMachineTransitionActions::ACTION_REFUND,
                'from_state_id' => $cancelledStateMachineStateId,
                'to_state_id' => $refundedStateMachineStateId,
            ],
            // From Cancelled to Refunded partially
            [
                'action_name' => StateMachineTransitionActions::ACTION_REFUND_PARTIALLY,
                'from_state_id' => $cancelledStateMachineStateId,
                'to_state_id' => $refundedPartiallyStateMachineStateId,
            ],
        ];

        return $transitions;
    }


    private function getOrderTransactionStateSql(): string
    {
        return join(' ', [
            'SELECT',
            'id',
            'FROM',
            'state_machine',
            'WHERE',
            'technical_name = :technical_name',
            'LIMIT 1'
        ]);
    }

    private function getSelectStateMachineState(): string
    {
        return join(' ', [
            'SELECT',
            'id',
            'FROM',
            'state_machine_state',
            'WHERE',
            'technical_name = :technical_name',
            'AND',
            'state_machine_id = :state_machine_id'
        ]);
    }

    private function getInsertTransitionSql(): string
    {
        return join(' ', [
            'INSERT INTO',
            'state_machine_transition',
            '(id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)',
            'VALUES',
            '(:id, :action_name, :state_machine_id, :from_state_id, :to_state_id, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `action_name` = :action_name, `updated_at` = CURRENT_TIME();'
        ]);
    }

    private function getStateMachineStateId(string $technicalName, $stateMachineId)
    {
        return $this->connection->executeQuery($this->getSelectStateMachineState(), [
            'technical_name' => $technicalName,
            'state_machine_id' => $stateMachineId,
        ])->fetchColumn();
    }
}
