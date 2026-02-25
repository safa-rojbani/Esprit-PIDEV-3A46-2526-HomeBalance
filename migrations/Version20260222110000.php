<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222110000 extends AbstractMigration
{
    private const FK_USER = 'FK_8AFA7013A76ED395';
    private const FK_REQUESTED_BY = 'FK_8AFA7013A96DA8E5';
    private const FK_REVIEWED_BY = 'FK_8AFA7013470DF7C0';

    public function getDescription(): string
    {
        return 'Add role_change_request table for admin approval workflow.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $userIdCollation = $this->resolveUserIdCollation();
        $userIdCharset = $this->resolveCharsetFromCollation($userIdCollation);

        if (!$schemaManager->tablesExist(['role_change_request'])) {
            $this->addSql(
                sprintf(
                    'CREATE TABLE role_change_request (
                    id INT AUTO_INCREMENT NOT NULL,
                    user_id VARCHAR(36) NOT NULL,
                    requested_by_id VARCHAR(36) NOT NULL,
                    reviewed_by_id VARCHAR(36) DEFAULT NULL,
                    requested_role VARCHAR(32) NOT NULL,
                    status VARCHAR(32) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    INDEX IDX_8AFA7013A76ED395 (user_id),
                    INDEX IDX_8AFA7013A96DA8E5 (requested_by_id),
                    INDEX IDX_8AFA7013470DF7C0 (reviewed_by_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET %s COLLATE `%s` ENGINE = InnoDB',
                    $userIdCharset,
                    $userIdCollation
                )
            );
        } else {
            // Recovery path for partially applied migration: align charset/collation with user.id.
            $this->addSql(sprintf(
                'ALTER TABLE role_change_request CONVERT TO CHARACTER SET %s COLLATE %s',
                $userIdCharset,
                $userIdCollation
            ));
            $this->addSql(sprintf('ALTER TABLE role_change_request MODIFY user_id VARCHAR(36) COLLATE %s NOT NULL', $userIdCollation));
            $this->addSql(sprintf('ALTER TABLE role_change_request MODIFY requested_by_id VARCHAR(36) COLLATE %s NOT NULL', $userIdCollation));
            $this->addSql(sprintf('ALTER TABLE role_change_request MODIFY reviewed_by_id VARCHAR(36) COLLATE %s DEFAULT NULL', $userIdCollation));
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('role_change_request')
        );

        if (!in_array(self::FK_USER, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE role_change_request ADD CONSTRAINT FK_8AFA7013A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        }
        if (!in_array(self::FK_REQUESTED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE role_change_request ADD CONSTRAINT FK_8AFA7013A96DA8E5 FOREIGN KEY (requested_by_id) REFERENCES `user` (id)');
        }
        if (!in_array(self::FK_REVIEWED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE role_change_request ADD CONSTRAINT FK_8AFA7013470DF7C0 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id)');
        }
    }

    private function resolveUserIdCollation(): string
    {
        $collation = $this->connection->fetchOne(
            "SELECT COLLATION_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'user'
               AND COLUMN_NAME = 'id'
             LIMIT 1"
        );

        if (!is_string($collation) || trim($collation) === '') {
            return 'utf8mb4_unicode_ci';
        }

        return trim($collation);
    }

    private function resolveCharsetFromCollation(string $collation): string
    {
        $charset = strstr($collation, '_', true);
        if (!is_string($charset) || $charset === '') {
            return 'utf8mb4';
        }

        return $charset;
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['role_change_request'])) {
            return;
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys('role_change_request')
        );

        if (in_array(self::FK_USER, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE role_change_request DROP FOREIGN KEY FK_8AFA7013A76ED395');
        }
        if (in_array(self::FK_REQUESTED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE role_change_request DROP FOREIGN KEY FK_8AFA7013A96DA8E5');
        }
        if (in_array(self::FK_REVIEWED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE role_change_request DROP FOREIGN KEY FK_8AFA7013470DF7C0');
        }

        $this->addSql('DROP TABLE role_change_request');
    }
}
