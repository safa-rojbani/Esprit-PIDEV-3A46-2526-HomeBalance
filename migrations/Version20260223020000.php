<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 3 Step 2 — Message Threading / Replies
 *
 * Adds `parent_message_id` (nullable FK → message, SET NULL on delete)
 * to the `message` table.
 */
final class Version20260223020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_message_id to message table for threading/replies';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE message
                ADD COLUMN parent_message_id INT DEFAULT NULL,
                ADD INDEX IDX_message_parent (parent_message_id),
                ADD CONSTRAINT FK_message_parent
                    FOREIGN KEY (parent_message_id) REFERENCES message (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_message_parent');
        $this->addSql('ALTER TABLE message DROP INDEX IDX_message_parent');
        $this->addSql('ALTER TABLE message DROP COLUMN parent_message_id');
    }
}
