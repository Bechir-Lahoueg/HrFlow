package org.example.models;
import java.sql.Timestamp;

public class FeedbackFormation {

    private int       id;
    private int       userId;
    private int       formationId;
    private Integer   sessionId;        // peut être null
    private int       rating;           // 1 à 5
    private String    contenuComment;
    private String    formateurComment;
    private String    organisationComment;
    private boolean   recommande;
    private Timestamp createdAt;

    // ─── Champs transients pour affichage ────────────────────────────
    private String username;
    private String formationName;
    private String sessionName;

    // ─── Constructeurs ───────────────────────────────────────────────

    public FeedbackFormation() {}

    // Constructeur pour INSERT
    public FeedbackFormation(int userId, int formationId, Integer sessionId,
                             int rating, String contenuComment,
                             String formateurComment, String organisationComment,
                             boolean recommande) {
        this.userId              = userId;
        this.formationId         = formationId;
        this.sessionId           = sessionId;
        this.rating              = rating;
        this.contenuComment      = contenuComment;
        this.formateurComment    = formateurComment;
        this.organisationComment = organisationComment;
        this.recommande          = recommande;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId()                              { return id; }
    public void setId(int id)                       { this.id = id; }

    public int getUserId()                          { return userId; }
    public void setUserId(int userId)               { this.userId = userId; }

    public int getFormationId()                     { return formationId; }
    public void setFormationId(int formationId)     { this.formationId = formationId; }

    public Integer getSessionId()                   { return sessionId; }
    public void setSessionId(Integer sessionId)     { this.sessionId = sessionId; }

    public int getRating()                          { return rating; }
    public void setRating(int rating)               { this.rating = rating; }

    public String getContenuComment()               { return contenuComment; }
    public void setContenuComment(String c)         { this.contenuComment = c; }

    public String getFormateurComment()             { return formateurComment; }
    public void setFormateurComment(String c)       { this.formateurComment = c; }

    public String getOrganisationComment()          { return organisationComment; }
    public void setOrganisationComment(String c)    { this.organisationComment = c; }

    public boolean isRecommande()                   { return recommande; }
    public void setRecommande(boolean recommande)   { this.recommande = recommande; }

    public Timestamp getCreatedAt()                 { return createdAt; }
    public void setCreatedAt(Timestamp createdAt)   { this.createdAt = createdAt; }

    // ─── Transients ──────────────────────────────────────────────────

    public String getUsername()                     { return username; }
    public void setUsername(String username)        { this.username = username; }

    public String getFormationName()                { return formationName; }
    public void setFormationName(String name)       { this.formationName = name; }

    public String getSessionName()                  { return sessionName; }
    public void setSessionName(String name)         { this.sessionName = name; }

    // ─── Utilitaires ─────────────────────────────────────────────────

    public String getRatingStars() {
        return "★".repeat(rating) + "☆".repeat(5 - rating);
    }

    public String getRecommandeLabel() {
        return recommande ? "✅ Oui" : "❌ Non";
    }

    @Override
    public String toString() {
        return "FeedbackFormation{id=" + id + ", formation=" + formationName
                + ", rating=" + rating + "}";
    }
}