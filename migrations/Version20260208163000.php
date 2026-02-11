<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification metadata to user accounts';
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

        $this->addSql('ALTER TABLE user ADD email_verification_token VARCHAR(64) DEFAULT NULL, ADD email_verification_requested_at DATETIME DEFAULT NULL, ADD email_verified_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE user SET email_verified_at = NOW() WHERE email_verified_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->assertMysqlOrMariaDb();

        $this->addSql('ALTER TABLE user DROP email_verification_token, DROP email_verification_requested_at, DROP email_verified_at');
    }
}
