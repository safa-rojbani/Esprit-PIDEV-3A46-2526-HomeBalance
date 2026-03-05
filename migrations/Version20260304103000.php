<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304103000 extends AbstractMigration
{
    private const TABLE = 'message';
    private const COLUMN = 'parent_message_id';
    private const INDEX = 'IDX_MESSAGE_PARENT_MESSAGE';
    private const FK = 'FK_MESSAGE_PARENT_MESSAGE';

    public function getDescription(): string
    {
        return 'Add self-referencing parent_message_id to message table for reply threads';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return;
        }

        $columns = array_map(
            static fn ($column) => $column->getName(),
            $schemaManager->listTableColumns(self::TABLE)
        );

        if (!in_array(self::COLUMN, $columns, true)) {
            $this->addSql(sprintf('ALTER TABLE %s ADD %s INT DEFAULT NULL', self::TABLE, self::COLUMN));
        }

        $indexes = array_map(
            static fn ($index) => $index->getName(),
            $schemaManager->listTableIndexes(self::TABLE)
        );

        if (!in_array(self::INDEX, $indexes, true)) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', self::INDEX, self::TABLE, self::COLUMN));
        }

        $foreignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys(self::TABLE)
        );

        if (!in_array(self::FK, $foreignKeys, true)) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (id) ON DELETE SET NULL',
                self::TABLE,
                self::FK,
                self::COLUMN,
                self::TABLE
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return;
        }

        $foreignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys(self::TABLE)
        );

        if (in_array(self::FK, $foreignKeys, true)) {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', self::TABLE, self::FK));
        }

        $indexes = array_map(
            static fn ($index) => $index->getName(),
            $schemaManager->listTableIndexes(self::TABLE)
        );

        if (in_array(self::INDEX, $indexes, true)) {
            $this->addSql(sprintf('DROP INDEX %s ON %s', self::INDEX, self::TABLE));
        }

        $columns = array_map(
            static fn ($column) => $column->getName(),
            $schemaManager->listTableColumns(self::TABLE)
        );

        if (in_array(self::COLUMN, $columns, true)) {
            $this->addSql(sprintf('ALTER TABLE %s DROP COLUMN %s', self::TABLE, self::COLUMN));
        }
    }
}
