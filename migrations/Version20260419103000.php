<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add emotion_label and emotion_score to feedbacks for Hugging Face emotion analysis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE feedbacks ADD COLUMN IF NOT EXISTS emotion_label VARCHAR(40) DEFAULT 'unknown' NOT NULL");
        $this->addSql('ALTER TABLE feedbacks ADD COLUMN IF NOT EXISTS emotion_score DECIMAL(5,4) DEFAULT 0.0000 NOT NULL');
        $this->addSql('CREATE INDEX idx_feedbacks_emotion_label ON feedbacks (emotion_label)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_feedbacks_emotion_label ON feedbacks');
        $this->addSql('ALTER TABLE feedbacks DROP COLUMN IF EXISTS emotion_label');
        $this->addSql('ALTER TABLE feedbacks DROP COLUMN IF EXISTS emotion_score');
    }
}

