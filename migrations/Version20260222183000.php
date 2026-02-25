<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add portal notifications table for family document activity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_notification (id INT AUTO_INCREMENT NOT NULL, family_id INT NOT NULL, recipient_id VARCHAR(36) NOT NULL, actor_id VARCHAR(36) NOT NULL, notification_type VARCHAR(64) NOT NULL, payload JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_126ED7A6C35E566A (family_id), INDEX IDX_126ED7A6E92F8F78 (recipient_id), INDEX IDX_126ED7A610DAD8A8 (actor_id), INDEX IDX_126ED7A68CDE5729A89FB6BB (recipient_id, family_id, is_read), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE portal_notification ADD CONSTRAINT FK_126ED7A6C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE portal_notification ADD CONSTRAINT FK_126ED7A6E92F8F78 FOREIGN KEY (recipient_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE portal_notification ADD CONSTRAINT FK_126ED7A610DAD8A8 FOREIGN KEY (actor_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_notification DROP FOREIGN KEY FK_126ED7A6C35E566A');
        $this->addSql('ALTER TABLE portal_notification DROP FOREIGN KEY FK_126ED7A6E92F8F78');
        $this->addSql('ALTER TABLE portal_notification DROP FOREIGN KEY FK_126ED7A610DAD8A8');
        $this->addSql('DROP TABLE portal_notification');
    }
}
