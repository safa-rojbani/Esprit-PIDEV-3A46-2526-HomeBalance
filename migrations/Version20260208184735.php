<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208184735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE achat CHANGE categorie_id categorie_id INT DEFAULT NULL');
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
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE achat DROP FOREIGN KEY FK_26A98456BCF5E72D');
        $this->addSql('ALTER TABLE achat DROP FOREIGN KEY FK_26A98456C35E566A');
        $this->addSql('ALTER TABLE achat DROP FOREIGN KEY FK_26A98456B03A8386');
        $this->addSql('ALTER TABLE achat CHANGE categorie_id categorie_id INT NOT NULL');
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
    }
}
