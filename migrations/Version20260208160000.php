<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align event schema: nullable type_evenement fields and share_with_family';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE type_evenement MODIFY couleur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE type_evenement MODIFY family_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement ADD share_with_family TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP share_with_family');
        $this->addSql('ALTER TABLE type_evenement MODIFY couleur VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE type_evenement MODIFY family_id INT NOT NULL');
    }
}
