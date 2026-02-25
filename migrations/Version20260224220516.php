<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224220516 extends AbstractMigration
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
        $this->addSql('ALTER TABLE ai_conversation_summary ADD CONSTRAINT FK_ABFD0B2B9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE ai_conversation_summary ADD CONSTRAINT FK_ABFD0B2B4DA1E751 FOREIGN KEY (requested_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE ai_smart_reply ADD CONSTRAINT FK_3F21C58C9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE ai_smart_reply ADD CONSTRAINT FK_3F21C58CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_conversation_summary DROP FOREIGN KEY FK_ABFD0B2B9AC0396');
        $this->addSql('ALTER TABLE ai_conversation_summary DROP FOREIGN KEY FK_ABFD0B2B4DA1E751');
        $this->addSql('ALTER TABLE ai_smart_reply DROP FOREIGN KEY FK_3F21C58C9AC0396');
        $this->addSql('ALTER TABLE ai_smart_reply DROP FOREIGN KEY FK_3F21C58CA76ED395');
        $this->addSql('DROP TABLE ai_conversation_summary');
        $this->addSql('DROP TABLE ai_smart_reply');
    }
}
