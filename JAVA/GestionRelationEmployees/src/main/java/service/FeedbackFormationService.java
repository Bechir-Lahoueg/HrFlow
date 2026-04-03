package service;

import models.FeedbackFormation;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class FeedbackFormationService {

    private final Connection conn;
    private final NotificationService notifService = new NotificationService();

    public FeedbackFormationService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(FeedbackFormation f) {
        String sql = "INSERT INTO feedback_formation " +
                "(user_id, formation_id, session_id, rating, " +
                "contenu_comment, formateur_comment, organisation_comment, recommande) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, f.getUserId());
            ps.setInt(2, f.getFormationId());
            if (f.getSessionId() != null) ps.setInt(3, f.getSessionId());
            else ps.setNull(3, Types.INTEGER);
            ps.setInt(4, f.getRating());
            ps.setString(5, f.getContenuComment());
            ps.setString(6, f.getFormateurComment());
            ps.setString(7, f.getOrganisationComment());
            ps.setBoolean(8, f.isRecommande());
            ps.executeUpdate();
            System.out.println("✅ Feedback formation ajouté");
            // 🔔 NOTIFICATION AU RH
            int rhId = getRhIdByEmployeeId(f.getUserId());
            if (rhId != -1) {
                notifService.notify(
                        rhId,
                        "feedback_formation",
                        "🎓 Nouveau feedback formation",
                        "Un employé a soumis un avis (" + f.getRating() + "/5) sur une formation.",
                        f.getId(), // Référence vers l'ID du feedback
                        "feedback_formation"
                );
            }
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur add FeedbackFormation : " + e.getMessage());
            return false;
        }
    }

    // ─── READ ALL ────────────────────────────────────────────────────

    public List<FeedbackFormation> getAll() {
        List<FeedbackFormation> list = new ArrayList<>();
        // ✅ Corrections : id_formation, id_session, CONCAT date+lieu pour session
        String sql = "SELECT ff.*, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name, " +
                "f.titre    AS formation_name, " +
                "CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name " +
                "FROM feedback_formation ff " +
                "LEFT JOIN employees        e  ON ff.user_id      = e.id " + // CHANGÉ ICI
                "LEFT JOIN formation         f  ON ff.formation_id = f.id_formation " +
                "LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session";
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAll FeedbackFormation : " + e.getMessage());
        }
        return list;
    }

    // ─── READ par formation ──────────────────────────────────────────

    public List<FeedbackFormation> getByFormation(int formationId) {
        List<FeedbackFormation> list = new ArrayList<>();
        String sql = "SELECT ff.*, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name, " +
                "f.titre    AS formation_name, " +
                "CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name " +
                "FROM feedback_formation ff " +
                "LEFT JOIN employees        e  ON ff.user_id      = e.id " + // Changé ici
                "LEFT JOIN formation         f  ON ff.formation_id = f.id_formation " +
                "LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session " +
                "WHERE ff.formation_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, formationId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByFormation : " + e.getMessage());
        }
        return list;
    }

    public List<FeedbackFormation> getByUser(int userId) {
        List<FeedbackFormation> list = new ArrayList<>();
        String sql = "SELECT ff.*, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name, " +
                "f.titre    AS formation_name, " +
                "CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name " +
                "FROM feedback_formation ff " +
                "LEFT JOIN employees        e  ON ff.user_id      = e.id " + // Changé ici
                "LEFT JOIN formation         f  ON ff.formation_id = f.id_formation " +
                "LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session " +
                "WHERE ff.user_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, userId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByUser : " + e.getMessage());
        }
        return list;
    }
// ─── READ : feedbacks formation des employés d'un RH ─────────────

    public List<FeedbackFormation> getByRhId(int rhId) {
        List<FeedbackFormation> list = new ArrayList<>();
        String sql = "SELECT ff.*, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name, " +
                "f.titre    AS formation_name, " +
                "CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name " +
                "FROM feedback_formation ff " +
                "INNER JOIN employees        e  ON ff.user_id      = e.id " +
                "LEFT JOIN formation         f  ON ff.formation_id = f.id_formation " +
                "LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session " +
                "WHERE e.rh_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByRhId FeedbackFormation : " + e.getMessage());
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
    // ─── Note moyenne d'une formation ───────────────────────────────

    public double getAverageRating(int formationId) {
        String sql = "SELECT AVG(rating) FROM feedback_formation WHERE formation_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, formationId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return rs.getDouble(1);
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAverageRating : " + e.getMessage());
        }
        return 0.0;
    }

    // ─── Taux de recommandation d'une formation ──────────────────────

    public double getRecommendationRate(int formationId) {
        String sql = "SELECT " +
                "COUNT(CASE WHEN recommande = true THEN 1 END) * 100.0 / COUNT(*) " +
                "FROM feedback_formation WHERE formation_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, formationId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return rs.getDouble(1);
        } catch (SQLException e) {
            System.err.println("❌ Erreur getRecommendationRate : " + e.getMessage());
        }
        return 0.0;
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public boolean update(FeedbackFormation f) {
        String sql = "UPDATE feedback_formation SET " +
                "rating=?, contenu_comment=?, formateur_comment=?, " +
                "organisation_comment=?, recommande=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, f.getRating());
            ps.setString(2, f.getContenuComment());
            ps.setString(3, f.getFormateurComment());
            ps.setString(4, f.getOrganisationComment());
            ps.setBoolean(5, f.isRecommande());
            ps.setInt(6, f.getId());
            ps.executeUpdate();
            System.out.println("✅ FeedbackFormation mis à jour (id=" + f.getId() + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update FeedbackFormation : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM feedback_formation WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ FeedbackFormation supprimé (id=" + id + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete FeedbackFormation : " + e.getMessage());
            return false;
        }
    }

    // ─── HELPER ──────────────────────────────────────────────────────

    private FeedbackFormation mapRow(ResultSet rs) throws SQLException {
        FeedbackFormation f = new FeedbackFormation();
        f.setId(rs.getInt("id"));
        f.setUserId(rs.getInt("user_id"));
        f.setFormationId(rs.getInt("formation_id"));
        f.setSessionId(rs.getObject("session_id") != null ? rs.getInt("session_id") : null);
        f.setRating(rs.getInt("rating"));
        f.setContenuComment(rs.getString("contenu_comment"));
        f.setFormateurComment(rs.getString("formateur_comment"));
        f.setOrganisationComment(rs.getString("organisation_comment"));
        f.setRecommande(rs.getBoolean("recommande"));
        f.setCreatedAt(rs.getTimestamp("created_at"));
        // Transients
        f.setUsername(rs.getString("employee_name"));
        f.setFormationName(rs.getString("formation_name"));
        f.setSessionName(rs.getString("session_name"));
        return f;
    }
}