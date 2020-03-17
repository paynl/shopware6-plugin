<?php declare(strict_types=1);

namespace PaynlPayment\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1584438271InsertingStatuses extends MigrationStep
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    public function getCreationTimestamp(): int
    {
        return 1584438271;
    }

    public function update(Connection $connection): void
    {
        $this->addStateMachineState($connection);
        $this->addStateMachineStateTransition($connection);
        $this->addMailTemplateType($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param Connection $connection
     * @throws \Doctrine\DBAL\DBALException
     */
    private function addStateMachineState(Connection $connection): void
    {
        $date = date(self::DATE_FORMAT);
        $orderTransactionStateSQL = <<<SQL
SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1
SQL;
        $stateMachineStateVerifySQL = <<<SQL
SELECT id FROM state_machine_state 
                WHERE technical_name = 'verify' AND state_machine_id = ($orderTransactionStateSQL)
SQL;
        $stateMachineStateAuthorizeSQL = <<<SQL
SELECT id FROM state_machine_state 
                WHERE technical_name = 'authorize' AND state_machine_id = ($orderTransactionStateSQL)
SQL;
        $stateMachineStatePartlyCapturedSQL = <<<SQL
SELECT id FROM state_machine_state 
                WHERE technical_name = 'partly_captured' AND state_machine_id = ($orderTransactionStateSQL)
SQL;
        $languageEnglish = <<<SQL
SELECT `id` FROM `language` where `name` = 'English' limit 1
SQL;

        $languageDeutsch = <<<SQL
SELECT `id` FROM `language` where `name` = 'Deutsch' limit 1
SQL;

        // Adding verify state with translations
        $sql = <<<SQL
            INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at, updated_at)
            VALUES (
                :id, 
                :technical_name, 
                ($orderTransactionStateSQL), 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql, [
            'technical_name' => 'verify',
            'id' => Uuid::randomBytes()
        ]);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation 
                (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES (
                ($languageEnglish), 
                ($stateMachineStateVerifySQL), 
                :name, 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql, [
            'name' => 'Verify'
        ]);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation 
                (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES (
                ($languageDeutsch), 
                ($stateMachineStateVerifySQL), 
                'Überprüfen', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql);

        // Adding authorize state with translations
        $sql = <<<SQL
            INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at, updated_at)
            VALUES (
                :id, 
                'authorize', 
                ($orderTransactionStateSQL), 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql, [
            'id' => Uuid::randomBytes()
        ]);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation 
                (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES (
                ($languageEnglish), 
                ($stateMachineStateAuthorizeSQL), 
                'Authorize', 
                NULL, 
                '{$date}',
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation 
                (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES (
                ($languageDeutsch), 
                ($stateMachineStateAuthorizeSQL), 
                'Autorisieren', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql);

        // Adding partly_captured state with translations
        $sql = <<<SQL
            INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at, updated_at)
            VALUES (
                :id, 
                'partly_captured', 
                ($orderTransactionStateSQL), 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql, [
            'id' => Uuid::randomBytes()
        ]);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation 
                (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES (
                ($languageEnglish), 
                ($stateMachineStatePartlyCapturedSQL), 
                'Partly captured', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation 
                (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES (
                ($languageDeutsch), 
                ($stateMachineStatePartlyCapturedSQL), 
                'Partly captured', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($sql);
    }

    /**
     * @param Connection $connection
     * @throws \Doctrine\DBAL\DBALException
     */
    private function addStateMachineStateTransition(Connection $connection): void
    {
        $date = date(self::DATE_FORMAT);
        $stateMachineId = <<<SQL
            SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1
SQL;
        $paidStateMachineStateIdSQL = <<<SQL
            SELECT id 
            FROM state_machine_state 
            WHERE 
                state_machine_id = 
                    (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) 
                    AND technical_name = 'paid' 
            LIMIT 1
SQL;

        $openStateMachineStateId = <<<SQL
            SELECT id 
            FROM state_machine_state 
            WHERE 
                state_machine_id = 
                    (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) 
                AND technical_name = 'open' 
            LIMIT 1
SQL;

        $authorizeStateMachineStateId = <<<SQL
            SELECT id 
            FROM state_machine_state 
            WHERE 
                technical_name = 'authorize' 
                AND state_machine_id = 
                    (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) 
            LIMIT 1
SQL;

        $verifyStateMachineStateId = <<<SQL
            SELECT id 
            FROM state_machine_state 
            WHERE 
                technical_name = 'verify' 
                AND state_machine_id = 
                    (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) 
            LIMIT 1
SQL;

        $partlyCapturedStateMachineStateId = <<<SQL
            SELECT id 
            FROM state_machine_state 
            WHERE 
                technical_name = 'partly_captured' 
                AND state_machine_id = 
                    (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) 
            LIMIT 1
SQL;

        $cancelStateMachineStateIdSQL = <<<SQL
            SELECT id 
            FROM state_machine_state 
            WHERE 
                technical_name = 'cancelled' 
                AND state_machine_id = 
                    (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) 
            LIMIT 1
SQL;

        $insertTransitionOpenToAuthorize = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                'authorize',  
                ({$stateMachineId}), 
                ({$openStateMachineStateId}), 
                ({$authorizeStateMachineStateId}), 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertTransitionOpenToAuthorize, [
            'id' => Uuid::randomBytes()
        ]);

        $insertTransitionOpenToVerify = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                'verify',  ({$stateMachineId}), 
                ({$openStateMachineStateId}), 
                ({$verifyStateMachineStateId}), 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertTransitionOpenToVerify, [
            'id' => Uuid::randomBytes()
        ]);

        $insertTransitionOpenToPartlyCaptured = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                'partly_captured',  ({$stateMachineId}), 
                ({$openStateMachineStateId}), 
                ({$partlyCapturedStateMachineStateId}), 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertTransitionOpenToPartlyCaptured, [
            'id' => Uuid::randomBytes()
        ]);

        $insertTransitionAuthorizeToPaid = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                'pay',  
                ({$stateMachineId}), 
                ({$authorizeStateMachineStateId}), 
                ({$paidStateMachineStateIdSQL}), 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertTransitionAuthorizeToPaid, [
            'id' => Uuid::randomBytes()
        ]);

        $insertTransitionAuthorizeToCancel = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                'cancel',  
                ({$stateMachineId}), 
                ({$authorizeStateMachineStateId}), 
                ({$cancelStateMachineStateIdSQL}), 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertTransitionAuthorizeToCancel, [
            'id' => Uuid::randomBytes()
        ]);

        $uuidForVerifyToPaidStateMachineTransition = Uuid::randomBytes();
        $insertTransitionVerifyToPaid = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                'pay',  
                ({$stateMachineId}), 
                ({$verifyStateMachineStateId}), 
                ({$paidStateMachineStateIdSQL}), 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertTransitionVerifyToPaid, [
            'id' => Uuid::randomBytes()
        ]);

        $insertTransitionVerifyToCancel = <<<SQL
            INSERT INTO state_machine_transition 
                (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (
                :id, 
                'cancel',  
                ({$stateMachineId}), 
                ({$verifyStateMachineStateId}), 
                ({$cancelStateMachineStateIdSQL}), 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertTransitionVerifyToCancel, [
            'id' => Uuid::randomBytes()
        ]);
    }

    /**
     * @param Connection $connection
     * @throws \Doctrine\DBAL\DBALException
     */
    private function addMailTemplateType(Connection $connection): void
    {
        $date = date(self::DATE_FORMAT);
        $availableEntries = <<<JSON
{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel"}
JSON;

        $englishLanguageId = <<<SQL
            SELECT id FROM language WHERE name = 'English' LIMIT 1
SQL;
        $deutschLanguageId = <<<SQL
            SELECT id FROM language WHERE name = 'Deutsch' LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type (id, technical_name, available_entities, created_at, updated_at)
            VALUES (
                :id, 
                'order_transaction.state.authorize', 
                '{$availableEntries}', 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType, [
            'id' => Uuid::randomBytes()
        ]);

        $mailTempaleTypeId = <<<SQL
            SELECT id FROM mail_template_type WHERE technical_name LIKE 'order_transaction.state.authorize' LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation 
                (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (
                ({$mailTempaleTypeId}), 
                ({$englishLanguageId}), 
                'Enter payment state: Authorize', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation 
                (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (
                ({$mailTempaleTypeId}), 
                ({$deutschLanguageId}), 
                'Zahlungsstatus eingeben: Autorisieren', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type (id, technical_name, available_entities, created_at, updated_at)
            VALUES (
                :id, 
                'order_transaction.state.verify', 
                '{$availableEntries}', 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType, [
            'id' => Uuid::randomBytes()
        ]);

        $mailTempaleTypeId = <<<SQL
            SELECT id FROM mail_template_type WHERE technical_name LIKE 'order_transaction.state.verify' LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation 
                (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (
                ({$mailTempaleTypeId}), 
                ({$englishLanguageId}), 
                'Enter payment state: Verify', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation 
                (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (
                ({$mailTempaleTypeId}), 
                ({$deutschLanguageId}), 
                'Zahlungsstatus eingeben: Verify', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type (id, technical_name, available_entities, created_at, updated_at)
            VALUES (
                :id, 
                'order_transaction.state.partly_captured', 
                '{$availableEntries}', 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType, [
            'id' => Uuid::randomBytes()
        ]);

        $mailTempaleTypeId = <<<SQL
            SELECT id 
            FROM mail_template_type 
            WHERE technical_name LIKE 'order_transaction.state.partly_captured' 
            LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation 
                (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (
                ({$mailTempaleTypeId}), 
                ({$englishLanguageId}), 
                'Enter payment state: Partly captured', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation 
                (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (
                ({$mailTempaleTypeId}), 
                ({$deutschLanguageId}), 
                'Zahlungsstatus eingeben: Partly captured', 
                NULL, 
                '{$date}', 
                NULL
            )
            ON DUPLICATE KEY
                UPDATE `updated_at` = CURRENT_TIME();
SQL;
        $connection->executeUpdate($insertMailTemplateType);
    }
}
