<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add evenement_image table and drop evenement.image_name for multi-image support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE evenement_image (id INT AUTO_INCREMENT NOT NULL, evenement_id INT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9184A2A9FD02F13 (evenement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE evenement_image ADD CONSTRAINT FK_9184A2A9FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement DROP image_name');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement_image DROP FOREIGN KEY FK_9184A2A9FD02F13');
        $this->addSql('DROP TABLE evenement_image');
        $this->addSql('ALTER TABLE evenement ADD image_name VARCHAR(255) DEFAULT NULL');
    }
}
