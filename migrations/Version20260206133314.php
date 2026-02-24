<?php

declare(strict_types=1);

namespace DoctrineMigrations;


use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206133314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }
    private function assertMysqlOrMariaDb(): void
    {
    $platformClass = get_class($this->connection->getDatabasePlatform());

    $this->skipIf(
        !str_contains($platformClass, 'MySQLPlatform') && !str_contains($platformClass, 'MariaDBPlatform'),
        sprintf('Migration skipped: requires MySQL/MariaDB. Current platform: %s', $platformClass)
        );
    }

    public function up(Schema $schema): void
    {
        $this->assertMysqlOrMariaDb();
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE achat (id INT AUTO_INCREMENT NOT NULL, nom_article VARCHAR(255) NOT NULL, est_achete TINYINT NOT NULL, created_at DATETIME NOT NULL, categorie_id INT NOT NULL, family_id INT DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_26A98456BCF5E72D (categorie_id), INDEX IDX_26A98456C35E566A (family_id), INDEX IDX_26A98456B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit_trail (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(255) DEFAULT NULL, payload JSON DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id VARCHAR(36) DEFAULT NULL, family_id INT DEFAULT NULL, INDEX IDX_B523E178A76ED395 (user_id), INDEX IDX_B523E178C35E566A (family_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE badge (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, required_points INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorie_achat (id INT AUTO_INCREMENT NOT NULL, nom_categorie VARCHAR(255) NOT NULL, family_id INT NOT NULL, INDEX IDX_D3D16986C35E566A (family_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation (id INT AUTO_INCREMENT NOT NULL, conversation_name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, family_id INT NOT NULL, created_by_id VARCHAR(36) NOT NULL, INDEX IDX_8A8E26E9C35E566A (family_id), INDEX IDX_8A8E26E9B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation_participant (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, conversation_id INT NOT NULL, user_id VARCHAR(36) NOT NULL, INDEX IDX_398016619AC0396 (conversation_id), INDEX IDX_39801661A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL, file_type VARCHAR(255) NOT NULL, filesize VARCHAR(255) DEFAULT NULL, uploaded_at DATETIME DEFAULT NULL, etat VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, family_id INT DEFAULT NULL, uploaded_by_id VARCHAR(36) NOT NULL, INDEX IDX_D8698A76C35E566A (family_id), INDEX IDX_D8698A76A2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, lieu VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME NOT NULL, family_id INT DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, type_evenement_id INT NOT NULL, INDEX IDX_B26681EC35E566A (family_id), INDEX IDX_B26681EB03A8386 (created_by_id), INDEX IDX_B26681E88939516 (type_evenement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE family (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, join_code VARCHAR(255) DEFAULT NULL, code_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) NOT NULL, INDEX IDX_A5E6215BB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE family_invitation (id INT AUTO_INCREMENT NOT NULL, invited_email VARCHAR(255) DEFAULT NULL, join_code VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, expires_at DATETIME DEFAULT NULL, family_id INT NOT NULL, created_by_id VARCHAR(36) NOT NULL, INDEX IDX_C2D7B2DDC35E566A (family_id), INDEX IDX_C2D7B2DDB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE favorite_docs (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, updated_by DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, document_id INT NOT NULL, user_id VARCHAR(36) NOT NULL, INDEX IDX_BF513288C33F7837 (document_id), INDEX IDX_BF513288A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE gallery (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, etat VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, family_id INT DEFAULT NULL, created_by_id VARCHAR(36) NOT NULL, INDEX IDX_472B783AC35E566A (family_id), INDEX IDX_472B783AB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE historique_achat (id INT AUTO_INCREMENT NOT NULL, montant_achete NUMERIC(10, 2) NOT NULL, quantite_achete INT DEFAULT NULL, date_achat DATETIME NOT NULL, achat_id INT NOT NULL, revenu_id INT DEFAULT NULL, family_id INT DEFAULT NULL, paid_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_68295E25FE95D117 (achat_id), INDEX IDX_68295E259435AF7A (revenu_id), INDEX IDX_68295E25C35E566A (family_id), INDEX IDX_68295E257F9BC654 (paid_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, attachment_url VARCHAR(255) DEFAULT NULL, sent_at DATETIME NOT NULL, is_read TINYINT NOT NULL, conversation_id INT NOT NULL, sender_id VARCHAR(36) NOT NULL, INDEX IDX_B6BD307F9AC0396 (conversation_id), INDEX IDX_B6BD307FF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE point_rule (id INT AUTO_INCREMENT NOT NULL, points INT NOT NULL, valid_from DATETIME DEFAULT NULL, valid_to DATETIME DEFAULT NULL, task_id INT NOT NULL, INDEX IDX_CF584BAE8DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rappel (id INT AUTO_INCREMENT NOT NULL, offset_minutes INT NOT NULL, canal VARCHAR(255) NOT NULL, actif TINYINT DEFAULT NULL, est_lu TINYINT NOT NULL, evenement_id INT NOT NULL, user_id VARCHAR(36) DEFAULT NULL, family_id INT DEFAULT NULL, INDEX IDX_303A29C9FD02F13 (evenement_id), INDEX IDX_303A29C9A76ED395 (user_id), INDEX IDX_303A29C9C35E566A (family_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE revenu (id INT AUTO_INCREMENT NOT NULL, type_revenu VARCHAR(255) NOT NULL, montant NUMERIC(10, 2) NOT NULL, montant_total NUMERIC(10, 2) DEFAULT NULL, date_revenu DATETIME DEFAULT NULL, family_id INT DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_7DA3C045C35E566A (family_id), INDEX IDX_7DA3C045B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE score (id INT AUTO_INCREMENT NOT NULL, total_points INT NOT NULL, last_updated DATETIME NOT NULL, user_id VARCHAR(36) NOT NULL, family_id INT NOT NULL, INDEX IDX_32993751A76ED395 (user_id), INDEX IDX_32993751C35E566A (family_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE score_history (id INT AUTO_INCREMENT NOT NULL, points INT NOT NULL, created_at DATETIME NOT NULL, score_id INT NOT NULL, task_id INT NOT NULL, INDEX IDX_463255DF12EB0A51 (score_id), INDEX IDX_463255DF8DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE support_ticket (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, INDEX IDX_1F5A4D539AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, difficulty VARCHAR(50) NOT NULL, recurrence VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, is_active TINYINT NOT NULL, family_id INT NOT NULL, created_by_id VARCHAR(36) NOT NULL, INDEX IDX_527EDB25C35E566A (family_id), INDEX IDX_527EDB25B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_assignment (id INT AUTO_INCREMENT NOT NULL, assigned_at DATETIME DEFAULT NULL, due_date DATETIME DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, task_id INT DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, INDEX IDX_2CD60F158DB60186 (task_id), INDEX IDX_2CD60F15A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_completion (id INT AUTO_INCREMENT NOT NULL, completed_at DATETIME NOT NULL, proof VARCHAR(255) NOT NULL, is_validated TINYINT NOT NULL, task_id INT NOT NULL, user_id VARCHAR(36) NOT NULL, validated_by_id VARCHAR(36) DEFAULT NULL, INDEX IDX_24C57CD18DB60186 (task_id), INDEX IDX_24C57CD1A76ED395 (user_id), INDEX IDX_24C57CD1C69DE5E5 (validated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_evenement (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, couleur VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, family_id INT NOT NULL, INDEX IDX_BFE0290EC35E566A (family_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, birth_date DATE NOT NULL, avatar_path VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) NOT NULL, time_zone VARCHAR(255) DEFAULT NULL, preferences JSON DEFAULT NULL, status VARCHAR(255) NOT NULL, system_role VARCHAR(255) NOT NULL, family_role VARCHAR(255) NOT NULL, reset_token VARCHAR(255) DEFAULT NULL, reset_expires_at DATETIME DEFAULT NULL, last_login DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, family_id INT DEFAULT NULL, INDEX IDX_8D93D649C35E566A (family_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_badge (id INT AUTO_INCREMENT NOT NULL, awarded_at DATETIME DEFAULT NULL, user_id VARCHAR(36) NOT NULL, badge_id INT NOT NULL, INDEX IDX_1C32B345A76ED395 (user_id), INDEX IDX_1C32B345F7A2C2FC (badge_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE achat ADD CONSTRAINT FK_26A98456BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_achat (id)');
        $this->addSql('ALTER TABLE achat ADD CONSTRAINT FK_26A98456C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE achat ADD CONSTRAINT FK_26A98456B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE audit_trail ADD CONSTRAINT FK_B523E178A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE audit_trail ADD CONSTRAINT FK_B523E178C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE categorie_achat ADD CONSTRAINT FK_D3D16986C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681EC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681EB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E88939516 FOREIGN KEY (type_evenement_id) REFERENCES type_evenement (id)');
        $this->addSql('ALTER TABLE family ADD CONSTRAINT FK_A5E6215BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE family_invitation ADD CONSTRAINT FK_C2D7B2DDC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE family_invitation ADD CONSTRAINT FK_C2D7B2DDB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE favorite_docs ADD CONSTRAINT FK_BF513288C33F7837 FOREIGN KEY (document_id) REFERENCES document (id)');
        $this->addSql('ALTER TABLE favorite_docs ADD CONSTRAINT FK_BF513288A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783AC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE gallery ADD CONSTRAINT FK_472B783AB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT FK_68295E25FE95D117 FOREIGN KEY (achat_id) REFERENCES achat (id)');
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT FK_68295E259435AF7A FOREIGN KEY (revenu_id) REFERENCES revenu (id)');
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT FK_68295E25C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE historique_achat ADD CONSTRAINT FK_68295E257F9BC654 FOREIGN KEY (paid_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE point_rule ADD CONSTRAINT FK_CF584BAE8DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE rappel ADD CONSTRAINT FK_303A29C9FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id)');
        $this->addSql('ALTER TABLE rappel ADD CONSTRAINT FK_303A29C9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rappel ADD CONSTRAINT FK_303A29C9C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE revenu ADD CONSTRAINT FK_7DA3C045C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE revenu ADD CONSTRAINT FK_7DA3C045B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE score ADD CONSTRAINT FK_32993751A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE score ADD CONSTRAINT FK_32993751C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE score_history ADD CONSTRAINT FK_463255DF12EB0A51 FOREIGN KEY (score_id) REFERENCES score (id)');
        $this->addSql('ALTER TABLE score_history ADD CONSTRAINT FK_463255DF8DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D539AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F158DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT FK_2CD60F15A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE task_completion ADD CONSTRAINT FK_24C57CD18DB60186 FOREIGN KEY (task_id) REFERENCES task (id)');
        $this->addSql('ALTER TABLE task_completion ADD CONSTRAINT FK_24C57CD1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE task_completion ADD CONSTRAINT FK_24C57CD1C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE type_evenement ADD CONSTRAINT FK_BFE0290EC35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649C35E566A FOREIGN KEY (family_id) REFERENCES family (id)');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id)');
    }

    public function down(Schema $schema): void
    {
        $this->assertMysqlOrMariaDb();
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE achat DROP FOREIGN KEY FK_26A98456BCF5E72D');
        $this->addSql('ALTER TABLE achat DROP FOREIGN KEY FK_26A98456C35E566A');
        $this->addSql('ALTER TABLE achat DROP FOREIGN KEY FK_26A98456B03A8386');
        $this->addSql('ALTER TABLE audit_trail DROP FOREIGN KEY FK_B523E178A76ED395');
        $this->addSql('ALTER TABLE audit_trail DROP FOREIGN KEY FK_B523E178C35E566A');
        $this->addSql('ALTER TABLE categorie_achat DROP FOREIGN KEY FK_D3D16986C35E566A');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9C35E566A');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9B03A8386');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_398016619AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_39801661A76ED395');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76C35E566A');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A2B28FE8');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681EC35E566A');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681EB03A8386');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E88939516');
        $this->addSql('ALTER TABLE family DROP FOREIGN KEY FK_A5E6215BB03A8386');
        $this->addSql('ALTER TABLE family_invitation DROP FOREIGN KEY FK_C2D7B2DDC35E566A');
        $this->addSql('ALTER TABLE family_invitation DROP FOREIGN KEY FK_C2D7B2DDB03A8386');
        $this->addSql('ALTER TABLE favorite_docs DROP FOREIGN KEY FK_BF513288C33F7837');
        $this->addSql('ALTER TABLE favorite_docs DROP FOREIGN KEY FK_BF513288A76ED395');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783AC35E566A');
        $this->addSql('ALTER TABLE gallery DROP FOREIGN KEY FK_472B783AB03A8386');
        $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY FK_68295E25FE95D117');
        $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY FK_68295E259435AF7A');
        $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY FK_68295E25C35E566A');
        $this->addSql('ALTER TABLE historique_achat DROP FOREIGN KEY FK_68295E257F9BC654');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE point_rule DROP FOREIGN KEY FK_CF584BAE8DB60186');
        $this->addSql('ALTER TABLE rappel DROP FOREIGN KEY FK_303A29C9FD02F13');
        $this->addSql('ALTER TABLE rappel DROP FOREIGN KEY FK_303A29C9A76ED395');
        $this->addSql('ALTER TABLE rappel DROP FOREIGN KEY FK_303A29C9C35E566A');
        $this->addSql('ALTER TABLE revenu DROP FOREIGN KEY FK_7DA3C045C35E566A');
        $this->addSql('ALTER TABLE revenu DROP FOREIGN KEY FK_7DA3C045B03A8386');
        $this->addSql('ALTER TABLE score DROP FOREIGN KEY FK_32993751A76ED395');
        $this->addSql('ALTER TABLE score DROP FOREIGN KEY FK_32993751C35E566A');
        $this->addSql('ALTER TABLE score_history DROP FOREIGN KEY FK_463255DF12EB0A51');
        $this->addSql('ALTER TABLE score_history DROP FOREIGN KEY FK_463255DF8DB60186');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D539AC0396');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25C35E566A');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F158DB60186');
        $this->addSql('ALTER TABLE task_assignment DROP FOREIGN KEY FK_2CD60F15A76ED395');
        $this->addSql('ALTER TABLE task_completion DROP FOREIGN KEY FK_24C57CD18DB60186');
        $this->addSql('ALTER TABLE task_completion DROP FOREIGN KEY FK_24C57CD1A76ED395');
        $this->addSql('ALTER TABLE task_completion DROP FOREIGN KEY FK_24C57CD1C69DE5E5');
        $this->addSql('ALTER TABLE type_evenement DROP FOREIGN KEY FK_BFE0290EC35E566A');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649C35E566A');
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345A76ED395');
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345F7A2C2FC');
        $this->addSql('DROP TABLE achat');
        $this->addSql('DROP TABLE audit_trail');
        $this->addSql('DROP TABLE badge');
        $this->addSql('DROP TABLE categorie_achat');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_participant');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE family');
        $this->addSql('DROP TABLE family_invitation');
        $this->addSql('DROP TABLE favorite_docs');
        $this->addSql('DROP TABLE gallery');
        $this->addSql('DROP TABLE historique_achat');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE point_rule');
        $this->addSql('DROP TABLE rappel');
        $this->addSql('DROP TABLE revenu');
        $this->addSql('DROP TABLE score');
        $this->addSql('DROP TABLE score_history');
        $this->addSql('DROP TABLE support_ticket');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_assignment');
        $this->addSql('DROP TABLE task_completion');
        $this->addSql('DROP TABLE type_evenement');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_badge');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
