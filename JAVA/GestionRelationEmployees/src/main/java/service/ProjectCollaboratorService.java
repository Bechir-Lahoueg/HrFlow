package service;

import models.ProjectCollaborator;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service pour la gestion des collaborateurs de projet
 */
public class ProjectCollaboratorService {

    private final Connection conn;

    public ProjectCollaboratorService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(ProjectCollaborator collab) {
        String sql = "INSERT INTO project_collaborators " +
                "(project_id, employee_id, role, assigned_hours, joined_date, is_active) " +
                "VALUES (?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, collab.getProjectId());
            ps.setInt(2, collab.getEmployeeId());
            ps.setString(3, collab.getRole());
            if (collab.getAssignedHours() != null) {
                ps.setInt(4, collab.getAssignedHours());
            } else {
                ps.setNull(4, Types.INTEGER);
            }
            ps.setDate(5, collab.getJoinedDate());
            ps.setBoolean(6, collab.isActive());

            int affected = ps.executeUpdate();
            if (affected > 0) {
                ResultSet rs = ps.getGeneratedKeys();
                if (rs.next()) {
                    collab.setId(rs.getInt(1));
                }
                System.out.println("✅ Collaborateur ajouté au projet");
                return true;
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur add ProjectCollaborator : " + e.getMessage());
        }
        return false;
    }

    // ─── READ BY PROJECT ─────────────────────────────────────────────

    public List<ProjectCollaborator> getByProjectId(int projectId) {
        List<ProjectCollaborator> list = new ArrayList<>();
        String sql = "SELECT pc.*, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name, " +
                "p.name AS project_name " +
                "FROM project_collaborators pc " +
                "LEFT JOIN employees e ON pc.employee_id = e.id " +
                "LEFT JOIN projects p ON pc.project_id = p.id " +
                "WHERE pc.project_id = ? AND pc.is_active = TRUE " +
                "ORDER BY pc.joined_date";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByProjectId : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY EMPLOYEE ────────────────────────────────────────────

    public List<ProjectCollaborator> getByEmployeeId(int employeeId) {
        List<ProjectCollaborator> list = new ArrayList<>();
        String sql = "SELECT pc.*, " +
                "CONCAT(e.first_name, ' ', e.last_name) AS employee_name, " +
                "p.name AS project_name " +
                "FROM project_collaborators pc " +
                "LEFT JOIN employees e ON pc.employee_id = e.id " +
                "LEFT JOIN projects p ON pc.project_id = p.id " +
                "WHERE pc.employee_id = ? AND pc.is_active = TRUE " +
                "ORDER BY pc.joined_date DESC";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) list.add(mapRow(rs));
        } catch (SQLException e) {
            System.err.println("❌ Erreur getByEmployeeId : " + e.getMessage());
        }
        return list;
    }

    // ─── READ : Employés disponibles (pas encore sur le projet) ──────

    public List<Integer> getAvailableEmployees(int projectId, int rhId) {
        List<Integer> employeeIds = new ArrayList<>();
        String sql = "SELECT e.id FROM employees e " +
                "WHERE e.rh_id = ? " +
                "AND e.id NOT IN (SELECT employee_id FROM project_collaborators " +
                "                 WHERE project_id = ? AND is_active = TRUE)";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ps.setInt(2, projectId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                employeeIds.add(rs.getInt("id"));
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAvailableEmployees : " + e.getMessage());
        }
        return employeeIds;
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public boolean update(ProjectCollaborator collab) {
        String sql = "UPDATE project_collaborators SET role=?, assigned_hours=?, " +
                "worked_hours=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, collab.getRole());
            if (collab.getAssignedHours() != null) {
                ps.setInt(2, collab.getAssignedHours());
            } else {
                ps.setNull(2, Types.INTEGER);
            }
            ps.setInt(3, collab.getWorkedHours());
            ps.setInt(4, collab.getId());

            ps.executeUpdate();
            System.out.println("✅ Collaborateur mis à jour");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update ProjectCollaborator : " + e.getMessage());
            return false;
        }
    }

    // ─── LOG WORKED HOURS ────────────────────────────────────────────

    public boolean logWorkedHours(int collaboratorId, int hours) {
        String sql = "UPDATE project_collaborators SET worked_hours = worked_hours + ? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, hours);
            ps.setInt(2, collaboratorId);
            ps.executeUpdate();
            System.out.println("✅ " + hours + "h ajoutées au collaborateur #" + collaboratorId);
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur logWorkedHours : " + e.getMessage());
            return false;
        }
    }

    // ─── REMOVE FROM PROJECT (soft delete) ───────────────────────────

    public boolean removeFromProject(int collaboratorId) {
        String sql = "UPDATE project_collaborators SET is_active = FALSE, left_date = CURDATE() WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, collaboratorId);
            ps.executeUpdate();
            System.out.println("✅ Collaborateur retiré du projet");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur removeFromProject : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE (hard delete) ────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM project_collaborators WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Collaborateur supprimé définitivement");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete ProjectCollaborator : " + e.getMessage());
            return false;
        }
    }

    // ─── CHECK IF EMPLOYEE IS IN PROJECT ─────────────────────────────

    public boolean isEmployeeInProject(int projectId, int employeeId) {
        String sql = "SELECT COUNT(*) FROM project_collaborators " +
                "WHERE project_id = ? AND employee_id = ? AND is_active = TRUE";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, projectId);
            ps.setInt(2, employeeId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) {
                return rs.getInt(1) > 0;
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur isEmployeeInProject : " + e.getMessage());
        }
        return false;
    }

    // ─── HELPER : mapper un ResultSet → ProjectCollaborator ──────────

    private ProjectCollaborator mapRow(ResultSet rs) throws SQLException {
        ProjectCollaborator collab = new ProjectCollaborator();
        collab.setId(rs.getInt("id"));
        collab.setProjectId(rs.getInt("project_id"));
        collab.setEmployeeId(rs.getInt("employee_id"));
        collab.setRole(rs.getString("role"));
        collab.setAssignedHours(rs.getObject("assigned_hours") != null ? rs.getInt("assigned_hours") : null);
        collab.setWorkedHours(rs.getInt("worked_hours"));
        collab.setJoinedDate(rs.getDate("joined_date"));
        collab.setLeftDate(rs.getDate("left_date"));
        collab.setActive(rs.getBoolean("is_active"));
        collab.setCreatedAt(rs.getTimestamp("created_at"));

        // Champs transients
        collab.setEmployeeName(rs.getString("employee_name"));
        collab.setProjectName(rs.getString("project_name"));

        return collab;
    }
}