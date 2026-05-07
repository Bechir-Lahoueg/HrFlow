<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Performance: add composite indexes to the applications table.
 * Safe to run at any time – only CREATE INDEX IF NOT EXISTS, nothing is dropped or altered.
 */
final class Version20260507120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes on applications (candidate_id+is_deleted, job_offer_id+is_deleted+status, status+applied_at)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('applications');

        if (!$table->hasIndex('idx_app_candidate_deleted')) {
            $table->addIndex(['candidate_id', 'is_deleted'], 'idx_app_candidate_deleted');
        }
        if (!$table->hasIndex('idx_app_joboffer_deleted_status')) {
            $table->addIndex(['job_offer_id', 'is_deleted', 'status'], 'idx_app_joboffer_deleted_status');
        }
        if (!$table->hasIndex('idx_app_status_applied')) {
            $table->addIndex(['status', 'applied_at'], 'idx_app_status_applied');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('applications');

        if ($table->hasIndex('idx_app_candidate_deleted')) {
            $table->dropIndex('idx_app_candidate_deleted');
        }
        if ($table->hasIndex('idx_app_joboffer_deleted_status')) {
            $table->dropIndex('idx_app_joboffer_deleted_status');
        }
        if ($table->hasIndex('idx_app_status_applied')) {
            $table->dropIndex('idx_app_status_applied');
        }
    }
}
