<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 3 Step 1 — Message Reactions
 *
 * Creates the `message_reaction` table with:
 *  - id (PK, auto-increment)
 *  - message_id (FK → message, CASCADE DELETE)
 *  - user_id    (FK → user,    CASCADE DELETE)
 *  - emoji      (varchar 10)
 *  - created_at (datetime immutable)
 *  - unique constraint: (message_id, user_id, emoji)
 */
final class Version20260223010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create message_reaction table for Phase 3 Step 1';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE message_reaction (
                id         INT AUTO_INCREMENT NOT NULL,
                message_id INT NOT NULL,
                user_id    VARCHAR(36) NOT NULL,
                emoji      VARCHAR(10) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_message_reaction_message (message_id),
                INDEX IDX_message_reaction_user    (user_id),
                UNIQUE INDEX uq_reaction_user_message_emoji (message_id, user_id, emoji),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE message_reaction
                ADD CONSTRAINT FK_message_reaction_message
                    FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE,
                ADD CONSTRAINT FK_message_reaction_user
                    FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_reaction DROP FOREIGN KEY FK_message_reaction_message');
        $this->addSql('ALTER TABLE message_reaction DROP FOREIGN KEY FK_message_reaction_user');
        $this->addSql('DROP TABLE message_reaction');
    }
}
