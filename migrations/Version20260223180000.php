<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create weekly_ai_insight table for weekly AI summaries and engagement insights.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE weekly_ai_insight (id INT AUTO_INCREMENT NOT NULL, family_id INT NOT NULL, week_start DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', week_end DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', status VARCHAR(16) NOT NULL, provider VARCHAR(32) DEFAULT NULL, model VARCHAR(128) DEFAULT NULL, payload JSON DEFAULT NULL, raw_response LONGTEXT DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, generated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_40483D6DFBAB50 (family_id), UNIQUE INDEX uniq_weekly_ai_insight_family_week (family_id, week_start), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE weekly_ai_insight ADD CONSTRAINT FK_40483D6DFBAB50 FOREIGN KEY (family_id) REFERENCES family (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weekly_ai_insight DROP FOREIGN KEY FK_40483D6DFBAB50');
        $this->addSql('DROP TABLE weekly_ai_insight');
    }
}
