<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create saving_goal table for automatic savings plans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE saving_goal (id INT AUTO_INCREMENT NOT NULL, family_id INT NOT NULL, created_by_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, target_amount NUMERIC(12, 2) NOT NULL, current_amount NUMERIC(12, 2) NOT NULL, target_date DATE DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_57AFB78BC35E566A (family_id), INDEX IDX_57AFB78BB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE saving_goal ADD CONSTRAINT FK_57AFB78BC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE saving_goal ADD CONSTRAINT FK_57AFB78BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saving_goal DROP FOREIGN KEY FK_57AFB78BC35E566A');
        $this->addSql('ALTER TABLE saving_goal DROP FOREIGN KEY FK_57AFB78BB03A8386');
        $this->addSql('DROP TABLE saving_goal');
    }
}
