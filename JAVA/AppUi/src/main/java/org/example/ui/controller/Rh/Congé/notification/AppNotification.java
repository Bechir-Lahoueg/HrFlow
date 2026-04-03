package org.example.ui.controller.Rh.Congé.notification;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.UUID;

/**
 * Représente une notification in-app.
 */
public class AppNotification {

    public enum Type {
        LEAVE_APPROVED("✅", "#22c55e"),
        LEAVE_REJECTED("❌", "#ef4444"),
        LEAVE_SUBMITTED("📋", "#3b82f6"),
        INFO           ("ℹ️", "#6366f1");

        public final String icon;
        public final String color;
        Type(String icon, String color) { this.icon = icon; this.color = color; }
    }

    private final String        id;
    private final String        message;
    private final Type          type;
    private final LocalDateTime timestamp;
    private boolean             read;

    public AppNotification(String message, Type type) {
        this.id        = UUID.randomUUID().toString();
        this.message   = message;
        this.type      = type;
        this.timestamp = LocalDateTime.now();
        this.read      = false;
    }

    /** Constructeur utilisé lors du chargement depuis la base de données. */
    public AppNotification(String message, Type type, LocalDateTime timestamp) {
        this.id        = UUID.randomUUID().toString();
        this.message   = message;
        this.type      = type;
        this.timestamp = timestamp != null ? timestamp : LocalDateTime.now();
        this.read      = false;
    }

    // ─── Getters ────────────────────────────────────────────────────────────────

    public String        getId()        { return id; }
    public String        getMessage()   { return message; }
    public Type          getType()      { return type; }
    public LocalDateTime getTimestamp() { return timestamp; }
    public boolean       isRead()       { return read; }

    public void markAsRead() { this.read = true; }

    /** Ex: "14:32  ·  Votre congé a été approuvé" */
    public String getFormattedLine() {
        String time = timestamp.format(DateTimeFormatter.ofPattern("HH:mm"));
        return type.icon + "  " + message + "\n     " + time;
    }

    @Override
    public String toString() { return getFormattedLine(); }
}
