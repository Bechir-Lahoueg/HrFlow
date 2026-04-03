package org.example.service;

import org.example.config.DatabaseConfig;

import java.sql.*;
import java.time.YearMonth;
import java.util.*;

/**
 * Service d'agrégation statistique des congés.
 * Toutes les requêtes sont optimisées pour un rendu temps-réel dans l'UI.
 */
public class LeaveStatisticsService {

    // ─────────────────────────────────────────────────────────────────────────────
    // 1. MÉTRIQUES GLOBALES
    // ─────────────────────────────────────────────────────────────────────────────

    /** Résumé global renvoyé en un seul objet. */
    public GlobalStats getGlobalStats() {
        String sql = """
            SELECT
                COUNT(*)                                               AS total,
                SUM(status = 'ACCEPTE')                               AS approved,
                SUM(status = 'REFUSE')                                AS rejected,
                SUM(status = 'ATTENTE')                               AS pending,
                COALESCE(SUM(CASE WHEN status='ACCEPTE' THEN days_count ELSE 0 END), 0) AS totalDays,
                COALESCE(AVG(CASE WHEN status='ACCEPTE' THEN days_count END),  0)        AS avgDays,
                COUNT(DISTINCT employee_id)                           AS uniqueEmps
            FROM leave_requests
            """;
        try (Connection c = DatabaseConfig.getConnection();
             Statement  s = c.createStatement();
             ResultSet  r = s.executeQuery(sql)) {
            if (r.next()) {
                return new GlobalStats(
                    r.getLong("total"),
                    r.getLong("approved"),
                    r.getLong("rejected"),
                    r.getLong("pending"),
                    r.getLong("totalDays"),
                    r.getDouble("avgDays"),
                    r.getInt("uniqueEmps")
                );
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return new GlobalStats(0, 0, 0, 0, 0, 0, 0);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 2. RÉPARTITION PAR TYPE
    // ─────────────────────────────────────────────────────────────────────────────

    /** Nombre de demandes par type de congé (tous statuts). */
    public Map<String, Long> countByType() {
        return queryGrouped("SELECT leave_type, COUNT(*) AS n FROM leave_requests GROUP BY leave_type ORDER BY n DESC");
    }

    /** Jours approuvés par type de congé. */
    public Map<String, Long> daysByType() {
        return queryGrouped(
            "SELECT leave_type, COALESCE(SUM(days_count),0) AS n " +
            "FROM leave_requests WHERE status='ACCEPTE' GROUP BY leave_type ORDER BY n DESC");
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 3. TENDANCES MENSUELLES
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Demandes soumises par mois sur les 12 derniers mois.
     * Clé = "YYYY-MM", valeur = nombre de demandes.
     */
    public Map<String, Long> submissionsPerMonth() {
        String sql = """
            SELECT DATE_FORMAT(request_date,'%Y-%m') AS m, COUNT(*) AS n
            FROM leave_requests
            WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY m ORDER BY m
            """;
        return queryGrouped(sql);
    }

    /**
     * Jours de congé approuvés par mois sur les 12 derniers mois.
     */
    public Map<String, Long> approvedDaysPerMonth() {
        String sql = """
            SELECT DATE_FORMAT(start_date,'%Y-%m') AS m, COALESCE(SUM(days_count),0) AS n
            FROM leave_requests
            WHERE status='ACCEPTE'
              AND start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY m ORDER BY m
            """;
        return queryGrouped(sql);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 4. ANALYSE PAR EMPLOYÉ
    // ─────────────────────────────────────────────────────────────────────────────

    /** Top N employés par nombre de jours de congé approuvés. */
    public List<EmployeeStat> topEmployeesByDays(int limit) {
        String sql = """
            SELECT employee_name,
                   COUNT(*)                                              AS requests,
                   COALESCE(SUM(CASE WHEN status='ACCEPTE' THEN days_count ELSE 0 END),0) AS days,
                   SUM(status='ACCEPTE')                                AS approved,
                   SUM(status='REFUSE')                                 AS rejected
            FROM leave_requests
            GROUP BY employee_id, employee_name
            ORDER BY days DESC
            LIMIT ?
            """;
        List<EmployeeStat> list = new ArrayList<>();
        try (Connection c = DatabaseConfig.getConnection();
             PreparedStatement p = c.prepareStatement(sql)) {
            p.setInt(1, limit);
            ResultSet r = p.executeQuery();
            while (r.next()) {
                list.add(new EmployeeStat(
                    r.getString("employee_name"),
                    r.getLong("requests"),
                    r.getLong("days"),
                    r.getLong("approved"),
                    r.getLong("rejected")
                ));
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    /** Statistiques complètes pour tous les employés. */
    public List<EmployeeStat> allEmployeeStats() {
        return topEmployeesByDays(Integer.MAX_VALUE);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 5. TAUX D'APPROBATION
    // ─────────────────────────────────────────────────────────────────────────────

    /** Taux d'approbation global (0.0 – 1.0). */
    public double approvalRate() {
        String sql = "SELECT SUM(status='ACCEPTE') AS ok, COUNT(*) AS total FROM leave_requests WHERE status != 'ATTENTE'";
        try (Connection c = DatabaseConfig.getConnection();
             Statement  s = c.createStatement();
             ResultSet  r = s.executeQuery(sql)) {
            if (r.next()) {
                long total = r.getLong("total");
                return total == 0 ? 0 : (double) r.getLong("ok") / total;
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return 0;
    }

    /** Taux d'approbation par mois (12 derniers mois). */
    public Map<String, Double> approvalRatePerMonth() {
        String sql = """
            SELECT DATE_FORMAT(request_date,'%Y-%m') AS m,
                   SUM(status='ACCEPTE') AS ok,
                   COUNT(*) AS total
            FROM leave_requests
            WHERE status != 'ATTENTE'
              AND request_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY m ORDER BY m
            """;
        Map<String, Double> result = new LinkedHashMap<>();
        try (Connection c = DatabaseConfig.getConnection();
             Statement  s = c.createStatement();
             ResultSet  r = s.executeQuery(sql)) {
            while (r.next()) {
                long total = r.getLong("total");
                result.put(r.getString("m"), total == 0 ? 0 : (double) r.getLong("ok") / total * 100);
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return result;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 6. DURÉE MOYENNE
    // ─────────────────────────────────────────────────────────────────────────────

    /** Durée moyenne (jours) des congés approuvés par type. */
    public Map<String, Double> avgDurationByType() {
        String sql = """
            SELECT leave_type, AVG(days_count) AS avg
            FROM leave_requests WHERE status='ACCEPTE'
            GROUP BY leave_type ORDER BY avg DESC
            """;
        Map<String, Double> result = new LinkedHashMap<>();
        try (Connection c = DatabaseConfig.getConnection();
             Statement  s = c.createStatement();
             ResultSet  r = s.executeQuery(sql)) {
            while (r.next()) {
                result.put(r.getString("leave_type"), r.getDouble("avg"));
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return result;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // 7. CALENDRIER DE CHARGE (heatmap mensuelle)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Nombre d'employés en congé (approuvé) pour chaque mois calendaire.
     * Utile pour détecter les pics d'absence.
     */
    public Map<String, Long> absenceLoadByMonth() {
        String sql = """
            SELECT DATE_FORMAT(start_date,'%Y-%m') AS m, COUNT(DISTINCT employee_id) AS n
            FROM leave_requests
            WHERE status='ACCEPTE'
            GROUP BY m ORDER BY m
            """;
        return queryGrouped(sql);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // MÉTRIQUES FILTRÉES PAR RH (join users.rh_id)
    // ─────────────────────────────────────────────────────────────────────────────

    private static final String RH_JOIN =
        "FROM leave_requests lr JOIN users u ON lr.employee_id = u.id WHERE u.rh_id = ?";

    /** Résumé global pour les employés d'un RH donné. */
    public GlobalStats getGlobalStatsByRH(int rhId) {
        String sql = """
            SELECT
                COUNT(*)                                               AS total,
                SUM(lr.status = 'ACCEPTE')                            AS approved,
                SUM(lr.status = 'REFUSE')                             AS rejected,
                SUM(lr.status = 'ATTENTE')                            AS pending,
                COALESCE(SUM(CASE WHEN lr.status='ACCEPTE' THEN lr.days_count ELSE 0 END), 0) AS totalDays,
                COALESCE(AVG(CASE WHEN lr.status='ACCEPTE' THEN lr.days_count END), 0)         AS avgDays,
                COUNT(DISTINCT lr.employee_id)                        AS uniqueEmps
            """ + RH_JOIN;
        try (Connection c = DatabaseConfig.getConnection();
             PreparedStatement p = c.prepareStatement(sql)) {
            p.setInt(1, rhId);
            ResultSet r = p.executeQuery();
            if (r.next()) {
                return new GlobalStats(
                    r.getLong("total"), r.getLong("approved"),
                    r.getLong("rejected"), r.getLong("pending"),
                    r.getLong("totalDays"), r.getDouble("avgDays"),
                    r.getInt("uniqueEmps"));
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return new GlobalStats(0, 0, 0, 0, 0, 0, 0);
    }

    /** Nombre de demandes par type pour les employés d'un RH. */
    public Map<String, Long> countByTypeByRH(int rhId) {
        String sql =
            "SELECT lr.leave_type, COUNT(*) AS n " +
            "FROM leave_requests lr JOIN users u ON lr.employee_id = u.id " +
            "WHERE u.rh_id = ? GROUP BY lr.leave_type ORDER BY n DESC";
        return queryGroupedByRH(sql, rhId);
    }

    /** Demandes soumises par mois (12 derniers mois) pour les employés d'un RH. */
    public Map<String, Long> submissionsPerMonthByRH(int rhId) {
        String sql = """
            SELECT DATE_FORMAT(lr.request_date,'%Y-%m') AS m, COUNT(*) AS n
            FROM leave_requests lr JOIN users u ON lr.employee_id = u.id
            WHERE u.rh_id = ?
              AND lr.request_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY m ORDER BY m
            """;
        return queryGroupedByRH(sql, rhId);
    }

    /** Jours approuvés par mois (12 derniers mois) pour les employés d'un RH. */
    public Map<String, Long> approvedDaysPerMonthByRH(int rhId) {
        String sql = """
            SELECT DATE_FORMAT(lr.start_date,'%Y-%m') AS m, COALESCE(SUM(lr.days_count),0) AS n
            FROM leave_requests lr JOIN users u ON lr.employee_id = u.id
            WHERE u.rh_id = ?
              AND lr.status = 'ACCEPTE'
              AND lr.start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY m ORDER BY m
            """;
        return queryGroupedByRH(sql, rhId);
    }

    /** Statistiques complètes pour les employés d'un RH. */
    public List<EmployeeStat> allEmployeeStatsByRH(int rhId) {
        String sql = """
            SELECT lr.employee_name,
                   COUNT(*)                                                           AS requests,
                   COALESCE(SUM(CASE WHEN lr.status='ACCEPTE' THEN lr.days_count ELSE 0 END),0) AS days,
                   SUM(lr.status='ACCEPTE')                                           AS approved,
                   SUM(lr.status='REFUSE')                                            AS rejected
            FROM leave_requests lr JOIN users u ON lr.employee_id = u.id
            WHERE u.rh_id = ?
            GROUP BY lr.employee_id, lr.employee_name
            ORDER BY days DESC
            """;
        List<EmployeeStat> list = new ArrayList<>();
        try (Connection c = DatabaseConfig.getConnection();
             PreparedStatement p = c.prepareStatement(sql)) {
            p.setInt(1, rhId);
            ResultSet r = p.executeQuery();
            while (r.next()) {
                list.add(new EmployeeStat(
                    r.getString("employee_name"),
                    r.getLong("requests"),
                    r.getLong("days"),
                    r.getLong("approved"),
                    r.getLong("rejected")));
            }
        } catch (SQLException e) { e.printStackTrace(); }
        return list;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // UTILITAIRES PRIVÉS
    // ─────────────────────────────────────────────────────────────────────────────

    private Map<String, Long> queryGrouped(String sql) {
        Map<String, Long> map = new LinkedHashMap<>();
        try (Connection c = DatabaseConfig.getConnection();
             Statement  s = c.createStatement();
             ResultSet  r = s.executeQuery(sql)) {
            while (r.next()) map.put(r.getString(1), r.getLong(2));
        } catch (SQLException e) { e.printStackTrace(); }
        return map;
    }

    private Map<String, Long> queryGroupedByRH(String sql, int rhId) {
        Map<String, Long> map = new LinkedHashMap<>();
        try (Connection c = DatabaseConfig.getConnection();
             PreparedStatement p = c.prepareStatement(sql)) {
            p.setInt(1, rhId);
            ResultSet r = p.executeQuery();
            while (r.next()) map.put(r.getString(1), r.getLong(2));
        } catch (SQLException e) { e.printStackTrace(); }
        return map;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // OBJETS DE TRANSFERT
    // ─────────────────────────────────────────────────────────────────────────────

    public record GlobalStats(
        long   total,
        long   approved,
        long   rejected,
        long   pending,
        long   totalApprovedDays,
        double avgApprovedDays,
        int    uniqueEmployees
    ) {
        public double approvalRatePct() {
            long decided = approved + rejected;
            return decided == 0 ? 0 : (double) approved / decided * 100.0;
        }
    }

    public record EmployeeStat(
        String name,
        long   totalRequests,
        long   approvedDays,
        long   approvedCount,
        long   rejectedCount
    ) {}
}
