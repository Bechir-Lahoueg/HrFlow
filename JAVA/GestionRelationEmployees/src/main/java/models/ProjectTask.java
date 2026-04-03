package models;

import java.sql.Date;
import java.sql.Timestamp;

/**
 * Modèle représentant une tâche d'un projet (pour Kanban)
 */
public class ProjectTask {

    // Enums
    public enum Status {
        todo, in_progress, review, done
    }

    public enum Priority {
        low, medium, high
    }

    private int id;
    private int projectId;
    private Integer assignedTo;  // employee_id, peut être null
    private String title;
    private String description;
    private Status status;
    private Priority priority;
    private Integer estimatedHours;
    private int actualHours;
    private Date dueDate;
    private Date completedDate;
    private int orderIndex;  // Pour le tri dans le Kanban
    private Timestamp createdAt;
    private Timestamp updatedAt;

    // Champs transients pour l'affichage
    private String projectName;
    private String assignedToName;

    // ─── Constructeurs ───────────────────────────────────────────────

    public ProjectTask() {}

    public ProjectTask(int projectId, String title, String description,
                       Priority priority, Integer estimatedHours, Date dueDate) {
        this.projectId = projectId;
        this.title = title;
        this.description = description;
        this.priority = priority;
        this.estimatedHours = estimatedHours;
        this.dueDate = dueDate;
        this.status = Status.todo;
        this.actualHours = 0;
        this.orderIndex = 0;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getProjectId() { return projectId; }
    public void setProjectId(int projectId) { this.projectId = projectId; }

    public Integer getAssignedTo() { return assignedTo; }
    public void setAssignedTo(Integer assignedTo) { this.assignedTo = assignedTo; }

    public String getTitle() { return title; }
    public void setTitle(String title) { this.title = title; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public Status getStatus() { return status; }
    public void setStatus(Status status) { this.status = status; }

    public Priority getPriority() { return priority; }
    public void setPriority(Priority priority) { this.priority = priority; }

    public Integer getEstimatedHours() { return estimatedHours; }
    public void setEstimatedHours(Integer estimatedHours) { this.estimatedHours = estimatedHours; }

    public int getActualHours() { return actualHours; }
    public void setActualHours(int actualHours) { this.actualHours = actualHours; }

    public Date getDueDate() { return dueDate; }
    public void setDueDate(Date dueDate) { this.dueDate = dueDate; }

    public Date getCompletedDate() { return completedDate; }
    public void setCompletedDate(Date completedDate) { this.completedDate = completedDate; }

    public int getOrderIndex() { return orderIndex; }
    public void setOrderIndex(int orderIndex) { this.orderIndex = orderIndex; }

    public Timestamp getCreatedAt() { return createdAt; }
    public void setCreatedAt(Timestamp createdAt) { this.createdAt = createdAt; }

    public Timestamp getUpdatedAt() { return updatedAt; }
    public void setUpdatedAt(Timestamp updatedAt) { this.updatedAt = updatedAt; }

    // Champs transients
    public String getProjectName() { return projectName; }
    public void setProjectName(String projectName) { this.projectName = projectName; }

    public String getAssignedToName() { return assignedToName; }
    public void setAssignedToName(String assignedToName) { this.assignedToName = assignedToName; }

    // ─── Méthodes utilitaires ────────────────────────────────────────

    /**
     * Retourne un emoji de statut pour l'affichage
     */
    public String getStatusEmoji() {
        return switch (status) {
            case todo -> "□";
            case in_progress -> "⏳";
            case review -> "👁️";
            case done -> "✅";
        };
    }

    /**
     * Retourne un emoji de priorité pour l'affichage
     */
    public String getPriorityEmoji() {
        return switch (priority) {
            case low -> "🟢";
            case medium -> "🟡";
            case high -> "🔴";
        };
    }

    /**
     * Retourne le nom du statut pour l'affichage
     */
    public String getStatusLabel() {
        return switch (status) {
            case todo -> "À faire";
            case in_progress -> "En cours";
            case review -> "Review";
            case done -> "Terminé";
        };
    }

    /**
     * Vérifie si la tâche est en retard
     */
    public boolean isOverdue() {
        if (dueDate == null || status == Status.done) return false;
        return new java.util.Date().after(dueDate);
    }

    /**
     * Retourne le temps restant avant l'échéance
     */
    public String getDaysUntilDue() {
        if (dueDate == null) return "Pas d'échéance";
        if (status == Status.done) return "Terminé";

        long diff = dueDate.getTime() - System.currentTimeMillis();
        long days = diff / (1000 * 60 * 60 * 24);

        if (days < 0) return "⚠️ " + Math.abs(days) + " jours de retard";
        if (days == 0) return "🔥 Aujourd'hui";
        if (days == 1) return "⏰ Demain";
        return days + " jours";
    }

    /**
     * Calcule le taux d'utilisation du temps estimé
     */
    public double getTimeUsageRate() {
        if (estimatedHours == null || estimatedHours == 0) return 0;
        return (double) actualHours / estimatedHours * 100;
    }

    @Override
    public String toString() {
        return "ProjectTask{id=" + id + ", title='" + title + "', status=" + status +
                ", assignedTo=" + assignedToName + "}";
    }
}