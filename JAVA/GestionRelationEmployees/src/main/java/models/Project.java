package models;

import java.math.BigDecimal;
import java.sql.Date;
import java.sql.Timestamp;

/**
 * Modèle représentant un projet
 */
public class Project {

    // Enums
    public enum Status {
        planning, in_progress, on_hold, completed, cancelled
    }

    public enum Priority {
        low, medium, high, critical
    }

    // Attributs
    private int id;
    private String name;
    private String description;
    private int rhId;
    private Status status;
    private Priority priority;
    private Date startDate;
    private Date endDate;
    private Integer estimatedHours;
    private int actualHours;
    private BigDecimal budget;
    private int completionRate;  // 0-100
    private Timestamp createdAt;
    private Timestamp updatedAt;

    // Champs transients pour l'affichage
    private String rhName;
    private int collaboratorCount;
    private int taskCount;
    private int completedTaskCount;

    // ─── Constructeurs ───────────────────────────────────────────────

    public Project() {}

    public Project(String name, String description, int rhId, Priority priority,
                   Date startDate, Date endDate, Integer estimatedHours, BigDecimal budget) {
        this.name = name;
        this.description = description;
        this.rhId = rhId;
        this.priority = priority;
        this.startDate = startDate;
        this.endDate = endDate;
        this.estimatedHours = estimatedHours;
        this.budget = budget;
        this.status = Status.planning;
        this.completionRate = 0;
        this.actualHours = 0;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public String getName() { return name; }
    public void setName(String name) { this.name = name; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public int getRhId() { return rhId; }
    public void setRhId(int rhId) { this.rhId = rhId; }

    public Status getStatus() { return status; }
    public void setStatus(Status status) { this.status = status; }

    public Priority getPriority() { return priority; }
    public void setPriority(Priority priority) { this.priority = priority; }

    public Date getStartDate() { return startDate; }
    public void setStartDate(Date startDate) { this.startDate = startDate; }

    public Date getEndDate() { return endDate; }
    public void setEndDate(Date endDate) { this.endDate = endDate; }

    public Integer getEstimatedHours() { return estimatedHours; }
    public void setEstimatedHours(Integer estimatedHours) { this.estimatedHours = estimatedHours; }

    public int getActualHours() { return actualHours; }
    public void setActualHours(int actualHours) { this.actualHours = actualHours; }

    public BigDecimal getBudget() { return budget; }
    public void setBudget(BigDecimal budget) { this.budget = budget; }

    public int getCompletionRate() { return completionRate; }
    public void setCompletionRate(int completionRate) { this.completionRate = completionRate; }

    public Timestamp getCreatedAt() { return createdAt; }
    public void setCreatedAt(Timestamp createdAt) { this.createdAt = createdAt; }

    public Timestamp getUpdatedAt() { return updatedAt; }
    public void setUpdatedAt(Timestamp updatedAt) { this.updatedAt = updatedAt; }

    // Champs transients
    public String getRhName() { return rhName; }
    public void setRhName(String rhName) { this.rhName = rhName; }

    public int getCollaboratorCount() { return collaboratorCount; }
    public void setCollaboratorCount(int collaboratorCount) { this.collaboratorCount = collaboratorCount; }

    public int getTaskCount() { return taskCount; }
    public void setTaskCount(int taskCount) { this.taskCount = taskCount; }

    public int getCompletedTaskCount() { return completedTaskCount; }
    public void setCompletedTaskCount(int completedTaskCount) { this.completedTaskCount = completedTaskCount; }

    // ─── Méthodes utilitaires ────────────────────────────────────────

    /**
     * Retourne un emoji de statut pour l'affichage
     */
    public String getStatusEmoji() {
        return switch (status) {
            case planning -> "📋";
            case in_progress -> "🟢";
            case on_hold -> "🟡";
            case completed -> "✅";
            case cancelled -> "❌";
        };
    }

    /**
     * Retourne un emoji de priorité pour l'affichage
     */
    public String getPriorityEmoji() {
        return switch (priority) {
            case low -> "🟢";
            case medium -> "🟡";
            case high -> "🟠";
            case critical -> "🔴";
        };
    }

    /**
     * Retourne une barre de progression visuelle
     */
    public String getProgressBar() {
        int filled = completionRate / 10;
        int empty = 10 - filled;
        return "█".repeat(filled) + "░".repeat(empty) + " " + completionRate + "%";
    }

    /**
     * Vérifie si le projet est en retard
     */
    public boolean isDelayed() {
        if (endDate == null || status == Status.completed || status == Status.cancelled) {
            return false;
        }
        return new java.util.Date().after(endDate) && status != Status.completed;
    }

    /**
     * Calcule le taux d'utilisation du budget (heures réelles / heures estimées)
     */
    public double getBudgetUsageRate() {
        if (estimatedHours == null || estimatedHours == 0) return 0;
        return (double) actualHours / estimatedHours * 100;
    }

    @Override
    public String toString() {
        return "Project{id=" + id + ", name='" + name + "', status=" + status + ", completion=" + completionRate + "%}";
    }
}