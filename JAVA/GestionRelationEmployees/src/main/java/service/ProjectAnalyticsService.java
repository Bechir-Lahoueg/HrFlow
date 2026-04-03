package service;

import models.Project;
import models.ProjectTask;
import models.ProjectCollaborator;
import models.ProjectMilestone;
import utils.Mydb;

import java.sql.*;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * Service d'analytics avancé pour les projets
 * Prédictions, métriques, alertes et indicateurs de performance
 */
public class ProjectAnalyticsService {

    private final Connection conn;
    private final ProjectService projectService = new ProjectService();
    private final ProjectTaskService taskService = new ProjectTaskService();
    private final ProjectCollaboratorService collaboratorService = new ProjectCollaboratorService();
    private final ProjectMilestoneService milestoneService = new ProjectMilestoneService();

    public ProjectAnalyticsService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ═══════════════════════════════════════════════════════════════
    // PRÉDICTION DE DATE DE FIN
    // ═══════════════════════════════════════════════════════════════

    /**
     * Prédit la date de fin réelle basée sur la vélocité actuelle
     */
    public ProjectPrediction predictCompletionDate(int projectId) {
        Project project = projectService.getById(projectId);
        if (project == null) return null;

        List<ProjectTask> allTasks = taskService.getByProjectId(projectId);
        if (allTasks.isEmpty()) {
            return new ProjectPrediction(
                    project.getEndDate(),
                    0,
                    "Aucune tâche",
                    ProjectPrediction.Status.NO_DATA
            );
        }

        // Calculer la vélocité (tâches terminées / jours écoulés)
        long totalTasks = allTasks.size();
        long completedTasks = allTasks.stream()
                .filter(t -> t.getStatus() == ProjectTask.Status.done)
                .count();

        if (completedTasks == 0) {
            return new ProjectPrediction(
                    project.getEndDate(),
                    0,
                    "Aucune tâche terminée",
                    ProjectPrediction.Status.NO_DATA
            );
        }

        // Jours écoulés depuis le début
        LocalDate startDate = project.getStartDate() != null ?
                project.getStartDate().toLocalDate() : LocalDate.now().minusMonths(1);
        long daysElapsed = ChronoUnit.DAYS.between(startDate, LocalDate.now());

        if (daysElapsed <= 0) daysElapsed = 1;

        // Vélocité = tâches terminées par jour
        double velocity = (double) completedTasks / daysElapsed;

        // Tâches restantes
        long remainingTasks = totalTasks - completedTasks;

        // Jours nécessaires pour terminer
        int daysNeeded = (int) Math.ceil(remainingTasks / velocity);

        // Date de fin prédite
        LocalDate predictedEnd = LocalDate.now().plusDays(daysNeeded);

        // Comparer avec la date de fin planifiée
        LocalDate plannedEnd = project.getEndDate().toLocalDate();
        long daysDifference = ChronoUnit.DAYS.between(plannedEnd, predictedEnd);

        ProjectPrediction.Status status;
        String message;

        if (daysDifference < -7) {
            status = ProjectPrediction.Status.EARLY;
            message = "Projet en avance de " + Math.abs(daysDifference) + " jours";
        } else if (daysDifference > 7) {
            status = ProjectPrediction.Status.LATE;
            message = "Projet en retard de " + daysDifference + " jours";
        } else {
            status = ProjectPrediction.Status.ON_TRACK;
            message = "Projet dans les temps";
        }

        return new ProjectPrediction(
                Date.valueOf(predictedEnd),
                (int) daysDifference,
                message,
                status
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // INDICATEURS DE SANTÉ DU PROJET
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calcule l'indicateur de santé global du projet (0-100)
     */
    public ProjectHealthIndicator calculateProjectHealth(int projectId) {
        Project project = projectService.getById(projectId);
        if (project == null) return null;

        int healthScore = 100;
        List<String> warnings = new ArrayList<>();
        List<String> criticals = new ArrayList<>();

        // 1. Vérifier l'avancement par rapport au temps écoulé
        LocalDate start = project.getStartDate() != null ?
                project.getStartDate().toLocalDate() : LocalDate.now().minusMonths(1);
        LocalDate end = project.getEndDate().toLocalDate();
        long totalDays = ChronoUnit.DAYS.between(start, end);
        long elapsedDays = ChronoUnit.DAYS.between(start, LocalDate.now());

        double expectedProgress = totalDays > 0 ? (double) elapsedDays / totalDays * 100 : 0;
        double actualProgress = project.getCompletionRate();
        double progressGap = expectedProgress - actualProgress;

        if (progressGap > 20) {
            healthScore -= 25;
            criticals.add("⚠️ Retard significatif: " + (int)progressGap + "% de retard");
        } else if (progressGap > 10) {
            healthScore -= 15;
            warnings.add("⚠️ Léger retard: " + (int)progressGap + "% de retard");
        }

        // 2. Vérifier les tâches en retard
        List<ProjectTask> overdueTasks = taskService.getOverdueTasks(projectId);
        int overdueCount = overdueTasks.size();
        if (overdueCount > 5) {
            healthScore -= 20;
            criticals.add("🔴 " + overdueCount + " tâches en retard");
        } else if (overdueCount > 0) {
            healthScore -= 10;
            warnings.add("🟡 " + overdueCount + " tâche(s) en retard");
        }

        // 3. Vérifier le budget (heures)
        if (project.getEstimatedHours() != null && project.getEstimatedHours() > 0) {
            double budgetUsage = (double) project.getActualHours() / project.getEstimatedHours() * 100;
            if (budgetUsage > 120) {
                healthScore -= 20;
                criticals.add("💰 Budget heures dépassé: " + (int)budgetUsage + "%");
            } else if (budgetUsage > 100) {
                healthScore -= 10;
                warnings.add("💰 Budget heures proche: " + (int)budgetUsage + "%");
            }
        }

        // 4. Vérifier les jalons en retard
        List<ProjectMilestone> milestones = milestoneService.getByProjectId(projectId);
        long delayedMilestones = milestones.stream()
                .filter(ProjectMilestone::isDelayed)
                .count();
        if (delayedMilestones > 0) {
            healthScore -= 15;
            warnings.add("🎯 " + delayedMilestones + " jalon(s) en retard");
        }

        // 5. Vérifier la charge de l'équipe
        List<ProjectCollaborator> team = collaboratorService.getByProjectId(projectId);
        long overAllocated = team.stream()
                .filter(ProjectCollaborator::isOverAllocated)
                .count();
        if (overAllocated > 0) {
            healthScore -= 10;
            warnings.add("👥 " + overAllocated + " membre(s) en surcharge");
        }

        healthScore = Math.max(0, healthScore);

        ProjectHealthIndicator.HealthStatus status;
        if (healthScore >= 80) status = ProjectHealthIndicator.HealthStatus.EXCELLENT;
        else if (healthScore >= 60) status = ProjectHealthIndicator.HealthStatus.GOOD;
        else if (healthScore >= 40) status = ProjectHealthIndicator.HealthStatus.WARNING;
        else status = ProjectHealthIndicator.HealthStatus.CRITICAL;

        return new ProjectHealthIndicator(healthScore, status, warnings, criticals);
    }

    // ═══════════════════════════════════════════════════════════════
    // RISQUES DU PROJET
    // ═══════════════════════════════════════════════════════════════

    /**
     * Identifie les risques du projet
     */
    public List<ProjectRisk> analyzeRisks(int projectId) {
        List<ProjectRisk> risks = new ArrayList<>();
        Project project = projectService.getById(projectId);

        // Risque 1: Tâches non assignées
        List<ProjectTask> unassignedTasks = taskService.getByProjectId(projectId).stream()
                .filter(t -> t.getAssignedTo() == null && t.getStatus() != ProjectTask.Status.done)
                .toList();
        if (!unassignedTasks.isEmpty()) {
            risks.add(new ProjectRisk(
                    ProjectRisk.Severity.MEDIUM,
                    "Tâches non assignées",
                    unassignedTasks.size() + " tâche(s) sans responsable",
                    "Assigner ces tâches aux membres de l'équipe"
            ));
        }

        // Risque 2: Deadline proche
        if (project.getEndDate() != null) {
            long daysUntilDeadline = ChronoUnit.DAYS.between(LocalDate.now(),
                    project.getEndDate().toLocalDate());
            if (daysUntilDeadline <= 7 && daysUntilDeadline > 0 &&
                    project.getCompletionRate() < 90) {
                risks.add(new ProjectRisk(
                        ProjectRisk.Severity.HIGH,
                        "Deadline imminente",
                        "Seulement " + daysUntilDeadline + " jour(s) restant(s), projet à " +
                                project.getCompletionRate() + "%",
                        "Prioriser les tâches critiques ou négocier une extension"
                ));
            }
        }

        // Risque 3: Équipe surchargée
        List<ProjectCollaborator> overloaded = collaboratorService.getByProjectId(projectId).stream()
                .filter(c -> c.getHoursUsageRate() > 90)
                .toList();
        if (!overloaded.isEmpty()) {
            risks.add(new ProjectRisk(
                    ProjectRisk.Severity.MEDIUM,
                    "Équipe surchargée",
                    overloaded.size() + " membre(s) proche de leur limite d'heures",
                    "Redistribuer la charge de travail ou ajouter des ressources"
            ));
        }

        // Risque 4: Pas de mise à jour récente
        // (À implémenter avec project_updates si nécessaire)

        return risks;
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES GLOBALES RH
    // ═══════════════════════════════════════════════════════════════

    /**
     * Statistiques globales pour le dashboard RH
     */
    public RHDashboardStats getGlobalStats(int rhId) {
        List<Project> allProjects = projectService.getByRhId(rhId);

        int totalProjects = allProjects.size();
        long activeProjects = allProjects.stream()
                .filter(p -> p.getStatus() == Project.Status.in_progress)
                .count();
        long delayedProjects = allProjects.stream()
                .filter(Project::isDelayed)
                .count();
        long completedProjects = allProjects.stream()
                .filter(p -> p.getStatus() == Project.Status.completed)
                .count();

        // Taux de succès (projets terminés à temps)
        long completedOnTime = allProjects.stream()
                .filter(p -> p.getStatus() == Project.Status.completed)
                .filter(p -> !p.isDelayed())
                .count();
        double successRate = completedProjects > 0 ?
                (double) completedOnTime / completedProjects * 100 : 0;

        // Budget total et dépensé (heures)
        int totalBudgetHours = allProjects.stream()
                .filter(p -> p.getEstimatedHours() != null)
                .mapToInt(Project::getEstimatedHours)
                .sum();
        int totalSpentHours = allProjects.stream()
                .mapToInt(Project::getActualHours)
                .sum();

        // Projets à risque
        long atRiskProjects = 0;
        for (Project p : allProjects) {
            if (p.getStatus() == Project.Status.in_progress) {
                ProjectHealthIndicator health = calculateProjectHealth(p.getId());
                if (health != null && health.getScore() < 60) {
                    atRiskProjects++;
                }
            }
        }

        return new RHDashboardStats(
                totalProjects,
                (int) activeProjects,
                (int) delayedProjects,
                (int) completedProjects,
                (int) atRiskProjects,
                successRate,
                totalBudgetHours,
                totalSpentHours
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // TOP PERFORMERS / BOTTOM PERFORMERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Employés les plus productifs (tâches terminées)
     */
    public List<EmployeePerformance> getTopPerformers(int rhId, int limit) {
        Map<Integer, EmployeePerformance> performanceMap = new HashMap<>();

        List<Project> projects = projectService.getByRhId(rhId);
        System.out.println("DEBUG: Projets trouvés pour RH " + rhId + " : " + projects.size());
        for (Project p : projects) {
            List<ProjectTask> tasks = taskService.getByProjectId(p.getId());
            for (ProjectTask task : tasks) {
                System.out.println("DEBUG: Tâche " + task.getId() + " - Statut: " + task.getStatus() + " - Assigné à: " + task.getAssignedTo());
                if (task.getAssignedTo() != null && task.getStatus() == ProjectTask.Status.done) {
                    int empId = task.getAssignedTo();
                    // Si le nom est null, on met un nom par défaut pour éviter le vide
                    String name = (task.getAssignedToName() != null) ? task.getAssignedToName() : "Employé #" + empId;
                    performanceMap.putIfAbsent(empId,
                            new EmployeePerformance(empId, task.getAssignedToName()));
                    performanceMap.get(empId).incrementCompletedTasks();
                    performanceMap.get(empId).addHours(task.getActualHours());
                }
            }
        }

        return performanceMap.values().stream()
                .sorted((a, b) -> Integer.compare(b.getCompletedTasks(), a.getCompletedTasks()))
                .limit(limit)
                .peek(p -> System.out.println("DEBUG Resultat: " + p.getEmployeeName() + " - Tâches: " + p.getCompletedTasks()))
                .toList();
    }

    // ═══════════════════════════════════════════════════════════════
    // CLASSES INTERNES (DTOs)
    // ═══════════════════════════════════════════════════════════════

    public static class ProjectPrediction {
        public enum Status { ON_TRACK, EARLY, LATE, NO_DATA }

        private final Date predictedEndDate;
        private final int daysDifference;
        private final String message;
        private final Status status;

        public ProjectPrediction(Date predictedEndDate, int daysDifference,
                                 String message, Status status) {
            this.predictedEndDate = predictedEndDate;
            this.daysDifference = daysDifference;
            this.message = message;
            this.status = status;
        }

        public Date getPredictedEndDate() { return predictedEndDate; }
        public int getDaysDifference() { return daysDifference; }
        public String getMessage() { return message; }
        public Status getStatus() { return status; }

        public String getStatusEmoji() {
            return switch (status) {
                case ON_TRACK -> "✅";
                case EARLY -> "🟢";
                case LATE -> "🔴";
                case NO_DATA -> "⚪";
            };
        }
    }

    public static class ProjectHealthIndicator {
        public enum HealthStatus { EXCELLENT, GOOD, WARNING, CRITICAL }

        private final int score;
        private final HealthStatus status;
        private final List<String> warnings;
        private final List<String> criticals;

        public ProjectHealthIndicator(int score, HealthStatus status,
                                      List<String> warnings, List<String> criticals) {
            this.score = score;
            this.status = status;
            this.warnings = warnings;
            this.criticals = criticals;
        }

        public int getScore() { return score; }
        public HealthStatus getStatus() { return status; }
        public List<String> getWarnings() { return warnings; }
        public List<String> getCriticals() { return criticals; }

        public String getStatusEmoji() {
            return switch (status) {
                case EXCELLENT -> "🟢";
                case GOOD -> "🟡";
                case WARNING -> "🟠";
                case CRITICAL -> "🔴";
            };
        }

        public String getStatusColor() {
            return switch (status) {
                case EXCELLENT -> "#27ae60";
                case GOOD -> "#f39c12";
                case WARNING -> "#e67e22";
                case CRITICAL -> "#e74c3c";
            };
        }
    }

    public static class ProjectRisk {
        public enum Severity { LOW, MEDIUM, HIGH, CRITICAL }

        private final Severity severity;
        private final String title;
        private final String description;
        private final String recommendation;

        public ProjectRisk(Severity severity, String title,
                           String description, String recommendation) {
            this.severity = severity;
            this.title = title;
            this.description = description;
            this.recommendation = recommendation;
        }

        public Severity getSeverity() { return severity; }
        public String getTitle() { return title; }
        public String getDescription() { return description; }
        public String getRecommendation() { return recommendation; }

        public String getSeverityEmoji() {
            return switch (severity) {
                case LOW -> "🟢";
                case MEDIUM -> "🟡";
                case HIGH -> "🟠";
                case CRITICAL -> "🔴";
            };
        }
    }

    public static class RHDashboardStats {
        private final int totalProjects;
        private final int activeProjects;
        private final int delayedProjects;
        private final int completedProjects;
        private final int atRiskProjects;
        private final double successRate;
        private final int totalBudgetHours;
        private final int totalSpentHours;

        public RHDashboardStats(int totalProjects, int activeProjects, int delayedProjects,
                                int completedProjects, int atRiskProjects, double successRate,
                                int totalBudgetHours, int totalSpentHours) {
            this.totalProjects = totalProjects;
            this.activeProjects = activeProjects;
            this.delayedProjects = delayedProjects;
            this.completedProjects = completedProjects;
            this.atRiskProjects = atRiskProjects;
            this.successRate = successRate;
            this.totalBudgetHours = totalBudgetHours;
            this.totalSpentHours = totalSpentHours;
        }

        public int getTotalProjects() { return totalProjects; }
        public int getActiveProjects() { return activeProjects; }
        public int getDelayedProjects() { return delayedProjects; }
        public int getCompletedProjects() { return completedProjects; }
        public int getAtRiskProjects() { return atRiskProjects; }
        public double getSuccessRate() { return successRate; }
        public int getTotalBudgetHours() { return totalBudgetHours; }
        public int getTotalSpentHours() { return totalSpentHours; }
        public double getBudgetUsageRate() {
            return totalBudgetHours > 0 ? (double) totalSpentHours / totalBudgetHours * 100 : 0;
        }
    }

    public static class EmployeePerformance {
        private final int employeeId;
        private final String employeeName;
        private int completedTasks;
        private int totalHours;

        public EmployeePerformance(int employeeId, String employeeName) {
            this.employeeId = employeeId;
            this.employeeName = employeeName;
            this.completedTasks = 0;
            this.totalHours = 0;
        }

        public void incrementCompletedTasks() { this.completedTasks++; }
        public void addHours(int hours) { this.totalHours += hours; }

        public int getEmployeeId() { return employeeId; }
        public String getEmployeeName() { return employeeName; }
        public int getCompletedTasks() { return completedTasks; }
        public int getTotalHours() { return totalHours; }
    }
}