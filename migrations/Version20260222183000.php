<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222183000 extends AbstractMigration
{
    private const FK_FAMILY = 'FK_126ED7A6C35E566A';
    private const FK_RECIPIENT = 'FK_126ED7A6E92F8F78';
    private const FK_ACTOR = 'FK_126ED7A610DAD8A8';

    public function getDescription(): string
    {
        return 'Add portal notifications table for family document activity';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $userIdCollation = $this->resolveUserIdCollation();
        $userIdCharset = $this->resolveCharsetFromCollation($userIdCollation);

        if (!$schemaManager->tablesExist(['portal_notification'])) {
            $this->addSql(sprintf(
                'CREATE TABLE portal_notification (id INT AUTO_INCREMENT NOT NULL, family_id INT NOT NULL, recipient_id VARCHAR(36) NOT NULL, actor_id VARCHAR(36) NOT NULL, notification_type VARCHAR(64) NOT NULL, payload JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_126ED7A6C35E566A (family_id), INDEX IDX_126ED7A6E92F8F78 (recipient_id), INDEX IDX_126ED7A610DAD8A8 (actor_id), INDEX IDX_126ED7A68CDE5729A89FB6BB (recipient_id, family_id, is_read), PRIMARY KEY(id)) DEFAULT CHARACTER SET %s COLLATE `%s` ENGINE = InnoDB',
                $userIdCharset,
                $userIdCollation
            ));
        } else {
            // Recovery path for partially failed migration runs.
            $this->addSql(sprintf(
                'ALTER TABLE portal_notification CONVERT TO CHARACTER SET %s COLLATE %s',
                $userIdCharset,
                $userIdCollation
            ));
            $this->addSql(sprintf('ALTER TABLE portal_notification MODIFY recipient_id VARCHAR(36) COLLATE %s NOT NULL', $userIdCollation));
            $this->addSql(sprintf('ALTER TABLE portal_notification MODIFY actor_id VARCHAR(36) COLLATE %s NOT NULL', $userIdCollation));
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('portal_notification')
        );

        if (!in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE portal_notification ADD CONSTRAINT FK_126ED7A6C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        }
        if (!in_array(self::FK_RECIPIENT, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE portal_notification ADD CONSTRAINT FK_126ED7A6E92F8F78 FOREIGN KEY (recipient_id) REFERENCES `user` (id)');
        }
        if (!in_array(self::FK_ACTOR, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE portal_notification ADD CONSTRAINT FK_126ED7A610DAD8A8 FOREIGN KEY (actor_id) REFERENCES `user` (id)');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['portal_notification'])) {
            return;
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys('portal_notification')
        );

        if (in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE portal_notification DROP FOREIGN KEY FK_126ED7A6C35E566A');
        }
        if (in_array(self::FK_RECIPIENT, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE portal_notification DROP FOREIGN KEY FK_126ED7A6E92F8F78');
        }
        if (in_array(self::FK_ACTOR, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE portal_notification DROP FOREIGN KEY FK_126ED7A610DAD8A8');
        }

        $this->addSql('DROP TABLE portal_notification');
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
            return 'utf8mb4_general_ci';
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
