<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224222410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_conversation_summary (id INT AUTO_INCREMENT NOT NULL, summary LONGTEXT NOT NULL, message_count INT NOT NULL, generated_at DATETIME NOT NULL, conversation_id INT NOT NULL, requested_by_id VARCHAR(36) NOT NULL, INDEX IDX_ABFD0B2B9AC0396 (conversation_id), INDEX IDX_ABFD0B2B4DA1E751 (requested_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ai_smart_reply (id INT AUTO_INCREMENT NOT NULL, suggestions JSON NOT NULL, generated_at DATETIME NOT NULL, is_used TINYINT NOT NULL, conversation_id INT NOT NULL, user_id VARCHAR(36) NOT NULL, INDEX IDX_3F21C58C9AC0396 (conversation_id), INDEX IDX_3F21C58CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_activity_pattern (id INT AUTO_INCREMENT NOT NULL, peak_hours JSON DEFAULT NULL, last_calculated_at DATETIME DEFAULT NULL, user_id VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_DEFBC8B3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_presence (id INT AUTO_INCREMENT NOT NULL, last_seen_at DATETIME DEFAULT NULL, is_online TINYINT NOT NULL, user_id VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_89FA23A5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ai_conversation_summary ADD CONSTRAINT FK_ABFD0B2B9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE ai_conversation_summary ADD CONSTRAINT FK_ABFD0B2B4DA1E751 FOREIGN KEY (requested_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE ai_smart_reply ADD CONSTRAINT FK_3F21C58C9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE ai_smart_reply ADD CONSTRAINT FK_3F21C58CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_activity_pattern ADD CONSTRAINT FK_DEFBC8B3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_presence ADD CONSTRAINT FK_89FA23A5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD phone_number VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_conversation_summary DROP FOREIGN KEY FK_ABFD0B2B9AC0396');
        $this->addSql('ALTER TABLE ai_conversation_summary DROP FOREIGN KEY FK_ABFD0B2B4DA1E751');
        $this->addSql('ALTER TABLE ai_smart_reply DROP FOREIGN KEY FK_3F21C58C9AC0396');
        $this->addSql('ALTER TABLE ai_smart_reply DROP FOREIGN KEY FK_3F21C58CA76ED395');
        $this->addSql('ALTER TABLE user_activity_pattern DROP FOREIGN KEY FK_DEFBC8B3A76ED395');
        $this->addSql('ALTER TABLE user_presence DROP FOREIGN KEY FK_89FA23A5A76ED395');
        $this->addSql('DROP TABLE ai_conversation_summary');
        $this->addSql('DROP TABLE ai_smart_reply');
        $this->addSql('DROP TABLE user_activity_pattern');
        $this->addSql('DROP TABLE user_presence');
        $this->addSql('ALTER TABLE user DROP phone_number');
    }
}
