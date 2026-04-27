<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin_dashboard_theme preference column to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD admin_dashboard_theme VARCHAR(20) NOT NULL DEFAULT 'violet'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP admin_dashboard_theme');
    }
}
