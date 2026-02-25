<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223110000 extends AbstractMigration
{
    private const FK_FAMILY = 'FK_57AFB78BC35E566A';
    private const FK_CREATED_BY = 'FK_57AFB78BB03A8386';

    public function getDescription(): string
    {
        return 'Create saving_goal table for automatic savings plans';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $userIdCollation = $this->resolveUserIdCollation();
        $userIdCharset = $this->resolveCharsetFromCollation($userIdCollation);

        if (!$schemaManager->tablesExist(['saving_goal'])) {
            $this->addSql(sprintf(
                'CREATE TABLE saving_goal (
                    id INT AUTO_INCREMENT NOT NULL,
                    family_id INT NOT NULL,
                    created_by_id VARCHAR(36) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    target_amount NUMERIC(12, 2) NOT NULL,
                    current_amount NUMERIC(12, 2) NOT NULL,
                    target_date DATE DEFAULT NULL,
                    created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                    INDEX IDX_57AFB78BC35E566A (family_id),
                    INDEX IDX_57AFB78BB03A8386 (created_by_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET %s COLLATE `%s` ENGINE = InnoDB',
                $userIdCharset,
                $userIdCollation
            ));
        } else {
            // Recovery path for partially applied migrations.
            $this->addSql(sprintf(
                'ALTER TABLE saving_goal CONVERT TO CHARACTER SET %s COLLATE %s',
                $userIdCharset,
                $userIdCollation
            ));
            $this->addSql(sprintf(
                'ALTER TABLE saving_goal MODIFY created_by_id VARCHAR(36) COLLATE %s NOT NULL',
                $userIdCollation
            ));
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('saving_goal')
        );

        if (!in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE saving_goal ADD CONSTRAINT FK_57AFB78BC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        }
        if (!in_array(self::FK_CREATED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE saving_goal ADD CONSTRAINT FK_57AFB78BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['saving_goal'])) {
            return;
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys('saving_goal')
        );

        if (in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE saving_goal DROP FOREIGN KEY FK_57AFB78BC35E566A');
        }
        if (in_array(self::FK_CREATED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE saving_goal DROP FOREIGN KEY FK_57AFB78BB03A8386');
        }
        $this->addSql('DROP TABLE saving_goal');
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
}
