package models;

import java.sql.Timestamp;

public class RequestType {

    private int id;
    private String name;
    private String description;
    private boolean requiresApproval;
    private Timestamp createdAt;

    // ─── Constructeurs ───────────────────────────────────────────────

    public RequestType() {}

    // Constructeur sans id (pour INSERT)
    public RequestType(String name, String description, boolean requiresApproval) {
        this.name             = name;
        this.description      = description;
        this.requiresApproval = requiresApproval;
    }

    // Constructeur complet (pour SELECT)
    public RequestType(int id, String name, String description,
                       boolean requiresApproval, Timestamp createdAt) {
        this.id               = id;
        this.name             = name;
        this.description      = description;
        this.requiresApproval = requiresApproval;
        this.createdAt        = createdAt;
    }

    // ─── Getters & Setters ───────────────────────────────────────────

    public int getId()                        { return id; }
    public void setId(int id)                 { this.id = id; }

    public String getName()                   { return name; }
    public void setName(String name)          { this.name = name; }

    public String getDescription()            { return description; }
    public void setDescription(String desc)   { this.description = desc; }

    public boolean isRequiresApproval()              { return requiresApproval; }
    public void setRequiresApproval(boolean val)     { this.requiresApproval = val; }

    public Timestamp getCreatedAt()                  { return createdAt; }
    public void setCreatedAt(Timestamp createdAt)    { this.createdAt = createdAt; }

    // ─── toString ────────────────────────────────────────────────────

    @Override
    public String toString() {
        return name; // utile pour l'affichage dans ComboBox JavaFX
    }
}