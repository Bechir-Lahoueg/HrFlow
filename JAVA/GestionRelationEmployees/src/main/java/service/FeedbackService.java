package service;

import models.Feedback;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class FeedbackService {

    private final Connection conn;
    private final NotificationService notifService = new NotificationService();

    public FeedbackService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(Feedback f) {
        String sql = "INSERT INTO feedbacks " +
                "(from_user_id, to_user_id, feedback_type, rating, comment, is_anonymous, status) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, f.getFromUserId());
            ps.setInt(2, f.getToUserId());
            ps.setString(3, f.getFeedbackType().name());
            ps.setInt(4, f.getRating());
            ps.setString(5, f.getComment());
            ps.setBoolean(6, f.isAnonymous());
            ps.setString(7, f.getStatus().name());
            ps.executeUpdate();
            System.out.println("✅ Feedback ajouté");
            // 🔔 LOGIQUE DE NOTIFICATION AU RH
            // On récupère le RH de l'employé qui REÇOIT le feedback
            int rhId = getRhIdByEmployeeId(f.getToUserId());

            if (rhId != -1) {
                // Respect de l'anonymat dans le texte de la notification
                String senderDisplayName = f.isAnonymous() ? "un employé (anonyme)" : "un collègue";

                notifService.notify(
                        rhId,
                        "feedback",
                        "💬 Nouveau feedback entre employés",
                        "Un nouveau feedback a été soumis par " + senderDisplayName + " pour l'un de vos collaborateurs.",
                        f.getId(), // Optionnel : l'ID du feedback pour pouvoir cliquer dessus
                        "feedback"
                );
            }
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur add Feedback : " + e.getMessage());
            return false;
        }
    }

    // ─── READ ALL ────────────────────────────────────────────────────

    public List<Feedback> getAll() {
        List<Feedback> list = new ArrayList<>();
        String sql = "SELECT f.*, " +
                "CONCAT(e1.first_name, ' ', e1.last_name) AS from_username, " +
                "CONCAT(e2.first_name, ' ', e2.last_name) AS to_username " +
                "FROM feedbacks f " +
                "INNER JOIN employees e1 ON f.from_user_id = e1.id " +
                "INNER JOIN employees e2 ON f.to_user_id = e2.id " +
                "WHERE e1.rh_id = ? OR e2.rh_id = ?";
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAll Feedback : " + e.getMessage());
        }
        return list;
    }

    // ─── READ : feedbacks reçus par un utilisateur ───────────────────

    public List<Feedback> getReceivedByUser(int employeeId) {
        List<Feedback> list = new ArrayList<>();
        String sql = "SELECT f.*, " +
                "CONCAT(e1.first_name, ' ', e1.last_name) AS from_username, " +
                "CONCAT(e2.first_name, ' ', e2.last_name) AS to_username " +
                "FROM feedbacks f " +
                "LEFT JOIN employees e1 ON f.from_user_id = e1.id " +
                "LEFT JOIN employees e2 ON f.to_user_id = e2.id " +
                "WHERE f.to_user_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getReceivedByUser : " + e.getMessage());
        }
        return list;
    }

    // ─── READ : feedbacks envoyés par un utilisateur ─────────────────

    public List<Feedback> getSentByUser(int employeeId) {
        List<Feedback> list = new ArrayList<>();
        String sql = "SELECT f.*, " +
                "CONCAT(e1.first_name, ' ', e1.last_name) AS from_username, " +
                "CONCAT(e2.first_name, ' ', e2.last_name) AS to_username " +
                "FROM feedbacks f " +
                "LEFT JOIN employees e1 ON f.from_user_id = e1.id " +
                "LEFT JOIN employees e2 ON f.to_user_id = e2.id " +
                "WHERE f.from_user_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getSentByUser : " + e.getMessage());
        }
        return list;
    }
// ─── READ : feedbacks des employés d'un RH ───────────────────────

    public List<Feedback> getByRhId(int rhId) {
        List<Feedback> list = new ArrayList<>();
        // On force la jointure sur 'employees' (e1 et e2) pour avoir les vrais noms
        String sql = "SELECT f.*, " +
                "CONCAT(e1.first_name, ' ', e1.last_name) AS from_username, " +
                "CONCAT(e2.first_name, ' ', e2.last_name) AS to_username " +
                "FROM feedbacks f " +
                "INNER JOIN employees e1 ON f.from_user_id = e1.id " +
                "INNER JOIN employees e2 ON f.to_user_id = e2.id " +
                "WHERE e1.rh_id = ? OR e2.rh_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ps.setInt(2, rhId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                list.add(mapRow(rs));
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByRhId : " + e.getMessage());
        }
        return list;
    }

    private int getRhIdByEmployeeId(int employeeId) {
        String sql = "SELECT rh_id FROM employees WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return rs.getInt("rh_id");
        } catch (SQLException e) {
            System.err.println("❌ Erreur recup RH ID : " + e.getMessage());
        }
        return -1;
    }
    // ─── UPDATE statut → acknowledged ────────────────────────────────

    public boolean acknowledge(int feedbackId) {
        String sql = "UPDATE feedbacks SET status='acknowledged' WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, feedbackId);
            ps.executeUpdate();
            System.out.println("✅ Feedback acknowledged (id=" + feedbackId + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur acknowledge : " + e.getMessage());
            return false;
        }
    }

    // ─── UPDATE général ──────────────────────────────────────────────

    public boolean update(Feedback f) {
        String sql = "UPDATE feedbacks SET feedback_type=?, rating=?, comment=?, " +
                "is_anonymous=?, status=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, f.getFeedbackType().name());
            ps.setInt(2, f.getRating());
            ps.setString(3, f.getComment());
            ps.setBoolean(4, f.isAnonymous());
            ps.setString(5, f.getStatus().name());
            ps.setInt(6, f.getId());
            ps.executeUpdate();
            System.out.println("✅ Feedback mis à jour (id=" + f.getId() + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update Feedback : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM feedbacks WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Feedback supprimé (id=" + id + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete Feedback : " + e.getMessage());
            return false;
        }
    }

    // ─── HELPER ──────────────────────────────────────────────────────

    private Feedback mapRow(ResultSet rs) throws SQLException {
        Feedback f = new Feedback();
        f.setId(rs.getInt("id"));
        f.setFromUserId(rs.getInt("from_user_id"));
        f.setToUserId(rs.getInt("to_user_id"));
        f.setFeedbackType(Feedback.FeedbackType.valueOf(rs.getString("feedback_type")));
        f.setRating(rs.getInt("rating"));
        f.setComment(rs.getString("comment"));
        f.setAnonymous(rs.getBoolean("is_anonymous"));
        f.setStatus(Feedback.Status.valueOf(rs.getString("status")));
        f.setCreatedAt(rs.getTimestamp("created_at"));
        f.setUpdatedAt(rs.getTimestamp("updated_at"));
        // --- LOGIQUE D'ANONYMAT ICI ---
        if (f.isAnonymous()) {
            f.setFromUsername("👤 Anonyme");
        } else {
            f.setFromUsername(rs.getString("from_username"));
        }
        //f.setFromUsername(rs.getString("from_username"));
        f.setToUsername(rs.getString("to_username"));
        return f;
    }
}