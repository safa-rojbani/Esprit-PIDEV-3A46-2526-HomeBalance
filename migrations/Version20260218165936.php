<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218165936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account_notification (id INT AUTO_INCREMENT NOT NULL, notification_key VARCHAR(64) NOT NULL, channel VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, payload JSON DEFAULT NULL, attempts INT NOT NULL, last_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, user_id VARCHAR(36) NOT NULL, INDEX IDX_268C608CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE avatar_upload_log (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, author_id VARCHAR(36) NOT NULL, INDEX IDX_B883883700047D2 (ticket_id), INDEX IDX_B883883F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE account_notification ADD CONSTRAINT FK_268C608CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_B883883700047D2 FOREIGN KEY (ticket_id) REFERENCES support_ticket (id)');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_B883883F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE default_gallery');
        $this->addSql('ALTER TABLE badge CHANGE code code VARCHAR(64) NOT NULL, CHANGE scope scope VARCHAR(32) NOT NULL');
        $this->addSql('DROP INDEX uniq_badge_code ON badge');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FEF0481D77153098 ON badge (code)');
        $this->addSql('ALTER TABLE categorie_achat CHANGE family_id family_id INT NOT NULL');
        $this->addSql('ALTER TABLE conversation CHANGE created_by_id created_by_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE conversation_participant DROP last_read_at');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A5E6215BE64D7D01 ON family (join_code)');
        $this->addSql('ALTER TABLE family_badge ADD CONSTRAINT FK_C287DB6EC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE family_badge ADD CONSTRAINT FK_C287DB6EF7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id)');
        $this->addSql('DROP INDEX idx_2d7d0f4e8db60186 ON family_badge');
        $this->addSql('CREATE INDEX IDX_C287DB6EC35E566A ON family_badge (family_id)');
        $this->addSql('DROP INDEX idx_2d7d0f4e7a9f8bf ON family_badge');
        $this->addSql('CREATE INDEX IDX_C287DB6EF7A2C2FC ON family_badge (badge_id)');
        $this->addSql('ALTER TABLE family_memberships ADD CONSTRAINT FK_46D3E108C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE family_memberships ADD CONSTRAINT FK_46D3E108A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('DROP INDEX idx_8b97ac9f8db60186 ON family_memberships');
        $this->addSql('CREATE INDEX IDX_46D3E108C35E566A ON family_memberships (family_id)');
        $this->addSql('DROP INDEX idx_8b97ac9fa76ed395 ON family_memberships');
        $this->addSql('CREATE INDEX IDX_46D3E108A76ED395 ON family_memberships (user_id)');
        $this->addSql('DROP INDEX uniq_8b97ac9f8db60186a76ed395 ON family_memberships');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_46D3E108C35E566AA76ED395 ON family_memberships (family_id, user_id)');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY `FK_472B783A4CC4FBEC`');
        $this->addSql('DROP INDEX IDX_472B783A4CC4FBEC ON gallery');
        $this->addSql('ALTER TABLE gallery DROP default_gallery_id');
        $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY `FK_68295E25FE95D117`');
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT FK_68295E25FE95D117 FOREIGN KEY (achat_id) REFERENCES achat (id)');
        $this->addSql('ALTER TABLE rappel DROP scheduled_at, DROP read_at');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY `FK_1F5A4D539AC0396`');
        $this->addSql('DROP INDEX IDX_1F5A4D539AC0396 ON support_ticket');
        $this->addSql('ALTER TABLE support_ticket ADD message LONGTEXT NOT NULL, ADD priority VARCHAR(255) NOT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD user_id VARCHAR(36) NOT NULL, DROP conversation_id, CHANGE title subject VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_1F5A4D53A76ED395 ON support_ticket (user_id)');
        $this->addSql('ALTER TABLE task CHANGE family_id family_id INT NOT NULL');
        $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY `FK_2CD60F15C35E566A`');
        $this->addSql('DROP INDEX IDX_2CD60F15C35E566A ON task_assignment');
        $this->addSql('ALTER TABLE task_assignment DROP refused_at, DROP family_id, CHANGE assigned_at assigned_at DATETIME DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE task_id task_id INT DEFAULT NULL, CHANGE user_id user_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON user (username)');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_3a9a2b8fa76ed395 ON user_badges');
        $this->addSql('CREATE INDEX IDX_1DA448A7A76ED395 ON user_badges (user_id)');
        $this->addSql('DROP INDEX idx_3a9a2b8f7a9f8bf ON user_badges');
        $this->addSql('CREATE INDEX IDX_1DA448A7F7A2C2FC ON user_badges (badge_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE default_gallery (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE account_notification DROP FOREIGN KEY FK_268C608CA76ED395');
        $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY FK_B883883700047D2');
        $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY FK_B883883F675F31B');
        $this->addSql('DROP TABLE account_notification');
        $this->addSql('DROP TABLE avatar_upload_log');
        $this->addSql('DROP TABLE support_message');
        $this->addSql('ALTER TABLE badge CHANGE code code VARCHAR(64) DEFAULT NULL, CHANGE scope scope VARCHAR(32) DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_fef0481d77153098 ON badge');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BADGE_CODE ON badge (code)');
        $this->addSql('ALTER TABLE categorie_achat CHANGE family_id family_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation CHANGE created_by_id created_by_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_participant ADD last_read_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_A5E6215BE64D7D01 ON family');
        $this->addSql('ALTER TABLE family_badge DROP FOREIGN KEY FK_C287DB6EC35E566A');
        $this->addSql('ALTER TABLE family_badge DROP FOREIGN KEY FK_C287DB6EF7A2C2FC');
        $this->addSql('ALTER TABLE family_badge DROP FOREIGN KEY FK_C287DB6EC35E566A');
        $this->addSql('ALTER TABLE family_badge DROP FOREIGN KEY FK_C287DB6EF7A2C2FC');
        $this->addSql('DROP INDEX idx_c287db6ec35e566a ON family_badge');
        $this->addSql('CREATE INDEX IDX_2D7D0F4E8DB60186 ON family_badge (family_id)');
        $this->addSql('DROP INDEX idx_c287db6ef7a2c2fc ON family_badge');
        $this->addSql('CREATE INDEX IDX_2D7D0F4E7A9F8BF ON family_badge (badge_id)');
        $this->addSql('ALTER TABLE family_badge ADD CONSTRAINT FK_C287DB6EC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE family_badge ADD CONSTRAINT FK_C287DB6EF7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id)');
        $this->addSql('ALTER TABLE family_memberships DROP FOREIGN KEY FK_46D3E108C35E566A');
        $this->addSql('ALTER TABLE family_memberships DROP FOREIGN KEY FK_46D3E108A76ED395');
        $this->addSql('ALTER TABLE family_memberships DROP FOREIGN KEY FK_46D3E108C35E566A');
        $this->addSql('ALTER TABLE family_memberships DROP FOREIGN KEY FK_46D3E108A76ED395');
        $this->addSql('DROP INDEX idx_46d3e108c35e566a ON family_memberships');
        $this->addSql('CREATE INDEX IDX_8B97AC9F8DB60186 ON family_memberships (family_id)');
        $this->addSql('DROP INDEX idx_46d3e108a76ed395 ON family_memberships');
        $this->addSql('CREATE INDEX IDX_8B97AC9FA76ED395 ON family_memberships (user_id)');
        $this->addSql('DROP INDEX uniq_46d3e108c35e566aa76ed395 ON family_memberships');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B97AC9F8DB60186A76ED395 ON family_memberships (family_id, user_id)');
        $this->addSql('ALTER TABLE family_memberships ADD CONSTRAINT FK_46D3E108C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE family_memberships ADD CONSTRAINT FK_46D3E108A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE gallery ADD default_gallery_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT `FK_472B783A4CC4FBEC` FOREIGN KEY (default_gallery_id) REFERENCES default_gallery (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_472B783A4CC4FBEC ON gallery (default_gallery_id)');
        $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY FK_68295E25FE95D117');
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT `FK_68295E25FE95D117` FOREIGN KEY (achat_id) REFERENCES achat (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rappel ADD scheduled_at DATETIME NOT NULL, ADD read_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53A76ED395');
        $this->addSql('DROP INDEX IDX_1F5A4D53A76ED395 ON support_ticket');
        $this->addSql('ALTER TABLE support_ticket ADD title VARCHAR(255) NOT NULL, ADD conversation_id INT NOT NULL, DROP subject, DROP message, DROP priority, DROP updated_at, DROP user_id');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT `FK_1F5A4D539AC0396` FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('CREATE INDEX IDX_1F5A4D539AC0396 ON support_ticket (conversation_id)');
        $this->addSql('ALTER TABLE task CHANGE family_id family_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_assignment ADD refused_at DATETIME DEFAULT NULL, ADD family_id INT NOT NULL, CHANGE assigned_at assigned_at DATETIME NOT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE task_id task_id INT NOT NULL, CHANGE user_id user_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT `FK_2CD60F15C35E566A` FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('CREATE INDEX IDX_2CD60F15C35E566A ON task_assignment (family_id)');
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_USERNAME ON user');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7A76ED395');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7F7A2C2FC');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7A76ED395');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7F7A2C2FC');
        $this->addSql('DROP INDEX idx_1da448a7f7a2c2fc ON user_badges');
        $this->addSql('CREATE INDEX IDX_3A9A2B8F7A9F8BF ON user_badges (badge_id)');
        $this->addSql('DROP INDEX idx_1da448a7a76ed395 ON user_badges');
        $this->addSql('CREATE INDEX IDX_3A9A2B8FA76ED395 ON user_badges (user_id)');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
    }
}
