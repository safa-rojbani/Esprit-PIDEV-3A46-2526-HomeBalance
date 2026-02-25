<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224123000 extends AbstractMigration
{
    private const FK_BIO_USER = 'FK_7E64D8A3A76ED395';
    private const FK_ATTEMPT_ACTOR = 'FK_E8BEA14D2EA4A39D';
    private const FK_ATTEMPT_TARGET = 'FK_E8BEA14D7388E6EB';
    private const FK_SESSION_ACTOR = 'FK_A6AC6FC42EA4A39D';
    private const FK_EXEC_SESSION = 'FK_2471B4DE613FECDF';
    private const FK_EXEC_ACTOR = 'FK_2471B4DE2EA4A39D';

    public function getDescription(): string
    {
        return 'Add biometric step-up and AI admin assistant tables.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['admin_biometric_profile'])) {
            $this->addSql('CREATE TABLE admin_biometric_profile (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, provider VARCHAR(32) NOT NULL, reference_face_token_encrypted VARCHAR(512) NOT NULL, enabled TINYINT(1) NOT NULL, consent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_admin_biometric_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$schemaManager->tablesExist(['biometric_verification_attempt'])) {
            $this->addSql('CREATE TABLE biometric_verification_attempt (id INT AUTO_INCREMENT NOT NULL, actor_user_id VARCHAR(36) NOT NULL, target_user_id VARCHAR(36) DEFAULT NULL, action_key VARCHAR(128) NOT NULL, similarity_score DOUBLE PRECISION DEFAULT NULL, threshold_used DOUBLE PRECISION NOT NULL, result VARCHAR(32) NOT NULL, ip_address VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, provider_response_meta JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E8BEA14D2EA4A39D (actor_user_id), INDEX IDX_E8BEA14D7388E6EB (target_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$schemaManager->tablesExist(['admin_ai_session'])) {
            $this->addSql('CREATE TABLE admin_ai_session (id VARCHAR(36) NOT NULL, actor_user_id VARCHAR(36) NOT NULL, raw_prompt LONGTEXT NOT NULL, normalized_intent JSON NOT NULL, status VARCHAR(32) NOT NULL, dry_run_snapshot JSON DEFAULT NULL, requires_step_up TINYINT(1) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A6AC6FC42EA4A39D (actor_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$schemaManager->tablesExist(['admin_ai_execution_log'])) {
            $this->addSql('CREATE TABLE admin_ai_execution_log (id INT AUTO_INCREMENT NOT NULL, session_id VARCHAR(36) NOT NULL, actor_user_id VARCHAR(36) NOT NULL, result VARCHAR(32) NOT NULL, executed_actions_count INT NOT NULL, error_summary LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2471B4DE613FECDF (session_id), INDEX IDX_2471B4DE2EA4A39D (actor_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // Recovery path when tables exist from a previously failed migration.
        $this->addSql('ALTER TABLE admin_biometric_profile CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE admin_biometric_profile MODIFY id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE admin_biometric_profile MODIFY user_id VARCHAR(36) NOT NULL');

        $this->addSql('ALTER TABLE biometric_verification_attempt CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE biometric_verification_attempt MODIFY actor_user_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE biometric_verification_attempt MODIFY target_user_id VARCHAR(36) DEFAULT NULL');

        $this->addSql('ALTER TABLE admin_ai_session CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE admin_ai_session MODIFY id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE admin_ai_session MODIFY actor_user_id VARCHAR(36) NOT NULL');

        $this->addSql('ALTER TABLE admin_ai_execution_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE admin_ai_execution_log MODIFY session_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE admin_ai_execution_log MODIFY actor_user_id VARCHAR(36) NOT NULL');

        $bioForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('admin_biometric_profile')
        );
        if (!in_array(self::FK_BIO_USER, $bioForeignKeys, true)) {
            $this->addSql('ALTER TABLE admin_biometric_profile ADD CONSTRAINT FK_7E64D8A3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }

        $attemptForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('biometric_verification_attempt')
        );
        if (!in_array(self::FK_ATTEMPT_ACTOR, $attemptForeignKeys, true)) {
            $this->addSql('ALTER TABLE biometric_verification_attempt ADD CONSTRAINT FK_E8BEA14D2EA4A39D FOREIGN KEY (actor_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }
        if (!in_array(self::FK_ATTEMPT_TARGET, $attemptForeignKeys, true)) {
            $this->addSql('ALTER TABLE biometric_verification_attempt ADD CONSTRAINT FK_E8BEA14D7388E6EB FOREIGN KEY (target_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        }

        $sessionForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('admin_ai_session')
        );
        if (!in_array(self::FK_SESSION_ACTOR, $sessionForeignKeys, true)) {
            $this->addSql('ALTER TABLE admin_ai_session ADD CONSTRAINT FK_A6AC6FC42EA4A39D FOREIGN KEY (actor_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }

        $executionForeignKeys = array_map(
            static fn ($fk) => $fk->getName(),
            $this->connection->createSchemaManager()->listTableForeignKeys('admin_ai_execution_log')
        );
        if (!in_array(self::FK_EXEC_SESSION, $executionForeignKeys, true)) {
            $this->addSql('ALTER TABLE admin_ai_execution_log ADD CONSTRAINT FK_2471B4DE613FECDF FOREIGN KEY (session_id) REFERENCES admin_ai_session (id) ON DELETE CASCADE');
        }
        if (!in_array(self::FK_EXEC_ACTOR, $executionForeignKeys, true)) {
            $this->addSql('ALTER TABLE admin_ai_execution_log ADD CONSTRAINT FK_2471B4DE2EA4A39D FOREIGN KEY (actor_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if ($schemaManager->tablesExist(['admin_ai_execution_log'])) {
            $executionForeignKeys = array_map(static fn ($fk) => $fk->getName(), $schemaManager->listTableForeignKeys('admin_ai_execution_log'));
            if (in_array(self::FK_EXEC_SESSION, $executionForeignKeys, true)) {
                $this->addSql('ALTER TABLE admin_ai_execution_log DROP FOREIGN KEY FK_2471B4DE613FECDF');
            }
            if (in_array(self::FK_EXEC_ACTOR, $executionForeignKeys, true)) {
                $this->addSql('ALTER TABLE admin_ai_execution_log DROP FOREIGN KEY FK_2471B4DE2EA4A39D');
            }
            $this->addSql('DROP TABLE admin_ai_execution_log');
        }

        if ($schemaManager->tablesExist(['admin_ai_session'])) {
            $sessionForeignKeys = array_map(static fn ($fk) => $fk->getName(), $schemaManager->listTableForeignKeys('admin_ai_session'));
            if (in_array(self::FK_SESSION_ACTOR, $sessionForeignKeys, true)) {
                $this->addSql('ALTER TABLE admin_ai_session DROP FOREIGN KEY FK_A6AC6FC42EA4A39D');
            }
            $this->addSql('DROP TABLE admin_ai_session');
        }

        if ($schemaManager->tablesExist(['biometric_verification_attempt'])) {
            $attemptForeignKeys = array_map(static fn ($fk) => $fk->getName(), $schemaManager->listTableForeignKeys('biometric_verification_attempt'));
            if (in_array(self::FK_ATTEMPT_ACTOR, $attemptForeignKeys, true)) {
                $this->addSql('ALTER TABLE biometric_verification_attempt DROP FOREIGN KEY FK_E8BEA14D2EA4A39D');
            }
            if (in_array(self::FK_ATTEMPT_TARGET, $attemptForeignKeys, true)) {
                $this->addSql('ALTER TABLE biometric_verification_attempt DROP FOREIGN KEY FK_E8BEA14D7388E6EB');
            }
            $this->addSql('DROP TABLE biometric_verification_attempt');
        }

        if ($schemaManager->tablesExist(['admin_biometric_profile'])) {
            $bioForeignKeys = array_map(static fn ($fk) => $fk->getName(), $schemaManager->listTableForeignKeys('admin_biometric_profile'));
            if (in_array(self::FK_BIO_USER, $bioForeignKeys, true)) {
                $this->addSql('ALTER TABLE admin_biometric_profile DROP FOREIGN KEY FK_7E64D8A3A76ED395');
            }
            $this->addSql('DROP TABLE admin_biometric_profile');
        }
    }
}
