<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role_change_request table for admin approval workflow.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE role_change_request (id INT AUTO_INCREMENT NOT NULL, user_id VARCHAR(36) NOT NULL, requested_by_id VARCHAR(36) NOT NULL, reviewed_by_id VARCHAR(36) DEFAULT NULL, requested_role VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8AFA7013A76ED395 (user_id), INDEX IDX_8AFA7013A96DA8E5 (requested_by_id), INDEX IDX_8AFA7013470DF7C0 (reviewed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE role_change_request ADD CONSTRAINT FK_8AFA7013A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE role_change_request ADD CONSTRAINT FK_8AFA7013A96DA8E5 FOREIGN KEY (requested_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE role_change_request ADD CONSTRAINT FK_8AFA7013470DF7C0 FOREIGN KEY (reviewed_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role_change_request DROP FOREIGN KEY FK_8AFA7013A76ED395');
        $this->addSql('ALTER TABLE role_change_request DROP FOREIGN KEY FK_8AFA7013A96DA8E5');
        $this->addSql('ALTER TABLE role_change_request DROP FOREIGN KEY FK_8AFA7013470DF7C0');
        $this->addSql('DROP TABLE role_change_request');
    }
}
