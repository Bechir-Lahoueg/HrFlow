<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260507192436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE agent_action_log');
        $this->addSql('DROP TABLE tool_embedding');
        $this->addSql('DROP TABLE user_two_factor');
        $this->addSql('DROP INDEX idx_app_candidate_deleted ON applications');
        $this->addSql('DROP INDEX idx_app_joboffer_deleted_status ON applications');
        $this->addSql('DROP INDEX idx_app_status_applied ON applications');
        $this->addSql('DROP INDEX idx_applications_email ON applications');
        $this->addSql('ALTER TABLE applications CHANGE email_address EmailAddress VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_applications_email ON applications (EmailAddress)');
        $this->addSql('ALTER TABLE employees DROP accessibility_preferences');
        $this->addSql('ALTER TABLE formation CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE participation_formation DROP quiz_score, DROP quiz_passed, DROP quiz_attempted_at, DROP quiz_correct_count, DROP quiz_total_questions');
        $this->addSql('ALTER TABLE presence_formation DROP FOREIGN KEY `FK_26B0BE09157D332A`');
        $this->addSql('DROP INDEX IDX_26B0BE09157D332A ON presence_formation');
        $this->addSql('ALTER TABLE presence_formation CHANGE id_participation_id id_participation INT NOT NULL');
        $this->addSql('ALTER TABLE presence_formation ADD CONSTRAINT FK_26B0BE09157D332A FOREIGN KEY (id_participation) REFERENCES participation_formation (id_participation)');
        $this->addSql('CREATE INDEX IDX_26B0BE09157D332A ON presence_formation (id_participation)');
        $this->addSql('ALTER TABLE session_formation DROP FOREIGN KEY `FK_3A264B5C0759D98`');
        $this->addSql('DROP INDEX IDX_3A264B5C0759D98 ON session_formation');
        $this->addSql('ALTER TABLE session_formation CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE id_formation_id id_formation INT NOT NULL');
        $this->addSql('ALTER TABLE session_formation ADD CONSTRAINT FK_3A264B5C0759D98 FOREIGN KEY (id_formation) REFERENCES formation (id_formation)');
        $this->addSql('CREATE INDEX IDX_3A264B5C0759D98 ON session_formation (id_formation)');
        $this->addSql('ALTER TABLE users DROP accessibility_preferences');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_action_log (id INT AUTO_INCREMENT NOT NULL, session_id VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, user_id INT NOT NULL, tool_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, parameters JSON NOT NULL, result_summary LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, confirmed TINYINT DEFAULT 0 NOT NULL, executed_at DATETIME NOT NULL, INDEX idx_user_date (user_id, executed_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE tool_embedding (id INT AUTO_INCREMENT NOT NULL, tool_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, embedding JSON NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX idx_tool_name_unique (tool_name), INDEX idx_tool_updated_at (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_two_factor (id INT AUTO_INCREMENT NOT NULL, user_source VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, user_id INT NOT NULL, secret VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, enabled TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_user_two_factor_enabled (enabled), UNIQUE INDEX uniq_user_two_factor_identity (user_source, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP INDEX idx_applications_email ON applications');
        $this->addSql('ALTER TABLE applications CHANGE EmailAddress email_address VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_app_candidate_deleted ON applications (candidate_id, is_deleted)');
        $this->addSql('CREATE INDEX idx_app_joboffer_deleted_status ON applications (job_offer_id, is_deleted, status)');
        $this->addSql('CREATE INDEX idx_app_status_applied ON applications (status, applied_at)');
        $this->addSql('CREATE INDEX idx_applications_email ON applications (email_address)');
        $this->addSql('ALTER TABLE employees ADD accessibility_preferences JSON DEFAULT NULL COMMENT \'Préférences d\'\'accessibilité utilisateur (contraste, taille texte, voix, mode simplifié)\'');
        $this->addSql('ALTER TABLE formation CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE participation_formation ADD quiz_score INT DEFAULT NULL, ADD quiz_passed TINYINT DEFAULT 0 NOT NULL, ADD quiz_attempted_at DATETIME DEFAULT NULL, ADD quiz_correct_count INT DEFAULT NULL, ADD quiz_total_questions INT DEFAULT NULL');
        $this->addSql('ALTER TABLE presence_formation DROP FOREIGN KEY FK_26B0BE09157D332A');
        $this->addSql('DROP INDEX IDX_26B0BE09157D332A ON presence_formation');
        $this->addSql('ALTER TABLE presence_formation CHANGE id_participation id_participation_id INT NOT NULL');
        $this->addSql('ALTER TABLE presence_formation ADD CONSTRAINT `FK_26B0BE09157D332A` FOREIGN KEY (id_participation_id) REFERENCES participation_formation (id_participation) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_26B0BE09157D332A ON presence_formation (id_participation_id)');
        $this->addSql('ALTER TABLE session_formation DROP FOREIGN KEY FK_3A264B5C0759D98');
        $this->addSql('DROP INDEX IDX_3A264B5C0759D98 ON session_formation');
        $this->addSql('ALTER TABLE session_formation CHANGE created_at created_at DATETIME NOT NULL, CHANGE id_formation id_formation_id INT NOT NULL');
        $this->addSql('ALTER TABLE session_formation ADD CONSTRAINT `FK_3A264B5C0759D98` FOREIGN KEY (id_formation_id) REFERENCES formation (id_formation) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_3A264B5C0759D98 ON session_formation (id_formation_id)');
        $this->addSql('ALTER TABLE users ADD accessibility_preferences JSON DEFAULT NULL COMMENT \'Préférences d\'\'accessibilité utilisateur (contraste, taille texte, voix, mode simplifié)\'');
    }
}
