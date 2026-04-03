package service;

import models.Project;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service pour la gestion des projets
 */
public class ProjectService {

    private final Connection conn;

    public ProjectService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(Project p) {
        String sql = "INSERT INTO projects " +
                "(name, description, rh_id, status, priority, start_date, end_date, " +
                "estimated_hours, budget, completion_rate) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, p.getName());
            ps.setString(2, p.getDescription());
            ps.setInt(3, p.getRhId());
            ps.setString(4, p.getStatus().name());
            ps.setString(5, p.getPriority().name());
            ps.setDate(6, p.getStartDate());
            ps.setDate(7, p.getEndDate());
            if (p.getEstimatedHours() != null) {
                ps.setInt(8, p.getEstimatedHours());
            } else {
                ps.setNull(8, Types.INTEGER);
            }
            ps.setBigDecimal(9, p.getBudget());
            ps.setInt(10, p.getCompletionRate());

            int affected = ps.executeUpdate();
            if (affected > 0) {
                ResultSet rs = ps.getGeneratedKeys();
                if (rs.next()) {
                    p.setId(rs.getInt(1));
                }
                System.out.println("✅ Projet créé : " + p.getName());
                return true;
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur add Project : " + e.getMessage());
        }
        return false;
    }

    // ─── READ ALL ────────────────────────────────────────────────────

    public List<Project> getAll() {
        List<Project> list = new ArrayList<>();
        String sql = "SELECT p.*, u.username AS rh_name, " +
                "(SELECT COUNT(*) FROM project_collaborators WHERE project_id = p.id AND is_active = TRUE) AS collab_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) AS task_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'done') AS completed_task_count " +
                "FROM projects p " +
                "LEFT JOIN users u ON p.rh_id = u.id " +
                "ORDER BY p.created_at DESC";
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAll Project : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY ID ──────────────────────────────────────────────────

    public Project getById(int id) {
        String sql = "SELECT p.*, u.username AS rh_name, " +
                "(SELECT COUNT(*) FROM project_collaborators WHERE project_id = p.id AND is_active = TRUE) AS collab_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) AS task_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'done') AS completed_task_count " +
                "FROM projects p " +
                "LEFT JOIN users u ON p.rh_id = u.id " +
                "WHERE p.id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return mapRow(rs);
        } catch (SQLException e) {
            System.err.println("❌ Erreur getById Project : " + e.getMessage());
        }
        return null;
    }

    // ─── READ BY RH ──────────────────────────────────────────────────

    public List<Project> getByRhId(int rhId) {
        List<Project> list = new ArrayList<>();
        String sql = "SELECT p.*, u.username AS rh_name, " +
                "(SELECT COUNT(*) FROM project_collaborators WHERE project_id = p.id AND is_active = TRUE) AS collab_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) AS task_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'done') AS completed_task_count " +
                "FROM projects p " +
                "LEFT JOIN users u ON p.rh_id = u.id " +
                "WHERE p.rh_id = ? " +
                "ORDER BY p.created_at DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByRhId : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY STATUS ──────────────────────────────────────────────

    public List<Project> getByStatus(Project.Status status, int rhId) {
        List<Project> list = new ArrayList<>();
        String sql = "SELECT p.*, u.username AS rh_name, " +
                "(SELECT COUNT(*) FROM project_collaborators WHERE project_id = p.id AND is_active = TRUE) AS collab_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) AS task_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'done') AS completed_task_count " +
                "FROM projects p " +
                "LEFT JOIN users u ON p.rh_id = u.id " +
                "WHERE p.rh_id = ? AND p.status = ? " +
                "ORDER BY p.created_at DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ps.setString(2, status.name());
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByStatus : " + e.getMessage());
        }
        return list;
    }

    // ─── READ : Projets en retard ────────────────────────────────────

