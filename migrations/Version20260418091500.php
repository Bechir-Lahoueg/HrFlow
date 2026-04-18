<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure image column exists on formation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation DROP COLUMN IF EXISTS image');
    }
}


