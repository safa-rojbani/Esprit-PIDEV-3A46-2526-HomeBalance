<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225011500 extends AbstractMigration
{
    private const FK_FAMILY = 'FK_DOCUMENT_ACTIVITY_LOG_FAMILY';
    private const FK_USER = 'FK_DOCUMENT_ACTIVITY_LOG_USER';
    private const FK_DOCUMENT = 'FK_DOCUMENT_ACTIVITY_LOG_DOCUMENT';

    public function getDescription(): string
    {
        return 'Add document activity logs table';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['document_activity_log'])) {
            $this->addSql('CREATE TABLE document_activity_log (id INT AUTO_INCREMENT NOT NULL, family_id INT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, document_id INT DEFAULT NULL, event_type VARCHAR(64) NOT NULL, channel VARCHAR(32) DEFAULT NULL, metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_document_activity_family_created (family_id, created_at), INDEX idx_document_activity_user_created (user_id, created_at), INDEX idx_document_activity_document_created (document_id, created_at), INDEX idx_document_activity_event_created (event_type, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        } else {
            // Recovery path for partially failed migration runs.
            $this->addSql('ALTER TABLE document_activity_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE document_activity_log MODIFY user_id VARCHAR(36) DEFAULT NULL');
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('document_activity_log')
        );

        if (!in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_activity_log ADD CONSTRAINT FK_DOCUMENT_ACTIVITY_LOG_FAMILY FOREIGN KEY (family_id) REFERENCES family (id) ON DELETE CASCADE');
        }
        if (!in_array(self::FK_USER, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_activity_log ADD CONSTRAINT FK_DOCUMENT_ACTIVITY_LOG_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        }
        if (!in_array(self::FK_DOCUMENT, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_activity_log ADD CONSTRAINT FK_DOCUMENT_ACTIVITY_LOG_DOCUMENT FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['document_activity_log'])) {
            return;
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys('document_activity_log')
        );

        if (in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_activity_log DROP FOREIGN KEY FK_DOCUMENT_ACTIVITY_LOG_FAMILY');
        }
        if (in_array(self::FK_USER, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_activity_log DROP FOREIGN KEY FK_DOCUMENT_ACTIVITY_LOG_USER');
        }
        if (in_array(self::FK_DOCUMENT, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_activity_log DROP FOREIGN KEY FK_DOCUMENT_ACTIVITY_LOG_DOCUMENT');
        }

        $this->addSql('DROP TABLE document_activity_log');
        $this->addSql('DROP TABLE IF EXISTS document_insight_daily');
    }
}
