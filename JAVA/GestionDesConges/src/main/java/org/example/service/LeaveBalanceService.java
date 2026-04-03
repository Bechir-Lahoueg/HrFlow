package org.example.service;

import org.example.config.DatabaseConfig;
import org.example.model.LeaveBalance;

import java.sql.*;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;

/**
 * Service de gestion du solde de congés.
 *
 * Règle : chaque mois calendaire travaillé génère 1.8 jours de congé.
 * Le solde est enregistré dans la table {@code leave_balance}.
 *
 * L'accumulation est "paresseuse" : les jours sont calculés et ajoutés
 * la première fois qu'on consulte (ou force) le solde d'un employé.
 */
public class LeaveBalanceService {

    public LeaveBalanceService() {
        initializeTable();
    }

    // =====================================================================
    // TABLE INITIALIZATION
    // =====================================================================

    private void initializeTable() {
        String sql = """
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
                    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                """;
        try (Connection conn = DatabaseConfig.getConnection();
             Statement stmt = conn.createStatement()) {
            stmt.execute(sql);
        } catch (SQLException e) {
            System.err.println("Erreur création table leave_balance: " + e.getMessage());
        }
    }

    // =====================================================================
    // GET / CREATE BALANCE
    // =====================================================================

    /**
     * Récupère le solde d'un employé, en créant une entrée si elle n'existe pas.
     * Déclenche automatiquement l'accumulation des mois en attente.
     *
     * @param employeeId   identifiant de l'employé
     * @param employeeName nom complet (pour création initiale)
     * @param hireDate     date d'embauche (pour création initiale)
     */
    public LeaveBalance getOrCreateBalance(int employeeId, String employeeName, LocalDate hireDate) {
        LeaveBalance balance = findByEmployeeId(employeeId);
        if (balance == null) {
            balance = createBalance(employeeId, employeeName, hireDate);
        }
        // Accumulation des mois passés non encore attribués
        int pending = balance.computePendingMonths();
        if (pending > 0) {
            accrueMonths(balance, pending);
        }
        return balance;
    }

    /**
     * Récupère le solde d'un employé sans création automatique.
     * Retourne null si aucun solde enregistré.
     */
    public LeaveBalance getBalance(int employeeId) {
        LeaveBalance balance = findByEmployeeId(employeeId);
        if (balance != null) {
            int pending = balance.computePendingMonths();
            if (pending > 0) {
                accrueMonths(balance, pending);
            }
        }
        return balance;
    }

