<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207171928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE family_memberships (id VARCHAR(36) NOT NULL, role VARCHAR(32) NOT NULL, joined_at DATETIME NOT NULL, left_at DATETIME DEFAULT NULL, family_id INT NOT NULL, user_id VARCHAR(36) NOT NULL, INDEX IDX_46D3E108C35E566A (family_id), INDEX IDX_46D3E108A76ED395 (user_id), UNIQUE INDEX UNIQ_46D3E108C35E566AA76ED395 (family_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_badges (user_id VARCHAR(36) NOT NULL, badge_id INT NOT NULL, INDEX IDX_1DA448A7A76ED395 (user_id), INDEX IDX_1DA448A7F7A2C2FC (badge_id), PRIMARY KEY (user_id, badge_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE family_memberships ADD CONSTRAINT FK_46D3E108C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE family_memberships ADD CONSTRAINT FK_46D3E108A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT FK_1DA448A7F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE audit_trail ADD ip_address VARCHAR(64) DEFAULT NULL, ADD channel VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE family_memberships DROP FOREIGN KEY FK_46D3E108C35E566A');
        $this->addSql('ALTER TABLE family_memberships DROP FOREIGN KEY FK_46D3E108A76ED395');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7A76ED395');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY FK_1DA448A7F7A2C2FC');
        $this->addSql('DROP TABLE family_memberships');
        $this->addSql('DROP TABLE user_badges');
        $this->addSql('ALTER TABLE audit_trail DROP ip_address, DROP channel');
    }
}
