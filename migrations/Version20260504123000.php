<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les compteurs de reponses correctes et total de quiz.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation_formation ADD quiz_correct_count INT DEFAULT NULL, ADD quiz_total_questions INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation_formation DROP quiz_correct_count, DROP quiz_total_questions');
    }
}

