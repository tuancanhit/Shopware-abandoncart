<?php declare(strict_types=1);

namespace AbandonCart\Plugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use DateTime;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Migration1668320164AbandonCartEmailType
 *
 * @package AbandonCart\Plugin\Migration
 */
class Migration1668320164AbandonCartEmailType extends MigrationStep
{
    /**
     * @return int
     */
    public function getCreationTimestamp(): int
    {
        return 1668320164;
    }

    /**
     * @param Connection $connection
     * @param string $locale
     * @return string|null
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<SQL
        SELECT `language`.`id`
        FROM `language`
        INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
        WHERE `locale`.`code` = :code
        SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();

        if (empty($languageId)) {
            return null;
        }

        return $languageId;
    }

    /**
     * @param Connection $connection
     * @return void
     */
    public function update(Connection $connection): void
    {
        $mailTemplateTypeId = $this->createMailTemplateType($connection);

        $this->createMailTemplate($connection, $mailTemplateTypeId);
    }

    /**
     * @param Connection $connection
     * @return string
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function createMailTemplateType(Connection $connection): string
    {
        $mailTemplateTypeId = Uuid::randomHex();

        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $englishName = 'Abandon Cart';
        $germanName = 'Abandon Cart';

        $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_type`
                (id, technical_name, available_entities, created_at)
            VALUES
                (:id, :technicalName, :availableEntities, :createdAt)
        ", [
            'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'technicalName' => 'custom_mail_template_type',
            'availableEntities' => json_encode(['product' => 'product']),
            'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if (!empty($enGbLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_type_translation`
                (mail_template_type_id, language_id, name, created_at)
            VALUES
                (:mailTemplateTypeId, :languageId, :name, :createdAt)
            ", [
                'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'languageId' => $enGbLangId,
                'name' => $englishName,
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if (!empty($deDeLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_type_translation`
                (mail_template_type_id, language_id, name, created_at)
            VALUES
                (:mailTemplateTypeId, :languageId, :name, :createdAt)
            ", [
                'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'languageId' => $deDeLangId,
                'name' => $germanName,
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    /**
     * @param Connection $connection
     * @return void
     */
    public function updateDestructive(Connection $connection): void
    {
        // TODO: Implement updateDestructive() method.
    }

    /**
     * @param Connection $connection
     * @param string $mailTemplateTypeId
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $mailTemplateId = Uuid::randomHex();

        $enGbLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deDeLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->executeStatement("
        INSERT IGNORE INTO `mail_template`
            (id, mail_template_type_id, system_default, created_at)
        VALUES
            (:id, :mailTemplateTypeId, :systemDefault, :createdAt)
        ", [
            'id' => Uuid::fromHexToBytes($mailTemplateId),
            'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
            'systemDefault' => 0,
            'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if (!empty($enGbLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ", [
                'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                'languageId' => $enGbLangId,
                'senderName' => '{{ salesChannel.name }}',
                'subject' => 'Abandon Cart',
                'description' => 'Abandon Cart',
                'contentHtml' => '',
                'contentPlain' => '',
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if (!empty($deDeLangId)) {
            $connection->executeStatement("
            INSERT IGNORE INTO `mail_template_translation`
                (mail_template_id, language_id, sender_name, subject, description, content_html, content_plain, created_at)
            VALUES
                (:mailTemplateId, :languageId, :senderName, :subject, :description, :contentHtml, :contentPlain, :createdAt)
            ", [
                'mailTemplateId' => Uuid::fromHexToBytes($mailTemplateId),
                'languageId' => $deDeLangId,
                'senderName' => '{{ salesChannel.name }}',
                'subject' => 'Abandon Cart',
                'description' => 'Abandon Cart',
                'contentHtml' => '',
                'contentPlain' => '',
                'createdAt' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

    }
}
