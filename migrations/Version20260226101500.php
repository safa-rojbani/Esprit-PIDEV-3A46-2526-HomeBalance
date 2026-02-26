<?php

declare(strict_types = 1)
;

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix illegal mix of collations by converting tables to utf8mb4_unicode_ci';
    }

    public function up(Schema $schema): void
    {
        // This migration fixes the "Illegal mix of collations" error by ensuring both tables use the same collation.
        // We convert to utf8mb4_unicode_ci as it is the more modern and accurate collation.

        // Disable foreign key checks to avoid issues during column conversion
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');

        $this->addSql('ALTER TABLE user CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE portal_notification CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE family CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE message CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE conversation CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE conversation_participant CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        // Re-enable foreign key checks
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        // Changing it back might be tricky as we don't know for sure what it was, 
        // but assuming it was general_ci for those who had the error.
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('ALTER TABLE user CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->addSql('ALTER TABLE portal_notification CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
