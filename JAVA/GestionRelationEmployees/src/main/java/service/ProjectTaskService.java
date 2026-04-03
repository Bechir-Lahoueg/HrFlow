package service;

import models.ProjectTask;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service pour la gestion des tâches de projet (Kanban)
 */
public class ProjectTaskService {

    private final Connection conn;
    private final ProjectService projectService = new ProjectService();

    public ProjectTaskService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(ProjectTask task) {
        String sql = "INSERT INTO project_tasks " +
                "(project_id, assigned_to, title, description, status, priority, " +
                "estimated_hours, due_date, order_index) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, task.getProjectId());
            if (task.getAssignedTo() != null) {
                ps.setInt(2, task.getAssignedTo());
            } else {
                ps.setNull(2, Types.INTEGER);
            }
            ps.setString(3, task.getTitle());
            ps.setString(4, task.getDescription());
            ps.setString(5, task.getStatus().name());
            ps.setString(6, task.getPriority().name());
            if (task.getEstimatedHours() != null) {
                ps.setInt(7, task.getEstimatedHours());
            } else {
                ps.setNull(7, Types.INTEGER);
            }
            ps.setDate(8, task.getDueDate());
            ps.setInt(9, task.getOrderIndex());

            int affected = ps.executeUpdate();
            if (affected > 0) {
                ResultSet rs = ps.getGeneratedKeys();
                if (rs.next()) {
                    task.setId(rs.getInt(1));
                }
                System.out.println("✅ Tâche créée : " + task.getTitle());

                // Recalculer l'avancement du projet
                projectService.updateCompletionRate(task.getProjectId());
                return true;
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur add ProjectTask : " + e.getMessage());
        }
        return false;
    }

    // ─── READ BY PROJECT ─────────────────────────────────────────────

    public List<ProjectTask> getByProjectId(int projectId) {
        List<ProjectTask> list = new ArrayList<>();
        String sql = "SELECT t.*, p.name AS project_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS assigned_to_name " +
                "FROM project_tasks t " +
                "LEFT JOIN projects p ON t.project_id = p.id " +
                "LEFT JOIN employees e ON t.assigned_to = e.id " +
                "WHERE t.project_id = ? " +
                "ORDER BY t.order_index, t.created_at";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByProjectId : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY STATUS (pour Kanban) ────────────────────────────────

    public List<ProjectTask> getByProjectAndStatus(int projectId, ProjectTask.Status status) {
        List<ProjectTask> list = new ArrayList<>();
        String sql = "SELECT t.*, p.name AS project_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS assigned_to_name " +
                "FROM project_tasks t " +
                "LEFT JOIN projects p ON t.project_id = p.id " +
                "LEFT JOIN employees e ON t.assigned_to = e.id " +
                "WHERE t.project_id = ? AND t.status = ? " +
                "ORDER BY t.order_index, t.created_at";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ps.setString(2, status.name());
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByProjectAndStatus : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY EMPLOYEE ────────────────────────────────────────────

    public List<ProjectTask> getByEmployeeId(int employeeId) {
        List<ProjectTask> list = new ArrayList<>();
        String sql = "SELECT t.*, p.name AS project_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS assigned_to_name " +
                "FROM project_tasks t " +
                "LEFT JOIN projects p ON t.project_id = p.id " +
                "LEFT JOIN employees e ON t.assigned_to = e.id " +
                "WHERE t.assigned_to = ? " +
                "ORDER BY t.due_date, t.priority DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByEmployeeId : " + e.getMessage());
        }
        return list;
    }

    // ─── READ : Tâches en retard ─────────────────────────────────────

    public List<ProjectTask> getOverdueTasks(int projectId) {
        List<ProjectTask> list = new ArrayList<>();
        String sql = "SELECT t.*, p.name AS project_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS assigned_to_name " +
                "FROM project_tasks t " +
                "LEFT JOIN projects p ON t.project_id = p.id " +
                "LEFT JOIN employees e ON t.assigned_to = e.id " +
                "WHERE t.project_id = ? " +
                "AND t.due_date < CURDATE() " +
                "AND t.status != 'done' " +
                "ORDER BY t.due_date";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getOverdueTasks : " + e.getMessage());
        }
        return list;
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public boolean update(ProjectTask task) {
        String sql = "UPDATE project_tasks SET assigned_to=?, title=?, description=?, " +
                "status=?, priority=?, estimated_hours=?, actual_hours=?, due_date=?, " +
                "order_index=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            if (task.getAssignedTo() != null) {
                ps.setInt(1, task.getAssignedTo());
            } else {
                ps.setNull(1, Types.INTEGER);
            }
            ps.setString(2, task.getTitle());
            ps.setString(3, task.getDescription());
            ps.setString(4, task.getStatus().name());
            ps.setString(5, task.getPriority().name());
            if (task.getEstimatedHours() != null) {
                ps.setInt(6, task.getEstimatedHours());
            } else {
                ps.setNull(6, Types.INTEGER);
            }
            ps.setInt(7, task.getActualHours());
            ps.setDate(8, task.getDueDate());
            ps.setInt(9, task.getOrderIndex());
            ps.setInt(10, task.getId());

