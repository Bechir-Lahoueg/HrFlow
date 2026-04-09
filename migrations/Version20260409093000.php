<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exceptional leave workflow fields, attachment metadata, and audit log to leave_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE leave_requests ADD request_category VARCHAR(20) DEFAULT 'NORMAL' NOT NULL");
        $this->addSql("ALTER TABLE leave_requests ADD workflow_status VARCHAR(50) DEFAULT NULL");
        $this->addSql("ALTER TABLE leave_requests ADD urgency_level VARCHAR(20) DEFAULT NULL");
        $this->addSql('ALTER TABLE leave_requests ADD expected_return_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD attachment_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD admin_comment LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD rh_decision_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD rh_decision_by VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD admin_decision_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD admin_decision_by VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE leave_requests ADD audit_log LONGTEXT DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_lr_category ON leave_requests (request_category)');
        $this->addSql('CREATE INDEX idx_lr_workflow_status ON leave_requests (workflow_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_lr_category ON leave_requests');
        $this->addSql('DROP INDEX idx_lr_workflow_status ON leave_requests');

        $this->addSql('ALTER TABLE leave_requests DROP request_category');
        $this->addSql('ALTER TABLE leave_requests DROP workflow_status');
        $this->addSql('ALTER TABLE leave_requests DROP urgency_level');
        $this->addSql('ALTER TABLE leave_requests DROP expected_return_date');
        $this->addSql('ALTER TABLE leave_requests DROP attachment_path');
        $this->addSql('ALTER TABLE leave_requests DROP admin_comment');
        $this->addSql('ALTER TABLE leave_requests DROP rh_decision_at');
        $this->addSql('ALTER TABLE leave_requests DROP rh_decision_by');
        $this->addSql('ALTER TABLE leave_requests DROP admin_decision_at');
        $this->addSql('ALTER TABLE leave_requests DROP admin_decision_by');
        $this->addSql('ALTER TABLE leave_requests DROP audit_log');
    }
}
