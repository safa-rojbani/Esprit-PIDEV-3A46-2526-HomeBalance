<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scheduled_at/read_at to rappel and backfill scheduled_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rappel ADD scheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE rappel ADD read_at DATETIME DEFAULT NULL');

        // Backfill scheduled_at for existing reminders
        $this->addSql('
            UPDATE rappel r
            INNER JOIN evenement e ON e.id = r.evenement_id
            SET r.scheduled_at = DATE_SUB(e.date_debut, INTERVAL r.offset_minutes MINUTE)
            WHERE r.scheduled_at IS NULL
        ');

        $this->addSql('ALTER TABLE rappel MODIFY scheduled_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rappel DROP read_at');
        $this->addSql('ALTER TABLE rappel DROP scheduled_at');
    }
}