            ps.executeUpdate();
            System.out.println("✅ Tâche mise à jour : " + task.getTitle());

            // Recalculer l'avancement du projet
            projectService.updateCompletionRate(task.getProjectId());
            projectService.updateActualHours(task.getProjectId());
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update ProjectTask : " + e.getMessage());
            return false;
        }
    }

    // ─── MOVE TASK (Drag & Drop Kanban) ──────────────────────────────

    public boolean moveTask(int taskId, ProjectTask.Status newStatus) {
        String sql = "UPDATE project_tasks SET status=?, " +
                "completed_date = CASE WHEN ? = 'done' THEN NOW() ELSE NULL END " +
                "WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, newStatus.name());
            ps.setString(2, newStatus.name());
            ps.setInt(3, taskId);
            ps.executeUpdate();

            // Récupérer le project_id pour recalculer l'avancement
            ProjectTask task = getById(taskId);
            if (task != null) {
                projectService.updateCompletionRate(task.getProjectId());
            }

            System.out.println("✅ Tâche déplacée vers : " + newStatus);
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur moveTask : " + e.getMessage());
            return false;
        }
    }

    // ─── ASSIGN TASK ─────────────────────────────────────────────────

    public boolean assignTask(int taskId, int employeeId) {
        String sql = "UPDATE project_tasks SET assigned_to=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ps.setInt(2, taskId);
            ps.executeUpdate();
            System.out.println("✅ Tâche assignée à l'employé #" + employeeId);
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur assignTask : " + e.getMessage());
            return false;
        }
    }

    // ─── LOG HOURS ───────────────────────────────────────────────────

    public boolean logHours(int taskId, int hours) {
        String sql = "UPDATE project_tasks SET actual_hours = actual_hours + ? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, hours);
            ps.setInt(2, taskId);
            ps.executeUpdate();

            // Recalculer les heures du projet
            ProjectTask task = getById(taskId);
            if (task != null) {
                projectService.updateActualHours(task.getProjectId());
            }

            System.out.println("✅ " + hours + "h ajoutées à la tâche #" + taskId);
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur logHours : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        ProjectTask task = getById(id);
        String sql = "DELETE FROM project_tasks WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Tâche supprimée (id=" + id + ")");

            // Recalculer l'avancement du projet
            if (task != null) {
                projectService.updateCompletionRate(task.getProjectId());
            }
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete ProjectTask : " + e.getMessage());
            return false;
        }
    }

    // ─── GET BY ID ───────────────────────────────────────────────────

    public ProjectTask getById(int id) {
        String sql = "SELECT t.*, p.name AS project_name, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS assigned_to_name " +
                "FROM project_tasks t " +
                "LEFT JOIN projects p ON t.project_id = p.id " +
                "LEFT JOIN employees e ON t.assigned_to = e.id " +
                "WHERE t.id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return mapRow(rs);
        } catch (SQLException e) {
            System.err.println("❌ Erreur getById : " + e.getMessage());
        }
        return null;
    }

    // ─── HELPER : mapper un ResultSet → ProjectTask ──────────────────

    private ProjectTask mapRow(ResultSet rs) throws SQLException {
        ProjectTask task = new ProjectTask();
        task.setId(rs.getInt("id"));
        task.setProjectId(rs.getInt("project_id"));
        task.setAssignedTo(rs.getObject("assigned_to") != null ? rs.getInt("assigned_to") : null);
        task.setTitle(rs.getString("title"));
        task.setDescription(rs.getString("description"));
        task.setStatus(ProjectTask.Status.valueOf(rs.getString("status")));
        task.setPriority(ProjectTask.Priority.valueOf(rs.getString("priority")));
        task.setEstimatedHours(rs.getObject("estimated_hours") != null ? rs.getInt("estimated_hours") : null);
        task.setActualHours(rs.getInt("actual_hours"));
        task.setDueDate(rs.getDate("due_date"));
        task.setCompletedDate(rs.getDate("completed_date"));
        task.setOrderIndex(rs.getInt("order_index"));
        task.setCreatedAt(rs.getTimestamp("created_at"));
        task.setUpdatedAt(rs.getTimestamp("updated_at"));

        // Champs transients
        task.setProjectName(rs.getString("project_name"));
        task.setAssignedToName(rs.getString("assigned_to_name"));

        return task;
    }
}