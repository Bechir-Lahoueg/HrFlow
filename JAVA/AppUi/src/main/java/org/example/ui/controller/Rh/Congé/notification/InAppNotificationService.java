package org.example.ui.controller.Rh.Congé.notification;

import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import org.example.service.LeaveNotificationService;

import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.CopyOnWriteArrayList;
import java.util.function.Consumer;

/**
 * Service singleton de notifications in-app.
 *
 * <p>Deux canaux :
 * <ul>
 *   <li><b>Employé</b> : indexé par {@code employeeId}, persisté en base via
 *       {@link LeaveNotificationService} pour survivre aux reconnexions.</li>
 *   <li><b>RH</b> : liste broadcast en mémoire (session courante).</li>
 * </ul>
 *
 * <p>Appeler {@link #loadEmployeeNotificationsFromDB(int)} lors de la connexion
 * d'un employé pour récupérer les notifications précédemment envoyées.
 */
public final class InAppNotificationService {

    // ─── Singleton ───────────────────────────────────────────────────────────────
    private static final InAppNotificationService INSTANCE = new InAppNotificationService();

    public static InAppNotificationService getInstance() { return INSTANCE; }

    private InAppNotificationService() {}

    // ─── Dépendances ─────────────────────────────────────────────────────────────

    private final LeaveNotificationService dbService = new LeaveNotificationService();

    // ─── Stockage en mémoire ─────────────────────────────────────────────────────

    /** Notifications par employé (clé = employeeId). */
    private final Map<Integer, ObservableList<AppNotification>> employeeNotifications = new HashMap<>();

    /** Notifications broadcast pour tous les RH. */
    private final ObservableList<AppNotification> rhNotifications =
            FXCollections.observableArrayList();

    /** Listeners globaux pour badge live-update. */
    private final List<Consumer<Void>> globalListeners = new CopyOnWriteArrayList<>();

    // ─── API Employé ─────────────────────────────────────────────────────────────

    /**
     * Crée une notification pour un employé.
     * La notification est persistée en base ET ajoutée en mémoire.
     */
    public void notifyEmployee(int employeeId, String message, AppNotification.Type type) {
        // Persistance DB
        new Thread(() -> {
            try { dbService.saveNotification(employeeId, message, type.name()); }
            catch (Exception e) {
                System.err.println("InAppNotificationService – erreur sauvegarde DB: " + e.getMessage());
            }
        }, "notif-db-save").start();

        // Mise à jour mémoire
        AppNotification notif = new AppNotification(message, type);
        Platform.runLater(() -> {
            getOrCreateEmployeeList(employeeId).add(0, notif);
            fireGlobalListeners();
        });
    }

    /**
     * Charge depuis la base de données toutes les notifications d'un employé
     * et les place dans sa liste en mémoire.
     * À appeler une fois lors de la connexion de l'employé.
     */
    public void loadEmployeeNotificationsFromDB(int employeeId) {
        new Thread(() -> {
            try {
                List<LeaveNotificationService.NotificationRecord> records =
                        dbService.getNotifications(employeeId);

                List<AppNotification> loaded = records.stream()
                        .map(r -> {
                            AppNotification.Type t;
                            try { t = AppNotification.Type.valueOf(r.type); }
                            catch (IllegalArgumentException ex) { t = AppNotification.Type.INFO; }
                            AppNotification n = new AppNotification(r.message, t, r.createdAt);
                            if (r.read) n.markAsRead();
                            return n;
                        })
                        .toList();

                Platform.runLater(() -> {
                    getOrCreateEmployeeList(employeeId).setAll(loaded);
                    fireGlobalListeners();
                });
            } catch (Exception e) {
                System.err.println("InAppNotificationService – erreur chargement DB: " + e.getMessage());
            }
        }, "notif-db-load").start();
    }

    public ObservableList<AppNotification> getEmployeeNotifications(int employeeId) {
        return getOrCreateEmployeeList(employeeId);
    }

    public long unreadCountEmployee(int employeeId) {
        return getOrCreateEmployeeList(employeeId).stream()
                .filter(n -> !n.isRead()).count();
    }

    public void markAllReadEmployee(int employeeId) {
        // Marquer en DB
        new Thread(() -> {
            try { dbService.markAllRead(employeeId); }
            catch (Exception e) {
                System.err.println("InAppNotificationService – erreur markAllRead DB: " + e.getMessage());
            }
        }, "notif-db-mark").start();

        // Marquer en mémoire
        Platform.runLater(() -> {
            getOrCreateEmployeeList(employeeId).forEach(AppNotification::markAsRead);
            fireGlobalListeners();
        });
    }

    // ─── API RH ──────────────────────────────────────────────────────────────────

    /**
     * Envoie une notification broadcast à tous les RH connectés.
     *
     * @param message Texte de la notification
     * @param type    Type de notification
     */
    public void notifyAllRH(String message, AppNotification.Type type) {
        AppNotification notif = new AppNotification(message, type);
        Platform.runLater(() -> {
            rhNotifications.add(0, notif);
            fireGlobalListeners();
        });
    }

    /**
     * Retourne la liste observable des notifications RH.
     * À utiliser dans le RHDashboard.
     */
    public ObservableList<AppNotification> getRHNotifications() {
        return rhNotifications;
    }

    /** Nombre de notifs RH non lues. */
    public long unreadCountRH() {
        return rhNotifications.stream().filter(n -> !n.isRead()).count();
    }

    /** Marque toutes les notifs RH comme lues. */
    public void markAllReadRH() {
        Platform.runLater(() -> {
            rhNotifications.forEach(AppNotification::markAsRead);
            fireGlobalListeners();
        });
    }

    // ─── Listeners globaux (pour badges) ─────────────────────────────────────────

    /** Abonne un listener appelé à chaque nouvelle notification. */
    public void addGlobalListener(Consumer<Void> listener) {
        globalListeners.add(listener);
    }

    public void removeGlobalListener(Consumer<Void> listener) {
        globalListeners.remove(listener);
    }

    // ─── Privé ───────────────────────────────────────────────────────────────────

    private ObservableList<AppNotification> getOrCreateEmployeeList(int employeeId) {
        return employeeNotifications.computeIfAbsent(
                employeeId, k -> FXCollections.observableArrayList());
    }

    private void fireGlobalListeners() {
        globalListeners.forEach(l -> l.accept(null));
    }
}
