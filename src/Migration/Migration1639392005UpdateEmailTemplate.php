<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1639392005UpdateEmailTemplate extends MigrationStep
{
    const ORDER_TRANSACTION_PAID_MAIL_TEMPLATE = 'order_transaction.state.paid';

    /** @var Connection */
    private $connection;

    public function getCreationTimestamp(): int
    {
        return 1639392005;
    }

    public function update(Connection $connection): void
    {
        $this->connection = $connection;

        $mailTemplateTypeId = $this->getMailTemplateTypeId(self::ORDER_TRANSACTION_PAID_MAIL_TEMPLATE);
        $mailTemplates = $this->getMailTemplates($mailTemplateTypeId);

        foreach ($mailTemplates as $mailTemplate) {
            if (empty($mailTemplate['id'])) {
                continue;
            }

            $mailTemplateTranslations = $this->getMailTemplateTranslations($mailTemplate['id']);

            foreach ($mailTemplateTranslations as $mailTemplateTranslation) {
                if (empty($mailTemplateTranslation['mail_template_id']) || empty($mailTemplateTranslation['language_id'])) {
                    continue;
                }

                $mailContentHtml = $mailTemplateTranslation['content_html'] ?? '';
                $mailTemplate = $this->getMailTemplate();
                if (strpos($mailContentHtml, $mailTemplate) === false) {
                    $this->updateMailTemplateTranslationContentHtml([
                        'mail_template_id' => $mailTemplateTranslation['mail_template_id'],
                        'language_id' => $mailTemplateTranslation['language_id'],
                        'content_html' => $mailContentHtml . $mailTemplate
                    ]);
                }

                $mailContentPlain = $mailTemplateTranslation['content_plain'] ?? '';
                if (strpos($mailContentPlain, $mailTemplate) === false) {
                    $this->updateMailTemplateTranslationContentPlain([
                        'mail_template_id' => $mailTemplateTranslation['mail_template_id'],
                        'language_id' => $mailTemplateTranslation['language_id'],
                        'content_plain' => $mailContentPlain . $mailTemplate
                    ]);
                }
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateMailTemplateTranslationContentHtml(array $mailTemplateTranslationUpdate): void
    {
        $sqlQuery = implode(' ', [
            'UPDATE',
            'mail_template_translation',
            'SET',
            'content_html = :content_html, updated_at = CURRENT_TIME()',
            'WHERE',
            'mail_template_id = :mail_template_id',
            'AND',
            'language_id = :language_id',
            ';'
        ]);

        $this->connection->executeUpdate($sqlQuery, $mailTemplateTranslationUpdate);
    }

    private function updateMailTemplateTranslationContentPlain(array $mailTemplateTranslationUpdate): void
    {
        $sqlQuery = implode(' ', [
            'UPDATE',
            'mail_template_translation',
            'SET',
            'content_plain = :content_plain, updated_at = CURRENT_TIME()',
            'WHERE',
            'mail_template_id = :mail_template_id',
            'AND',
            'language_id = :language_id',
            ';'
        ]);

        $this->connection->executeUpdate($sqlQuery, $mailTemplateTranslationUpdate);
    }

    private function getMailTemplate(): string
    {
        return "\n\n{% set lastTransaction = order.transactions|last %}\n"
            . "{% for transaction in order.transactions %}\n"
            . "{% if transaction.stateMachineState.technicalName == \"paid\" %}\n"
            . "{% set lastTransaction = transaction %}\n"
            . "{% endif %}\n"
            . "{% endfor %}\n"
            . "{% if lastTransaction is not null and lastTransaction.customFields.paynl_payments.approval_id is defined %} \n"
            . "ApprovalID - {{ lastTransaction.customFields.paynl_payments.approval_id }}\n"
            . "{% endif %}";
    }

    private function getMailTemplateTypeId(string $technicalName)
    {
        $sqlQuery = implode(' ', [
            'SELECT',
            'id',
            'FROM',
            'mail_template_type',
            'WHERE',
            'technical_name = :technical_name',
        ]);

        return $this->connection->executeQuery($sqlQuery, [
            'technical_name' => $technicalName,
        ])->fetchColumn();
    }

    private function getMailTemplates(string $mailTemplateTypeId)
    {
        $sqlQuery = implode(' ', [
            'SELECT',
            'id',
            'FROM',
            'mail_template',
            'WHERE',
            'mail_template_type_id = :mail_template_type_id',
        ]);

        return $this->connection->executeQuery($sqlQuery, [
            'mail_template_type_id' => $mailTemplateTypeId,
        ])->fetchAll();
    }

    private function getMailTemplateTranslations(string $mailTemplateId)
    {
        $sqlQuery = implode(' ', [
            'SELECT',
            'mail_template_id, language_id, content_html, content_plain',
            'FROM',
            'mail_template_translation',
            'WHERE',
            'mail_template_id = :mail_template_id',
        ]);

        return $this->connection->executeQuery($sqlQuery, [
            'mail_template_id' => $mailTemplateId,
        ])->fetchAll();
    }
}
