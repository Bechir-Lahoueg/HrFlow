package service;

import models.ProjectMilestone;
import models.ProjectUpdate;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service pour la gestion des jalons (milestones) de projet
 */
public class ProjectMilestoneService {

    private final Connection conn;

    public ProjectMilestoneService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(ProjectMilestone milestone) {
        String sql = "INSERT INTO project_milestones " +
                "(project_id, name, description, target_date, status, completion_rate) " +
                "VALUES (?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, milestone.getProjectId());
            ps.setString(2, milestone.getName());
            ps.setString(3, milestone.getDescription());
            ps.setDate(4, milestone.getTargetDate());
            ps.setString(5, milestone.getStatus().name());
            ps.setInt(6, milestone.getCompletionRate());

            int affected = ps.executeUpdate();
            if (affected > 0) {
                ResultSet rs = ps.getGeneratedKeys();
                if (rs.next()) {
                    milestone.setId(rs.getInt(1));
                }
                System.out.println("✅ Jalon créé : " + milestone.getName());
                return true;
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur add ProjectMilestone : " + e.getMessage());
        }
        return false;
    }

    // ─── READ BY PROJECT ─────────────────────────────────────────────

    public List<ProjectMilestone> getByProjectId(int projectId) {
        List<ProjectMilestone> list = new ArrayList<>();
        String sql = "SELECT m.*, p.name AS project_name " +
                "FROM project_milestones m " +
                "LEFT JOIN projects p ON m.project_id = p.id " +
                "WHERE m.project_id = ? " +
                "ORDER BY m.target_date";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByProjectId : " + e.getMessage());
        }
        return list;
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public boolean update(ProjectMilestone milestone) {
        String sql = "UPDATE project_milestones SET name=?, description=?, target_date=?, " +
                "status=?, completion_rate=?, completion_date=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, milestone.getName());
            ps.setString(2, milestone.getDescription());
            ps.setDate(3, milestone.getTargetDate());
            ps.setString(4, milestone.getStatus().name());
            ps.setInt(5, milestone.getCompletionRate());
            ps.setDate(6, milestone.getCompletionDate());
            ps.setInt(7, milestone.getId());

            ps.executeUpdate();
            System.out.println("✅ Jalon mis à jour : " + milestone.getName());
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update ProjectMilestone : " + e.getMessage());
            return false;
        }
    }

    // ─── MARK AS COMPLETED ───────────────────────────────────────────

    public boolean markAsCompleted(int milestoneId) {
        String sql = "UPDATE project_milestones SET status='completed', " +
                "completion_rate=100, completion_date=CURDATE() WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, milestoneId);
            ps.executeUpdate();
            System.out.println("✅ Jalon marqué comme terminé");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur markAsCompleted : " + e.getMessage());
            return false;
        }
    }

    // ─── CHECK AND UPDATE DELAYED ────────────────────────────────────

    public void checkDelayed(int projectId) {
        String sql = "UPDATE project_milestones SET status='delayed' " +
                "WHERE project_id = ? AND target_date < CURDATE() " +
                "AND status NOT IN ('completed')";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ps.executeUpdate();
        } catch (SQLException e) {
            System.err.println("❌ Erreur checkDelayed : " + e.getMessage());
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM project_milestones WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Jalon supprimé");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete ProjectMilestone : " + e.getMessage());
            return false;
        }
    }

    private ProjectMilestone mapRow(ResultSet rs) throws SQLException {
        ProjectMilestone m = new ProjectMilestone();
        m.setId(rs.getInt("id"));
        m.setProjectId(rs.getInt("project_id"));
        m.setName(rs.getString("name"));
        m.setDescription(rs.getString("description"));
        m.setTargetDate(rs.getDate("target_date"));
        m.setCompletionDate(rs.getDate("completion_date"));
        m.setStatus(ProjectMilestone.Status.valueOf(rs.getString("status")));
        m.setCompletionRate(rs.getInt("completion_rate"));
        m.setCreatedAt(rs.getTimestamp("created_at"));
        m.setProjectName(rs.getString("project_name"));
        return m;
    }
}

/**
 * Service pour la gestion des mises à jour / activités de projet
 */
/*class ProjectUpdateService {

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
}*/