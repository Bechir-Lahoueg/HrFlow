package service;

import models.JobOffer;
import utils.Mydb;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class JobOfferService {
    private Connection cnx;

    public JobOfferService() {
        this.cnx = Mydb.getInstance().getConnection();
    }

    public void createOffer(JobOffer offer) throws SQLException {
        if (offer.getTitle() == null || offer.getTitle().trim().isEmpty()) {
            throw new IllegalArgumentException("Title cannot be empty");
        }
        if (offer.getDescription() == null || offer.getDescription().trim().isEmpty()) {
            throw new IllegalArgumentException("Description cannot be empty");
        }
        if (offer.getSalaryMin() < 0) {
            throw new IllegalArgumentException("Salary cannot be negative");
        }
        if (offer.getSalaryMax() < offer.getSalaryMin()) {
            throw new IllegalArgumentException("Max salary cannot be less than min salary");
        }

        // Validate Status
        String status = offer.getStatus();
        if (status == null || (!status.equals("OPEN") && !status.equals("CLOSED") && !status.equals("DRAFT"))) {
            throw new IllegalArgumentException("Invalid status. Must be OPEN, CLOSED, or DRAFT");
        }

        String sql = "INSERT INTO job_offer(title, description, department, location, employmentType, salary_min, salary_max, status, created_at, created_by) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setString(1, offer.getTitle());
            ps.setString(2, offer.getDescription());
            ps.setString(3, offer.getDepartment());
            ps.setString(4, offer.getLocation());
            ps.setString(5, offer.getEmploymentType());
            ps.setDouble(6, offer.getSalaryMin());
            ps.setDouble(7, offer.getSalaryMax());
            ps.setString(8, offer.getStatus());
            ps.setTimestamp(9, Timestamp.valueOf(offer.getCreatedAt()));
            ps.setInt(10, offer.getCreatedBy());
            ps.executeUpdate();
        }
    }

    public void updateJobOffer(JobOffer offer) throws SQLException {
        if (offer.getTitle() == null || offer.getTitle().trim().isEmpty()) {
            throw new IllegalArgumentException("Title cannot be empty");
        }
        if (offer.getSalaryMin() < 0) {
            throw new IllegalArgumentException("Salary cannot be negative");
        }

        // Validate Status
        String status = offer.getStatus();
        if (status == null || (!status.equals("OPEN") && !status.equals("CLOSED") && !status.equals("DRAFT"))) {
            throw new IllegalArgumentException("Invalid status. Must be OPEN, CLOSED, or DRAFT");
        }

        String sql = "UPDATE job_offer SET title=?, description=?, department=?, location=?, employmentType=?, salary_min=?, salary_max=?, status=? WHERE id=?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setString(1, offer.getTitle());
            ps.setString(2, offer.getDescription());
            ps.setString(3, offer.getDepartment());
            ps.setString(4, offer.getLocation());
            ps.setString(5, offer.getEmploymentType());
            ps.setDouble(6, offer.getSalaryMin());
            ps.setDouble(7, offer.getSalaryMax());
            ps.setString(8, offer.getStatus());
            ps.setInt(9, offer.getId());
            ps.executeUpdate();
        }
    }

    public void softDeleteJobOffer(int id) throws SQLException {
        String sql = "UPDATE job_offer SET is_deleted = TRUE WHERE id = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    public void hardDeleteJobOffer(int id) throws SQLException {
        String sql = "DELETE FROM job_offer WHERE id = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    public List<JobOffer> getAllActiveOffers() throws SQLException {
        List<JobOffer> offers = new ArrayList<>();
        String sql = "SELECT * FROM job_offer WHERE is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                offers.add(new JobOffer(
                        rs.getInt("id"), rs.getString("title"), rs.getString("description"),
                        rs.getString("department"), rs.getString("location"),
                        rs.getString("employmentType"), rs.getDouble("salary_min"),
                        rs.getDouble("salary_max"), rs.getString("status"),
                        rs.getTimestamp("created_at").toLocalDateTime(), rs.getInt("created_by")));
            }
        }
        return offers;
    }

    public List<String> getLocations () throws SQLException {
        List<String> locations = new ArrayList<>();
        String sql = "SELECT DISTINCT location FROM job_offer WHERE is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                locations.add(rs.getString("location"));
            }
        }
        return locations;
    }
    public List<String> getDepartments () throws SQLException {
        List<String> departments  = new ArrayList<>();
        String sql = "SELECT DISTINCT department FROM job_offer WHERE is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                departments.add(rs.getString("department"));
            }
        }
        return departments;
    }
    public List<String> getEmploymentTypes () throws SQLException {
        List<String> types = new ArrayList<>();
        String sql = "SELECT DISTINCT employmentType FROM job_offer WHERE is_deleted = FALSE";
        try (Statement stmt = cnx.createStatement(); ResultSet rs = stmt.executeQuery(sql)) {
            while (rs.next()) {
                types.add(rs.getString("employmentType"));
            }
        }
        return types;
    }
    
    public void addJobOffer(JobOffer jobOffer) throws SQLException {
        String sql = "INSERT INTO job_offer (title, description, department, location, employment_type, salary_min, salary_max, status, created_at, created_by) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setString(1, jobOffer.getTitle());
            ps.setString(2, jobOffer.getDescription());
            ps.setString(3, jobOffer.getDepartment());
            ps.setString(4, jobOffer.getLocation());
            ps.setString(5, jobOffer.getEmploymentType());
            ps.setDouble(6, jobOffer.getSalaryMin());
            ps.setDouble(7, jobOffer.getSalaryMax());
            ps.setString(8, jobOffer.getStatus());
            ps.setTimestamp(9, Timestamp.valueOf(jobOffer.getCreatedAt()));
            ps.setInt(10, jobOffer.getCreatedBy());
            ps.executeUpdate();
        }
    }
}