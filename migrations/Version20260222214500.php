<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222214500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create type_revenu table for admin-managed revenue types';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE type_revenu (id INT AUTO_INCREMENT NOT NULL, family_id INT DEFAULT NULL, nom_type VARCHAR(255) NOT NULL, INDEX IDX_25156ABBC35E566A (family_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE type_revenu ADD CONSTRAINT FK_25156ABBC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE type_revenu DROP FOREIGN KEY FK_25156ABBC35E566A');
        $this->addSql('DROP TABLE type_revenu');
    }
}
