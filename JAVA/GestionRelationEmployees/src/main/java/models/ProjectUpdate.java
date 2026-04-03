package models;

import java.sql.Timestamp;

/**
 * Modèle représentant une mise à jour/activité sur un projet
 */
public class ProjectUpdate {

    // Enums
    public enum UpdateType {
        status_change, milestone, task, comment, document
    }

    private int id;
    private int projectId;
    private int userId;
    private UpdateType updateType;
    private String title;
    private String content;
    private Timestamp createdAt;

    // Champs transients pour l'affichage
    private String projectName;
    private String username;

    // ─── Constructeurs ───────────────────────────────────────────────

    public ProjectUpdate() {}

    public ProjectUpdate(int projectId, int userId, UpdateType updateType,
                         String title, String content) {
        this.projectId = projectId;
        this.userId = userId;
        this.updateType = updateType;
        this.title = title;
        this.content = content;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getProjectId() { return projectId; }
    public void setProjectId(int projectId) { this.projectId = projectId; }

    public int getUserId() { return userId; }
    public void setUserId(int userId) { this.userId = userId; }

    public UpdateType getUpdateType() { return updateType; }
    public void setUpdateType(UpdateType updateType) { this.updateType = updateType; }

    public String getTitle() { return title; }
    public void setTitle(String title) { this.title = title; }

    public String getContent() { return content; }
    public void setContent(String content) { this.content = content; }

    public Timestamp getCreatedAt() { return createdAt; }
    public void setCreatedAt(Timestamp createdAt) { this.createdAt = createdAt; }

    // Champs transients
    public String getProjectName() { return projectName; }
    public void setProjectName(String projectName) { this.projectName = projectName; }

    public String getUsername() { return username; }
    public void setUsername(String username) { this.username = username; }

    // ─── Méthodes utilitaires ────────────────────────────────────────

    /**
     * Retourne un emoji de type pour l'affichage
     */
    public String getTypeEmoji() {
        return switch (updateType) {
            case status_change -> "🔄";
            case milestone -> "🎯";
            case task -> "✓";
            case comment -> "💬";
            case document -> "📎";
        };
    }

    /**
     * Retourne le nom du type pour l'affichage
     */
    public String getTypeLabel() {
        return switch (updateType) {
            case status_change -> "Changement de statut";
            case milestone -> "Jalon";
            case task -> "Tâche";
            case comment -> "Commentaire";
            case document -> "Document";
        };
    }

    /**
     * Retourne une version formatée de la date
     */
    public String getFormattedDate() {
        if (createdAt == null) return "";

        long diff = System.currentTimeMillis() - createdAt.getTime();
        long minutes = diff / (1000 * 60);
        long hours = diff / (1000 * 60 * 60);
        long days = diff / (1000 * 60 * 60 * 24);

        if (minutes < 1) return "À l'instant";
        if (minutes < 60) return "Il y a " + minutes + " min";
        if (hours < 24) return "Il y a " + hours + "h";
        if (days == 1) return "Hier";
        if (days < 7) return "Il y a " + days + " jours";

        return createdAt.toString().substring(0, 16);  // YYYY-MM-DD HH:MM
    }

    /**
     * Retourne un résumé court pour la liste d'activités
     */
    public String getSummary() {
        String emoji = getTypeEmoji();
        String date = getFormattedDate();
        String user = username != null ? username : "Utilisateur";

        return emoji + " " + date + " - " + user + ": " + title;
    }

    @Override
    public String toString() {
        return "ProjectUpdate{id=" + id + ", type=" + updateType + ", title='" + title + "'}";
    }
}