    /** Retourne les soldes filtrés par RH (employés créés par ce RH). */
    public List<LeaveBalance> getBalancesByRH(int rhId) {
        List<LeaveBalance> list = new ArrayList<>();
        String sql = "SELECT lb.* FROM leave_balance lb JOIN employees e ON lb.employee_id = e.id WHERE e.rh_id = ? ORDER BY lb.employee_name";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    list.add(mapRow(rs));
                }
            }
        } catch (SQLException e) {
            System.err.println("Erreur getBalancesByRH: " + e.getMessage());
        }
        return list;
    }

    /** Retourne tous les soldes (pour affichage RH). */
    public List<LeaveBalance> getAllBalances() {
        List<LeaveBalance> list = new ArrayList<>();
        String sql = "SELECT * FROM leave_balance ORDER BY employee_name";
        try (Connection conn = DatabaseConfig.getConnection();
             Statement stmt = conn.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                list.add(mapRow(rs));
            }
        } catch (SQLException e) {
            System.err.println("Erreur getAllBalances: " + e.getMessage());
        }
        return list;
    }

    // =====================================================================
    // ACCRUAL
    // =====================================================================

    /**
     * Force le recalcul et l'accumulation des jours pour UN employé.
     * Ajoute 1.8 jours par mois complet non encore attribué.
     *
     * @return nombre de mois nouvellement attribués (-1 en cas d'erreur)
     */
    public int accrueForEmployee(int employeeId) {
        LeaveBalance balance = findByEmployeeId(employeeId);
        if (balance == null) return -1;
        int pending = balance.computePendingMonths();
        if (pending <= 0) return 0;
        accrueMonths(balance, pending);
        return pending;
    }

    /**
     * Force le recalcul pour TOUS les employés ayant un solde enregistré.
     *
     * @return nombre total de mois attribués sur l'ensemble des employés
     */
    public int accrueForAllEmployees() {
        List<LeaveBalance> allBalances = getAllBalances();
        int totalMonths = 0;
        for (LeaveBalance b : allBalances) {
            int pending = b.computePendingMonths();
            if (pending > 0) {
                accrueMonths(b, pending);
                totalMonths += pending;
            }
        }
        return totalMonths;
    }

    // =====================================================================
    // DEDUCT / REFUND
    // =====================================================================

    /**
     * Déduit des jours du solde disponible (après approbation d'un congé).
     *
     * @return true si la déduction a réussi
     */
    public boolean deductLeave(int employeeId, double days) {
        LeaveBalance balance = findByEmployeeId(employeeId);
        if (balance == null) return false;

        double newAvailable = balance.getAvailableDays() - days;
        // On autorise un solde négatif (dette) mais on loggue un avertissement
        if (newAvailable < 0) {
            System.out.println("⚠️ Solde négatif pour l'employé " + employeeId
                    + " après déduction de " + days + " jours.");
        }
        double newUsed = balance.getTotalUsed() + days;

        String sql = "UPDATE leave_balance SET available_days = ?, total_used = ? WHERE employee_id = ?";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setDouble(1, newAvailable);
            ps.setDouble(2, newUsed);
            ps.setInt(3, employeeId);
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            System.err.println("Erreur deductLeave: " + e.getMessage());
        }
        return false;
    }

    /**
     * Rembourse des jours au solde (après annulation / refus après approbation).
     */
    public boolean refundLeave(int employeeId, double days) {
        LeaveBalance balance = findByEmployeeId(employeeId);
        if (balance == null) return false;

        double newAvailable = balance.getAvailableDays() + days;
        double newUsed = Math.max(0, balance.getTotalUsed() - days);

        String sql = "UPDATE leave_balance SET available_days = ?, total_used = ? WHERE employee_id = ?";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setDouble(1, newAvailable);
            ps.setDouble(2, newUsed);
            ps.setInt(3, employeeId);
            return ps.executeUpdate() > 0;
        } catch (SQLException e) {
            System.err.println("Erreur refundLeave: " + e.getMessage());
        }
        return false;
    }

    // =====================================================================
    // MANUAL ADJUSTMENT (RH)
    // =====================================================================

    /**
     * Ajustement manuel par le RH (ajout ou déduction directe).
     *
     * @param days valeur positive pour ajouter, négative pour déduire
     */
    public boolean manualAdjust(int employeeId, double days, String reason) {
        LeaveBalance balance = findByEmployeeId(employeeId);
        if (balance == null) return false;
        double newAvailable = balance.getAvailableDays() + days;
        String sql = "UPDATE leave_balance SET available_days = ? WHERE employee_id = ?";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setDouble(1, Math.max(0, newAvailable));
            ps.setInt(2, employeeId);
            boolean ok = ps.executeUpdate() > 0;
            if (ok) System.out.printf("✏️ Ajustement manuel [%s] : %.1f jours pour employé %d%n",
                    reason, days, employeeId);
            return ok;
        } catch (SQLException e) {
            System.err.println("Erreur manualAdjust: " + e.getMessage());
        }
        return false;
    }

    /**
     * Vérifie si l'employé a suffisamment de jours disponibles.
     */
    public boolean hasSufficientBalance(int employeeId, double requestedDays) {
        LeaveBalance balance = findByEmployeeId(employeeId);
        if (balance == null) return false;
        return balance.getAvailableDays() >= requestedDays;
    }

    // =====================================================================
    // INTERNAL HELPERS
    // =====================================================================

    private LeaveBalance createBalance(int employeeId, String employeeName, LocalDate hireDate) {
        // hireDate peut être null si l'employé n'a pas de date d'embauche renseignée
        LocalDate safeHireDate = (hireDate != null) ? hireDate : LocalDate.now();
        String sql = """
                INSERT INTO leave_balance (employee_id, employee_name, available_days,
                    total_accrued, total_used, last_accrual_date, hire_date)
                VALUES (?, ?, 0, 0, 0, NULL, ?)
                """;
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, employeeId);
            ps.setString(2, employeeName);
            ps.setDate(3, Date.valueOf(safeHireDate));
            ps.executeUpdate();
            ResultSet keys = ps.getGeneratedKeys();
            LeaveBalance b = new LeaveBalance(employeeId, employeeName, safeHireDate);
            if (keys.next()) b.setId(keys.getInt(1));
            System.out.println("✓ Solde créé pour " + employeeName);
            return b;
        } catch (SQLException e) {
            System.err.println("Erreur createBalance: " + e.getMessage());
            LeaveBalance b = new LeaveBalance(employeeId, employeeName, safeHireDate);
            return b;
        }
    }

    /** Ajoute N mois d'accumulation au solde et met à jour la DB. */
    private void accrueMonths(LeaveBalance balance, int months) {
        double daysToAdd = months * LeaveBalance.MONTHLY_ACCRUAL_RATE;
        double newAvailable = balance.getAvailableDays() + daysToAdd;
        double newAccrued = balance.getTotalAccrued() + daysToAdd;
        // La date de dernière attribution = debut du dernier mois complet
        LocalDate newAccrualDate = LocalDate.now().withDayOfMonth(1).minusMonths(1);

        String sql = """
                UPDATE leave_balance
                SET available_days = ?, total_accrued = ?, last_accrual_date = ?
                WHERE employee_id = ?
                """;
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setDouble(1, newAvailable);
            ps.setDouble(2, newAccrued);
            ps.setDate(3, Date.valueOf(newAccrualDate));
            ps.setInt(4, balance.getEmployeeId());
            ps.executeUpdate();

            // Mettre à jour l'objet en mémoire
            balance.setAvailableDays(newAvailable);
            balance.setTotalAccrued(newAccrued);
            balance.setLastAccrualDate(newAccrualDate);

            System.out.printf("✓ Attribution : +%.1f jours (%d mois × 1.8) pour %s  | Solde : %.1f%n",
                    daysToAdd, months, balance.getEmployeeName(), newAvailable);
        } catch (SQLException e) {
            System.err.println("Erreur accrueMonths: " + e.getMessage());
        }
    }

    private LeaveBalance findByEmployeeId(int employeeId) {
        String sql = "SELECT * FROM leave_balance WHERE employee_id = ?";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return mapRow(rs);
        } catch (SQLException e) {
            System.err.println("Erreur findByEmployeeId: " + e.getMessage());
        }
        return null;
    }

    private LeaveBalance mapRow(ResultSet rs) throws SQLException {
        LeaveBalance b = new LeaveBalance();
        b.setId(rs.getInt("id"));
        b.setEmployeeId(rs.getInt("employee_id"));
        b.setEmployeeName(rs.getString("employee_name"));
        b.setAvailableDays(rs.getDouble("available_days"));
        b.setTotalAccrued(rs.getDouble("total_accrued"));
        b.setTotalUsed(rs.getDouble("total_used"));
        Date lastAccrual = rs.getDate("last_accrual_date");
        b.setLastAccrualDate(lastAccrual != null ? lastAccrual.toLocalDate() : null);
        Date hireDate = rs.getDate("hire_date");
        b.setHireDate(hireDate != null ? hireDate.toLocalDate() : LocalDate.now());
        return b;
    }
}
