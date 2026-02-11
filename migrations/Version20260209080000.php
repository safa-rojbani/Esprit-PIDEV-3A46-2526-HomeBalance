<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align account notification column name, add avatar upload log, and enforce family badge awarded date.';
    }

    private function assertMysqlOrMariaDb(): void
    {
        $platformClass = get_class($this->connection->getDatabasePlatform());

        $this->skipIf(
            !str_contains($platformClass, 'MySQLPlatform') && !str_contains($platformClass, 'MariaDBPlatform'),
            sprintf('Migration skipped: requires MySQL/MariaDB. Current platform: %s', $platformClass)
        );
    }

    public function up(Schema $schema): void
    {
        $this->assertMysqlOrMariaDb();

        $this->addSql('CREATE TABLE avatar_upload_log (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE account_notification CHANGE `key` notification_key VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE account_notification MODIFY user_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
        $this->addSql('ALTER TABLE account_notification ADD CONSTRAINT FK_6E453C1FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE family_badge MODIFY awarded_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->assertMysqlOrMariaDb();

        $this->addSql('DROP TABLE avatar_upload_log');
        $this->addSql('ALTER TABLE account_notification DROP FOREIGN KEY FK_6E453C1FA76ED395');
        $this->addSql('ALTER TABLE account_notification CHANGE notification_key `key` VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE account_notification MODIFY user_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        $this->addSql('ALTER TABLE family_badge MODIFY awarded_at DATETIME DEFAULT NULL');
    }
}
