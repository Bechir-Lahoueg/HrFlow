package org.example.service;

import org.example.config.DatabaseConfig;

import java.sql.*;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

/**
 * Service de persistance des notifications de congés.
 * Stocke / récupère les notifications en base pour survivre aux
 * redémarrages ou changements de session.
 */
public class LeaveNotificationService {

    // ─── Initialisation de la table ─────────────────────────────────────────────

    public LeaveNotificationService() {
        initializeTable();
    }

    private void initializeTable() {
        String sql = """
                CREATE TABLE IF NOT EXISTS leave_notifications (
                    id          INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT          NOT NULL,
                    message     TEXT         NOT NULL,
                    type        VARCHAR(30)  NOT NULL,
                    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
                    INDEX idx_emp (employee_id),
                    INDEX idx_read (is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                """;
        try (Connection conn = DatabaseConfig.getConnection();
             Statement stmt = conn.createStatement()) {
            stmt.execute(sql);
        } catch (SQLException e) {
            System.err.println("LeaveNotificationService – erreur init table: " + e.getMessage());
        }
    }

    // ─── Écriture ────────────────────────────────────────────────────────────────

    /**
     * Enregistre une notification pour un employé.
     *
     * @param employeeId  identifiant de l'employé destinataire
     * @param message     texte de la notification
     * @param type        type parmi LEAVE_APPROVED / LEAVE_REJECTED / LEAVE_SUBMITTED / INFO
     */
    public void saveNotification(int employeeId, String message, String type) {
        String sql = "INSERT INTO leave_notifications (employee_id, message, type) VALUES (?, ?, ?)";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, employeeId);
            pstmt.setString(2, message);
            pstmt.setString(3, type);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("LeaveNotificationService – erreur saveNotification: " + e.getMessage());
        }
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────────

    /**
     * Retourne toutes les notifications (lues et non lues) d'un employé,
     * de la plus récente à la plus ancienne.
     */
    public List<NotificationRecord> getNotifications(int employeeId) {
        List<NotificationRecord> list = new ArrayList<>();
        String sql = "SELECT id, message, type, created_at, is_read "
                   + "FROM leave_notifications "
                   + "WHERE employee_id = ? "
                   + "ORDER BY created_at DESC";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, employeeId);
            ResultSet rs = pstmt.executeQuery();
            while (rs.next()) {
                list.add(new NotificationRecord(
                        rs.getInt("id"),
                        rs.getString("message"),
                        rs.getString("type"),
                        rs.getTimestamp("created_at").toLocalDateTime(),
                        rs.getInt("is_read") == 1
                ));
            }
        } catch (SQLException e) {
            System.err.println("LeaveNotificationService – erreur getNotifications: " + e.getMessage());
        }
        return list;
    }

    /** Nombre de notifications non lues pour un employé. */
    public long countUnread(int employeeId) {
        String sql = "SELECT COUNT(*) FROM leave_notifications WHERE employee_id = ? AND is_read = 0";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, employeeId);
            ResultSet rs = pstmt.executeQuery();
            if (rs.next()) return rs.getLong(1);
        } catch (SQLException e) {
            System.err.println("LeaveNotificationService – erreur countUnread: " + e.getMessage());
        }
        return 0L;
    }

    // ─── Mise à jour ─────────────────────────────────────────────────────────────

    /** Marque toutes les notifications d'un employé comme lues. */
    public void markAllRead(int employeeId) {
        String sql = "UPDATE leave_notifications SET is_read = 1 WHERE employee_id = ? AND is_read = 0";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, employeeId);
            pstmt.executeUpdate();
        } catch (SQLException e) {
            System.err.println("LeaveNotificationService – erreur markAllRead: " + e.getMessage());
        }
    }

    // ─── DTO interne ─────────────────────────────────────────────────────────────

    public static class NotificationRecord {
        public final int           id;
        public final String        message;
        public final String        type;
        public final LocalDateTime createdAt;
        public final boolean       read;

        public NotificationRecord(int id, String message, String type,
                                  LocalDateTime createdAt, boolean read) {
            this.id        = id;
            this.message   = message;
            this.type      = type;
            this.createdAt = createdAt;
            this.read      = read;
        }
    }
}
