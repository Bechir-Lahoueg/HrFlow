package models;

import java.sql.Date;
import java.sql.Timestamp;

/**
 * Modèle représentant un collaborateur assigné à un projet
 */
public class ProjectCollaborator {

    private int id;
    private int projectId;
    private int employeeId;
    private String role;  // Ex: "Chef de projet", "Développeur", "Designer"
    private Integer assignedHours;
    private int workedHours;
    private Date joinedDate;
    private Date leftDate;
    private boolean isActive;
    private Timestamp createdAt;

    // Champs transients pour l'affichage
    private String employeeName;
    private String projectName;

    // ─── Constructeurs ───────────────────────────────────────────────

    public ProjectCollaborator() {}

    public ProjectCollaborator(int projectId, int employeeId, String role, Integer assignedHours, Date joinedDate) {
        this.projectId = projectId;
        this.employeeId = employeeId;
        this.role = role;
        this.assignedHours = assignedHours;
        this.joinedDate = joinedDate;
        this.workedHours = 0;
        this.isActive = true;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getProjectId() { return projectId; }
    public void setProjectId(int projectId) { this.projectId = projectId; }

    public int getEmployeeId() { return employeeId; }
    public void setEmployeeId(int employeeId) { this.employeeId = employeeId; }

    public String getRole() { return role; }
    public void setRole(String role) { this.role = role; }

    public Integer getAssignedHours() { return assignedHours; }
    public void setAssignedHours(Integer assignedHours) { this.assignedHours = assignedHours; }

    public int getWorkedHours() { return workedHours; }
    public void setWorkedHours(int workedHours) { this.workedHours = workedHours; }

    public Date getJoinedDate() { return joinedDate; }
    public void setJoinedDate(Date joinedDate) { this.joinedDate = joinedDate; }

    public Date getLeftDate() { return leftDate; }
    public void setLeftDate(Date leftDate) { this.leftDate = leftDate; }

    public boolean isActive() { return isActive; }
    public void setActive(boolean active) { isActive = active; }

    public Timestamp getCreatedAt() { return createdAt; }
    public void setCreatedAt(Timestamp createdAt) { this.createdAt = createdAt; }

    // Champs transients
    public String getEmployeeName() { return employeeName; }
    public void setEmployeeName(String employeeName) { this.employeeName = employeeName; }

    public String getProjectName() { return projectName; }
    public void setProjectName(String projectName) { this.projectName = projectName; }

    // ─── Méthodes utilitaires ────────────────────────────────────────

    /**
     * Retourne le taux d'avancement des heures travaillées
     */
    public String getHoursProgress() {
        if (assignedHours == null || assignedHours == 0) {
            return workedHours + "h";
        }
        return workedHours + "h / " + assignedHours + "h";
    }

    /**
     * Calcule le pourcentage d'heures utilisées
     */
    public double getHoursUsageRate() {
        if (assignedHours == null || assignedHours == 0) return 0;
        return (double) workedHours / assignedHours * 100;
    }

    /**
     * Vérifie si le collaborateur a dépassé ses heures allouées
     */
    public boolean isOverAllocated() {
        if (assignedHours == null) return false;
        return workedHours > assignedHours;
    }

    @Override
    public String toString() {
        return "ProjectCollaborator{id=" + id + ", employee=" + employeeName +
                ", role='" + role + "', hours=" + getHoursProgress() + "}";
    }
}