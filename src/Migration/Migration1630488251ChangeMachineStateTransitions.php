<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1630488251ChangeMachineStateTransitions extends MigrationStep
{
    //Payment statuses
    const REFUNDING_STATUS = 'refunding';
    const REFUNDED_STATUS = 'refunded';
    const REFUNDED_PARTIALLY_STATUS = 'refunded_partially';
    const CANCELLED_STATUS = 'cancelled';

    /** @var Connection */
    private $connection;

    public function getCreationTimestamp(): int
    {
        return 1630488251;
    }

    public function update(Connection $connection): void
    {
        $this->connection = $connection;

        $orderTransactionStateId = $connection->executeQuery($this->getOrderTransactionStateSql(), [
            'technical_name' => 'order_transaction.state'
        ])->fetchColumn();

        $transitions = $this->getTransitions($orderTransactionStateId);

        foreach ($transitions as $transition) {
            $connection->executeUpdate(
                $this->getDeleteTransitionSql(),
                $transition
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getTransitions($stateMachineStateId): array
    {
        $refundingStateMachineStateId = $this->getStateMachineStateId(self::REFUNDING_STATUS, $stateMachineStateId);
        $cancelledStateMachineStateId = $this->getStateMachineStateId(self::CANCELLED_STATUS, $stateMachineStateId);
        $refundedStateMachineStateId = $this->getStateMachineStateId(self::REFUNDED_STATUS, $stateMachineStateId);
        $refundedPartiallyStateMachineStateId = $this->getStateMachineStateId(
            self::REFUNDED_PARTIALLY_STATUS,
            $stateMachineStateId
        );

        $transitions = [
            // From Cancelled to Refunding
            [
                'from_state_id' => $cancelledStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            // From Cancelled to Refunded
            [
                'from_state_id' => $cancelledStateMachineStateId,
                'to_state_id' => $refundedStateMachineStateId,
            ],
            // From Cancelled to Refunded partially
            [
                'from_state_id' => $cancelledStateMachineStateId,
                'to_state_id' => $refundedPartiallyStateMachineStateId,
            ],
        ];

        return $transitions;
    }

    private function getStateMachineStateId(string $technicalName, $stateMachineId)
    {
        return $this->connection->executeQuery($this->getSelectStateMachineState(), [
            'technical_name' => $technicalName,
            'state_machine_id' => $stateMachineId,
        ])->fetchColumn();
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

    private function getDeleteTransitionSql(): string
    {
        return join(' ', [
            'DELETE FROM',
            'state_machine_transition',
            'WHERE from_state_id = :from_state_id',
            'AND to_state_id = :to_state_id',
        ]);
    }
}
