<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_two_factor table for Google Authenticator (TOTP)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_two_factor (
            id INT AUTO_INCREMENT NOT NULL,
            user_source VARCHAR(20) NOT NULL,
            user_id INT NOT NULL,
            secret VARCHAR(64) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_user_two_factor_identity (user_source, user_id),
            INDEX idx_user_two_factor_enabled (enabled),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_two_factor');
    }
}
