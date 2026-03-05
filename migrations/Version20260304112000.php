<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304112000 extends AbstractMigration
{
    private const SUPPORT_TICKET_TABLE = 'support_ticket';
    private const SUPPORT_MESSAGE_TABLE = 'support_message';
    private const USER_TABLE = 'user';

    private const SUPPORT_TICKET_USER_INDEX = 'IDX_SUPPORT_TICKET_USER';
    private const SUPPORT_TICKET_USER_FK = 'FK_SUPPORT_TICKET_USER';
    private const LEGACY_SUPPORT_TICKET_CONVERSATION_INDEX = 'IDX_1F5A4D539AC0396';
    private const LEGACY_SUPPORT_TICKET_CONVERSATION_FK = 'FK_1F5A4D539AC0396';

    private const SUPPORT_MESSAGE_TICKET_INDEX = 'IDX_SUPPORT_MESSAGE_TICKET';
    private const SUPPORT_MESSAGE_AUTHOR_INDEX = 'IDX_SUPPORT_MESSAGE_AUTHOR';
    private const SUPPORT_MESSAGE_TICKET_FK = 'FK_SUPPORT_MESSAGE_TICKET';
    private const SUPPORT_MESSAGE_AUTHOR_FK = 'FK_SUPPORT_MESSAGE_AUTHOR';

    public function getDescription(): string
    {
        return 'Align support_ticket schema with entity (subject/message/user/priority) and create support_message table';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $userIdCollation = $this->resolveUserIdCollation();
        $userIdCharset = $this->resolveCharsetFromCollation($userIdCollation);

        $this->migrateSupportTicket($schemaManager, $userIdCollation);
        $this->migrateSupportMessage($schemaManager, $userIdCharset, $userIdCollation);
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist([self::SUPPORT_MESSAGE_TABLE])) {
            if ($this->hasForeignKey($schemaManager, self::SUPPORT_MESSAGE_TABLE, self::SUPPORT_MESSAGE_TICKET_FK)) {
                $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY ' . self::SUPPORT_MESSAGE_TICKET_FK);
            }
            if ($this->hasForeignKey($schemaManager, self::SUPPORT_MESSAGE_TABLE, self::SUPPORT_MESSAGE_AUTHOR_FK)) {
                $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY ' . self::SUPPORT_MESSAGE_AUTHOR_FK);
            }
            $this->addSql('DROP TABLE support_message');
        }

        if (!$schemaManager->tablesExist([self::SUPPORT_TICKET_TABLE])) {
            return;
        }

        if ($this->hasForeignKey($schemaManager, self::SUPPORT_TICKET_TABLE, self::SUPPORT_TICKET_USER_FK)) {
            $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY ' . self::SUPPORT_TICKET_USER_FK);
        }
        if ($this->hasIndex($schemaManager, self::SUPPORT_TICKET_TABLE, self::SUPPORT_TICKET_USER_INDEX)) {
            $this->addSql('DROP INDEX ' . self::SUPPORT_TICKET_USER_INDEX . ' ON support_ticket');
        }
        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'user_id')) {
            $this->addSql('ALTER TABLE support_ticket DROP COLUMN user_id');
        }

        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'updated_at')) {
            $this->addSql('ALTER TABLE support_ticket DROP COLUMN updated_at');
        }
        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'priority')) {
            $this->addSql('ALTER TABLE support_ticket DROP COLUMN priority');
        }
        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'message')) {
            $this->addSql('ALTER TABLE support_ticket DROP COLUMN message');
        }

        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'subject')
            && !$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'title')) {
            $this->addSql('ALTER TABLE support_ticket CHANGE subject title VARCHAR(255) NOT NULL');
        }

        if (!$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'conversation_id')) {
            $this->addSql('ALTER TABLE support_ticket ADD conversation_id INT DEFAULT NULL');
        }
        if (!$this->hasIndex($schemaManager, self::SUPPORT_TICKET_TABLE, self::LEGACY_SUPPORT_TICKET_CONVERSATION_INDEX)) {
            $this->addSql('CREATE INDEX ' . self::LEGACY_SUPPORT_TICKET_CONVERSATION_INDEX . ' ON support_ticket (conversation_id)');
        }
        if (!$this->hasForeignKey($schemaManager, self::SUPPORT_TICKET_TABLE, self::LEGACY_SUPPORT_TICKET_CONVERSATION_FK)) {
            $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT ' . self::LEGACY_SUPPORT_TICKET_CONVERSATION_FK . ' FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        }
    }

    private function migrateSupportTicket(object $schemaManager, string $userIdCollation): void
    {
        if (!$schemaManager->tablesExist([self::SUPPORT_TICKET_TABLE])) {
            $this->addSql(sprintf(
                'CREATE TABLE support_ticket (id INT AUTO_INCREMENT NOT NULL, user_id VARCHAR(36) COLLATE %s NOT NULL, subject VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, priority VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX %s (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET %s COLLATE `%s` ENGINE = InnoDB',
                $userIdCollation,
                self::SUPPORT_TICKET_USER_INDEX,
                $this->resolveCharsetFromCollation($userIdCollation),
                $userIdCollation
            ));
            $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT ' . self::SUPPORT_TICKET_USER_FK . ' FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
            return;
        }

        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'title')
            && !$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'subject')) {
            $this->addSql('ALTER TABLE support_ticket CHANGE title subject VARCHAR(255) NOT NULL');
        } elseif (!$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'subject')) {
            $this->addSql('ALTER TABLE support_ticket ADD subject VARCHAR(255) NOT NULL');
        }

        if (!$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'message')) {
            $this->addSql('ALTER TABLE support_ticket ADD message LONGTEXT NOT NULL');
        }

        if (!$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'priority')) {
            $this->addSql("ALTER TABLE support_ticket ADD priority VARCHAR(255) NOT NULL DEFAULT 'medium'");
        }

        if (!$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'updated_at')) {
            $this->addSql("ALTER TABLE support_ticket ADD updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }

        if (!$this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'user_id')) {
            $this->addSql(sprintf('ALTER TABLE support_ticket ADD user_id VARCHAR(36) COLLATE %s DEFAULT NULL', $userIdCollation));
        }

        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'created_at')) {
            $this->addSql("ALTER TABLE support_ticket MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
        }

        $ticketCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM support_ticket');
        if ($ticketCount === 0) {
            $this->addSql('ALTER TABLE support_ticket MODIFY user_id VARCHAR(36) COLLATE ' . $userIdCollation . ' NOT NULL');
        }

        if ($this->hasForeignKey($schemaManager, self::SUPPORT_TICKET_TABLE, self::LEGACY_SUPPORT_TICKET_CONVERSATION_FK)) {
            $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY ' . self::LEGACY_SUPPORT_TICKET_CONVERSATION_FK);
        }
        if ($this->hasIndex($schemaManager, self::SUPPORT_TICKET_TABLE, self::LEGACY_SUPPORT_TICKET_CONVERSATION_INDEX)) {
            $this->addSql('DROP INDEX ' . self::LEGACY_SUPPORT_TICKET_CONVERSATION_INDEX . ' ON support_ticket');
        }
        if ($this->hasColumn($schemaManager, self::SUPPORT_TICKET_TABLE, 'conversation_id')) {
            $this->addSql('ALTER TABLE support_ticket DROP COLUMN conversation_id');
        }

        if (!$this->hasIndex($schemaManager, self::SUPPORT_TICKET_TABLE, self::SUPPORT_TICKET_USER_INDEX)) {
            $this->addSql('CREATE INDEX ' . self::SUPPORT_TICKET_USER_INDEX . ' ON support_ticket (user_id)');
        }
        if (!$this->hasForeignKey($schemaManager, self::SUPPORT_TICKET_TABLE, self::SUPPORT_TICKET_USER_FK)) {
            $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT ' . self::SUPPORT_TICKET_USER_FK . ' FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }
    }

    private function migrateSupportMessage(object $schemaManager, string $userIdCharset, string $userIdCollation): void
    {
        if (!$schemaManager->tablesExist([self::SUPPORT_MESSAGE_TABLE])) {
            $this->addSql(sprintf(
                'CREATE TABLE support_message (id INT AUTO_INCREMENT NOT NULL, ticket_id INT NOT NULL, author_id VARCHAR(36) COLLATE %s NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX %s (ticket_id), INDEX %s (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET %s COLLATE `%s` ENGINE = InnoDB',
                $userIdCollation,
                self::SUPPORT_MESSAGE_TICKET_INDEX,
                self::SUPPORT_MESSAGE_AUTHOR_INDEX,
                $userIdCharset,
                $userIdCollation
            ));

            $this->addSql('ALTER TABLE support_message ADD CONSTRAINT ' . self::SUPPORT_MESSAGE_TICKET_FK . ' FOREIGN KEY (ticket_id) REFERENCES support_ticket (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE support_message ADD CONSTRAINT ' . self::SUPPORT_MESSAGE_AUTHOR_FK . ' FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
            return;
        }

        if (!$this->hasIndex($schemaManager, self::SUPPORT_MESSAGE_TABLE, self::SUPPORT_MESSAGE_TICKET_INDEX)) {
            $this->addSql('CREATE INDEX ' . self::SUPPORT_MESSAGE_TICKET_INDEX . ' ON support_message (ticket_id)');
        }
        if (!$this->hasIndex($schemaManager, self::SUPPORT_MESSAGE_TABLE, self::SUPPORT_MESSAGE_AUTHOR_INDEX)) {
            $this->addSql('CREATE INDEX ' . self::SUPPORT_MESSAGE_AUTHOR_INDEX . ' ON support_message (author_id)');
        }

        if (!$this->hasForeignKey($schemaManager, self::SUPPORT_MESSAGE_TABLE, self::SUPPORT_MESSAGE_TICKET_FK)) {
            $this->addSql('ALTER TABLE support_message ADD CONSTRAINT ' . self::SUPPORT_MESSAGE_TICKET_FK . ' FOREIGN KEY (ticket_id) REFERENCES support_ticket (id) ON DELETE CASCADE');
        }
        if (!$this->hasForeignKey($schemaManager, self::SUPPORT_MESSAGE_TABLE, self::SUPPORT_MESSAGE_AUTHOR_FK)) {
            $this->addSql('ALTER TABLE support_message ADD CONSTRAINT ' . self::SUPPORT_MESSAGE_AUTHOR_FK . ' FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }
    }

    private function hasColumn(object $schemaManager, string $table, string $column): bool
    {
        $columns = array_map(
            static fn ($c) => $c->getName(),
            $schemaManager->listTableColumns($table)
        );

        return in_array($column, $columns, true);
    }

    private function hasIndex(object $schemaManager, string $table, string $index): bool
    {
        $indexes = array_map(
            static fn ($i) => $i->getName(),
            $schemaManager->listTableIndexes($table)
        );

        return in_array($index, $indexes, true);
    }

    private function hasForeignKey(object $schemaManager, string $table, string $foreignKey): bool
    {
        $foreignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys($table)
        );

        return in_array($foreignKey, $foreignKeys, true);
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
