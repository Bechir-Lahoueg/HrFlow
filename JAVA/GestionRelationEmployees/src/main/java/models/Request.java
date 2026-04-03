package models;

import java.sql.Timestamp;

public class Request {

    // Valeurs possibles pour status
    public enum Status   { pending, approved, rejected, cancelled }

    // Valeurs possibles pour priority
    public enum Priority { low, medium, high }

    private int       id;
    private int       userId;
    private int       requestTypeId;
    private String    title;
    private String    description;
    private String attachmentUrl;
    private Status    status;
    private Priority  priority;
    private Timestamp submittedDate;
    private Integer   reviewedBy;       // peut être null
    private Timestamp reviewedDate;     // peut être null
    private String    reviewComment;    // peut être null
    private Timestamp createdAt;
    private Timestamp updatedAt;

    // Champ transient pour affichage (nom du type de demande)
    private String requestTypeName;
    // Champ transient pour affichage (nom du reviewer)
    private String reviewerName;
    private String employeeName;

    // ─── Constructeurs ───────────────────────────────────────────────

    public Request() {}

    // Constructeur pour INSERT (champs obligatoires)
    public Request(int userId, int requestTypeId, String title,
                   String description, Priority priority) {
        this.userId        = userId;
        this.requestTypeId = requestTypeId;
        this.title         = title;
        this.description   = description;
        this.priority      = priority;
        this.status        = Status.pending; // par défaut
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId()                          { return id; }
    public void setId(int id)                   { this.id = id; }

    public int getUserId()                      { return userId; }
    public void setUserId(int userId)           { this.userId = userId; }

    public int getRequestTypeId()               { return requestTypeId; }
    public void setRequestTypeId(int id)        { this.requestTypeId = id; }

    public String getTitle()                    { return title; }
    public void setTitle(String title)          { this.title = title; }

    public String getDescription()              { return description; }
    public void setDescription(String desc)     { this.description = desc; }

    public String getAttachmentUrl() {
        return attachmentUrl;
    }

    public void setAttachmentUrl(String attachmentUrl) {
        this.attachmentUrl = attachmentUrl;
    }

    public Status getStatus()                   { return status; }
    public void setStatus(Status status)        { this.status = status; }

    public Priority getPriority()               { return priority; }
    public void setPriority(Priority priority)  { this.priority = priority; }

    public Timestamp getSubmittedDate()                  { return submittedDate; }
    public void setSubmittedDate(Timestamp submittedDate){ this.submittedDate = submittedDate; }

    public Integer getReviewedBy()                       { return reviewedBy; }
    public void setReviewedBy(Integer reviewedBy)        { this.reviewedBy = reviewedBy; }

    public Timestamp getReviewedDate()                   { return reviewedDate; }
    public void setReviewedDate(Timestamp reviewedDate)  { this.reviewedDate = reviewedDate; }

    public String getReviewComment()                     { return reviewComment; }
    public void setReviewComment(String reviewComment)   { this.reviewComment = reviewComment; }

    public Timestamp getCreatedAt()                      { return createdAt; }
    public void setCreatedAt(Timestamp createdAt)        { this.createdAt = createdAt; }

    public Timestamp getUpdatedAt()                      { return updatedAt; }
    public void setUpdatedAt(Timestamp updatedAt)        { this.updatedAt = updatedAt; }

    // Champs transients (pour l'affichage)
    public String getRequestTypeName()                       { return requestTypeName; }
    public void setRequestTypeName(String requestTypeName)   { this.requestTypeName = requestTypeName; }

    public String getReviewerName()                          { return reviewerName; }
    public void setReviewerName(String reviewerName)         { this.reviewerName = reviewerName; }
    public String getEmployeeName()                          { return employeeName; }
    public void setEmployeeName(String employeeName)         { this.employeeName = employeeName; }


    // ─── toString ────────────────────────────────────────────────────

    @Override
    public String toString() {
        return "Request{id=" + id + ", title='" + title + "', status=" + status + "}";
    }
}