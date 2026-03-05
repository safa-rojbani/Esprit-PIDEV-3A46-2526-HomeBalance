<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220230145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure evenement.image_name exists (legacy compatibility migration).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['evenement'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('evenement');
        if (!array_key_exists('image_name', $columns)) {
            $this->addSql('ALTER TABLE evenement ADD image_name VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['evenement'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('evenement');
        if (array_key_exists('image_name', $columns)) {
            $this->addSql('ALTER TABLE evenement DROP image_name');
        }
    }
}
