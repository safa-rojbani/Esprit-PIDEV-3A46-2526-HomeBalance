<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211180714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$table]);
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $columns = $this->connection->createSchemaManager()->listTableColumns($table);

        return array_key_exists($column, $columns);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $indexes = $this->connection->createSchemaManager()->listTableIndexes($table);

        if (array_key_exists($indexName, $indexes)) {
            return true;
        }

        foreach ($indexes as $index) {
            if (strcasecmp($index->getName(), $indexName) === 0) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $fkName): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        foreach ($this->connection->createSchemaManager()->listTableForeignKeys($table) as $foreignKey) {
            if ($foreignKey->getName() === $fkName) {
                return true;
            }
        }

        return false;
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if (!$this->tableExists('default_gallery')) {
            $this->addSql('CREATE TABLE default_gallery (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        }
        $this->addSql('ALTER TABLE categorie_achat CHANGE family_id family_id INT DEFAULT NULL');
        if (!$this->columnExists('gallery', 'default_gallery_id')) {
            $this->addSql('ALTER TABLE gallery ADD default_gallery_id INT DEFAULT NULL');
        }
        if (!$this->foreignKeyExists('gallery', 'FK_472B783A4CC4FBEC')) {
            $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783A4CC4FBEC FOREIGN KEY (default_gallery_id) REFERENCES default_gallery (id) ON DELETE SET NULL');
        }
        if (!$this->indexExists('gallery', 'IDX_472B783A4CC4FBEC')) {
            $this->addSql('CREATE INDEX IDX_472B783A4CC4FBEC ON gallery (default_gallery_id)');
        }
        if ($this->foreignKeyExists('historique_achat', 'FK_68295E25FE95D117')) {
            $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY `FK_68295E25FE95D117`');
        }
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT FK_68295E25FE95D117 FOREIGN KEY (achat_id) REFERENCES achat (id) ON DELETE CASCADE');
        if (!$this->columnExists('rappel', 'scheduled_at')) {
            $this->addSql('ALTER TABLE rappel ADD scheduled_at DATETIME NOT NULL');
        }
        if (!$this->columnExists('rappel', 'read_at')) {
            $this->addSql('ALTER TABLE rappel ADD read_at DATETIME DEFAULT NULL');
        }
        $this->addSql('ALTER TABLE task CHANGE family_id family_id INT DEFAULT NULL');
        if ($this->foreignKeyExists('task_assignment', 'FK_2CD60F158DB60186')) {
            $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F158DB60186');
        }
        if ($this->foreignKeyExists('task_assignment', 'FK_2CD60F15A76ED395')) {
            $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F15A76ED395');
        }
        $this->addSql('ALTER TABLE task_assignment ADD refused_at DATETIME DEFAULT NULL, ADD family_id INT NOT NULL, CHANGE assigned_at assigned_at DATETIME NOT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE task_id task_id INT NOT NULL, CHANGE user_id user_id VARCHAR(36) NOT NULL');
        if (!$this->foreignKeyExists('task_assignment', 'FK_2CD60F158DB60186')) {
            $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F158DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        }
        if (!$this->foreignKeyExists('task_assignment', 'FK_2CD60F15A76ED395')) {
            $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F15A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        }
        if (!$this->foreignKeyExists('task_assignment', 'FK_2CD60F15C35E566A')) {
            $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F15C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        }
        if (!$this->indexExists('task_assignment', 'IDX_2CD60F15C35E566A')) {
            $this->addSql('CREATE INDEX IDX_2CD60F15C35E566A ON task_assignment (family_id)');
        }
        $this->addSql('ALTER TABLE task_completion CHANGE validated_at validated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        if ($this->tableExists('default_gallery')) {
            $this->addSql('DROP TABLE default_gallery');
        }
        $this->addSql('ALTER TABLE categorie_achat CHANGE family_id family_id INT NOT NULL');
        if ($this->foreignKeyExists('gallery', 'FK_472B783A4CC4FBEC')) {
            $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783A4CC4FBEC');
        }
        if ($this->indexExists('gallery', 'IDX_472B783A4CC4FBEC')) {
            $this->addSql('DROP INDEX IDX_472B783A4CC4FBEC ON gallery');
        }
        if ($this->columnExists('gallery', 'default_gallery_id')) {
            $this->addSql('ALTER TABLE gallery DROP default_gallery_id');
        }
        if ($this->foreignKeyExists('historique_achat', 'FK_68295E25FE95D117')) {
            $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY FK_68295E25FE95D117');
        }
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT `FK_68295E25FE95D117` FOREIGN KEY (achat_id) REFERENCES achat (id)');
        if ($this->columnExists('rappel', 'scheduled_at')) {
            $this->addSql('ALTER TABLE rappel DROP scheduled_at');
        }
        if ($this->columnExists('rappel', 'read_at')) {
            $this->addSql('ALTER TABLE rappel DROP read_at');
        }
        $this->addSql('ALTER TABLE task CHANGE family_id family_id INT NOT NULL');
        if ($this->foreignKeyExists('task_assignment', 'FK_2CD60F158DB60186')) {
            $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F158DB60186');
        }
        if ($this->foreignKeyExists('task_assignment', 'FK_2CD60F15A76ED395')) {
            $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F15A76ED395');
        }
        if ($this->foreignKeyExists('task_assignment', 'FK_2CD60F15C35E566A')) {
            $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F15C35E566A');
        }
        if ($this->indexExists('task_assignment', 'IDX_2CD60F15C35E566A')) {
            $this->addSql('DROP INDEX IDX_2CD60F15C35E566A ON task_assignment');
        }
        $this->addSql('ALTER TABLE task_assignment DROP refused_at, DROP family_id, CHANGE assigned_at assigned_at DATETIME DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE task_id task_id INT DEFAULT NULL, CHANGE user_id user_id VARCHAR(36) DEFAULT NULL');
        if (!$this->foreignKeyExists('task_assignment', 'FK_2CD60F158DB60186')) {
            $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F158DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        }
        if (!$this->foreignKeyExists('task_assignment', 'FK_2CD60F15A76ED395')) {
            $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F15A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        }
        $this->addSql('ALTER TABLE task_completion CHANGE validated_at validated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
