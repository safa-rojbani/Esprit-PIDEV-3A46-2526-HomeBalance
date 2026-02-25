<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225011500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add document activity logs table';
    }

    public function up(Schema $schema): void
    {
        // Safety for reruns after a partially failed migration execution.
        $this->addSql('DROP TABLE IF EXISTS document_activity_log');
        $this->addSql('DROP TABLE IF EXISTS document_insight_daily');

        $this->addSql('CREATE TABLE document_activity_log (id INT AUTO_INCREMENT NOT NULL, family_id INT NOT NULL, user_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL, document_id INT DEFAULT NULL, event_type VARCHAR(64) NOT NULL, channel VARCHAR(32) DEFAULT NULL, metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_document_activity_family_created (family_id, created_at), INDEX idx_document_activity_user_created (user_id, created_at), INDEX idx_document_activity_document_created (document_id, created_at), INDEX idx_document_activity_event_created (event_type, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_activity_log ADD CONSTRAINT FK_DOCUMENT_ACTIVITY_LOG_FAMILY FOREIGN KEY (family_id) REFERENCES family (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_activity_log ADD CONSTRAINT FK_DOCUMENT_ACTIVITY_LOG_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document_activity_log ADD CONSTRAINT FK_DOCUMENT_ACTIVITY_LOG_DOCUMENT FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_activity_log DROP FOREIGN KEY FK_DOCUMENT_ACTIVITY_LOG_FAMILY');
        $this->addSql('ALTER TABLE document_activity_log DROP FOREIGN KEY FK_DOCUMENT_ACTIVITY_LOG_USER');
        $this->addSql('ALTER TABLE document_activity_log DROP FOREIGN KEY FK_DOCUMENT_ACTIVITY_LOG_DOCUMENT');
        $this->addSql('DROP TABLE document_activity_log');
        $this->addSql('DROP TABLE IF EXISTS document_insight_daily');
    }
}
