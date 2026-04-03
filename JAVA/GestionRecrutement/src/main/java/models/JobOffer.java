package models;

import java.time.LocalDateTime;
import java.util.Objects;

public class JobOffer {

    private int id;
    private String title;
    private String description;
    private String department;
    private String location;
    private String employmentType;
    private double salaryMin;
    private double salaryMax;
    private String status;
    private LocalDateTime createdAt;
    private int createdBy;

    public JobOffer() {}

    // Constructor without id (for creation)
    public JobOffer(String title, String description, String department,
                    String location, String employmentType,
                    double salaryMin, double salaryMax,
                    String status, LocalDateTime createdAt, int createdBy) {
        this.title = title;
        this.description = description;
        this.department = department;
        this.location = location;
        this.employmentType = employmentType;
        this.salaryMin = salaryMin;
        this.salaryMax = salaryMax;
        this.status = status;
        this.createdAt = createdAt;
        this.createdBy = createdBy;
    }

    // Full constructor
    public JobOffer(int id, String title, String description, String department,
                    String location, String employmentType,
                    double salaryMin, double salaryMax,
                    String status, LocalDateTime createdAt, int createdBy) {
        this(title, description, department, location, employmentType,
             salaryMin, salaryMax, status, createdAt, createdBy);
        this.id = id;
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public String getTitle() { return title; }
    public void setTitle(String title) { this.title = title; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public String getDepartment() { return department; }
    public void setDepartment(String department) { this.department = department; }

    public String getLocation() { return location; }
    public void setLocation(String location) { this.location = location; }

    public String getEmploymentType() { return employmentType; }
    public void setEmploymentType(String employmentType) { this.employmentType = employmentType; }

    public double getSalaryMin() { return salaryMin; }
    public void setSalaryMin(double salaryMin) { this.salaryMin = salaryMin; }

    public double getSalaryMax() { return salaryMax; }
    public void setSalaryMax(double salaryMax) { this.salaryMax = salaryMax; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }

    public LocalDateTime getCreatedAt() { return createdAt; }
    public void setCreatedAt(LocalDateTime createdAt) { this.createdAt = createdAt; }

    public int getCreatedBy() { return createdBy; }
    public void setCreatedBy(int createdBy) { this.createdBy = createdBy; }

    @Override
    public String toString() {
        return "JobOffer{id=" + id + ", title='" + title + "', status='" + status + "'}";
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof JobOffer)) return false;
        JobOffer that = (JobOffer) o;
        return id == that.id;
    }

    @Override
    public int hashCode() {
        return Objects.hash(id);
    }
}
