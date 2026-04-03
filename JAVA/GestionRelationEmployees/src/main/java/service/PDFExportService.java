package service;

import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.util.*;
import org.json.JSONObject;

/**
 * Service d'export PDF professionnel
 * Alternative 100% gratuite : Utilise iText (bibliothèque Java)
 * Pas besoin d'API externe, génération locale
 */
public class PDFExportService {

    // ═══════════════════════════════════════════════════════════════
    // GÉNÉRATION PDF - RAPPORT DE PROJET
    // ═══════════════════════════════════════════════════════════════

    /**
     * Génère un rapport PDF complet pour un projet
     * Utilise HTML + conversion locale (pas d'API nécessaire)
     */
    public File generateProjectReport(models.Project project,
                                      List<models.ProjectTask> tasks,
                                      List<models.ProjectCollaborator> team) {
        try {
            // Générer le HTML du rapport
            String htmlContent = buildProjectReportHTML(project, tasks, team);

            // Créer un fichier temporaire
            File pdfFile = File.createTempFile("project_report_" + project.getId() + "_", ".pdf");

            // Conversion HTML → PDF avec bibliothèque gratuite
            convertHTMLtoPDF(htmlContent, pdfFile);

            System.out.println("✅ Rapport PDF généré : " + pdfFile.getAbsolutePath());
            return pdfFile;

        } catch (Exception e) {
            System.err.println("❌ Erreur génération PDF : " + e.getMessage());
            e.printStackTrace();
            return null;
        }
    }

