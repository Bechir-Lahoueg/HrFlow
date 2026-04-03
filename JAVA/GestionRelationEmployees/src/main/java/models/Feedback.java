package models;

import java.sql.Timestamp;

public class Feedback {

    public enum FeedbackType { performance, behavior, collaboration, other }
    public enum Status       { draft, submitted, acknowledged }

    private int          id;
    private int          fromUserId;
    private int          toUserId;
    private FeedbackType feedbackType;
    private int          rating;        // 1 à 5
    private String       comment;
    private boolean      isAnonymous;
    private Status       status;
    private Timestamp    createdAt;
    private Timestamp    updatedAt;

    // Champs transients pour affichage
    private String fromUsername;
    private String toUsername;

    // ─── Constructeurs ───────────────────────────────────────────────

    public Feedback() {}

    // Constructeur pour INSERT
    public Feedback(int fromUserId, int toUserId, FeedbackType feedbackType,
                    int rating, String comment, boolean isAnonymous) {
        this.fromUserId   = fromUserId;
        this.toUserId     = toUserId;
        this.feedbackType = feedbackType;
        this.rating       = rating;
        this.comment      = comment;
        this.isAnonymous  = isAnonymous;
        this.status       = Status.submitted;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId()                              { return id; }
    public void setId(int id)                       { this.id = id; }

    public int getFromUserId()                      { return fromUserId; }
    public void setFromUserId(int fromUserId)       { this.fromUserId = fromUserId; }

    public int getToUserId()                        { return toUserId; }
    public void setToUserId(int toUserId)           { this.toUserId = toUserId; }

    public FeedbackType getFeedbackType()                       { return feedbackType; }
    public void setFeedbackType(FeedbackType feedbackType)      { this.feedbackType = feedbackType; }

    public int getRating()                          { return rating; }
    public void setRating(int rating)               { this.rating = rating; }

    public String getComment()                      { return comment; }
    public void setComment(String comment)          { this.comment = comment; }

    public boolean isAnonymous()                    { return isAnonymous; }
    public void setAnonymous(boolean anonymous)     { isAnonymous = anonymous; }

    public Status getStatus()                       { return status; }
    public void setStatus(Status status)            { this.status = status; }

    public Timestamp getCreatedAt()                 { return createdAt; }
    public void setCreatedAt(Timestamp createdAt)   { this.createdAt = createdAt; }

    public Timestamp getUpdatedAt()                 { return updatedAt; }
    public void setUpdatedAt(Timestamp updatedAt)   { this.updatedAt = updatedAt; }

    // Transients
    public String getFromUsername()                         { return fromUsername; }
    public void setFromUsername(String fromUsername)        { this.fromUsername = fromUsername; }

    public String getToUsername()                           { return toUsername; }
    public void setToUsername(String toUsername)            { this.toUsername = toUsername; }

    // Utilitaire pour afficher les étoiles
    public String getRatingStars() {
        return "★".repeat(rating) + "☆".repeat(5 - rating);
    }

    @Override
    public String toString() {
        return "Feedback{id=" + id + ", type=" + feedbackType + ", rating=" + rating + "}";
    }
}