    public List<Project> getDelayedProjects(int rhId) {
        List<Project> list = new ArrayList<>();
        String sql = "SELECT p.*, u.username AS rh_name, " +
                "(SELECT COUNT(*) FROM project_collaborators WHERE project_id = p.id AND is_active = TRUE) AS collab_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) AS task_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'done') AS completed_task_count " +
                "FROM projects p " +
                "LEFT JOIN users u ON p.rh_id = u.id " +
                "WHERE p.rh_id = ? " +
                "AND p.end_date < CURDATE() " +
                "AND p.status NOT IN ('completed', 'cancelled') " +
                "ORDER BY p.end_date ASC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getDelayedProjects : " + e.getMessage());
        }
        return list;
    }

    // ─── READ : Projets d'un employé ─────────────────────────────────

    public List<Project> getByEmployeeId(int employeeId) {
        List<Project> list = new ArrayList<>();
        String sql = "SELECT DISTINCT p.*, u.username AS rh_name, " +
                "(SELECT COUNT(*) FROM project_collaborators WHERE project_id = p.id AND is_active = TRUE) AS collab_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) AS task_count, " +
                "(SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'done') AS completed_task_count " +
                "FROM projects p " +
                "LEFT JOIN users u ON p.rh_id = u.id " +
                "INNER JOIN project_collaborators pc ON p.id = pc.project_id " +
                "WHERE pc.employee_id = ? AND pc.is_active = TRUE " +
                "ORDER BY p.created_at DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByEmployeeId : " + e.getMessage());
        }
        return list;
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public boolean update(Project p) {
        String sql = "UPDATE projects SET name=?, description=?, status=?, priority=?, " +
                "start_date=?, end_date=?, estimated_hours=?, budget=?, completion_rate=? " +
                "WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, p.getName());
            ps.setString(2, p.getDescription());
            ps.setString(3, p.getStatus().name());
            ps.setString(4, p.getPriority().name());
            ps.setDate(5, p.getStartDate());
            ps.setDate(6, p.getEndDate());
            if (p.getEstimatedHours() != null) {
                ps.setInt(7, p.getEstimatedHours());
            } else {
                ps.setNull(7, Types.INTEGER);
            }
            ps.setBigDecimal(8, p.getBudget());
            ps.setInt(9, p.getCompletionRate());
            ps.setInt(10, p.getId());

            ps.executeUpdate();
            System.out.println("✅ Projet mis à jour : " + p.getName());
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update Project : " + e.getMessage());
            return false;
        }
    }

    // ─── UPDATE STATUS ───────────────────────────────────────────────

    public boolean updateStatus(int projectId, Project.Status newStatus) {
        String sql = "UPDATE projects SET status=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, newStatus.name());
            ps.setInt(2, projectId);
            ps.executeUpdate();
            System.out.println("✅ Statut du projet mis à jour : " + newStatus);
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur updateStatus : " + e.getMessage());
            return false;
        }
    }

    // ─── UPDATE COMPLETION RATE (auto-calcul) ────────────────────────

    public boolean updateCompletionRate(int projectId) {
        String sql = "UPDATE projects p SET completion_rate = " +
                "(SELECT IFNULL(AVG(CASE WHEN status='done' THEN 100 ELSE 0 END), 0) " +
                "FROM project_tasks WHERE project_id = p.id) " +
                "WHERE p.id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ps.executeUpdate();
            System.out.println("✅ Taux d'avancement recalculé pour projet #" + projectId);
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur updateCompletionRate : " + e.getMessage());
            return false;
        }
    }

    // ─── UPDATE ACTUAL HOURS (auto-calcul) ───────────────────────────

    public boolean updateActualHours(int projectId) {
        String sql = "UPDATE projects p SET actual_hours = " +
                "(SELECT IFNULL(SUM(actual_hours), 0) FROM project_tasks WHERE project_id = p.id) " +
                "WHERE p.id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ps.executeUpdate();
            System.out.println("✅ Heures réelles recalculées pour projet #" + projectId);
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur updateActualHours : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM projects WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Projet supprimé (id=" + id + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete Project : " + e.getMessage());
            return false;
        }
    }

    // ─── HELPER : mapper un ResultSet → Project ──────────────────────

    private Project mapRow(ResultSet rs) throws SQLException {
        Project p = new Project();
        p.setId(rs.getInt("id"));
        p.setName(rs.getString("name"));
        p.setDescription(rs.getString("description"));
        p.setRhId(rs.getInt("rh_id"));
        p.setStatus(Project.Status.valueOf(rs.getString("status")));
        p.setPriority(Project.Priority.valueOf(rs.getString("priority")));
        p.setStartDate(rs.getDate("start_date"));
        p.setEndDate(rs.getDate("end_date"));
        p.setEstimatedHours(rs.getObject("estimated_hours") != null ? rs.getInt("estimated_hours") : null);
        p.setActualHours(rs.getInt("actual_hours"));
        p.setBudget(rs.getBigDecimal("budget"));
        p.setCompletionRate(rs.getInt("completion_rate"));
        p.setCreatedAt(rs.getTimestamp("created_at"));
        p.setUpdatedAt(rs.getTimestamp("updated_at"));

        // Champs transients
        p.setRhName(rs.getString("rh_name"));
        p.setCollaboratorCount(rs.getInt("collab_count"));
        p.setTaskCount(rs.getInt("task_count"));
        p.setCompletedTaskCount(rs.getInt("completed_task_count"));

        return p;
    }
}