<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notifications table for in-app formation/session notifications';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('notifications')) {
            return;
        }

        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, reference_id INT DEFAULT NULL, reference_type VARCHAR(60) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, type VARCHAR(40) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_NOTIFICATIONS_USER (user_id), INDEX IDX_NOTIFICATIONS_REFERENCE_ID (reference_id), INDEX IDX_NOTIFICATIONS_IS_READ (is_read), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_NOTIFICATIONS_USER FOREIGN KEY (user_id) REFERENCES employees (id)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('notifications')) {
            return;
        }

        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_NOTIFICATIONS_USER');
        $this->addSql('DROP TABLE notifications');
    }
}




