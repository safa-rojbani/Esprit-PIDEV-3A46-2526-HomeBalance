<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226033238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_assignment ADD refused_at DATETIME DEFAULT NULL, ADD family_id INT NOT NULL, CHANGE assigned_at assigned_at DATETIME NOT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE task_id task_id INT NOT NULL, CHANGE user_id user_id VARCHAR(36) NOT NULL, CHANGE penalty_applied_at penalty_applied_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F15C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('CREATE INDEX IDX_2CD60F15C35E566A ON task_assignment (family_id)');
        $this->addSql('ALTER TABLE task_completion ADD validated_at DATETIME DEFAULT NULL, ADD parent_comment LONGTEXT DEFAULT NULL, CHANGE is_validated is_validated TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE type_evenement CHANGE couleur couleur VARCHAR(255) DEFAULT NULL, CHANGE family_id family_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP phone_number');
        $this->addSql('ALTER TABLE user_activity_pattern DROP INDEX UNIQ_DEFBC8B3A76ED395, ADD INDEX IDX_DEFBC8B3A76ED395 (user_id)');
        $this->addSql('ALTER TABLE user_activity_pattern DROP FOREIGN KEY `FK_DEFBC8B3A76ED395`');
        $this->addSql('ALTER TABLE user_activity_pattern CHANGE peak_hours peak_hours JSON NOT NULL, CHANGE last_calculated_at last_calculated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_activity_pattern ADD CONSTRAINT FK_DEFBC8B3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F15C35E566A');
        $this->addSql('DROP INDEX IDX_2CD60F15C35E566A ON task_assignment');
        $this->addSql('ALTER TABLE task_assignment DROP refused_at, DROP family_id, CHANGE assigned_at assigned_at DATETIME DEFAULT NULL, CHANGE penalty_applied_at penalty_applied_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE status status VARCHAR(255) DEFAULT NULL, CHANGE task_id task_id INT DEFAULT NULL, CHANGE user_id user_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE task_completion DROP validated_at, DROP parent_comment, CHANGE is_validated is_validated TINYINT NOT NULL');
        $this->addSql('ALTER TABLE type_evenement CHANGE couleur couleur VARCHAR(255) NOT NULL, CHANGE family_id family_id INT NOT NULL');
        $this->addSql('ALTER TABLE user ADD phone_number VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_activity_pattern DROP INDEX IDX_DEFBC8B3A76ED395, ADD UNIQUE INDEX UNIQ_DEFBC8B3A76ED395 (user_id)');
        $this->addSql('ALTER TABLE user_activity_pattern DROP FOREIGN KEY FK_DEFBC8B3A76ED395');
        $this->addSql('ALTER TABLE user_activity_pattern CHANGE peak_hours peak_hours JSON DEFAULT NULL, CHANGE last_calculated_at last_calculated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_activity_pattern ADD CONSTRAINT `FK_DEFBC8B3A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}
