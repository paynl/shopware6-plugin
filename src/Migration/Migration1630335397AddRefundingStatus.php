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
        ])->fetchColumn();

        $statuses = $this->getStatuses();

        foreach ($statuses as $status => $translations) {
            $connection->executeUpdate($this->getInsertStateMachineStateSql(), [
                'id' => Uuid::randomBytes(),
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId,
                'created_at' => $date,
            ]);

            $stateMachineStateId = $connection->executeQuery($this->getSelectStateMachineState(), [
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId
            ])->fetchColumn();

            $connection->executeUpdate($this->getStateMachineStateTranslationSql(), [
                'language_id' => $translations['english']['id'],
                'state_machine_state_id' => $stateMachineStateId,
                'name' => $translations['english']['name'],
                'created_at' => $date
            ]);

            $connection->executeUpdate($this->getStateMachineStateTranslationSql(), [
                'language_id' => $translations['german']['id'],
                'state_machine_state_id' => $stateMachineStateId,
                'name' => $translations['german']['name'],
                'created_at' => $date
            ]);
        }

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
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $paidStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $paidPartiallyStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $refundedPartiallyStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $cancelledStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            [
                'action_name' => self::REFUNDING_STATUS,
                'from_state_id' => $refundingStateMachineStateId,
                'to_state_id' => $refundingStateMachineStateId,
            ],
            [
                'action_name' => StateMachineTransitionActions::ACTION_REFUND,
                'from_state_id' => $refundingStateMachineStateId,
                'to_state_id' => $refundedStateMachineStateId,
            ],
            [
                'action_name' => StateMachineTransitionActions::ACTION_REFUND_PARTIALLY,
                'from_state_id' => $refundingStateMachineStateId,
                'to_state_id' => $refundedPartiallyStateMachineStateId,
            ],
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
        $orderTransactionStateSQL = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'state_machine',
            'WHERE',
            'technical_name = :technical_name',
            'LIMIT 1'
        ]);

        return $orderTransactionStateSQL;
    }

    private function getLanguageSql(): string
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

        return $languageSQL;
    }

    private function getInsertStateMachineStateSql(): string
    {
        $sqlStateMachineState = join(' ', [
            'INSERT INTO',
            'state_machine_state',
            '(id, technical_name, state_machine_id, created_at, updated_at)',
            'VALUES',
            '(:id, :technical_name, :state_machine_id, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `updated_at` = CURRENT_TIME();'
        ]);

        return $sqlStateMachineState;
    }

    private function getSelectStateMachineState(): string
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

        return $stateMachineStateSQL;
    }

    private function getStateMachineStateTranslationSql(): string
    {
        $stateMachineStateTranslation = join(' ', [
            'INSERT INTO',
            'state_machine_state_translation',
            '(`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)',
            'VALUES',
            '(:language_id, :state_machine_state_id, :name, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `updated_at` = CURRENT_TIME();'
        ]);

        return $stateMachineStateTranslation;
    }

    private function getInsertTransitionSql(): string
    {
        $insertTransitionSQL = join(' ', [
            'INSERT INTO',
            'state_machine_transition',
            '(id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)',
            'VALUES',
            '(:id, :action_name, :state_machine_id, :from_state_id, :to_state_id, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `action_name` = :action_name, `updated_at` = CURRENT_TIME();'
        ]);

        return $insertTransitionSQL;
    }

    private function getStateMachineStateId(string $technicalName, $stateMachineId)
    {
         return $this->connection->executeQuery($this->getSelectStateMachineState(), [
            'technical_name' => $technicalName,
            'state_machine_id' => $stateMachineId,
        ])->fetchColumn();
    }

    private function getLanguageId(string $name)
    {
        return $this->connection->executeQuery($this->getLanguageSql(), [
            'name' => $name
        ])->fetchColumn();
    }
}
