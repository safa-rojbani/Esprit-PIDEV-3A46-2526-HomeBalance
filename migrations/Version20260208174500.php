<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208174500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account notification delivery queue table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS account_notification (id INT AUTO_INCREMENT NOT NULL, user_id VARCHAR(36) NOT NULL, `key` VARCHAR(64) NOT NULL, channel VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, payload JSON DEFAULT NULL, attempts INT NOT NULL, last_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, INDEX IDX_6E453C1FA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_notification DROP FOREIGN KEY FK_6E453C1FA76ED395');
        $this->addSql('DROP TABLE account_notification');
    }
}
