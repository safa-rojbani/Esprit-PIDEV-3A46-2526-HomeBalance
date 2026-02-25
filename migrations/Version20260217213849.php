<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217213849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE support_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, author_id VARCHAR(36) NOT NULL, INDEX IDX_B883883700047D2 (ticket_id), INDEX IDX_B883883F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_B883883700047D2 FOREIGN KEY (ticket_id) REFERENCES support_ticket (id)');
        $this->addSql('ALTER TABLE support_message ADD CONSTRAINT FK_B883883F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY `FK_1F5A4D539AC0396`');
        $this->addSql('DROP INDEX IDX_1F5A4D539AC0396 ON support_ticket');
        $this->addSql('ALTER TABLE support_ticket ADD message LONGTEXT NOT NULL, ADD priority VARCHAR(255) NOT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD user_id VARCHAR(36) NOT NULL, DROP conversation_id, CHANGE title subject VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_1F5A4D53A76ED395 ON support_ticket (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY FK_B883883700047D2');
        $this->addSql('ALTER TABLE support_message DROP FOREIGN KEY FK_B883883F675F31B');
        $this->addSql('DROP TABLE support_message');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53A76ED395');
        $this->addSql('DROP INDEX IDX_1F5A4D53A76ED395 ON support_ticket');
        $this->addSql('ALTER TABLE support_ticket ADD title VARCHAR(255) NOT NULL, ADD conversation_id INT NOT NULL, DROP subject, DROP message, DROP priority, DROP updated_at, DROP user_id');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT `FK_1F5A4D539AC0396` FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('CREATE INDEX IDX_1F5A4D539AC0396 ON support_ticket (conversation_id)');
    }
}
