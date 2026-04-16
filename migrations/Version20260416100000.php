<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add statut_paiement column to fiches_paie table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiches_paie ADD statut_paiement TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fiches_paie DROP COLUMN statut_paiement');
    }
}
