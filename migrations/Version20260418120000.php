<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill employees.department from managing RH users.department';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE employees e '
            . 'INNER JOIN users u ON u.id = e.rh_id '
            . 'SET e.department = u.department '
            . 'WHERE e.department IS NULL AND u.department IS NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
    }
}
