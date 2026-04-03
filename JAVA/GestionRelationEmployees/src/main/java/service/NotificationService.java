package service;

import models.Notification;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class NotificationService {

    private final Connection conn;

    public NotificationService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(Notification n) {
        String sql = "INSERT INTO notifications " +
                "(user_id, type, title, message, reference_id, reference_type, is_read) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, n.getUserId());
            ps.setString(2, n.getType());
            ps.setString(3, n.getTitle());
            ps.setString(4, n.getMessage());
            if (n.getReferenceId() != null) ps.setInt(5, n.getReferenceId());
            else ps.setNull(5, Types.INTEGER);
            ps.setString(6, n.getReferenceType());
            ps.setBoolean(7, n.isRead());
            ps.executeUpdate();
            System.out.println("✅ Notification ajoutée : " + n.getTitle());
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur add Notification : " + e.getMessage());
            return false;
        }
    }

    // ─── Méthode utilitaire : créer rapidement une notification ──────

    public void notify(int userId, String type, String title,
                       String message, Integer refId, String refType) {
        Notification n = new Notification(userId, type, title, message, refId, refType);
        add(n);
    }

    // ─── READ : toutes les notifications d'un utilisateur ────────────

    public List<Notification> getByUserId(int userId) {
        List<Notification> list = new ArrayList<>();
        String sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, userId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByUserId Notification : " + e.getMessage());
        }
        return list;
    }

    // ─── READ : notifications non lues d'un utilisateur ──────────────

    public List<Notification> getUnreadByUserId(int userId) {
        List<Notification> list = new ArrayList<>();
        String sql = "SELECT * FROM notifications WHERE user_id=? AND is_read=false " +
                "ORDER BY created_at DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, userId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getUnreadByUserId : " + e.getMessage());
        }
        return list;
    }

    // ─── Compter les non lues ─────────────────────────────────────────

    public int countUnread(int userId) {
        String sql = "SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=false";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, userId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return rs.getInt(1);
        } catch (SQLException e) {
            System.err.println("❌ Erreur countUnread : " + e.getMessage());
        }
        return 0;
    }

    // ─── Marquer une notification comme lue ──────────────────────────

    public boolean markAsRead(int notificationId) {
        String sql = "UPDATE notifications SET is_read=true WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, notificationId);
            ps.executeUpdate();
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur markAsRead : " + e.getMessage());
            return false;
        }
    }

    // ─── Marquer toutes comme lues pour un utilisateur ───────────────

    public boolean markAllAsRead(int userId) {
        String sql = "UPDATE notifications SET is_read=true WHERE user_id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, userId);
            ps.executeUpdate();
            System.out.println("✅ Toutes les notifications marquées comme lues");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur markAllAsRead : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM notifications WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete Notification : " + e.getMessage());
            return false;
        }
    }

    // ─── HELPER ──────────────────────────────────────────────────────

    private Notification mapRow(ResultSet rs) throws SQLException {
        Notification n = new Notification();
        n.setId(rs.getInt("id"));
        n.setUserId(rs.getInt("user_id"));
        n.setType(rs.getString("type"));
        n.setTitle(rs.getString("title"));
        n.setMessage(rs.getString("message"));
        n.setReferenceId(rs.getObject("reference_id") != null ? rs.getInt("reference_id") : null);
        n.setReferenceType(rs.getString("reference_type"));
        n.setRead(rs.getBoolean("is_read"));
        n.setCreatedAt(rs.getTimestamp("created_at"));
        return n;
    }
}