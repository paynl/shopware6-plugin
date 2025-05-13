<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class Migration1630335397AddRefundingStatus extends MigrationStep
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    //Payment statuses
    const REFUNDING_STATUS = 'refunding';
    const PAID_STATUS = 'paid';
    const PAID_PARTIALLY_STATUS = 'paid_partially';
    const REFUNDED_STATUS = 'refunded';
    const REFUNDED_PARTIALLY_STATUS = 'refunded_partially';
    const CANCELLED_STATUS = 'cancelled';

    /** @var Connection */
    private $connection;

    public function getCreationTimestamp(): int
    {
        return 1630407150;
    }

    public function update(Connection $connection): void
    {
        $this->connection = $connection;

        $date = date(self::DATE_FORMAT);

        $orderTransactionStateId = $connection->executeQuery($this->getOrderTransactionStateSql(), [
            'technical_name' => 'order_transaction.state'
        ])->fetchOne();

        $statuses = $this->getStatuses();

        foreach ($statuses as $status => $translations) {
            $connection->executeStatement($this->getInsertStateMachineStateSql(), [
                'id' => Uuid::randomBytes(),
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId,
                'created_at' => $date,
            ]);

            $stateMachineStateId = $connection->executeQuery($this->getSelectStateMachineState(), [
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId
            ])->fetchOne();

            if (!empty($translations['english']['id'])) {
                $connection->executeStatement($this->getStateMachineStateTranslationSql(), [
                    'language_id' => $translations['english']['id'],
                    'state_machine_state_id' => $stateMachineStateId,
                    'name' => $translations['english']['name'],
                    'created_at' => $date
                ]);
            }

            if (!empty($translations['german']['id'])) {
                $connection->executeStatement($this->getStateMachineStateTranslationSql(), [
                    'language_id' => $translations['german']['id'],
                    'state_machine_state_id' => $stateMachineStateId,
                    'name' => $translations['german']['name'],
                    'created_at' => $date
                ]);
            }
        }

        $transitions = $this->getTransitions($orderTransactionStateId);

        $defaultData = [
            'state_machine_id' => $orderTransactionStateId,
            'created_at' => $date
        ];
        foreach ($transitions as $transition) {
            $connection->executeStatement(
                $this->getInsertTransitionSql(),
                array_merge($transition, $defaultData, ['id' => Uuid::randomBytes()])
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getStatuses(): array
    {
        return [
            self::REFUNDING_STATUS => [
                'english' => [
                    'id' => $this->getLanguageId('English'),
                    'name' => 'Refunding',
                ],
                'german' => [
                    'id' => $this->getLanguageId('Deutsch'),
                    'name' => 'Erstattung',
                ],
            ]
        ];
    }

    private function getTransitions($stateMachineStateId): array
    {
        $refundingStateMachineStateId = $this->getStateMachineStateId(self::REFUNDING_STATUS, $stateMachineStateId);
        $paidStateMachineStateId = $this->getStateMachineStateId(self::PAID_STATUS, $stateMachineStateId);
        $cancelledStateMachineStateId = $this->getStateMachineStateId(self::CANCELLED_STATUS, $stateMachineStateId);
        $refundedStateMachineStateId = $this->getStateMachineStateId(self::REFUNDED_STATUS, $stateMachineStateId);
        $paidPartiallyStateMachineStateId = $this->getStateMachineStateId(
            self::PAID_PARTIALLY_STATUS,
            $stateMachineStateId
        );
        $refundedPartiallyStateMachineStateId = $this->getStateMachineStateId(
            self::REFUNDED_PARTIALLY_STATUS,
            $stateMachineStateId
        );

        $transitions = [
            //From Paid to Refunding
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $paidStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            //From Paid partially to Refunding
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $paidPartiallyStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            //From Refunded partially to Refunding
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $refundedPartiallyStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            //From Refunding to Refunding
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $refundingStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            //From Refunding to Refunded
            [
                'action_name' => StateMachineTransitionActions::ACTION_REFUND,
                'from_state_id' => $refundingStateMachineStateId,
                'to_state_id' => $refundedStateMachineStateId,
            ],
            //From Refunding to Refunded partially
            [
                'action_name' => StateMachineTransitionActions::ACTION_REFUND_PARTIALLY,
                'from_state_id' => $refundingStateMachineStateId,
                'to_state_id' => $refundedPartiallyStateMachineStateId,
            ],
            //From Refunding to Cancelled
            [
                'action_name' => StateMachineTransitionActions::ACTION_CANCEL,
                'from_state_id' => $refundingStateMachineStateId,
                'to_state_id' => $cancelledStateMachineStateId,
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

    private function getLanguageSql(): string
    {
        return join(' ', [
            'SELECT',
            'id',
            'FROM',
            'language',
            'WHERE',
            'name = :name',
            'LIMIT 1'
        ]);
    }

    private function getInsertStateMachineStateSql(): string
    {
        return join(' ', [
            'INSERT INTO',
            'state_machine_state',
            '(id, technical_name, state_machine_id, created_at, updated_at)',
            'VALUES',
            '(:id, :technical_name, :state_machine_id, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `updated_at` = CURRENT_TIME();'
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

    private function getStateMachineStateTranslationSql(): string
    {
        return join(' ', [
            'INSERT INTO',
            'state_machine_state_translation',
            '(`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)',
            'VALUES',
            '(:language_id, :state_machine_state_id, :name, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `updated_at` = CURRENT_TIME();'
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
        ])->fetchOne();
    }

    private function getLanguageId(string $name)
    {
        return $this->connection->executeQuery($this->getLanguageSql(), [
            'name' => $name
        ])->fetchOne();
    }
}
