<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208101935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE family_badge (id INT AUTO_INCREMENT NOT NULL, awarded_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', family_id INT NOT NULL, badge_id INT NOT NULL, INDEX IDX_C287DB6EC35E566A (family_id), INDEX IDX_C287DB6EF7A2C2FC (badge_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE family_badge ADD CONSTRAINT FK_C287DB6EC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE family_badge ADD CONSTRAINT FK_C287DB6EF7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id)');

        $this->addSql('ALTER TABLE badge ADD code VARCHAR(64) DEFAULT NULL, ADD scope VARCHAR(32) DEFAULT NULL');
        $this->addSql("UPDATE badge SET code = CONCAT('legacy_', id), scope = 'user' WHERE code IS NULL");
        $this->addSql('ALTER TABLE badge CHANGE code code VARCHAR(64) NOT NULL, CHANGE scope scope VARCHAR(32) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FEF0481D77153098 ON badge (code)');

        $this->addSql("INSERT INTO badge (name, code, scope, description, icon, required_points) VALUES
            ('Most hardworking member', 'hardworking_member', 'user', 'Top scorer inside a family.', 'bx bx-trophy', 0),
            ('Balanced family', 'balanced_family', 'family', 'Household keeps contributions evenly distributed.', 'bx bx-group', 0),
            ('Most hardworking family', 'hardworking_family', 'family', 'Highest scoring family across HomeBalance.', 'bx bx-crown', 0)
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), icon = VALUES(icon), required_points = VALUES(required_points), scope = VALUES(scope)");

        $this->addSql('CREATE UNIQUE INDEX UNIQ_A5E6215BE64D7D01 ON family (join_code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE family_badge DROP FOREIGN KEY FK_C287DB6EC35E566A');
        $this->addSql('ALTER TABLE family_badge DROP FOREIGN KEY FK_C287DB6EF7A2C2FC');
        $this->addSql('DROP TABLE family_badge');
        $this->addSql('DROP INDEX UNIQ_FEF0481D77153098 ON badge');
        $this->addSql('ALTER TABLE badge DROP code, DROP scope');
        $this->addSql('DROP INDEX UNIQ_A5E6215BE64D7D01 ON family');
    }
}
