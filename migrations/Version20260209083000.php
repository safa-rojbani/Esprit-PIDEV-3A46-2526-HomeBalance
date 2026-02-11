<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209083000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize account_notification collation and index to match ORM expectations.';
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

        $this->addSql('ALTER TABLE account_notification DROP FOREIGN KEY FK_6E453C1FA76ED395');
        $this->addSql('ALTER TABLE account_notification CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->addSql('ALTER TABLE account_notification CHANGE user_id user_id VARCHAR(36) NOT NULL');
        $this->addSql('DROP INDEX IDX_6E453C1FA76ED395 ON account_notification');
        $this->addSql('CREATE INDEX IDX_268C608CA76ED395 ON account_notification (user_id)');
        $this->addSql('ALTER TABLE account_notification ADD CONSTRAINT FK_6E453C1FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->assertMysqlOrMariaDb();

        $this->addSql('ALTER TABLE account_notification DROP FOREIGN KEY FK_6E453C1FA76ED395');
        $this->addSql('ALTER TABLE account_notification CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE account_notification CHANGE user_id user_id VARCHAR(36) NOT NULL');
        $this->addSql('DROP INDEX IDX_268C608CA76ED395 ON account_notification');
        $this->addSql('CREATE INDEX IDX_6E453C1FA76ED395 ON account_notification (user_id)');
        $this->addSql('ALTER TABLE account_notification ADD CONSTRAINT FK_6E453C1FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }
}
