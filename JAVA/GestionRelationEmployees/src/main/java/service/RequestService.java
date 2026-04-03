package service;

import models.Request;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class RequestService {

    private final Connection conn;
    private final NotificationService notifService = new NotificationService();

    public RequestService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(Request r) {
        System.out.println("🐛 DEBUG RequestService.add():");
        System.out.println("   - Request reçu - getUserId(): " + r.getUserId());
        String sql = "INSERT INTO requests " +
                "(user_id, request_type_id, title, description, status, priority, attachment_url) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, r.getUserId());
            System.out.println("   - Valeur insérée dans la DB (param 1): " + r.getUserId());
            ps.setInt(2, r.getRequestTypeId());
            ps.setString(3, r.getTitle());
            ps.setString(4, r.getDescription());
            ps.setString(5, r.getStatus().name());
            ps.setString(6, r.getPriority().name());
            ps.setString(7, r.getAttachmentUrl());  //
            ps.executeUpdate();
            System.out.println("✅ Demande ajoutée : " + r.getTitle());
            // 🔍 ÉTAPE 1 : Trouver le RH de cet employé
            int rhId = getRhIdByEmployeeId(r.getUserId());

            // 🔔 ÉTAPE 2 : Notifier le BON RH (au lieu de l'ID 1)
            if (rhId != -1) {
                notifService.notify(
                        rhId,
                        "demande",
                        "📋 Nouvelle demande en attente",
                        "Une nouvelle demande '" + r.getTitle() + "' attend votre traitement.",
                        null, "request"
                );
            }

            // 🔔 Notifier le RH qu'une nouvelle demande est en attente
            // reviewer_id = 1 (à remplacer par l'id du RH lors de l'intégration)
            /*notifService.notify(
                    1,
                    "demande",
                    "📋 Nouvelle demande en attente",
                    "Une nouvelle demande '" + r.getTitle() + "' attend votre traitement.",
                    null, "request"
            );*/

            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur add Request : " + e.getMessage());
            return false;
        }
    }
    // Méthode utilitaire à ajouter dans RequestService
    private int getRhIdByEmployeeId(int employeeId) {
        String sql = "SELECT rh_id FROM employees WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return rs.getInt("rh_id");
        } catch (SQLException e) {
            System.err.println("❌ Erreur recup RH ID : " + e.getMessage());
        }
        return -1; // Retourne -1 si non trouvé
    }

    // ─── READ ALL ────────────────────────────────────────────────────

    public List<Request> getAll() {
        List<Request> list = new ArrayList<>();
        String sql = "SELECT r.*, rt.name AS type_name, " +
                "CONCAT(u.username) AS reviewer_name " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name" +
                "FROM requests r " +
                "LEFT JOIN request_types rt ON r.request_type_id = rt.id " +
                "LEFT JOIN users u ON r.reviewed_by = u.id";
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                list.add(mapRow(rs));
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAll Request : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY ID ──────────────────────────────────────────────────

    public Request getById(int id) {
        String sql = "SELECT r.*, rt.name AS type_name, " +
                "CONCAT(u.username) AS reviewer_name " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name" +
                "FROM requests r " +
                "LEFT JOIN request_types rt ON r.request_type_id = rt.id " +
                "LEFT JOIN users u ON r.reviewed_by = u.id " +
                "WHERE r.id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return mapRow(rs);
        } catch (SQLException e) {
            System.err.println("❌ Erreur getById Request : " + e.getMessage());
        }
        return null;
    }

    // ─── READ BY USER ────────────────────────────────────────────────

    public List<Request> getByUserId(int userId) {
        List<Request> list = new ArrayList<>();
        String sql = "SELECT r.*, rt.name AS type_name, " +
                "CONCAT(u.username) AS reviewer_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name " +
                "FROM requests r " +
                "LEFT JOIN request_types rt ON r.request_type_id = rt.id " +
                "LEFT JOIN users u ON r.reviewed_by = u.id " +
                "LEFT JOIN employees e ON r.user_id = e.id " +
                "WHERE r.user_id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, userId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByUserId : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY STATUS ──────────────────────────────────────────────

    public List<Request> getByStatus(Request.Status status) {
        List<Request> list = new ArrayList<>();
        String sql = "SELECT r.*, rt.name AS type_name, " +
                "CONCAT(u.username) AS reviewer_name " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name" +
                "FROM requests r " +
                "LEFT JOIN request_types rt ON r.request_type_id = rt.id " +
                "LEFT JOIN users u ON r.reviewed_by = u.id " +
                "WHERE r.status = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, status.name());
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByStatus : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY RH ID ───────────────────────────────────────────────

    public List<Request> getByRhId(int rhId) {
        List<Request> list = new ArrayList<>();
        String sql = "SELECT r.*, rt.name AS type_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name, " +
                "CONCAT(u.username) AS reviewer_name " +   // ← PAS de virgule ici
                "FROM requests r " +
                "LEFT JOIN request_types rt ON r.request_type_id = rt.id " +
                "LEFT JOIN users u ON r.reviewed_by = u.id " +
                "INNER JOIN employees e ON r.user_id = e.id " +
                "WHERE e.rh_id = ? " +
                "ORDER BY r.submitted_date DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByRhId : " + e.getMessage());
        }
        return list;
    }

    // ─── UPDATE (general) ────────────────────────────────────────────

    public boolean update(Request r) {
        String sql = "UPDATE requests SET title=?, description=?, priority=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, r.getTitle());
            ps.setString(2, r.getDescription());
            ps.setString(3, r.getPriority().name());
            ps.setInt(4, r.getId());
            ps.executeUpdate();
            System.out.println("✅ Demande mise à jour (id=" + r.getId() + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update Request : " + e.getMessage());
            return false;
        }
    }

    // ─── UPDATE STATUS (approve / reject) ────────────────────────────

    public boolean updateStatus(int requestId, Request.Status newStatus,
                                int reviewerId, String reviewComment) {
        String sql = "UPDATE requests SET status=?, reviewed_by=?, " +
                "reviewed_date=NOW(), review_comment=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, newStatus.name());
            ps.setInt(2, reviewerId);
            ps.setString(3, reviewComment);
            ps.setInt(4, requestId);
            ps.executeUpdate();
            System.out.println("✅ Statut mis à jour → " + newStatus + " (id=" + requestId + ")");

            // 🔔 Notifier l'employé du changement de statut
            Request r = getById(requestId);
            if (r != null) {
                if (newStatus == Request.Status.approved) {
                    notifService.notify(
                            r.getUserId(),
                            "demande",
                            "✅ Demande approuvée",
                            "Votre demande '" + r.getTitle() + "' a été approuvée.",
                            requestId, "request"
                    );
                } else if (newStatus == Request.Status.rejected) {
                    String motif = (reviewComment != null && !reviewComment.isEmpty())
                            ? " Motif : " + reviewComment : "";
                    notifService.notify(
                            r.getUserId(),
                            "demande",
                            "❌ Demande rejetée",
                            "Votre demande '" + r.getTitle() + "' a été rejetée." + motif,
                            requestId, "request"
                    );
                } else if (newStatus == Request.Status.cancelled) {
                    notifService.notify(
                            r.getUserId(),
                            "demande",
                            "🚫 Demande annulée",
                            "Votre demande '" + r.getTitle() + "' a été annulée.",
                            requestId, "request"
                    );
                }
            }

            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur updateStatus : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM requests WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Demande supprimée (id=" + id + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete Request : " + e.getMessage());
            return false;
        }
    }

    // ─── HELPER : mapper un ResultSet → Request ──────────────────────

    private Request mapRow(ResultSet rs) throws SQLException {
        Request r = new Request();
        r.setId(rs.getInt("id"));
        r.setUserId(rs.getInt("user_id"));
        r.setRequestTypeId(rs.getInt("request_type_id"));
        r.setTitle(rs.getString("title"));
        r.setDescription(rs.getString("description"));
        r.setStatus(Request.Status.valueOf(rs.getString("status")));
        r.setPriority(Request.Priority.valueOf(rs.getString("priority")));
        r.setAttachmentUrl(rs.getString("attachment_url"));  //
        r.setSubmittedDate(rs.getTimestamp("submitted_date"));
        r.setReviewedBy(rs.getObject("reviewed_by") != null ? rs.getInt("reviewed_by") : null);
        r.setReviewedDate(rs.getTimestamp("reviewed_date"));
        r.setReviewComment(rs.getString("review_comment"));
        r.setCreatedAt(rs.getTimestamp("created_at"));
        r.setUpdatedAt(rs.getTimestamp("updated_at"));
        r.setRequestTypeName(rs.getString("type_name"));
        r.setReviewerName(rs.getString("reviewer_name"));
        r.setEmployeeName(rs.getString("employee_name"));
        System.out.println("🐛 mapRow - ID: " + r.getId() + ", employeeName: " + r.getEmployeeName());
        return r;
    }
}