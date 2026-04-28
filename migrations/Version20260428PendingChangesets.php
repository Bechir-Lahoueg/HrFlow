<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428PendingChangesets extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pending_changesets table for AI changeset management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pending_changesets (
            id VARCHAR(32) NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            tool VARCHAR(64) NOT NULL,
            action VARCHAR(32) NOT NULL,
            payload JSON NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');
        
        $this->addSql('CREATE INDEX idx_changeset_session ON pending_changesets (session_id)');
        $this->addSql('CREATE INDEX idx_changeset_status ON pending_changesets (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pending_changesets');
    }
}