<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223143000 extends AbstractMigration
{
    private const FK_DOCUMENT = 'FK_4CA4F446A1D7E7E7';
    private const FK_FAMILY = 'FK_4CA4F446C35E566A';
    private const FK_SHARED_BY = 'FK_4CA4F4462D520A8A';

    public function getDescription(): string
    {
        return 'Add secure document share table with expiring tokens';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $userIdCollation = $this->resolveUserIdCollation();
        $userIdCharset = $this->resolveCharsetFromCollation($userIdCollation);

        if (!$schemaManager->tablesExist(['document_share'])) {
            $this->addSql(sprintf(
                'CREATE TABLE document_share (id INT AUTO_INCREMENT NOT NULL, document_id INT NOT NULL, family_id INT NOT NULL, shared_by_id VARCHAR(36) NOT NULL, token_hash VARCHAR(64) NOT NULL, recipient_email VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_4CA4F446A1D7E7E76C8A81A9 (token_hash), INDEX IDX_4CA4F446A1D7E7E7 (document_id), INDEX IDX_4CA4F446C35E566A (family_id), INDEX IDX_4CA4F4462D520A8A (shared_by_id), INDEX IDX_4CA4F446C35E566A8387D5A6 (family_id, expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET %s COLLATE `%s` ENGINE = InnoDB',
                $userIdCharset,
                $userIdCollation
            ));
        } else {
            // Recovery path for partially failed migration runs.
            $this->addSql(sprintf(
                'ALTER TABLE document_share CONVERT TO CHARACTER SET %s COLLATE %s',
                $userIdCharset,
                $userIdCollation
            ));
            $this->addSql(sprintf('ALTER TABLE document_share MODIFY shared_by_id VARCHAR(36) COLLATE %s NOT NULL', $userIdCollation));
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('document_share')
        );

        if (!in_array(self::FK_DOCUMENT, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_4CA4F446A1D7E7E7 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
        }
        if (!in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_4CA4F446C35E566A FOREIGN KEY (family_id) REFERENCES family (id) ON DELETE CASCADE');
        }
        if (!in_array(self::FK_SHARED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_4CA4F4462D520A8A FOREIGN KEY (shared_by_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['document_share'])) {
            return;
        }

        $existingForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $schemaManager->listTableForeignKeys('document_share')
        );

        if (in_array(self::FK_DOCUMENT, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_4CA4F446A1D7E7E7');
        }
        if (in_array(self::FK_FAMILY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_4CA4F446C35E566A');
        }
        if (in_array(self::FK_SHARED_BY, $existingForeignKeys, true)) {
            $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_4CA4F4462D520A8A');
        }

        $this->addSql('DROP TABLE document_share');
    }

    private function resolveUserIdCollation(): string
    {
        $collation = $this->connection->fetchOne(
            "SELECT COLLATION_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'user'
               AND COLUMN_NAME = 'id'
             LIMIT 1"
        );

        if (!is_string($collation) || trim($collation) === '') {
            return 'utf8mb4_general_ci';
        }

        return trim($collation);
    }

    private function resolveCharsetFromCollation(string $collation): string
    {
        $charset = strstr($collation, '_', true);
        if (!is_string($charset) || $charset === '') {
            return 'utf8mb4';
        }

        return $charset;
    }
}
