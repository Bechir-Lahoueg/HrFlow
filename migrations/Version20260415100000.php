<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create leave_notifications table for real-time leave request notifications';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('leave_notifications')) {
            return;
        }

        $this->addSql('CREATE TABLE leave_notifications (
            id INT AUTO_INCREMENT NOT NULL,
            recipient_type VARCHAR(20) NOT NULL,
            recipient_id INT NOT NULL,
            leave_request_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            message LONGTEXT NOT NULL,
            type VARCHAR(40) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX idx_leave_notif_recipient (recipient_type, recipient_id, is_read),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('leave_notifications')) {
            return;
        }

        $this->addSql('DROP TABLE leave_notifications');
    }
}
