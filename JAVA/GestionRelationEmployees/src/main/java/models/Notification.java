package models;

import java.sql.Timestamp;

public class Notification {

    private int       id;
    private int       userId;
    private String    type;
    private String    title;
    private String    message;
    private Integer   referenceId;    // peut être null
    private String    referenceType;  // peut être null
    private boolean   isRead;
    private Timestamp createdAt;

    // Champ transient
    private String username;

    // ─── Constructeurs ───────────────────────────────────────────────

    public Notification() {}

    // Constructeur pour INSERT
    public Notification(int userId, String type, String title,
                        String message, Integer referenceId, String referenceType) {
        this.userId        = userId;
        this.type          = type;
        this.title         = title;
        this.message       = message;
        this.referenceId   = referenceId;
        this.referenceType = referenceType;
        this.isRead        = false;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId()                              { return id; }
    public void setId(int id)                       { this.id = id; }

    public int getUserId()                          { return userId; }
    public void setUserId(int userId)               { this.userId = userId; }

    public String getType()                         { return type; }
    public void setType(String type)                { this.type = type; }

    public String getTitle()                        { return title; }
    public void setTitle(String title)              { this.title = title; }

    public String getMessage()                      { return message; }
    public void setMessage(String message)          { this.message = message; }

    public Integer getReferenceId()                 { return referenceId; }
    public void setReferenceId(Integer referenceId) { this.referenceId = referenceId; }

    public String getReferenceType()                        { return referenceType; }
    public void setReferenceType(String referenceType)      { this.referenceType = referenceType; }

    public boolean isRead()                         { return isRead; }
    public void setRead(boolean read)               { isRead = read; }

    public Timestamp getCreatedAt()                 { return createdAt; }
    public void setCreatedAt(Timestamp createdAt)   { this.createdAt = createdAt; }

    // Transient
    public String getUsername()                     { return username; }
    public void setUsername(String username)        { this.username = username; }

    // Utilitaire pour affichage
    public String getReadStatus() {
        return isRead ? "Lu" : "🔵 Nouveau";
    }

    @Override
    public String toString() {
        return "Notification{id=" + id + ", title='" + title + "', isRead=" + isRead + "}";
    }
}