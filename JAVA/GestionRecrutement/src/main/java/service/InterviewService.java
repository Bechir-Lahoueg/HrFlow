package service;

import models.Interview;
import utils.Mydb;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class InterviewService {

    public InterviewService() {
    }

    public void scheduleInterview(Interview interview) throws SQLException {
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            throw new SQLException("Database connection is not available");

        if (interview.getApplicationId() <= 0) {
            throw new IllegalArgumentException("Invalid application ID");
        }
        if (interview.getInterviewerId() <= 0) {
            throw new IllegalArgumentException("Invalid interviewer ID");
        }
        if (interview.getInterviewDate() == null) {
            throw new IllegalArgumentException("Interview date cannot be null");
        }

        // Validate Type
        List<String> validTypes = List.of("HR", "TECHNICAL", "FINAL");
        String type = interview.getType();
        if (type == null || !validTypes.contains(type)) {
            throw new IllegalArgumentException("Invalid interview type. Must be one of: " + validTypes);
        }

        // Validate Result
        List<String> validResults = List.of("PENDING", "PASS", "FAIL");
        String result = interview.getResult();
        if (result == null || !validResults.contains(result)) {
            throw new IllegalArgumentException("Invalid interview result. Must be one of: " + validResults);
        }

        String sql = "INSERT INTO interviews(application_id, interviewer_id, interview_date, type, meeting_link, location, feedback, score, result) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, interview.getApplicationId());
            ps.setInt(2, interview.getInterviewerId());
            ps.setTimestamp(3, Timestamp.valueOf(interview.getInterviewDate()));
            ps.setString(4, interview.getType());
            ps.setString(5, interview.getMeetingLink());
            ps.setString(6, interview.getLocation());
            ps.setString(7, interview.getFeedback());
            ps.setInt(8, interview.getScore());
            ps.setString(9, interview.getResult());
            ps.executeUpdate();
        }
    }

    public void updateInterview(Interview interview) throws SQLException {
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            throw new SQLException("Database connection is not available");

        if (interview.getInterviewDate() == null) {
            throw new IllegalArgumentException("Interview date cannot be null");
        }

        // Validate Type
        List<String> validTypes = List.of("HR", "TECHNICAL", "FINAL");
        String type = interview.getType();
        if (type == null || !validTypes.contains(type)) {
            throw new IllegalArgumentException("Invalid interview type. Must be one of: " + validTypes);
        }

        // Validate Result
        List<String> validResults = List.of("PENDING", "PASS", "FAIL");
        String result = interview.getResult();
        if (result == null || !validResults.contains(result)) {
            throw new IllegalArgumentException("Invalid interview result. Must be one of: " + validResults);
        }

        String sql = "UPDATE interviews SET interviewer_id=?, interview_date=?, type=?, meeting_link=?, location=?, feedback=?, score=?, result=? WHERE id=?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, interview.getInterviewerId());
            ps.setTimestamp(2, Timestamp.valueOf(interview.getInterviewDate()));
            ps.setString(3, interview.getType());
            ps.setString(4, interview.getMeetingLink());
            ps.setString(5, interview.getLocation());
            ps.setString(6, interview.getFeedback());
            ps.setInt(7, interview.getScore());
            ps.setString(8, interview.getResult());
            ps.setInt(9, interview.getId());
            ps.executeUpdate();
        }
    }

    public boolean deleteInterview(int id) throws SQLException {
        softDeleteInterview(id);
        return true;
    }

    public void softDeleteInterview(int id) throws SQLException {
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            throw new SQLException("Database connection is not available");

        String sql = "UPDATE interviews SET is_deleted = TRUE WHERE id = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    public void hardDeleteInterview(int id) throws SQLException {
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            throw new SQLException("Database connection is not available");

        String sql = "DELETE FROM interviews WHERE id = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    public List<Interview> getActiveInterviews() throws SQLException {
        List<Interview> list = new ArrayList<>();
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            return list;

        // Optimized SQL: Using LEFT JOIN and reading candidate_name from applications
        // Interviewer name now comes from users table (role-based filtering)
        String sql = "SELECT i.*, a.candidate_name, " +
                "u.username as interviewer_name, " +
                "j.title as job_title " +
                "FROM interviews i " +
                "JOIN applications a ON i.application_id = a.id " +
                "LEFT JOIN users u ON i.interviewer_id = u.id " +
                "LEFT JOIN job_offer j ON a.job_offer_id = j.id " +
                "WHERE i.is_deleted = FALSE";

        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                java.sql.Timestamp tsDate = rs.getTimestamp("interview_date");
                java.time.LocalDateTime interviewDate = (tsDate != null) ? tsDate.toLocalDateTime() : null;

                list.add(new Interview(
                        rs.getInt("id"), rs.getInt("application_id"), rs.getInt("interviewer_id"),
                        interviewDate, rs.getString("type"),
                        rs.getString("location"), rs.getString("meeting_link"),
                        rs.getString("feedback"), rs.getInt("score"), rs.getString("result"),
                        rs.getString("candidate_name"),
                        rs.getString("interviewer_name") != null ? rs.getString("interviewer_name") : "Unassigned",
                        rs.getString("job_title")));
            }
        }
        return list;
    }

    public List<String> getAllInterviewers() throws SQLException {
        List<String> interviewers = new ArrayList<>();
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            return interviewers;

        String sql = "SELECT id, username FROM users WHERE role = 'RH' ORDER BY username";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                interviewers.add(rs.getString("username") + " (ID: " + rs.getInt("id") + ")");
            }
        }
        return interviewers;
    }

    public List<String> getDistinctInterviewTypes() throws SQLException {
        List<String> types = new ArrayList<>();
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            return types;

        String sql = "SELECT DISTINCT type FROM interviews WHERE is_deleted = FALSE ORDER BY type";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                String type = rs.getString("type");
                if (type != null) {
                    types.add(type);
                }
            }
        }
        return types;
    }

    public List<String> getDistinctResults() throws SQLException {
        List<String> results = new ArrayList<>();
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            return results;

        String sql = "SELECT DISTINCT result FROM interviews WHERE is_deleted = FALSE ORDER BY result";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                String result = rs.getString("result");
                if (result != null) {
                    results.add(result);
                }
            }
        }
        return results;
    }

    // --- ANALYTICS LOGIC ---

    public double getInterviewPassRate() throws SQLException {
        Connection cnx = Mydb.getInstance().getConnection();
        if (cnx == null)
            return 0;

        String sql = "SELECT " +
                "SUM(CASE WHEN result = 'PASS' THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as pass_rate " +
                "FROM interviews " +
                "WHERE result != 'PENDING' AND is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            if (rs.next()) {
                return rs.getDouble("pass_rate");
            }
        }
        return 0;
    }
}