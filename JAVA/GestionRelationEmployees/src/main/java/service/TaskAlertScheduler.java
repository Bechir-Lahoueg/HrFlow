package service;

import models.Project;
import models.ProjectTask;
import models.ProjectCollaborator;
import utils.Mydb;

import java.sql.*;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.*;

/**
 * Service de vérification automatique des tâches
 * Envoie des alertes par email pour tâches en retard ou échéance proche
 */
public class TaskAlertScheduler {

    private final Connection conn;
    private final ProjectService projectService = new ProjectService();
    private final ProjectTaskService taskService = new ProjectTaskService();
    private final ProjectCollaboratorService collaboratorService = new ProjectCollaboratorService();
    private final EmailNotificationService emailService = new EmailNotificationService();

    public TaskAlertScheduler() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ═══════════════════════════════════════════════════════════════
    // VÉRIFICATION POUR UN RH SPÉCIFIQUE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Vérifie et envoie les alertes pour tous les projets d'un RH
     */
    public AlertReport checkAndNotifyForRH(int rhId) {
        String rhEmail = getRHEmail(rhId);
        String rhName = getRHName(rhId);

        if (rhEmail == null || rhEmail.isEmpty()) {
            System.err.println("❌ Email RH introuvable pour ID: " + rhId);
            return new AlertReport(0, 0, 0);
        }

        List<Project> projects = projectService.getByRhId(rhId);

        List<EmailNotificationService.TaskAlert> allOverdueTasks = new ArrayList<>();
        List<EmailNotificationService.TaskAlert> allDueSoonTasks = new ArrayList<>();

        int employeeNotifications = 0;

        for (Project project : projects) {
            System.out.println("Analyse du projet : " + project.getName() + " (Status: " + project.getStatus() + ")");
            //if (project.getStatus() != Project.Status.in_progress) continue;

            List<ProjectTask> tasks = taskService.getByProjectId(project.getId());
            System.out.println("Nombre de tâches trouvées : " + tasks.size());

            for (ProjectTask task : tasks) {
                if (task.getStatus() == ProjectTask.Status.done) continue;
                if (task.getAssignedTo() == null) continue;
                System.out.println(" - Tâche : " + task.getTitle() + " | Statut: " + task.getStatus());

                String employeeEmail = getEmployeeEmail(task.getAssignedTo());
                String employeeName = task.getAssignedToName();

                if (employeeEmail == null || employeeEmail.isEmpty()) continue;

                LocalDate dueDate = task.getDueDate().toLocalDate();
                LocalDate today = LocalDate.now();
                long daysUntilDue = ChronoUnit.DAYS.between(today, dueDate);
                System.out.println("Task: " + task.getTitle() + " | DueDate: " + dueDate + " | DaysUntilDue: " + daysUntilDue + " | Status: " + task.getStatus());

                // 1. Tâche en retard
                if (daysUntilDue < 0) {
                    long daysOverdue = Math.abs(daysUntilDue);

                    // Notifier l'employé
                    if (emailService.notifyTaskOverdue(task, employeeEmail, employeeName, project.getName())) {
                        employeeNotifications++;
                    }

                    // Ajouter à la liste pour le RH
                    allOverdueTasks.add(new EmailNotificationService.TaskAlert(
                            project.getName(),
                            task.getTitle(),
                            employeeName,
                            employeeEmail,
                            daysOverdue,
                            task.getStatus().name()
                    ));
                }
                // 2. Échéance dans 1 jour
                else if (daysUntilDue == 1) {
                    // Notifier l'employé
                    if (emailService.notifyTaskDueSoon(task, employeeEmail, employeeName, project.getName())) {
                        employeeNotifications++;
                    }

                    // Ajouter à la liste pour le RH
                    allDueSoonTasks.add(new EmailNotificationService.TaskAlert(
                            project.getName(),
                            task.getTitle(),
                            employeeName,
                            employeeEmail,
                            0,
                            task.getStatus().name()
                    ));
                }
            }
        }

        // Envoyer les rapports au RH
        int rhNotifications = 0;
        if (!allOverdueTasks.isEmpty()) {
            if (emailService.notifyRHOverdueTasks(rhEmail, rhName, allOverdueTasks)) {
                rhNotifications++;
            }
        }
        if (!allDueSoonTasks.isEmpty()) {
            if (emailService.notifyRHTasksDueSoon(rhEmail, rhName, allDueSoonTasks)) {
                rhNotifications++;
            }
        }

        System.out.println("✅ Alertes envoyées pour RH #" + rhId + ": " +
                employeeNotifications + " employés, " + rhNotifications + " rapports RH");

        return new AlertReport(
                allOverdueTasks.size(),
                allDueSoonTasks.size(),
                employeeNotifications + rhNotifications
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // VÉRIFICATION GLOBALE (TOUS LES RH)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Vérifie et envoie les alertes pour TOUS les RH
     */
    public void checkAndNotifyAll() {
        System.out.println("🔔 Démarrage de la vérification des alertes...");

        List<Integer> rhIds = getAllRHIds();
        int totalOverdue = 0;
        int totalDueSoon = 0;
        int totalEmails = 0;

        for (int rhId : rhIds) {
            try {
                AlertReport report = checkAndNotifyForRH(rhId);
                totalOverdue += report.overdueTasks;
                totalDueSoon += report.dueSoonTasks;
                totalEmails += report.emailsSent;
            } catch (Exception e) {
                System.err.println("❌ Erreur pour RH #" + rhId + ": " + e.getMessage());
            }
        }

        System.out.println("✅ Vérification terminée:");
        System.out.println("   - Tâches en retard: " + totalOverdue);
        System.out.println("   - Tâches échéance demain: " + totalDueSoon);
        System.out.println("   - Emails envoyés: " + totalEmails);
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS - REQUÊTES SQL
    // ═══════════════════════════════════════════════════════════════

    private List<Integer> getAllRHIds() {
        List<Integer> ids = new ArrayList<>();
        String sql = "SELECT id FROM users WHERE role = 'rh'";
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                ids.add(rs.getInt("id"));
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAllRHIds: " + e.getMessage());
        }
        return ids;
    }

    private String getRHEmail(int rhId) {
        String sql = "SELECT email FROM users WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) {
                return rs.getString("email");
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getRHEmail: " + e.getMessage());
        }
        return null;
    }

    private String getRHName(int rhId) {
        String sql = "SELECT username FROM users WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, rhId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) {
                return rs.getString("username");
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getRHName: " + e.getMessage());
        }
        return "RH";
    }

    private String getEmployeeEmail(int employeeId) {
        String sql = "SELECT email FROM employees WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, employeeId);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) {
                return rs.getString("email");
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getEmployeeEmail: " + e.getMessage());
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    // CLASSE HELPER
    // ═══════════════════════════════════════════════════════════════

    public static class AlertReport {
        public int overdueTasks;
        public int dueSoonTasks;
        public int emailsSent;

        public AlertReport(int overdueTasks, int dueSoonTasks, int emailsSent) {
            this.overdueTasks = overdueTasks;
            this.dueSoonTasks = dueSoonTasks;
            this.emailsSent = emailsSent;
        }
    }
}