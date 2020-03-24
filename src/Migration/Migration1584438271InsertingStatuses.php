<?php declare(strict_types=1);

namespace PaynlPayment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1584438271InsertingStatuses extends MigrationStep
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    private $orderTransactionStateSQL = <<<SQL
SELECT id FROM state_machine WHERE technical_name = :technical_name LIMIT 1
SQL;
    
    private $stateMachineStateSQL = <<<SQL
SELECT id FROM state_machine_state WHERE technical_name = :technical_name AND state_machine_id = :state_machine_id
SQL;
    
    private $languageSQL = <<<SQL
SELECT `id` FROM `language` where `name` = :name limit 1
SQL;
    
    private $mailTempaleTypeId = <<<SQL
SELECT id FROM mail_template_type WHERE technical_name LIKE :technical_name LIMIT 1
SQL;
    
    private $sqlStateMachineState = <<<SQL
            INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at, updated_at)
            VALUES (
                :id, 
                :technical_name, 
                :state_machine_id, 
                :created_at, 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
    
    private $stateMachineStateTranslation = <<<SQL
            INSERT INTO state_machine_state_translation
                (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES (
                :language_id,
                :state_machine_state_id,
                :name,
                NULL,
                :created_at,
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
    
    private $insertTransitionSQL = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                :action_name, 
                :state_machine_id,
                :from_state_id,
                :to_state_id, 
                NULL, 
                :created_at, 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
    
    private $insertMailTemplateTypeSql = <<<SQL
            INSERT INTO mail_template_type (id, technical_name, available_entities, created_at, updated_at)
            VALUES (
                :id, 
                :technical_name, 
                :available_entities, 
                :created_at, 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
    
    private $insertMailTemplateTypeTranslationSQL = <<<SQL
            INSERT INTO mail_template_type_translation 
                (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (
                :mail_template_type_id, 
                :language_id, 
                :name, 
                NULL, 
                :created_at, 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
    
    private $availableEntries = <<<JSON
{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel"}
JSON;
    
    public function getCreationTimestamp(): int
    {
        return 1584438271;
    }

    public function update(Connection $connection): void
    {
        $date = date(self::DATE_FORMAT);

        $orderTransactionStateId = $connection->executeQuery($this->orderTransactionStateSQL, [
            'technical_name' => 'order_transaction.state'
        ])->fetchColumn();

        $languageEnglishId = $connection->executeQuery($this->languageSQL, [
            'name' => 'English'
        ])->fetchColumn();

        $languageDeutschId = $connection->executeQuery($this->languageSQL, [
            'name' => 'Deutsch'
        ])->fetchColumn();

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
            $connection->executeQuery($this->sqlStateMachineState, [
                'id' => Uuid::randomBytes(),
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId,
                'created_at' => $date,
            ]);

            $stateMachineStateId = $connection->executeQuery($this->stateMachineStateSQL, [
                'technical_name' => $status,
                'state_machine_id' => $orderTransactionStateId
            ])->fetchColumn();

            $connection->executeQuery($this->stateMachineStateTranslation, [
                'language_id' => $translations['english']['id'],
                'state_machine_state_id' => $stateMachineStateId,
                'name' => $translations['english']['name'],
                'created_at' => $date
            ]);

            $connection->executeQuery($this->stateMachineStateTranslation, [
                'language_id' => $translations['german']['id'],
                'state_machine_state_id' => $stateMachineStateId,
                'name' => $translations['german']['name'],
                'created_at' => $date
            ]);
        }
        
        // Adding transitions
        $paidStateMachineStateId = $connection->executeQuery($this->stateMachineStateSQL, [
            'technical_name' => 'paid',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();
        
        $openStateMachineStateId = $connection->executeQuery($this->stateMachineStateSQL, [
            'technical_name' => 'open',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();
        
        $authorizeStateMachineStateId = $connection->executeQuery($this->stateMachineStateSQL, [
            'technical_name' => 'authorize',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();
        
        $verifyStateMachineStateId = $connection->executeQuery($this->stateMachineStateSQL, [
            'technical_name' => 'verify',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();
        
        $partlyCapturedStateMachineStateId = $connection->executeQuery($this->stateMachineStateSQL, [
            'technical_name' => 'partly_captured',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();
        
        $cancelledCapturedStateMachineStateId = $connection->executeQuery($this->stateMachineStateSQL, [
            'technical_name' => 'cancelled',
            'state_machine_id' => $orderTransactionStateId,
        ])->fetchColumn();

        $connection->executeQuery($this->insertTransitionSQL, [
            'id' => Uuid::randomBytes(),
            'action_name' => 'authorize',
            'state_machine_id' => $orderTransactionStateId,
            'from_state_id' => $openStateMachineStateId,
            'to_state_id' => $authorizeStateMachineStateId,
            'created_at' => $date
        ]);

        $connection->executeQuery($this->insertTransitionSQL, [
            'id' => Uuid::randomBytes(),
            'action_name' => 'verify',
            'state_machine_id' => $orderTransactionStateId,
            'from_state_id' => $openStateMachineStateId,
            'to_state_id' => $verifyStateMachineStateId,
            'created_at' => $date
        ]);

        $connection->executeQuery($this->insertTransitionSQL, [
            'id' => Uuid::randomBytes(),
            'action_name' => 'partly_captured',
            'state_machine_id' => $orderTransactionStateId,
            'from_state_id' => $openStateMachineStateId,
            'to_state_id' => $partlyCapturedStateMachineStateId,
            'created_at' => $date
        ]);

        $connection->executeQuery($this->insertTransitionSQL, [
            'id' => Uuid::randomBytes(),
            'action_name' => 'pay',
            'state_machine_id' => $orderTransactionStateId,
            'from_state_id' => $authorizeStateMachineStateId,
            'to_state_id' => $paidStateMachineStateId,
            'created_at' => $date
        ]);
        
        $connection->executeQuery($this->insertTransitionSQL, [
            'id' => Uuid::randomBytes(),
            'action_name' => 'cancel',
            'state_machine_id' => $orderTransactionStateId,
            'from_state_id' => $authorizeStateMachineStateId,
            'to_state_id' => $cancelledCapturedStateMachineStateId,
            'created_at' => $date
        ]);
        
        $connection->executeQuery($this->insertTransitionSQL, [
            'id' => Uuid::randomBytes(),
            'action_name' => 'pay',
            'state_machine_id' => $orderTransactionStateId,
            'from_state_id' => $verifyStateMachineStateId,
            'to_state_id' => $paidStateMachineStateId,
            'created_at' => $date
        ]);
        
        $connection->executeQuery($this->insertTransitionSQL, [
            'id' => Uuid::randomBytes(),
            'action_name' => 'cancel',
            'state_machine_id' => $orderTransactionStateId,
            'from_state_id' => $verifyStateMachineStateId,
            'to_state_id' => $cancelledCapturedStateMachineStateId,
            'created_at' => $date
        ]);
        
        $connection->executeQuery($this->insertMailTemplateTypeSql, [
            'id' => Uuid::randomBytes(),
            'technical_name' => 'order_transaction.state.authorize',
            'available_entities' => $this->availableEntries,
            'created_at' => $date,
        ]);

        // Adding mail template types
        $mailTempaleAuthorizeTypeId = $connection->executeQuery($this->mailTempaleTypeId, [
            'technical_name' => 'order_transaction.state.authorize'
        ])->fetchColumn();

        $connection->executeQuery($this->insertMailTemplateTypeTranslationSQL, [
            'mail_template_type_id' => $mailTempaleAuthorizeTypeId,
            'language_id' => $languageEnglishId,
            'name' => 'Enter payment state: Authorize',
            'created_at' => $date,
        ]);

        $connection->executeQuery($this->insertMailTemplateTypeTranslationSQL, [
            'mail_template_type_id' => $mailTempaleAuthorizeTypeId,
            'language_id' => $languageDeutschId,
            'name' => 'Zahlungsstatus eingeben: Autorisieren',
            'created_at' => $date,
        ]);

        $connection->executeQuery($this->insertMailTemplateTypeSql, [
            'id' => Uuid::randomBytes(),
            'technical_name' => 'order_transaction.state.verify',
            'available_entities' => $this->availableEntries,
            'created_at' => $date,
        ]);

        $mailTempaleVerifyTypeId = $connection->executeQuery($this->mailTempaleTypeId, [
            'technical_name' => 'order_transaction.state.verify'
        ])->fetchColumn();

        $connection->executeQuery($this->insertMailTemplateTypeTranslationSQL, [
            'mail_template_type_id' => $mailTempaleVerifyTypeId,
            'language_id' => $languageEnglishId,
            'name' => 'Enter payment state: Verify',
            'created_at' => $date,
        ]);

        $connection->executeQuery($this->insertMailTemplateTypeTranslationSQL, [
            'mail_template_type_id' => $mailTempaleVerifyTypeId,
            'language_id' => $languageDeutschId,
            'name' => 'Zahlungsstatus eingeben: Verify',
            'created_at' => $date,
        ]);

        $connection->executeQuery($this->insertMailTemplateTypeSql, [
            'id' => Uuid::randomBytes(),
            'technical_name' => 'order_transaction.state.partly_captured',
            'available_entities' => $this->availableEntries,
            'created_at' => $date,
        ]);

        $mailTempalePartlyCapturedTypeId = $connection->executeQuery($this->mailTempaleTypeId, [
            'technical_name' => 'order_transaction.state.partly_captured'
        ])->fetchColumn();

        $connection->executeQuery($this->insertMailTemplateTypeTranslationSQL, [
            'mail_template_type_id' => $mailTempalePartlyCapturedTypeId,
            'language_id' => $languageEnglishId,
            'name' => 'Enter payment state: Partly captured',
            'created_at' => $date,
        ]);

        $connection->executeQuery($this->insertMailTemplateTypeTranslationSQL, [
            'mail_template_type_id' => $mailTempalePartlyCapturedTypeId,
            'language_id' => $languageDeutschId,
            'name' => 'Zahlungsstatus eingeben: Partly captured',
            'created_at' => $date,
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
