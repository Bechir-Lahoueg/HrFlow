<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi de quiz pour les certificats de formation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation_formation ADD quiz_score INT DEFAULT NULL, ADD quiz_passed TINYINT(1) NOT NULL, ADD quiz_attempted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation_formation DROP quiz_score, DROP quiz_passed, DROP quiz_attempted_at');
    }
}

