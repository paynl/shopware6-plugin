<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class Migration1584438271InsertingStatuses extends MigrationStep
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    public function getCreationTimestamp(): int
    {
        return 1584438271;
    }

    public function update(Connection $connection): void
    {
        $availableEntries = [
            'order' => 'order',
            'previousState' => 'state_machine_state',
            'newState' => 'state_machine_state',
            'salesChannel' => 'salesChannel',
        ];
        $availableEntriesJson = json_encode($availableEntries);

        $orderTransactionStateSQL = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'state_machine',
            'WHERE',
            'technical_name = :technical_name',
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

        $languageSQL = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'language',
            'WHERE',
            'name = :name',
            'LIMIT 1'
        ]);

        $mailTempaleTypeId = join(' ', [
            'SELECT',
            'id',
            'FROM',
            'mail_template_type',
            'WHERE',
            'technical_name LIKE :technical_name',
            'LIMIT 1'
        ]);

        $sqlStateMachineState = join(' ', [
            'INSERT INTO',
            'state_machine_state',
            '(id, technical_name, state_machine_id, created_at, updated_at)',
            'VALUES',
            '(:id, :technical_name, :state_machine_id, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `updated_at` = CURRENT_TIME();'
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

        $insertTransitionSQL = join(' ', [
            'INSERT INTO',
            'state_machine_transition',
            '(id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)',
            'VALUES',
            '(:id, :action_name, :state_machine_id, :from_state_id, :to_state_id, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `action_name` = :action_name, `updated_at` = CURRENT_TIME();'
        ]);

        $insertMailTemplateTypeSql = join(' ', [
            'INSERT INTO',
            'mail_template_type',
            '(id, technical_name, available_entities, created_at, updated_at)',
            'VALUES',
            '(:id, :technical_name, :available_entities, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `technical_name` = :technical_name, `updated_at` = CURRENT_TIME();'
        ]);

        $insertMailTemplateTypeTranslationSQL = join(' ', [
            'INSERT INTO',
            'mail_template_type_translation',
            '(mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)',
            'VALUES',
            '(:mail_template_type_id, :language_id, :name, NULL, :created_at, NULL)',
            'ON DUPLICATE KEY',
            'UPDATE `updated_at` = CURRENT_TIME();'
        ]);

        $date = date(self::DATE_FORMAT);

        $orderTransactionStateId = $connection->executeQuery($orderTransactionStateSQL, [
            'technical_name' => 'order_transaction.state'
        ])->fetchOne();

        $languageEnglishId = $connection->executeQuery($languageSQL, [
            'name' => 'English'
        ])->fetchOne();

        $languageDeutschId = $connection->executeQuery($languageSQL, [
            'name' => 'Deutsch'
        ])->fetchOne();

        $statusesArray = [
            'verify' => [
                'english' => [
                    'id' => $languageEnglishId,
                    'name' => 'Verify',
                ],
                'german' => [
                    'id' => $languageDeutschId,
                    'name' => 'Überprüfen',
                ],
            ],
            'authorize' => [
                'english' => [
                    'id' => $languageEnglishId,
                    'name' => 'Authorize',
                ],
                'german' => [
                    'id' => $languageDeutschId,
                    'name' => 'Autorisieren',
                ],
            ],
            'partly_captured' => [
                'english' => [
                    'id' => $languageEnglishId,
                    'name' => 'Partly captured',
                ],
                'german' => [
                    'id' => $languageDeutschId,
                    'name' => 'Teilweise erfasst',
                ],
            ]
        ];

        foreach ($statusesArray as $status => $translations) {
            $connection->executeStatement($sqlStateMachineState, [
                'id' => Uuid::randomBytes(),
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId,
                'created_at' => $date,
            ]);

            $stateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId
            ])->fetchOne();

            foreach ($translations as $translation) {
                if (empty($translation['id'])) {
                    continue;
                }

                $connection->executeStatement($stateMachineStateTranslation, [
                    'language_id' => $translation['id'],
                    'state_machine_state_id' => $stateMachineStateId,
                    'name' => $translation['name'],
                    'created_at' => $date
                ]);
            }
        }

        // Adding transitions
        $paidStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'paid',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchOne();

        $openStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'open',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchOne();

        $authorizeStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'authorize',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchOne();

        $verifyStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'verify',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchOne();

        $partlyCapturedStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'partly_captured',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchOne();

        $cancelledCapturedStateMachineStateId = $connection->executeQuery($stateMachineStateSQL, [
            'technical_name' => 'cancelled',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchOne();

        $transitions = [
            [
                'action_name' => 'authorize',
                'from_state_id' => $openStateMachineStateId,
                'to_state_id' => $authorizeStateMachineStateId,
            ],
            [
                'action_name' => 'verify',
                'from_state_id' => $openStateMachineStateId,
                'to_state_id' => $verifyStateMachineStateId,
            ],
            [
                'action_name' => 'partly_captured',
                'from_state_id' => $openStateMachineStateId,
                'to_state_id' => $partlyCapturedStateMachineStateId,
            ],
            [
                'action_name' => 'paid',
                'from_state_id' => $authorizeStateMachineStateId,
                'to_state_id' => $paidStateMachineStateId,
            ],
            [
                'action_name' => 'cancel',
                'from_state_id' => $authorizeStateMachineStateId,
                'to_state_id' => $cancelledCapturedStateMachineStateId,
            ],
            [
                'action_name' => 'paid',
                'from_state_id' => $verifyStateMachineStateId,
                'to_state_id' => $paidStateMachineStateId
            ],
            [
                'action_name' => 'cancel',
                'from_state_id' => $verifyStateMachineStateId,
                'to_state_id' => $cancelledCapturedStateMachineStateId,
            ]
        ];

        $defaultData = [
            'state_machine_id' => $orderTransactionStateId,
            'created_at' => $date
        ];
        foreach ($transitions as $transition) {
            $connection->executeStatement(
                $insertTransitionSQL,
                array_merge($transition, $defaultData, ['id' => Uuid::randomBytes()])
            );
        }

        $connection->executeStatement($insertMailTemplateTypeSql, [
            'id' => Uuid::randomBytes(),
            'technical_name' => 'order_transaction.state.authorize',
            'available_entities' => $availableEntriesJson,
            'created_at' => $date,
        ]);

        // Adding mail template types
        $mailTempaleAuthorizeTypeId = $connection->executeQuery($mailTempaleTypeId, [
            'technical_name' => 'order_transaction.state.authorize'
        ])->fetchOne();

        if (!empty($languageEnglishId)) {
            $connection->executeStatement($insertMailTemplateTypeTranslationSQL, [
                'mail_template_type_id' => $mailTempaleAuthorizeTypeId,
                'language_id' => $languageEnglishId,
                'name' => 'Enter payment state: Authorize',
                'created_at' => $date,
            ]);
        }

        if (!empty($languageDeutschId)) {
            $connection->executeStatement($insertMailTemplateTypeTranslationSQL, [
                'mail_template_type_id' => $mailTempaleAuthorizeTypeId,
                'language_id' => $languageDeutschId,
                'name' => 'Zahlungsstatus eingeben: Autorisieren',
                'created_at' => $date,
            ]);
        }

        $connection->executeStatement($insertMailTemplateTypeSql, [
            'id' => Uuid::randomBytes(),
            'technical_name' => 'order_transaction.state.verify',
            'available_entities' => $availableEntriesJson,
            'created_at' => $date,
        ]);

        $mailTempaleVerifyTypeId = $connection->executeQuery($mailTempaleTypeId, [
            'technical_name' => 'order_transaction.state.verify'
        ])->fetchOne();

        if (!empty($languageEnglishId)) {
            $connection->executeStatement($insertMailTemplateTypeTranslationSQL, [
                'mail_template_type_id' => $mailTempaleVerifyTypeId,
                'language_id' => $languageEnglishId,
                'name' => 'Enter payment state: Verify',
                'created_at' => $date,
            ]);
        }

        if (!empty($languageDeutschId)) {
            $connection->executeStatement($insertMailTemplateTypeTranslationSQL, [
                'mail_template_type_id' => $mailTempaleVerifyTypeId,
                'language_id' => $languageDeutschId,
                'name' => 'Zahlungsstatus eingeben: Verify',
                'created_at' => $date,
            ]);
        }

        $connection->executeStatement($insertMailTemplateTypeSql, [
            'id' => Uuid::randomBytes(),
            'technical_name' => 'order_transaction.state.partly_captured',
            'available_entities' => $availableEntriesJson,
            'created_at' => $date,
        ]);

        $mailTempalePartlyCapturedTypeId = $connection->executeQuery($mailTempaleTypeId, [
            'technical_name' => 'order_transaction.state.partly_captured'
        ])->fetchOne();

        if (!empty($languageEnglishId)) {
            $connection->executeStatement($insertMailTemplateTypeTranslationSQL, [
                'mail_template_type_id' => $mailTempalePartlyCapturedTypeId,
                'language_id' => $languageEnglishId,
                'name' => 'Enter payment state: Partly captured',
                'created_at' => $date,
            ]);
        }

        if (!empty($languageDeutschId)) {
            $connection->executeStatement($insertMailTemplateTypeTranslationSQL, [
                'mail_template_type_id' => $mailTempalePartlyCapturedTypeId,
                'language_id' => $languageDeutschId,
                'name' => 'Zahlungsstatus eingeben: Partly captured',
                'created_at' => $date,
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
