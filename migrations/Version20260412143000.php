<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure feedback_formation table exists for formation ratings and comments';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('feedback_formation')) {
            return;
        }

        $this->addSql('CREATE TABLE feedback_formation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, formation_id INT NOT NULL, session_id INT NOT NULL, rating INT NOT NULL, contenu_comment LONGTEXT NOT NULL, formateur_comment LONGTEXT DEFAULT NULL, organisation_comment LONGTEXT DEFAULT NULL, recommande TINYINT(1) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX IDX_FEEDBACK_USER (user_id), INDEX IDX_FEEDBACK_FORMATION (formation_id), INDEX IDX_FEEDBACK_SESSION (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('feedback_formation')) {
            return;
        }

        $this->addSql('DROP TABLE feedback_formation');
    }
}


