<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add secure document share table with expiring tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document_share (id INT AUTO_INCREMENT NOT NULL, document_id INT NOT NULL, family_id INT NOT NULL, shared_by_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, recipient_email VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_4CA4F446A1D7E7E76C8A81A9 (token_hash), INDEX IDX_4CA4F446A1D7E7E7 (document_id), INDEX IDX_4CA4F446C35E566A (family_id), INDEX IDX_4CA4F4462D520A8A (shared_by_id), INDEX IDX_4CA4F446C35E566A8387D5A6 (family_id, expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_4CA4F446A1D7E7E7 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_4CA4F446C35E566A FOREIGN KEY (family_id) REFERENCES family (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_4CA4F4462D520A8A FOREIGN KEY (shared_by_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_4CA4F446A1D7E7E7');
        $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_4CA4F446C35E566A');
        $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_4CA4F4462D520A8A');
        $this->addSql('DROP TABLE document_share');
    }
}
