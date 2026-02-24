<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223230406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create credit table for loan simulation feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE credit (
                id INT AUTO_INCREMENT NOT NULL,
                title VARCHAR(255) NOT NULL,
                principal DECIMAL(10,2) NOT NULL,
                annual_rate DECIMAL(5,2) NOT NULL,
                term_months INT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE credit");
    }
}