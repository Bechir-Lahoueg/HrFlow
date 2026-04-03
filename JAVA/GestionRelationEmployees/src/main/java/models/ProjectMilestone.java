package models;

import java.sql.Date;
import java.sql.Timestamp;

/**
 * Modèle représentant un jalon (milestone) d'un projet
 */
public class ProjectMilestone {

    // Enums
    public enum Status {
        pending, in_progress, completed, delayed
    }

    private int id;
    private int projectId;
    private String name;
    private String description;
    private Date targetDate;
    private Date completionDate;
    private Status status;
    private int completionRate;  // 0-100
    private Timestamp createdAt;

    // Champs transients pour l'affichage
    private String projectName;

    // ─── Constructeurs ───────────────────────────────────────────────

    public ProjectMilestone() {}

    public ProjectMilestone(int projectId, String name, String description, Date targetDate) {
        this.projectId = projectId;
        this.name = name;
        this.description = description;
        this.targetDate = targetDate;
        this.status = Status.pending;
        this.completionRate = 0;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getProjectId() { return projectId; }
    public void setProjectId(int projectId) { this.projectId = projectId; }

    public String getName() { return name; }
    public void setName(String name) { this.name = name; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public Date getTargetDate() { return targetDate; }
    public void setTargetDate(Date targetDate) { this.targetDate = targetDate; }

    public Date getCompletionDate() { return completionDate; }
    public void setCompletionDate(Date completionDate) { this.completionDate = completionDate; }

    public Status getStatus() { return status; }
    public void setStatus(Status status) { this.status = status; }

    public int getCompletionRate() { return completionRate; }
    public void setCompletionRate(int completionRate) { this.completionRate = completionRate; }

    public Timestamp getCreatedAt() { return createdAt; }
    public void setCreatedAt(Timestamp createdAt) { this.createdAt = createdAt; }

    // Champs transients
    public String getProjectName() { return projectName; }
    public void setProjectName(String projectName) { this.projectName = projectName; }

    // ─── Méthodes utilitaires ────────────────────────────────────────

    /**
     * Retourne un emoji de statut pour l'affichage
     */
    public String getStatusEmoji() {
        return switch (status) {
            case pending -> "□";
            case in_progress -> "⏳";
            case completed -> "✅";
            case delayed -> "🔴";
        };
    }

    /**
     * Retourne le nom du statut pour l'affichage
     */
    public String getStatusLabel() {
        return switch (status) {
            case pending -> "En attente";
            case in_progress -> "En cours";
            case completed -> "Terminé";
            case delayed -> "En retard";
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
     * Vérifie si le jalon est en retard
     */
    public boolean isDelayed() {
        if (targetDate == null || status == Status.completed) return false;
        return new java.util.Date().after(targetDate) && status != Status.completed;
    }

    /**
     * Calcule le nombre de jours avant/après l'échéance
     */
    public String getDaysInfo() {
        if (targetDate == null) return "Pas de date cible";
        if (status == Status.completed) {
            if (completionDate != null) {
                long diff = completionDate.getTime() - targetDate.getTime();
                long days = diff / (1000 * 60 * 60 * 24);
                if (days > 0) return "Terminé avec " + days + " jours de retard";
                if (days < 0) return "Terminé " + Math.abs(days) + " jours en avance";
                return "Terminé à temps";
            }
            return "Terminé";
        }

        long diff = targetDate.getTime() - System.currentTimeMillis();
        long days = diff / (1000 * 60 * 60 * 24);

        if (days < 0) return "⚠️ " + Math.abs(days) + " jours de retard";
        if (days == 0) return "🔥 Aujourd'hui";
        if (days <= 7) return "⏰ Dans " + days + " jours";
        return "Dans " + days + " jours";
    }

    @Override
    public String toString() {
        return "ProjectMilestone{id=" + id + ", name='" + name + "', status=" + status +
                ", completion=" + completionRate + "%}";
    }
}