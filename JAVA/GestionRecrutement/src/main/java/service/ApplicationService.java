package service;

import models.Application;
import utils.Mydb;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class ApplicationService {
    private Connection cnx;

    public ApplicationService() {
        this.cnx = Mydb.getInstance().getConnection();
    }

    public void addApplication(Application app) throws SQLException {
        if (app.getJobOfferId() <= 0) {
            throw new IllegalArgumentException("Invalid job offer ID");
        }
        if (app.getCvPath() == null || app.getCvPath().trim().isEmpty()) {
            throw new IllegalArgumentException("CV path cannot be empty");
        }
        if (app.getCandidateName() == null || app.getCandidateName().trim().isEmpty()) {
            throw new IllegalArgumentException("Candidate name cannot be empty");
        }

        // Validate Status
        String status = app.getStatus();
        List<String> validStatuses = List.of(
                "APPLIED", "SCREENING", "SHORTLISTED",
                "INTERVIEW", "OFFERED", "REJECTED", "HIRED");
        if (status == null || !validStatuses.contains(status)) {
            throw new IllegalArgumentException("Invalid status. Must be one of: " + validStatuses);
        }

        String sql = "INSERT INTO applications(candidate_name, job_offer_id, cv_path, cover_letter_path, status, notes, applied_at, Department, experience_level, EmailAddress, employee_id) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setString(1, app.getCandidateName());
            ps.setInt(2, app.getJobOfferId());
            ps.setString(3, app.getCvPath());
            ps.setString(4, app.getCoverLetterPath());
            ps.setString(5, app.getStatus());
            ps.setString(6, app.getNotes());
            ps.setTimestamp(7, Timestamp.valueOf(app.getAppliedAt()));
            ps.setString(8, app.getDepartment());
            ps.setString(9, app.getExperienceLevel());
            ps.setString(10, app.getEmailAddress());
            ps.setInt(11, app.getEmployeeId());
            ps.executeUpdate();
        }
    }

    public void updateApplication(Application app) throws SQLException {
        // Validate Status
        String status = app.getStatus();
        List<String> validStatuses = List.of(
                "APPLIED", "SCREENING", "SHORTLISTED",
                "INTERVIEW", "OFFERED", "REJECTED", "HIRED");

        if (status == null || !validStatuses.contains(status)) {
            throw new IllegalArgumentException("Invalid status: " + status +
                    ". Must be one of: " + validStatuses);
        }

        String sql = "UPDATE applications SET candidate_name=?, status=?, notes=?, Department=?, experience_level=?, EmailAddress=?, employee_id=? WHERE id=?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setString(1, app.getCandidateName());
            ps.setString(2, app.getStatus());
            ps.setString(3, app.getNotes());
            ps.setString(4, app.getDepartment());
            ps.setString(5, app.getExperienceLevel());
            ps.setString(6, app.getEmailAddress());
            ps.setInt(7, app.getEmployeeId());
            ps.setInt(8, app.getId());
            ps.executeUpdate();
        }
    }

    // --- DELETE LOGIC ---

    /**
     * SOFT DELETE: Marks the record as deleted without removing it from the disk.
     * Use this for everyday operations to keep history.
     */
    public void softDeleteApplication(int id) throws SQLException {
        String sql = "UPDATE applications SET is_deleted = TRUE WHERE id = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    /**
     * HARD DELETE: Permanently removes the record and its children (Interviews) via
     * CASCADE.
     * Use this only for data cleaning or GDPR compliance.
     */
    public void hardDeleteApplication(int id) throws SQLException {
        String sql = "DELETE FROM applications WHERE id = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    // --- READ LOGIC ---

    public List<Application> getActiveApplications() throws SQLException {
        List<Application> apps = new ArrayList<>();
        String sql = "SELECT a.*, j.title as job_title " +
                "FROM applications a " +
                "JOIN job_offer j ON a.job_offer_id = j.id " +
                "WHERE a.is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                apps.add(mapResultSetToApplication(rs));
            }
        }
        return apps;
    }

    private Application mapResultSetToApplication(ResultSet rs) throws SQLException {
        return new Application(
                rs.getInt("id"),
                rs.getString("candidate_name"),
                rs.getInt("job_offer_id"),
                rs.getString("cv_path"),
                rs.getString("cover_letter_path"),
                rs.getString("status"),
                rs.getString("notes"),
                rs.getTimestamp("applied_at").toLocalDateTime(),
                rs.getString("job_title"),
                rs.getString("Department"),
                rs.getString("experience_level"),
                rs.getString("EmailAddress"),
                // employee_id might be null if migrating from old schema where it didn't exist
                rs.getObject("employee_id") != null ? rs.getInt("employee_id") : 0);
    }

    public List<String> getAllCandidates() throws SQLException {
        List<String> candidates = new ArrayList<>();
        String sql = "SELECT id, username FROM users";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                candidates.add(rs.getString("username") + " (ID: " + rs.getInt("id") + ")");
            }
        }
        return candidates;
    }

    /**
     * Get applications submitted by a specific email (was candidate_id)
     */
    public List<Application> getApplicationsByEmail(String email) throws SQLException {
        List<Application> apps = new ArrayList<>();
        String sql = "SELECT a.*, j.title as job_title " +
                "FROM applications a " +
                "JOIN job_offer j ON a.job_offer_id = j.id " +
                "WHERE a.EmailAddress = ? AND a.is_deleted = FALSE";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setString(1, email);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                apps.add(mapResultSetToApplication(rs));
            }
        }
        return apps;
    }

    // --- ANALYTICS LOGIC ---

    public double getHiringVelocity() throws SQLException {
        String sql = "SELECT AVG(DATEDIFF(NOW(), applied_at)) as avg_days " +
                "FROM applications " +
                "WHERE status = 'HIRED' AND is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            if (rs.next()) {
                return rs.getDouble("avg_days");
            }
        }
        return 0;
    }

    public java.util.Map<String, Integer> getPipelineFunnelData() throws SQLException {
        java.util.Map<String, Integer> funnelData = new java.util.LinkedHashMap<>();
        String sql = "SELECT status, COUNT(*) as count " +
                "FROM applications " +
                "WHERE is_deleted = FALSE " +
                "GROUP BY status";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                funnelData.put(rs.getString("status"), rs.getInt("count"));
            }
        }
        return funnelData;
    }

    public java.util.Map<String, Integer> getApplicationTrends() throws SQLException {
        java.util.Map<String, Integer> trends = new java.util.TreeMap<>();
        String sql = "SELECT DATE(applied_at) as date, COUNT(*) as count " +
                "FROM applications " +
                "WHERE is_deleted = FALSE AND applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) " +
                "GROUP BY DATE(applied_at) " +
                "ORDER BY date ASC";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                trends.put(rs.getString("date").toString(), rs.getInt("count"));
            }
        }
        return trends;
    }

    public java.util.Map<String, Integer> getDepartmentDistribution() throws SQLException {
        java.util.Map<String, Integer> distribution = new java.util.HashMap<>();
        String sql = "SELECT Department, COUNT(*) as count " +
                "FROM applications " +
                "WHERE is_deleted = FALSE " +
                "GROUP BY Department";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                distribution.put(rs.getString("Department"), rs.getInt("count"));
            }
        }
        return distribution;
    }

    public List<String> getPositions() throws SQLException {
        List<String> positions = new ArrayList<>();
        String sql = "SELECT DISTINCT title FROM job_offer where is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                positions.add(rs.getString("title"));
            }
        }
        return positions;
    }

    public List<String> getDistinctStatuses() throws SQLException {
        List<String> statuses = new ArrayList<>();
        String sql = "SELECT DISTINCT status FROM applications WHERE is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                statuses.add(rs.getString("status"));
            }
        }
        return statuses;
    }

    public List<String> getDistinctSources() throws SQLException {
        List<String> sources = new ArrayList<>();
        String sql = "SELECT DISTINCT source FROM applications WHERE is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                sources.add(rs.getString("source"));
            }
        }
        return sources;
    }
}