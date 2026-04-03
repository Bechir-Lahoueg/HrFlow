-- Script SQL pour le module de Gestion des Congés
-- Base de données: Workforce Platform
-- Module: GestionDesConges

-- ============================================
-- Table: leave_balance
-- Description: Solde de congés par employé (1.8 jours/mois)
-- ============================================

CREATE TABLE IF NOT EXISTS leave_balance (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    employee_id    INT NOT NULL UNIQUE,
    employee_name  VARCHAR(255) NOT NULL,
    available_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_accrued  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_used     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    last_accrual_date DATE,
    hire_date      DATE NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lb_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: leave_requests
-- Description: Stocke toutes les demandes de congés
-- ============================================

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    leave_type VARCHAR(100) NOT NULL,
    reason TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'ATTENTE',
    request_date DATE NOT NULL,
    rh_comment TEXT,
    days_count INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status),
    INDEX idx_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Vérification de l'existence de la table
-- ============================================

-- Pour vérifier si la table existe:
-- SHOW TABLES LIKE 'leave_requests';

-- Pour voir la structure de la table:
-- DESCRIBE leave_requests;

-- ============================================
-- Requêtes utiles pour l'administration
-- ============================================

-- Compter le nombre total de demandes
-- SELECT COUNT(*) as total_requests FROM leave_requests;

-- Compter les demandes par statut
-- SELECT status, COUNT(*) as count 
-- FROM leave_requests 
-- GROUP BY status;

-- Voir les demandes en attente
-- SELECT * FROM leave_requests 
-- WHERE status = 'ATTENTE' 
-- ORDER BY request_date DESC;

-- Voir toutes les demandes d'un employé spécifique
-- SELECT * FROM leave_requests 
-- WHERE employee_id = ? 
-- ORDER BY request_date DESC;

-- Calculer le nombre total de jours de congés approuvés par employé
-- SELECT employee_id, employee_name, SUM(days_count) as total_days
-- FROM leave_requests
-- WHERE status = 'ACCEPTE'
-- GROUP BY employee_id, employee_name
-- ORDER BY total_days DESC;

-- ============================================
-- Données de test (optionnel)
-- ============================================

-- Insérer des demandes de test (décommenter si nécessaire)
/*
INSERT INTO leave_requests 
(employee_id, employee_name, start_date, end_date, leave_type, reason, status, request_date, days_count)
VALUES
(1, 'Jean Dupont', '2026-03-01', '2026-03-10', 'Congé annuel', 'Vacances familiales', 'ATTENTE', CURDATE(), 10),
(1, 'Jean Dupont', '2026-04-15', '2026-04-20', 'Congé maladie', 'Consultation médicale', 'ACCEPTE', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 6),
(2, 'Marie Martin', '2026-03-15', '2026-03-25', 'Congé annuel', 'Voyage', 'ATTENTE', CURDATE(), 11);
*/

-- ============================================
-- Nettoyage (à utiliser avec précaution)
-- ============================================

-- Supprimer toutes les demandes (ATTENTION: irréversible!)
-- DELETE FROM leave_requests;

-- Supprimer la table complètement
-- DROP TABLE IF EXISTS leave_requests;

-- ============================================
-- Maintenance
-- ============================================

-- Analyser la table pour optimiser les performances
-- ANALYZE TABLE leave_requests;

-- Optimiser la table
-- OPTIMIZE TABLE leave_requests;

-- Vérifier l'intégrité de la table
-- CHECK TABLE leave_requests;

-- ============================================
-- Statistiques utiles
-- ============================================

-- Demandes par mois
/*
SELECT 
    DATE_FORMAT(request_date, '%Y-%m') as month,
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'ACCEPTE' THEN 1 ELSE 0 END) as accepted,
    SUM(CASE WHEN status = 'REFUSE' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'ATTENTE' THEN 1 ELSE 0 END) as pending
FROM leave_requests
GROUP BY month
ORDER BY month DESC;
*/

-- Top 5 employés avec le plus de jours de congé
/*
SELECT 
    employee_name,
    COUNT(*) as total_requests,
    SUM(days_count) as total_days,
    SUM(CASE WHEN status = 'ACCEPTE' THEN days_count ELSE 0 END) as approved_days
FROM leave_requests
GROUP BY employee_id, employee_name
ORDER BY approved_days DESC
LIMIT 5;
*/

-- ============================================
-- Backup et Restore
-- ============================================

-- Exporter les données (à exécuter en ligne de commande)
-- mysqldump -h hrflow-hrflow.f.aivencloud.com -P 21031 -u avnadmin -p defaultdb leave_requests > leave_requests_backup.sql

-- Importer les données
-- mysql -h hrflow-hrflow.f.aivencloud.com -P 21031 -u avnadmin -p defaultdb < leave_requests_backup.sql
