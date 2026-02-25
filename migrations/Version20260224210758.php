<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224210758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invitation_rsvp (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, statut VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, repondu_at DATETIME DEFAULT NULL, evenement_id INT NOT NULL, invitee_id VARCHAR(36) NOT NULL, invited_by_id VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_52A4021A5F37A13B (token), INDEX IDX_52A4021AFD02F13 (evenement_id), INDEX IDX_52A4021A7A512022 (invitee_id), INDEX IDX_52A4021AA7B4A7E3 (invited_by_id), UNIQUE INDEX UNIQ_52A4021AFD02F137A512022 (evenement_id, invitee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invitation_rsvp ADD CONSTRAINT FK_52A4021AFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation_rsvp ADD CONSTRAINT FK_52A4021A7A512022 FOREIGN KEY (invitee_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation_rsvp ADD CONSTRAINT FK_52A4021AA7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_rsvp DROP FOREIGN KEY FK_52A4021AFD02F13');
        $this->addSql('ALTER TABLE invitation_rsvp DROP FOREIGN KEY FK_52A4021A7A512022');
        $this->addSql('ALTER TABLE invitation_rsvp DROP FOREIGN KEY FK_52A4021AA7B4A7E3');
        $this->addSql('DROP TABLE invitation_rsvp');
    }
}
