package service;

import models.ProjectUpdate;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service pour la gestion des mises à jour / activités de projet
 */
public class ProjectUpdateService {

    private final Connection conn;

    public ProjectUpdateService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(ProjectUpdate update) {
        String sql = "INSERT INTO project_updates " +
                "(project_id, user_id, update_type, title, content) " +
                "VALUES (?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, update.getProjectId());
            ps.setInt(2, update.getUserId());
            ps.setString(3, update.getUpdateType().name());
            ps.setString(4, update.getTitle());
            ps.setString(5, update.getContent());
            ps.executeUpdate();
            System.out.println("✅ Mise à jour ajoutée");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur add ProjectUpdate : " + e.getMessage());
            return false;
        }
    }

    // ─── READ BY PROJECT (récent en premier) ─────────────────────────

    public List<ProjectUpdate> getByProjectId(int projectId, int limit) {
        List<ProjectUpdate> list = new ArrayList<>();
        String sql = "SELECT u.*, p.name AS project_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS username " +
                "FROM project_updates u " +
                "LEFT JOIN projects p ON u.project_id = p.id " +
                "LEFT JOIN employees e ON u.user_id = e.id " +
                "WHERE u.project_id = ? " +
                "ORDER BY u.created_at DESC LIMIT ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ps.setInt(2, limit);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByProjectId : " + e.getMessage());
        }
        return list;
    }

    // ─── READ ALL BY PROJECT ─────────────────────────────────────────

    public List<ProjectUpdate> getAllByProject(int projectId) {
        return getByProjectId(projectId, 1000);
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM project_updates WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Mise à jour supprimée");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete ProjectUpdate : " + e.getMessage());
            return false;
        }
    }

    // ─── HELPER : mapper un ResultSet → ProjectUpdate ────────────────

    private ProjectUpdate mapRow(ResultSet rs) throws SQLException {
        ProjectUpdate u = new ProjectUpdate();
        u.setId(rs.getInt("id"));
        u.setProjectId(rs.getInt("project_id"));
        u.setUserId(rs.getInt("user_id"));
        u.setUpdateType(ProjectUpdate.UpdateType.valueOf(rs.getString("update_type")));
        u.setTitle(rs.getString("title"));
        u.setContent(rs.getString("content"));
        u.setCreatedAt(rs.getTimestamp("created_at"));
        u.setProjectName(rs.getString("project_name"));
        u.setUsername(rs.getString("username"));
        return u;
    }
}