<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Payroll Module: FichePaie, Prime, Deduction
 */
final class Version20240408000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for payroll module: fiches_paie, primes, deductions';
    }

    public function up(Schema $schema): void
    {
        // Create fiches_paie table
        $this->addSql('CREATE TABLE fiches_paie (
            id INT AUTO_INCREMENT NOT NULL,
            employee_id INT NOT NULL,
            mois SMALLINT NOT NULL,
            annee SMALLINT NOT NULL,
            salaire_brut NUMERIC(12, 2) NOT NULL,
            total_primes NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL,
            total_deductions NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL,
            salaire_net NUMERIC(12, 2) NOT NULL,
            notes LONGTEXT,
            created_at DATETIME,
            updated_at DATETIME,
            UNIQUE KEY unique_fiche_paie (employee_id, mois, annee),
            INDEX idx_fp_employee (employee_id),
            INDEX idx_fp_period (mois, annee),
            PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Create primes table
        $this->addSql('CREATE TABLE primes (
            id INT AUTO_INCREMENT NOT NULL,
            employee_id INT NOT NULL,
            type_prime VARCHAR(100) NOT NULL,
            montant NUMERIC(12, 2) NOT NULL,
            date_attribution DATE NOT NULL,
            motif LONGTEXT,
            created_at DATETIME,
            updated_at DATETIME,
            INDEX idx_prime_employee (employee_id),
            INDEX idx_prime_date (date_attribution),
            PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Create deductions table
        $this->addSql('CREATE TABLE deductions (
            id INT AUTO_INCREMENT NOT NULL,
            employee_id INT NOT NULL,
            type_deduction VARCHAR(100) NOT NULL,
            montant NUMERIC(12, 2) NOT NULL,
            date_deduction DATE NOT NULL,
            motif LONGTEXT,
            created_at DATETIME,
            updated_at DATETIME,
            INDEX idx_deduction_employee (employee_id),
            INDEX idx_deduction_date (date_deduction),
            PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE fiches_paie ADD CONSTRAINT FK_FICHE_PAIE_EMPLOYEE FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE primes ADD CONSTRAINT FK_PRIME_EMPLOYEE FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deductions ADD CONSTRAINT FK_DEDUCTION_EMPLOYEE FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS deductions');
        $this->addSql('DROP TABLE IF EXISTS primes');
        $this->addSql('DROP TABLE IF EXISTS fiches_paie');
    }
}
