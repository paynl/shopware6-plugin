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

                $mailTemplateContentHtml = ($mailTemplateTranslation['content_html'] ?? '') . $this->getMailTemplate();
                if (strpos($mailTemplateTranslation['content_html'], $this->getMailTemplate()) !== false) {
                    $mailTemplateContentHtml = $mailTemplateTranslation['content_html'] ?? '';
                }

                $mailTemplateContentPlain = ($mailTemplateTranslation['content_plain'] ?? '') . $this->getMailTemplate();
                if (strpos($mailTemplateTranslation['content_plain'], $this->getMailTemplate()) !== false) {
                    $mailTemplateContentPlain = $mailTemplateTranslation['content_plain'] ?? '';
                }

                $this->updateMailTemplateTranslation([
                    'mail_template_id' => $mailTemplateTranslation['mail_template_id'],
                    'language_id' => $mailTemplateTranslation['language_id'],
                    'content_html' => $mailTemplateContentHtml,
                    'content_plain' => $mailTemplateContentPlain,
                ]);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateMailTemplateTranslation(array $mailTemplateTranslationUpdate)
    {
        $this->connection->executeUpdate(
            $this->getUpdatingMailTemplateTranslationSql(),
            $mailTemplateTranslationUpdate
        );
    }

    private function getMailTemplate(): string
    {
        return "\n\n{% set lastTransaction = order.transactions|last %}\n"
            . "{% if lastTransaction is not null and lastTransaction.customFields.paynl_payments.approval_id is defined %} \n"
            . "ApprovalID - {{ lastTransaction.customFields.paynl_payments.approval_id }}\n"
            . "{% endif %}";
    }

    private function getMailTemplateTypeId(string $technicalName)
    {
        return $this->connection->executeQuery($this->getSelectMailTemplateType(), [
            'technical_name' => $technicalName,
        ])->fetchColumn();
    }

    private function getMailTemplates(string $mailTemplateTypeId)
    {
        return $this->connection->executeQuery($this->getSelectMailTemplate(), [
            'mail_template_type_id' => $mailTemplateTypeId,
        ])->fetchAll();
    }

    private function getMailTemplateTranslations(string $mailTemplateId)
    {
        return $this->connection->executeQuery($this->getSelectMailTemplateTranslation(), [
            'mail_template_id' => $mailTemplateId,
        ])->fetchAll();
    }

    private function getUpdatingMailTemplateTranslationSql(): string
    {
        return join(' ', [
            'UPDATE',
            'mail_template_translation',
            'SET',
            'content_html = :content_html, content_plain = :content_plain, updated_at = CURRENT_TIME()',
            'WHERE',
            'mail_template_id = :mail_template_id',
            'AND',
            'language_id = :language_id',
            ';'
        ]);
    }

    private function getSelectMailTemplateType(): string
    {
        return join(' ', [
            'SELECT',
            'id',
            'FROM',
            'mail_template_type',
            'WHERE',
            'technical_name = :technical_name',
        ]);
    }

    private function getSelectMailTemplate(): string
    {
        return join(' ', [
            'SELECT',
            'id',
            'FROM',
            'mail_template',
            'WHERE',
            'mail_template_type_id = :mail_template_type_id',
        ]);
    }

    private function getSelectMailTemplateTranslation(): string
    {
        return join(' ', [
            'SELECT',
            'mail_template_id, language_id, content_html, content_plain',
            'FROM',
            'mail_template_translation',
            'WHERE',
            'mail_template_id = :mail_template_id',
        ]);
    }
}
