<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428153610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pending_changesets (id VARCHAR(32) NOT NULL, session_id VARCHAR(64) NOT NULL, tool VARCHAR(64) NOT NULL, action VARCHAR(32) NOT NULL, payload JSON NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, confirmed_at DATETIME DEFAULT NULL, INDEX idx_changeset_session (session_id), INDEX idx_changeset_status (status), INDEX idx_changeset_created (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE Deduction DROP FOREIGN KEY `fk_deduction_employee`');
        $this->addSql('ALTER TABLE FichePaie DROP FOREIGN KEY `fk_fiche_paie_employee`');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY `fk_feedbacks_from_user`');
        $this->addSql('ALTER TABLE feedbacks DROP FOREIGN KEY `fk_feedbacks_to_user`');
        $this->addSql('ALTER TABLE project_chat_rooms DROP FOREIGN KEY `fk_project_chat_rooms_project`');
        $this->addSql('ALTER TABLE project_collaborators DROP FOREIGN KEY `fk_project_collaborators_employee`');
        $this->addSql('ALTER TABLE project_collaborators DROP FOREIGN KEY `fk_project_collaborators_project`');
        $this->addSql('ALTER TABLE project_milestones DROP FOREIGN KEY `fk_project_milestones_project`');
        $this->addSql('ALTER TABLE project_tasks DROP FOREIGN KEY `fk_project_tasks_assigned_to`');
        $this->addSql('ALTER TABLE project_tasks DROP FOREIGN KEY `fk_project_tasks_project`');
        $this->addSql('ALTER TABLE project_updates DROP FOREIGN KEY `fk_project_updates_project`');
        $this->addSql('ALTER TABLE project_updates DROP FOREIGN KEY `fk_project_updates_user`');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY `fk_projects_rh`');
        $this->addSql('ALTER TABLE requests DROP FOREIGN KEY `fk_requests_reviewer`');
        $this->addSql('ALTER TABLE requests DROP FOREIGN KEY `fk_requests_type`');
        $this->addSql('ALTER TABLE requests DROP FOREIGN KEY `fk_requests_user`');
        $this->addSql('DROP TABLE Deduction');
        $this->addSql('DROP TABLE FichePaie');
        $this->addSql('DROP TABLE feedbacks');
        $this->addSql('DROP TABLE project_chat_rooms');
        $this->addSql('DROP TABLE project_collaborators');
        $this->addSql('DROP TABLE project_milestones');
        $this->addSql('DROP TABLE project_tasks');
        $this->addSql('DROP TABLE project_updates');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE request_types');
        $this->addSql('DROP TABLE requests');
        $this->addSql('DROP TABLE user_two_factor');
        $this->addSql('ALTER TABLE deductions DROP FOREIGN KEY `fk_deduction_emp`');
        $this->addSql('ALTER TABLE deductions CHANGE type_deduction type_deduction VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE feedback_formation DROP FOREIGN KEY `fk_feedback_formation_formation`');
        $this->addSql('ALTER TABLE feedback_formation DROP FOREIGN KEY `fk_feedback_formation_session`');
        $this->addSql('ALTER TABLE feedback_formation DROP FOREIGN KEY `fk_feedback_formation_user`');
        $this->addSql('ALTER TABLE feedback_formation CHANGE session_id session_id INT NOT NULL, CHANGE rating rating SMALLINT NOT NULL, CHANGE contenu_comment contenu_comment LONGTEXT NOT NULL, CHANGE formateur_comment formateur_comment LONGTEXT DEFAULT NULL, CHANGE organisation_comment organisation_comment LONGTEXT DEFAULT NULL, CHANGE recommande recommande TINYINT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE feedback_formation ADD CONSTRAINT FK_FE047F04A76ED395 FOREIGN KEY (user_id) REFERENCES employees (id)');
        $this->addSql('ALTER TABLE feedback_formation ADD CONSTRAINT FK_FE047F045200282E FOREIGN KEY (formation_id) REFERENCES formation (id_formation)');
        $this->addSql('ALTER TABLE feedback_formation ADD CONSTRAINT FK_FE047F04613FECDF FOREIGN KEY (session_id) REFERENCES session_formation (id_session)');
        $this->addSql('ALTER TABLE feedback_formation RENAME INDEX idx_feedback_formation_user TO IDX_FE047F04A76ED395');
        $this->addSql('ALTER TABLE feedback_formation RENAME INDEX idx_feedback_formation_formation TO IDX_FE047F045200282E');
        $this->addSql('ALTER TABLE feedback_formation RENAME INDEX idx_feedback_formation_session TO IDX_FE047F04613FECDF');
        $this->addSql('ALTER TABLE fiches_paie DROP FOREIGN KEY `fk_fp_emp`');
        $this->addSql('DROP INDEX idx_created_at ON leave_notifications');
        $this->addSql('ALTER TABLE leave_notifications DROP data, DROP updated_at, CHANGE is_read is_read TINYINT NOT NULL');
        $this->addSql('DROP INDEX idx_lr_category ON leave_requests');
        $this->addSql('DROP INDEX idx_lr_workflow_status ON leave_requests');
        $this->addSql('DROP INDEX idx_notifications_is_read ON notifications');
        $this->addSql('DROP INDEX idx_notifications_created_at ON notifications');
        $this->addSql('ALTER TABLE notifications CHANGE type type VARCHAR(40) NOT NULL, CHANGE title title VARCHAR(255) DEFAULT NULL, CHANGE message message LONGTEXT NOT NULL, CHANGE reference_type reference_type VARCHAR(60) DEFAULT NULL, CHANGE is_read is_read TINYINT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES employees (id)');
        $this->addSql('ALTER TABLE notifications RENAME INDEX idx_notifications_user_id TO IDX_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE participation_formation RENAME INDEX token TO UNIQ_2EC70FD45F37A13B');
        $this->addSql('ALTER TABLE primes DROP FOREIGN KEY `fk_prime_emp`');
        $this->addSql('ALTER TABLE primes CHANGE type_prime type_prime VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE session_formation CHANGE lieu lieu VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE users DROP admin_dashboard_theme');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE Deduction (id_deduction INT AUTO_INCREMENT NOT NULL, type_deduction VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, montant NUMERIC(12, 2) NOT NULL, date_deduction DATE NOT NULL, id_employe INT NOT NULL, INDEX idx_deduction_id_employe (id_employe), PRIMARY KEY (id_deduction)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE FichePaie (id_fiche INT AUTO_INCREMENT NOT NULL, mois VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, annee INT NOT NULL, salaire_brut NUMERIC(12, 2) NOT NULL, total_primes NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, total_deductions NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, salaire_net NUMERIC(12, 2) NOT NULL, id_employees INT NOT NULL, INDEX idx_fichepaie_employee (id_employees), INDEX idx_fichepaie_periode (annee, mois), PRIMARY KEY (id_fiche)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE feedbacks (id INT AUTO_INCREMENT NOT NULL, from_user_id INT NOT NULL, to_user_id INT NOT NULL, feedback_type VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, rating INT NOT NULL, comment TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, is_anonymous TINYINT DEFAULT 0 NOT NULL, status VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT \'pending\' NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, emotion_label VARCHAR(40) CHARACTER SET utf8mb4 DEFAULT \'unknown\' NOT NULL COLLATE `utf8mb4_unicode_ci`, emotion_score NUMERIC(5, 4) DEFAULT \'0.0000\' NOT NULL, INDEX fk_feedbacks_from_user (from_user_id), INDEX idx_feedbacks_to_user (to_user_id), INDEX idx_feedbacks_status (status), INDEX idx_feedbacks_emotion_label (emotion_label), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE project_chat_rooms (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, room_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_project_chat_project_id (project_id), UNIQUE INDEX uniq_project_chat_room_id (room_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE project_collaborators (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, employee_id INT NOT NULL, role VARCHAR(120) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, assigned_hours INT DEFAULT NULL, worked_hours INT DEFAULT 0 NOT NULL, joined_date DATE NOT NULL, left_date DATE DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE INDEX uq_project_collaborator_active (project_id, employee_id, is_active), INDEX idx_project_collaborators_project_id (project_id), INDEX idx_project_collaborators_employee_id (employee_id), INDEX idx_project_collaborators_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE project_milestones (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, target_date DATE NOT NULL, completion_date DATE DEFAULT NULL, status VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT \'pending\' NOT NULL COLLATE `utf8mb4_unicode_ci`, completion_rate INT DEFAULT 0 NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_project_milestones_project_id (project_id), INDEX idx_project_milestones_target_date (target_date), INDEX idx_project_milestones_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE project_tasks (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, assigned_to INT DEFAULT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, priority VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, estimated_hours INT DEFAULT NULL, actual_hours INT DEFAULT 0 NOT NULL, due_date DATE DEFAULT NULL, completed_date DATE DEFAULT NULL, order_index INT DEFAULT 0 NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_project_tasks_project_id (project_id), INDEX idx_project_tasks_assigned_to (assigned_to), INDEX idx_project_tasks_status (status), INDEX idx_project_tasks_due_date (due_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE project_updates (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, user_id INT NOT NULL, actor_source VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT \'employee\' NOT NULL COLLATE `utf8mb4_unicode_ci`, update_type VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, content TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX fk_project_updates_user (user_id), INDEX idx_project_updates_project_id (project_id), INDEX idx_project_updates_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE projects (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, rh_id INT NOT NULL, status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, priority VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, estimated_hours INT DEFAULT NULL, actual_hours INT DEFAULT 0 NOT NULL, budget NUMERIC(14, 2) DEFAULT NULL, completion_rate INT DEFAULT 0 NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX idx_projects_rh_id (rh_id), INDEX idx_projects_status (status), INDEX idx_projects_end_date (end_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE request_types (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, requires_approval TINYINT DEFAULT 1 NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE INDEX uq_request_types_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE requests (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, request_type_id INT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, attachment_url VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, priority VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, submitted_date DATETIME DEFAULT CURRENT_TIMESTAMP, reviewed_by INT DEFAULT NULL, reviewed_date DATETIME DEFAULT NULL, review_comment TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX fk_requests_user (user_id), INDEX fk_requests_type (request_type_id), INDEX fk_requests_reviewer (reviewed_by), INDEX idx_requests_status (status), INDEX idx_requests_priority (priority), INDEX idx_requests_submitted_date (submitted_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_two_factor (id INT AUTO_INCREMENT NOT NULL, user_source VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, user_id INT NOT NULL, secret VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, enabled TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_user_two_factor_identity (user_source, user_id), INDEX idx_user_two_factor_enabled (enabled), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Deduction ADD CONSTRAINT `fk_deduction_employee` FOREIGN KEY (id_employe) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE FichePaie ADD CONSTRAINT `fk_fiche_paie_employee` FOREIGN KEY (id_employees) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT `fk_feedbacks_from_user` FOREIGN KEY (from_user_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedbacks ADD CONSTRAINT `fk_feedbacks_to_user` FOREIGN KEY (to_user_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_chat_rooms ADD CONSTRAINT `fk_project_chat_rooms_project` FOREIGN KEY (project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_collaborators ADD CONSTRAINT `fk_project_collaborators_employee` FOREIGN KEY (employee_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_collaborators ADD CONSTRAINT `fk_project_collaborators_project` FOREIGN KEY (project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_milestones ADD CONSTRAINT `fk_project_milestones_project` FOREIGN KEY (project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_tasks ADD CONSTRAINT `fk_project_tasks_assigned_to` FOREIGN KEY (assigned_to) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_tasks ADD CONSTRAINT `fk_project_tasks_project` FOREIGN KEY (project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_updates ADD CONSTRAINT `fk_project_updates_project` FOREIGN KEY (project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_updates ADD CONSTRAINT `fk_project_updates_user` FOREIGN KEY (user_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT `fk_projects_rh` FOREIGN KEY (rh_id) REFERENCES users (id) ON UPDATE NO ACTION');
        $this->addSql('ALTER TABLE requests ADD CONSTRAINT `fk_requests_reviewer` FOREIGN KEY (reviewed_by) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE requests ADD CONSTRAINT `fk_requests_type` FOREIGN KEY (request_type_id) REFERENCES request_types (id) ON UPDATE NO ACTION');
        $this->addSql('ALTER TABLE requests ADD CONSTRAINT `fk_requests_user` FOREIGN KEY (user_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('DROP TABLE pending_changesets');
        $this->addSql('ALTER TABLE deductions CHANGE type_deduction type_deduction VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE deductions ADD CONSTRAINT `fk_deduction_emp` FOREIGN KEY (employee_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback_formation DROP FOREIGN KEY FK_FE047F04A76ED395');
        $this->addSql('ALTER TABLE feedback_formation DROP FOREIGN KEY FK_FE047F045200282E');
        $this->addSql('ALTER TABLE feedback_formation DROP FOREIGN KEY FK_FE047F04613FECDF');
        $this->addSql('ALTER TABLE feedback_formation CHANGE rating rating INT NOT NULL, CHANGE contenu_comment contenu_comment TEXT DEFAULT NULL, CHANGE formateur_comment formateur_comment TEXT DEFAULT NULL, CHANGE organisation_comment organisation_comment TEXT DEFAULT NULL, CHANGE recommande recommande TINYINT DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE session_id session_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE feedback_formation ADD CONSTRAINT `fk_feedback_formation_formation` FOREIGN KEY (formation_id) REFERENCES formation (id_formation) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback_formation ADD CONSTRAINT `fk_feedback_formation_session` FOREIGN KEY (session_id) REFERENCES session_formation (id_session) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE feedback_formation ADD CONSTRAINT `fk_feedback_formation_user` FOREIGN KEY (user_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE feedback_formation RENAME INDEX idx_fe047f04a76ed395 TO idx_feedback_formation_user');
        $this->addSql('ALTER TABLE feedback_formation RENAME INDEX idx_fe047f045200282e TO idx_feedback_formation_formation');
        $this->addSql('ALTER TABLE feedback_formation RENAME INDEX idx_fe047f04613fecdf TO idx_feedback_formation_session');
        $this->addSql('ALTER TABLE fiches_paie ADD CONSTRAINT `fk_fp_emp` FOREIGN KEY (employee_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE leave_notifications ADD data JSON DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, CHANGE is_read is_read TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_created_at ON leave_notifications (created_at)');
        $this->addSql('CREATE INDEX idx_lr_category ON leave_requests (request_category)');
        $this->addSql('CREATE INDEX idx_lr_workflow_status ON leave_requests (workflow_status)');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE notifications CHANGE reference_type reference_type VARCHAR(80) DEFAULT NULL, CHANGE title title VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(50) NOT NULL, CHANGE message message TEXT NOT NULL, CHANGE is_read is_read TINYINT DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX idx_notifications_is_read ON notifications (is_read)');
        $this->addSql('CREATE INDEX idx_notifications_created_at ON notifications (created_at)');
        $this->addSql('ALTER TABLE notifications RENAME INDEX idx_6000b0d3a76ed395 TO idx_notifications_user_id');
        $this->addSql('ALTER TABLE participation_formation RENAME INDEX uniq_2ec70fd45f37a13b TO token');
        $this->addSql('ALTER TABLE primes CHANGE type_prime type_prime VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE primes ADD CONSTRAINT `fk_prime_emp` FOREIGN KEY (employee_id) REFERENCES employees (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_formation CHANGE lieu lieu VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD admin_dashboard_theme VARCHAR(20) DEFAULT \'violet\' NOT NULL');
    }
}
