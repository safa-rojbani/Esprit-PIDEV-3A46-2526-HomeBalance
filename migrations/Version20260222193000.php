<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add penalty tracking columns on task_assignment.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE task_assignment ADD penalty_applied_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD penalty_points INT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_assignment DROP penalty_applied_at, DROP penalty_points');
    }
}
