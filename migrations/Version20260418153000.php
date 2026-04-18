<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create project_chat_rooms table to map each project to a Matrix room';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('project_chat_rooms')) {
            return;
        }

        $table = $schema->createTable('project_chat_rooms');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('project_id', 'integer');
        $table->addColumn('room_id', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime_mutable');
        $table->addColumn('updated_at', 'datetime_mutable');

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['project_id'], 'uniq_project_chat_project_id');
        $table->addUniqueIndex(['room_id'], 'uniq_project_chat_room_id');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('project_chat_rooms')) {
            $schema->dropTable('project_chat_rooms');
        }
    }
}

