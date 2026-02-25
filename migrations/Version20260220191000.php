<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI image evaluations and score history attribution fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_image_evaluation (id INT AUTO_INCREMENT NOT NULL, completion_id INT NOT NULL, provider VARCHAR(32) NOT NULL, model VARCHAR(128) DEFAULT NULL, status VARCHAR(16) NOT NULL, decision VARCHAR(16) DEFAULT NULL, tidy_score INT DEFAULT NULL, confidence DOUBLE PRECISION DEFAULT NULL, reason_short VARCHAR(255) DEFAULT NULL, raw_response LONGTEXT DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_F3A67D6A86F0C346 (completion_id), INDEX IDX_F3A67D6A9ACB4450 (status), INDEX IDX_F3A67D6A4A3CA296 (processed_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ai_image_evaluation ADD CONSTRAINT FK_F3A67D6A86F0C346 FOREIGN KEY (completion_id) REFERENCES task_completion (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE score_history ADD completion_id INT DEFAULT NULL, ADD awarded_by_ai TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX IDX_463255DF86F0C346 ON score_history (completion_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_463255DF86F0C346 ON score_history (completion_id)');
        $this->addSql('ALTER TABLE score_history ADD CONSTRAINT FK_463255DF86F0C346 FOREIGN KEY (completion_id) REFERENCES task_completion (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_image_evaluation DROP FOREIGN KEY FK_F3A67D6A86F0C346');
        $this->addSql('DROP TABLE ai_image_evaluation');

        $this->addSql('ALTER TABLE score_history DROP FOREIGN KEY FK_463255DF86F0C346');
        $this->addSql('DROP INDEX UNIQ_463255DF86F0C346 ON score_history');
        $this->addSql('DROP INDEX IDX_463255DF86F0C346 ON score_history');
        $this->addSql('ALTER TABLE score_history DROP completion_id, DROP awarded_by_ai');
    }
}

