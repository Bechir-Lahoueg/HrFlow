<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260506180808 extends AbstractMigration
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
        $this->addSql('ALTER TABLE employees DROP accessibility_preferences');
        $this->addSql('ALTER TABLE participation_formation DROP FOREIGN KEY `FK_2EC70FD450EAE44`');
        $this->addSql('DROP INDEX IDX_2EC70FD450EAE44 ON participation_formation');
        $this->addSql('ALTER TABLE participation_formation CHANGE quiz_passed quiz_passed TINYINT NOT NULL, CHANGE id_utilisateur id_utilisateur_id INT NOT NULL');
        $this->addSql('ALTER TABLE participation_formation ADD CONSTRAINT FK_2EC70FD4C6EE5C49 FOREIGN KEY (id_utilisateur_id) REFERENCES employees (id)');
        $this->addSql('CREATE INDEX IDX_2EC70FD4C6EE5C49 ON participation_formation (id_utilisateur_id)');
        $this->addSql('ALTER TABLE users DROP accessibility_preferences');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_action_log (id INT AUTO_INCREMENT NOT NULL, session_id VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, user_id INT NOT NULL, tool_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, parameters JSON NOT NULL, result_summary LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, confirmed TINYINT DEFAULT 0 NOT NULL, executed_at DATETIME NOT NULL, INDEX idx_user_date (user_id, executed_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE tool_embedding (id INT AUTO_INCREMENT NOT NULL, tool_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, embedding JSON NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX idx_tool_name_unique (tool_name), INDEX idx_tool_updated_at (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_two_factor (id INT AUTO_INCREMENT NOT NULL, user_source VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, user_id INT NOT NULL, secret VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, enabled TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_user_two_factor_identity (user_source, user_id), INDEX idx_user_two_factor_enabled (enabled), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE employees ADD accessibility_preferences JSON DEFAULT NULL COMMENT \'Préférences d\'\'accessibilité utilisateur (contraste, taille texte, voix, mode simplifié)\'');
        $this->addSql('ALTER TABLE participation_formation DROP FOREIGN KEY FK_2EC70FD4C6EE5C49');
        $this->addSql('DROP INDEX IDX_2EC70FD4C6EE5C49 ON participation_formation');
        $this->addSql('ALTER TABLE participation_formation CHANGE quiz_passed quiz_passed TINYINT DEFAULT 0 NOT NULL, CHANGE id_utilisateur_id id_utilisateur INT NOT NULL');
        $this->addSql('ALTER TABLE participation_formation ADD CONSTRAINT `FK_2EC70FD450EAE44` FOREIGN KEY (id_utilisateur) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_2EC70FD450EAE44 ON participation_formation (id_utilisateur)');
        $this->addSql('ALTER TABLE users ADD accessibility_preferences JSON DEFAULT NULL COMMENT \'Préférences d\'\'accessibilité utilisateur (contraste, taille texte, voix, mode simplifié)\'');
    }
}