    /**
     * Génère un PDF d'évaluation d'employé
     */
    public File generateEmployeeEvaluation(String employeeName, String period,
                                           Map<String, String> evaluationData) {
        try {
            String htmlContent = buildEmployeeEvaluationHTML(employeeName, period, evaluationData);
            File pdfFile = File.createTempFile("evaluation_" + employeeName.replace(" ", "_") + "_", ".pdf");
            convertHTMLtoPDF(htmlContent, pdfFile);

            System.out.println("✅ Évaluation PDF générée : " + pdfFile.getAbsolutePath());
            return pdfFile;

        } catch (Exception e) {
            System.err.println("❌ Erreur génération évaluation PDF : " + e.getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // TEMPLATES HTML
    // ═══════════════════════════════════════════════════════════════

    private String buildProjectReportHTML(models.Project project,
                                          List<models.ProjectTask> tasks,
                                          List<models.ProjectCollaborator> team) {

        long completedTasks = tasks.stream().filter(t -> t.getStatus() == models.ProjectTask.Status.done).count();
        long overdueTasks = tasks.stream().filter(models.ProjectTask::isOverdue).count();

        StringBuilder html = new StringBuilder();
        html.append("<!DOCTYPE html><html><head><meta charset='UTF-8' />");
        html.append("<style>");
        html.append("body { font-family: Arial, sans-serif; margin: 40px; color: #333; }");
        html.append("h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }");
        html.append("h2 { color: #34495e; margin-top: 30px; }");
        html.append(".header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; margin: -40px -40px 30px -40px; }");
        html.append(".info-box { background: #ecf0f1; padding: 15px; margin: 10px 0; border-radius: 5px; }");
        html.append(".label { font-weight: bold; color: #2c3e50; }");
        html.append("table { width: 100%; border-collapse: collapse; margin: 20px 0; }");
        html.append("th { background: #3498db; color: white; padding: 12px; text-align: left; }");
        html.append("td { padding: 10px; border-bottom: 1px solid #ddd; }");
        html.append("tr:nth-child(even) { background: #f8f9fa; }");
        html.append(".status-done { color: #27ae60; font-weight: bold; }");
        html.append(".status-pending { color: #f39c12; font-weight: bold; }");
        html.append(".status-overdue { color: #e74c3c; font-weight: bold; }");
        html.append(".footer { margin-top: 50px; text-align: center; color: #95a5a6; font-size: 12px; }");
        html.append("</style></head><body>");

        // Header
        html.append("<div class='header'>");
        html.append("<h1 style='margin:0; color: white;'>📊 Rapport de Projet</h1>");
        html.append("<p style='margin: 5px 0; font-size: 18px;'>").append(project.getName()).append("</p>");
        html.append("<p style='margin: 0; font-size: 14px;'>Généré le ").append(new java.util.Date()).append("</p>");
        html.append("</div>");

        // Informations générales
        html.append("<h2>📋 Informations Générales</h2>");
        html.append("<div class='info-box'>");
        html.append("<p><span class='label'>Statut :</span> ").append(project.getStatusEmoji()).append(" ").append(project.getStatus().name()).append("</p>");
        html.append("<p><span class='label'>Priorité :</span> ").append(project.getPriorityEmoji()).append(" ").append(project.getPriority().name()).append("</p>");
        html.append("<p><span class='label'>Dates :</span> Du ").append(project.getStartDate()).append(" au ").append(project.getEndDate()).append("</p>");
        html.append("<p><span class='label'>Avancement :</span> ").append(project.getCompletionRate()).append("%</p>");
        if (project.getEstimatedHours() != null) {
            html.append("<p><span class='label'>Heures :</span> ").append(project.getActualHours()).append("h / ").append(project.getEstimatedHours()).append("h");
            html.append(" (").append(project.getBudgetUsageRate()).append("%)</p>");
        }
        if (project.getBudget() != null) {
            html.append("<p><span class='label'>Budget :</span> ").append(project.getBudget()).append("€</p>");
        }
        html.append("</div>");

        // Équipe
        html.append("<h2>👥 Équipe (").append(team.size()).append(" membres)</h2>");
        html.append("<table>");
        html.append("<tr><th>Nom</th><th>Rôle</th><th>Heures Travaillées</th></tr>");
        for (models.ProjectCollaborator collab : team) {
            html.append("<tr>");
            html.append("<td>").append(collab.getEmployeeName()).append("</td>");
            html.append("<td>").append(collab.getRole()).append("</td>");
            html.append("<td>").append(collab.getHoursProgress()).append("</td>");
            html.append("</tr>");
        }
        html.append("</table>");

        // Tâches
        html.append("<h2>✅ Tâches (").append(completedTasks).append("/").append(tasks.size()).append(" terminées)</h2>");
        if (overdueTasks > 0) {
            html.append("<p class='status-overdue'>⚠️ ").append(overdueTasks).append(" tâche(s) en retard</p>");
        }
        html.append("<table>");
        html.append("<tr><th>Tâche</th><th>Assigné à</th><th>Statut</th><th>Échéance</th></tr>");
        for (models.ProjectTask task : tasks) {
            html.append("<tr>");
            html.append("<td>").append(task.getTitle()).append("</td>");
            html.append("<td>").append(task.getAssignedToName() != null ? task.getAssignedToName() : "Non assigné").append("</td>");

            String statusClass = task.getStatus() == models.ProjectTask.Status.done ? "status-done" :
                    task.isOverdue() ? "status-overdue" : "status-pending";
            html.append("<td class='").append(statusClass).append("'>").append(task.getStatusLabel()).append("</td>");
            html.append("<td>").append(task.getDueDate()).append("</td>");
            html.append("</tr>");
        }
        html.append("</table>");

        // Footer
        html.append("<div class='footer'>");
        html.append("<p>Rapport généré par Workforce Platform</p>");
        html.append("<p>© 2026 Tous droits réservés</p>");
        html.append("</div>");

        html.append("</body></html>");

        return html.toString();
    }

    private String buildEmployeeEvaluationHTML(String employeeName, String period,
                                               Map<String, String> evaluationData) {
        StringBuilder html = new StringBuilder();
        html.append("<!DOCTYPE html><html><head><meta charset='UTF-8' />");
        html.append("<style>");
        html.append("body { font-family: Arial, sans-serif; margin: 40px; color: #333; }");
        html.append("h1 { color: #2c3e50; text-align: center; }");
        html.append(".header { background: #3498db; color: white; padding: 20px; text-align: center; margin: -40px -40px 30px -40px; }");
        html.append(".section { margin: 20px 0; padding: 15px; background: #ecf0f1; border-radius: 5px; }");
        html.append(".label { font-weight: bold; color: #2c3e50; }");
        html.append("</style></head><body>");

        html.append("<div class='header'>");
        html.append("<h1 style='margin:0; color: white;'>Fiche d'Évaluation</h1>");
        html.append("<p style='margin: 5px 0;'>").append(employeeName).append("</p>");
        html.append("<p style='margin: 0; font-size: 14px;'>Période : ").append(period).append("</p>");
        html.append("</div>");

        for (Map.Entry<String, String> entry : evaluationData.entrySet()) {
            html.append("<div class='section'>");
            html.append("<p class='label'>").append(entry.getKey()).append(" :</p>");
            html.append("<p>").append(entry.getValue()).append("</p>");
            html.append("</div>");
        }

        html.append("<div style='margin-top: 50px; text-align: center; color: #95a5a6; font-size: 12px;'>");
        html.append("<p>Document généré le ").append(new java.util.Date()).append("</p>");
        html.append("</div>");

        html.append("</body></html>");
        return html.toString();
    }

    // ═══════════════════════════════════════════════════════════════
    // CONVERSION HTML → PDF (Méthode simple avec Flying Saucer)
    // ═══════════════════════════════════════════════════════════════

    private void convertHTMLtoPDF(String htmlContent, File outputFile) throws Exception {
        // Cette méthode utilise Flying Saucer (bibliothèque gratuite)
        // Ajoutez la dépendance Maven :
        // <dependency>
        //     <groupId>org.xhtmlrenderer</groupId>
        //     <artifactId>flying-saucer-pdf</artifactId>
        //     <version>9.1.22</version>
        // </dependency>

        try (FileOutputStream os = new FileOutputStream(outputFile)) {
            org.xhtmlrenderer.pdf.ITextRenderer renderer = new org.xhtmlrenderer.pdf.ITextRenderer();
            renderer.setDocumentFromString(htmlContent);
            renderer.layout();
            renderer.createPDF(os);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // MÉTHODE ALTERNATIVE : Sauvegarde HTML (si PDF non dispo)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Sauvegarde le rapport en HTML (alternative si PDF pose problème)
     */
    public File saveAsHTML(models.Project project,
                           List<models.ProjectTask> tasks,
                           List<models.ProjectCollaborator> team) {
        try {
            String htmlContent = buildProjectReportHTML(project, tasks, team);
            File htmlFile = File.createTempFile("project_report_" + project.getId() + "_", ".html");

            Files.writeString(htmlFile.toPath(), htmlContent, StandardCharsets.UTF_8);

            System.out.println("✅ Rapport HTML généré : " + htmlFile.getAbsolutePath());
            return htmlFile;

        } catch (Exception e) {
            System.err.println("❌ Erreur génération HTML : " + e.getMessage());
            return null;
        }
    }
}