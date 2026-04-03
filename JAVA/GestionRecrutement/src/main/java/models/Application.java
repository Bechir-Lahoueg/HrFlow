package models;

import java.time.LocalDateTime;
import java.util.Objects;

public class Application {

    private int id;
    private String candidateName;
    private int jobOfferId;
    private String cvPath;
    private String coverLetterPath;
    private String status;
    private String notes;
    private LocalDateTime appliedAt;
    private String jobTitle; // Joined field
    private String department;
    private String experienceLevel;
    private String emailAddress;
    private int employeeId;

    public Application() {
    }

    // Constructor for manual entry (including new fields)
    public Application(String candidateName, int jobOfferId,
            String cvPath, String coverLetterPath,
            String status, String notes, LocalDateTime appliedAt,
            String department, String experienceLevel, String emailAddress, int employeeId) {
        this.candidateName = candidateName;
        this.jobOfferId = jobOfferId;
        this.cvPath = cvPath;
        this.coverLetterPath = coverLetterPath;
        this.status = status;
        this.notes = notes;
        this.appliedAt = appliedAt;
        this.department = department;
        this.experienceLevel = experienceLevel;
        this.emailAddress = emailAddress;
        this.employeeId = employeeId;
    }

    // Full constructor (for database mapping)
    public Application(int id, String candidateName, int jobOfferId,
            String cvPath, String coverLetterPath,
            String status, String notes, LocalDateTime appliedAt,
            String jobTitle, String department, String experienceLevel, String emailAddress, int employeeId) {
        this.id = id;
        this.candidateName = candidateName;
        this.jobOfferId = jobOfferId;
        this.cvPath = cvPath;
        this.coverLetterPath = coverLetterPath;
        this.status = status;
        this.notes = notes;
        this.appliedAt = appliedAt;
        this.jobTitle = jobTitle;
        this.department = department;
        this.experienceLevel = experienceLevel;
        this.emailAddress = emailAddress;
        this.employeeId = employeeId;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public int getJobOfferId() {
        return jobOfferId;
    }

    public void setJobOfferId(int jobOfferId) {
        this.jobOfferId = jobOfferId;
    }

    public String getCvPath() {
        return cvPath;
    }

    public void setCvPath(String cvPath) {
        this.cvPath = cvPath;
    }

    public String getCoverLetterPath() {
        return coverLetterPath;
    }

    public void setCoverLetterPath(String coverLetterPath) {
        this.coverLetterPath = coverLetterPath;
    }

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public String getNotes() {
        return notes;
    }

    public void setNotes(String notes) {
        this.notes = notes;
    }

    public LocalDateTime getAppliedAt() {
        return appliedAt;
    }

    public void setAppliedAt(LocalDateTime appliedAt) {
        this.appliedAt = appliedAt;
    }

    public String getJobTitle() {
        return jobTitle;
    }

    public void setJobTitle(String jobTitle) {
        this.jobTitle = jobTitle;
    }

    public String getCandidateName() {
        return candidateName;
    }

    public void setCandidateName(String candidateName) {
        this.candidateName = candidateName;
    }

    public String getDepartment() {
        return department;
    }

    public void setDepartment(String department) {
        this.department = department;
    }

    public String getExperienceLevel() {
        return experienceLevel;
    }

    public void setExperienceLevel(String experienceLevel) {
        this.experienceLevel = experienceLevel;
    }

    public String getEmailAddress() {
        return emailAddress;
    }

    public void setEmailAddress(String emailAddress) {
        this.emailAddress = emailAddress;
    }

    public int getEmployeeId() {
        return employeeId;
    }

    public void setEmployeeId(int employeeId) {
        this.employeeId = employeeId;
    }

    @Override
    public String toString() {
        return "Application{id=" + id +
                ", jobOfferId=" + jobOfferId +
                ", status='" + status + "'}";
    }

    @Override
    public boolean equals(Object o) {
        if (this == o)
            return true;
        if (!(o instanceof Application))
            return false;
        Application that = (Application) o;
        return id == that.id;
    }

    @Override
    public int hashCode() {
        return Objects.hash(id);
    }
}
