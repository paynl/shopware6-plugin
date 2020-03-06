<?php declare(strict_types=1);

namespace PaynlPayment\Migration;

use Doctrine\DBAL\Connection;
use Enqueue\Util\UUID;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1572594721AddPaynlTransactionsTable extends MigrationStep
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    public function getCreationTimestamp(): int
    {
        return 1572594721;
    }

    public function update(Connection $connection): void
    {
        $query = '
            CREATE TABLE IF NOT EXISTS `paynl_transactions` (
                `id` BINARY(16) NOT NULL,

                `customer_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NULL,
                `order_transaction_id` BINARY(16) NULL,

                `paynl_transaction_id` VARCHAR(16),
                `payment_id` INT(11) NOT NULL,
                `amount` FLOAT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `exception` TEXT,
                `comment` VARCHAR(255),
                `dispatch` VARCHAR(255),
                `state_id` INT(11) NULL,
                `order_state_id` BINARY(16) NULL,

                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,

                PRIMARY KEY (`id`),

                KEY `fk.paynl_transaction.customer_id` (`customer_id`),
                KEY `fk.paynl_transaction.order_id` (`order_id`),
                KEY `fk.paynl_transaction.order_state_id` (`order_state_id`),

                CONSTRAINT `fk.paynl_transaction.customer_id`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE,

                CONSTRAINT `fk.paynl_transaction.order_id`
                    FOREIGN KEY (`order_id`)
                    REFERENCES `order` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE,

                CONSTRAINT `fk.paynl_transaction.order_state_id`
                    FOREIGN KEY (`order_state_id`)
                    REFERENCES `state_machine_state` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';
        $connection->executeQuery($query);

        $this->addStateMachineState($connection);
        $this->addStateMachineStateTransition($connection);
        $this->addMailTemplateType($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }


    private function addStateMachineState($connection)
    {
        $date = date(self::DATE_FORMAT);

        // Adding verify state with translations
        $uuidForVerifyStateMachineState = UUID::generate();
        $sql = <<<SQL
            INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForVerifyStateMachineState}'), 'verify', (SELECT id FROM shopware.state_machine where technical_name = 'order_transaction.state' limit 1), '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);
        $sql = <<<SQL
            INSERT INTO state_machine_state_translation (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES ((SELECT `id` FROM `language` where `name` = 'English' limit 1), UUID_TO_BIN('{$uuidForVerifyStateMachineState}'), 'Verify', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES ((SELECT `id` FROM `language` where `name` = 'Deutsch' limit 1), UUID_TO_BIN('{$uuidForVerifyStateMachineState}'), 'Überprüfen', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);

        // Adding authorize state with translations
        $uuidForAuthorizeStateMachineState = UUID::generate();
        $sql = <<<SQL
            INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForAuthorizeStateMachineState}'), 'authorize', (SELECT id FROM shopware.state_machine where technical_name = 'order_transaction.state' limit 1), '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES ((SELECT `id` FROM `language` where `name` = 'English' limit 1), UUID_TO_BIN('{$uuidForAuthorizeStateMachineState}'), 'Authorize', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES ((SELECT `id` FROM `language` where `name` = 'Deutsch' limit 1), UUID_TO_BIN('{$uuidForAuthorizeStateMachineState}'), 'Autorisieren', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);

        // Adding partly_captured state with translations
        $uuidForPartlyCapturedStateMachineState = UUID::generate();
        $sql = <<<SQL
            INSERT INTO state_machine_state (id, technical_name, state_machine_id, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForPartlyCapturedStateMachineState}'), 'partly_captured', (SELECT id FROM shopware.state_machine where technical_name = 'order_transaction.state' limit 1), '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES ((SELECT `id` FROM `language` where `name` = 'English' limit 1), UUID_TO_BIN('{$uuidForPartlyCapturedStateMachineState}'), 'Partly captured', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            INSERT INTO state_machine_state_translation (`language_id`, `state_machine_state_id`, `name`, `custom_fields`, `created_at`, `updated_at`)
            VALUES ((SELECT `id` FROM `language` where `name` = 'Deutsch' limit 1), UUID_TO_BIN('{$uuidForPartlyCapturedStateMachineState}'), 'Partly captured', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($sql);
    }

    private function addStateMachineStateTransition($connection)
    {
        $date = date(self::DATE_FORMAT);
        $stateMachineId = <<<SQL
            SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1
SQL;

        $openStateMachineStateId = <<<SQL
            SELECT id FROM shopware.state_machine_state WHERE state_machine_id = (SELECT id FROM shopware.state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) AND technical_name = 'open' LIMIT 1
SQL;

        $authorizeStateMachineStateId = <<<SQL
            SELECT id FROM state_machine_state WHERE technical_name = 'authorize' AND state_machine_id = (SELECT id FROM shopware.state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) LIMIT 1
SQL;

        $uuidForOpenToAuthorizeStateMachineTransition = UUID::generate();
        $insertTransitionOpenToAuthorize = <<<SQL
            INSERT INTO state_machine_transition (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForOpenToAuthorizeStateMachineTransition}'), 'authorize',  ({$stateMachineId}), ({$openStateMachineStateId}), ({$authorizeStateMachineStateId}), NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertTransitionOpenToAuthorize);

        $verifyStateMachineStateId = <<<SQL
            SELECT id FROM state_machine_state WHERE technical_name = 'verify' AND state_machine_id = (SELECT id FROM shopware.state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) LIMIT 1
SQL;

        $uuidForOpenToVerifyStateMachineTransition = UUID::generate();
        $insertTransitionOpenToVerify = <<<SQL
            INSERT INTO state_machine_transition (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForOpenToVerifyStateMachineTransition}'), 'verify',  ({$stateMachineId}), ({$openStateMachineStateId}), ({$verifyStateMachineStateId}), NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertTransitionOpenToVerify);

        $partlyCapturedStateMachineStateId = <<<SQL
            SELECT id FROM state_machine_state WHERE technical_name = 'partly_captured' AND state_machine_id = (SELECT id FROM shopware.state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) LIMIT 1
SQL;

        $uuidForOpenToPartlyCapturedStateMachineTransition = UUID::generate();
        $insertTransitionOpenToPartlyCaptured = <<<SQL
            INSERT INTO state_machine_transition (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForOpenToPartlyCapturedStateMachineTransition}'), 'partly_captured',  ({$stateMachineId}), ({$openStateMachineStateId}), ({$partlyCapturedStateMachineStateId}), NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertTransitionOpenToPartlyCaptured);

        $uuidForAuthorizeToPaidStateMachineTransition = UUID::generate();

        $stateMachineId = <<<SQL
            SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1
SQL;
        $paidStateMachineStateIdSQL = <<<SQL
            SELECT id FROM state_machine_state WHERE state_machine_id = (SELECT id FROM state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) AND technical_name = 'paid' LIMIT 1
SQL;
        $authorizeStateMachineStateId = <<<SQL
            SELECT id FROM state_machine_state WHERE technical_name = 'authorize' AND state_machine_id = (SELECT id FROM shopware.state_machine WHERE technical_name = 'order_transaction.state' LIMIT 1) LIMIT 1
SQL;

        $insertTransitionAuthorizeToPaid = <<<SQL
            INSERT INTO state_machine_transition (id, action_name, state_machine_id, from_state_id, to_state_id, custom_fields, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForAuthorizeToPaidStateMachineTransition}'), 'pay',  ({$stateMachineId}), ({$authorizeStateMachineStateId}), ({$paidStateMachineStateIdSQL}), NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertTransitionAuthorizeToPaid);
    }

    private function addMailTemplateType($connection)
    {
        $date = date(self::DATE_FORMAT);
        $availableEntries = '{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel"}';

        $uuidForMailTempaleTypeAuthorize = UUID::generate();
        $englishLanguageId = <<<SQL
            SELECT id FROM language WHERE name = 'English' LIMIT 1
SQL;
        $deutschLanguageId = <<<SQL
            SELECT id FROM language WHERE name = 'Deutsch' LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type (id, technical_name, available_entities, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForMailTempaleTypeAuthorize}'), 'order_transaction.state.authorize', '{$availableEntries}', '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $mailTempaleTypeId = <<<SQL
            SELECT id FROM mail_template_type WHERE technical_name LIKE 'order_transaction.state.authorize' LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (({$mailTempaleTypeId}), ({$englishLanguageId}), 'Enter payment state: Authorize', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (({$mailTempaleTypeId}), ({$deutschLanguageId}), 'Zahlungsstatus eingeben: Autorisieren', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $uuidForMailTempaleTypeVerify = UUID::generate();
        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type (id, technical_name, available_entities, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForMailTempaleTypeVerify}'), 'order_transaction.state.verify', '{$availableEntries}', '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $mailTempaleTypeId = <<<SQL
            SELECT id FROM mail_template_type WHERE technical_name LIKE 'order_transaction.state.verify' LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (({$mailTempaleTypeId}), ({$englishLanguageId}), 'Enter payment state: Verify', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (({$mailTempaleTypeId}), ({$deutschLanguageId}), 'Zahlungsstatus eingeben: Verify', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $uuidForMailTempaleTypePartlyCaptured = UUID::generate();
        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type (id, technical_name, available_entities, created_at, updated_at)
            VALUES (UUID_TO_BIN('{$uuidForMailTempaleTypePartlyCaptured}'), 'order_transaction.state.partly_captured', '{$availableEntries}', '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $mailTempaleTypeId = <<<SQL
            SELECT id FROM mail_template_type WHERE technical_name LIKE 'order_transaction.state.partly_captured' LIMIT 1
SQL;

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (({$mailTempaleTypeId}), ({$englishLanguageId}), 'Enter payment state: Partly captured', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);

        $insertMailTemplateType = <<<SQL
            INSERT INTO mail_template_type_translation (mail_template_type_id, language_id, name, custom_fields, created_at, updated_at)
            VALUES (({$mailTempaleTypeId}), ({$deutschLanguageId}), 'Zahlungsstatus eingeben: Partly captured', NULL, '{$date}', NULL);
SQL;
        $connection->executeQuery($insertMailTemplateType);
    }
}
