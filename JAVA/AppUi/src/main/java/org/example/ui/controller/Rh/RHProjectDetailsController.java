package org.example.ui.controller.Rh;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.input.ClipboardContent;
import javafx.scene.input.Dragboard;
import javafx.scene.input.TransferMode;
import javafx.scene.layout.*;
import javafx.scene.paint.Color;
import models.*;
import org.example.model.User;
import org.example.ui.MainApp;
import service.*;
import service.PDFExportService;
import java.awt.Desktop;
import java.io.File;

import java.sql.Date;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

/**
 * Controller pour la page détaillée d'un projet avec onglets
 */
public class RHProjectDetailsController {

    // ═══════════════════════════════════════════════════════════════
    // FXML - GÉNÉRAL
    // ═══════════════════════════════════════════════════════════════

    @FXML private Label lblProjectName;
    @FXML private Label lblProjectStatus;
    @FXML private Label lblProjectPriority;
    @FXML private Label lblProgress;
    @FXML private Label lblDates;
    @FXML private Label lblBudget;
    @FXML private ProgressBar progressBar;
    @FXML private Button btnBack;
    @FXML private Button btnEdit;
    @FXML private Button btnDelete;
    @FXML private Button btnExportPDF;

    @FXML private TabPane tabPane;
    @FXML private Label lblPredictionDetail;
    @FXML private Label lblHealthScoreDetail;
    @FXML private VBox vboxHealthDetails;
    @FXML private Label lblPrediction;
    @FXML private Label lblHealthScore;
    @FXML private VBox vboxRisks;
    @FXML private VBox vboxWarnings;

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 1 : ÉQUIPE
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<ProjectCollaborator> tableTeam;
    @FXML private TableColumn<ProjectCollaborator, String> colTeamName;
    @FXML private TableColumn<ProjectCollaborator, String> colTeamRole;
    @FXML private TableColumn<ProjectCollaborator, String> colTeamHours;
    @FXML private TableColumn<ProjectCollaborator, Date> colTeamJoined;

    @FXML private Button btnAddMember;
    @FXML private Button btnRemoveMember;
    @FXML private Button btnEditMember;

    @FXML private Label lblTeamCount;
    @FXML private Label lblTotalHours;

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 2 : TÂCHES KANBAN
    // ═══════════════════════════════════════════════════════════════

    @FXML private VBox kanbanTodo;
    @FXML private VBox kanbanInProgress;
    @FXML private VBox kanbanReview;
    @FXML private VBox kanbanDone;

    @FXML private Button btnNewTask;
    @FXML private Label lblTaskStats;

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 3 : JALONS
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<ProjectMilestone> tableMilestones;
    @FXML private TableColumn<ProjectMilestone, String> colMilestoneName;
    @FXML private TableColumn<ProjectMilestone, String> colMilestoneStatus;
    @FXML private TableColumn<ProjectMilestone, String> colMilestoneProgress;
    @FXML private TableColumn<ProjectMilestone, Date> colMilestoneTarget;
    @FXML private TableColumn<ProjectMilestone, String> colMilestoneDays;

    @FXML private Button btnNewMilestone;
    @FXML private Button btnEditMilestone;
    @FXML private Button btnDeleteMilestone;
    @FXML private Button btnCompleteMilestone;

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 4 : ACTIVITÉS
    // ═══════════════════════════════════════════════════════════════

    @FXML private ListView<String> listActivities;
    @FXML private TextArea txtNewComment;
    @FXML private Button btnAddComment;

    // ═══════════════════════════════════════════════════════════════
    // SERVICES & DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private final ProjectService projectService = new ProjectService();
    private final ProjectTaskService taskService = new ProjectTaskService();
    private final ProjectCollaboratorService collaboratorService = new ProjectCollaboratorService();
    private final ProjectMilestoneService milestoneService = new ProjectMilestoneService();
    private final ProjectUpdateService updateService = new ProjectUpdateService();

    private final PDFExportService pdfService = new PDFExportService();

    private ObservableList<ProjectCollaborator> teamList = FXCollections.observableArrayList();
    private ObservableList<ProjectMilestone> milestonesList = FXCollections.observableArrayList();
    private List<ProjectTask> allTasks = new ArrayList<>();
    private final ProjectAnalyticsService analyticsService = new ProjectAnalyticsService();

    private Project currentProject;
    private User currentUser;
    private int rhId;

    // ═══════════════════════════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    public void initialize() {
        setupTeamTable();
        setupMilestonesTable();
        setupKanbanColumns();
    }

    public void initData(Project project, User user) {
        this.currentProject = project;
        this.currentUser = user;
        this.rhId = user.getId();

        loadProjectInfo();
        loadTeam();
        loadTasks();
        loadMilestones();
        loadActivities();
    }

    // ═══════════════════════════════════════════════════════════════
    // CHARGEMENT DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private void loadProjectInfo() {
        currentProject = projectService.getById(currentProject.getId());

        lblProjectName.setText(currentProject.getStatusEmoji() + " " + currentProject.getName());
        lblProjectStatus.setText("Statut: " + currentProject.getStatus().name());
        lblProjectPriority.setText("Priorité: " + currentProject.getPriorityEmoji() + " " +
                currentProject.getPriority().name());
        lblProgress.setText("Avancement: " + currentProject.getCompletionRate() + "%");
        lblDates.setText("Du " + currentProject.getStartDate() + " au " + currentProject.getEndDate());

        if (currentProject.getBudget() != null) {
            lblBudget.setText("Budget: " + currentProject.getBudget() + "€");
        }

        progressBar.setProgress(currentProject.getCompletionRate() / 100.0);
        loadAnalytics();
    }
    // ═══════════════════════════════════════════════════════════════
// NOUVELLE MÉTHODE : Charger les analytics
// ═══════════════════════════════════════════════════════════════

    private void loadAnalytics() {
        // 1. PRÉDICTION DE DATE DE FIN - DÉTAILLÉE
        ProjectAnalyticsService.ProjectPrediction prediction =
                analyticsService.predictCompletionDate(currentProject.getId());

        if (prediction != null && prediction.getStatus() !=
                ProjectAnalyticsService.ProjectPrediction.Status.NO_DATA) {

            // Calculer les détails pour l'affichage
            List<ProjectTask> allTasks = taskService.getByProjectId(currentProject.getId());
            long totalTasks = allTasks.size();
            long completedTasks = allTasks.stream()
                    .filter(t -> t.getStatus() == ProjectTask.Status.done)
                    .count();
            long remainingTasks = totalTasks - completedTasks;

            // Calculer la vélocité
            LocalDate startDate = currentProject.getStartDate() != null ?
                    currentProject.getStartDate().toLocalDate() : LocalDate.now().minusMonths(1);
            long daysElapsed = ChronoUnit.DAYS.between(startDate, LocalDate.now());
            if (daysElapsed <= 0) daysElapsed = 1;
            double velocity = completedTasks > 0 ? (double) completedTasks / daysElapsed : 0;
            int daysNeeded = velocity > 0 ? (int) Math.ceil(remainingTasks / velocity) : 0;

            // ✅ AFFICHAGE DÉTAILLÉ
            StringBuilder predictionText = new StringBuilder();
            predictionText.append(prediction.getStatusEmoji()).append(" ").append(prediction.getMessage()).append("\n\n");
            predictionText.append("📊 Détails de l'analyse:\n");
            predictionText.append(String.format("• Vélocité actuelle: %.2f tâches/jour\n", velocity));
            predictionText.append("• Tâches terminées: ").append(completedTasks).append("/").append(totalTasks).append("\n");
            predictionText.append("• Tâches restantes: ").append(remainingTasks).append("\n");
            predictionText.append("• Jours nécessaires: ").append(daysNeeded).append(" jours\n");
            predictionText.append("• Date planifiée: ").append(currentProject.getEndDate()).append("\n");
            predictionText.append("• Date prédite: ").append(prediction.getPredictedEndDate()).append("\n\n");

            if (prediction.getDaysDifference() > 0) {
                predictionText.append("🔴 Retard estimé: ").append(prediction.getDaysDifference()).append(" jours");
            } else if (prediction.getDaysDifference() < 0) {
                predictionText.append("🟢 Avance estimée: ").append(Math.abs(prediction.getDaysDifference())).append(" jours");
            } else {
                predictionText.append("✅ Dans les temps");
            }

            lblPredictionDetail.setText(predictionText.toString());

            // Couleur selon le statut
            switch (prediction.getStatus()) {
                case EARLY -> lblPredictionDetail.setStyle("-fx-text-fill: #27ae60;");
                case ON_TRACK -> lblPredictionDetail.setStyle("-fx-text-fill: #3498db;");
                case LATE -> lblPredictionDetail.setStyle("-fx-text-fill: #e74c3c;");
            }

            // Aussi mettre à jour le label dans le header
            if (lblPrediction != null) {
                lblPrediction.setText(prediction.getStatusEmoji() + " " + prediction.getMessage() +
                        "\nDate prédite: " + prediction.getPredictedEndDate());
                lblPrediction.setStyle("-fx-text-fill: " +
                        (prediction.getStatus() == ProjectAnalyticsService.ProjectPrediction.Status.LATE ?
                                "#e74c3c" : "#27ae60") + "; -fx-font-weight: bold;");
            }
        } else {
            lblPredictionDetail.setText("⚪ Données insuffisantes pour établir une prédiction.\n\n" +
                    "💡 Pour activer les prédictions:\n" +
                    "• Créez des tâches pour ce projet\n" +
                    "• Commencez à les marquer comme terminées\n" +
                    "• La prédiction sera disponible après quelques jours d'activité");
            lblPredictionDetail.setStyle("-fx-text-fill: #95a5a6;");
        }

        // 2. INDICATEUR DE SANTÉ - DÉTAILLÉ
        ProjectAnalyticsService.ProjectHealthIndicator health =
                analyticsService.calculateProjectHealth(currentProject.getId());

        if (health != null) {
            // Score principal
            lblHealthScoreDetail.setText(String.valueOf(health.getScore()));
            lblHealthScoreDetail.setStyle("-fx-text-fill: " + health.getStatusColor() + ";");

            // Détails de santé
            vboxHealthDetails.getChildren().clear();

            Label statusLabel = new Label(health.getStatusEmoji() + " État: " + health.getStatus().name());
            statusLabel.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: " +
                    health.getStatusColor() + ";");
            vboxHealthDetails.getChildren().add(statusLabel);

            vboxHealthDetails.getChildren().add(new Separator());

            // Avertissements
            if (!health.getWarnings().isEmpty() || !health.getCriticals().isEmpty()) {
                Label alertTitle = new Label("🔔 Alertes détectées:");
                alertTitle.setStyle("-fx-font-weight: bold; -fx-font-size: 14px;");
                vboxHealthDetails.getChildren().add(alertTitle);

                for (String critical : health.getCriticals()) {
                    Label lblCrit = new Label("  " + critical);
                    lblCrit.setStyle("-fx-text-fill: #e74c3c; -fx-font-weight: bold; -fx-padding: 3;");
                    lblCrit.setWrapText(true);
                    vboxHealthDetails.getChildren().add(lblCrit);
                }

                for (String warning : health.getWarnings()) {
                    Label lblWarn = new Label("  " + warning);
                    lblWarn.setStyle("-fx-text-fill: #f39c12; -fx-padding: 3;");
                    lblWarn.setWrapText(true);
                    vboxHealthDetails.getChildren().add(lblWarn);
                }
            } else {
                Label noAlert = new Label("✅ Aucune alerte - Projet en bonne santé");
                noAlert.setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;");
                vboxHealthDetails.getChildren().add(noAlert);
            }

            // Aussi mettre à jour le header
            if (lblHealthScore != null) {
                lblHealthScore.setText(health.getStatusEmoji() + " Santé: " + health.getScore() + "/100");
                lblHealthScore.setStyle("-fx-text-fill: " + health.getStatusColor() +
                        "; -fx-font-weight: bold; -fx-font-size: 16px;");
            }
        }

        // 3. RISQUES IDENTIFIÉS
        List<ProjectAnalyticsService.ProjectRisk> risks =
                analyticsService.analyzeRisks(currentProject.getId());

        vboxRisks.getChildren().clear();

        for (ProjectAnalyticsService.ProjectRisk risk : risks) {
            VBox riskCard = new VBox(8);
            riskCard.setPadding(new Insets(12));

            String bgColor = switch (risk.getSeverity()) {
                case CRITICAL -> "#fde2e2";
                case HIGH -> "#fed7d7";
                case MEDIUM -> "#fef3c7";
                case LOW -> "#d1fae5";
            };

            String borderColor = switch (risk.getSeverity()) {
                case CRITICAL -> "#f87171";
                case HIGH -> "#fca5a5";
                case MEDIUM -> "#fbbf24";
                case LOW -> "#6ee7b7";
            };

            riskCard.setStyle("-fx-background-color: " + bgColor + "; -fx-border-color: " + borderColor +
                    "; -fx-border-radius: 8; -fx-background-radius: 8; -fx-border-width: 2;");

            Label title = new Label(risk.getSeverityEmoji() + " " + risk.getTitle().toUpperCase());
            title.setStyle("-fx-font-weight: bold; -fx-font-size: 14px;");

            Label desc = new Label(risk.getDescription());
            desc.setWrapText(true);
            desc.setStyle("-fx-font-size: 13px; -fx-padding: 5 0;");

            Separator sep = new Separator();

            Label reco = new Label("💡 Recommandation: " + risk.getRecommendation());
            reco.setWrapText(true);
            reco.setStyle("-fx-font-size: 12px; -fx-text-fill: #059669; -fx-font-style: italic;");

            riskCard.getChildren().addAll(title, desc, sep, reco);
            vboxRisks.getChildren().add(riskCard);
        }

        if (risks.isEmpty()) {
            VBox noRiskCard = new VBox(10);
            noRiskCard.setPadding(new Insets(15));
            noRiskCard.setStyle("-fx-background-color: #d1fae5; -fx-border-color: #6ee7b7; " +
                    "-fx-border-radius: 8; -fx-background-radius: 8; -fx-border-width: 2;");

            Label noRisk = new Label("✅ AUCUN RISQUE IDENTIFIÉ");
            noRisk.setStyle("-fx-text-fill: #059669; -fx-font-weight: bold; -fx-font-size: 16px;");

            Label subtext = new Label("Le projet progresse bien et ne présente aucun risque majeur.");
            subtext.setStyle("-fx-text-fill: #065f46; -fx-font-size: 13px;");

            noRiskCard.getChildren().addAll(noRisk, subtext);
            vboxRisks.getChildren().add(noRiskCard);
        }
    }

    private void loadTeam() {
        teamList.clear();
        teamList.addAll(collaboratorService.getByProjectId(currentProject.getId()));

        int totalHours = teamList.stream().mapToInt(ProjectCollaborator::getWorkedHours).sum();
        lblTeamCount.setText("Équipe: " + teamList.size() + " membres");
        lblTotalHours.setText("Total heures: " + totalHours + "h");
    }

    private void loadTasks() {
        allTasks = taskService.getByProjectId(currentProject.getId());
        updateKanbanBoard();

        long total = allTasks.size();
        long done = allTasks.stream().filter(t -> t.getStatus() == ProjectTask.Status.done).count();
        lblTaskStats.setText("Tâches: " + done + "/" + total + " terminées");
    }

    private void loadMilestones() {
        milestonesList.clear();
        milestonesList.addAll(milestoneService.getByProjectId(currentProject.getId()));
        milestoneService.checkDelayed(currentProject.getId());
    }

    private void loadActivities() {
        List<ProjectUpdate> updates = updateService.getByProjectId(currentProject.getId(), 20);
        ObservableList<String> items = FXCollections.observableArrayList();
        for (ProjectUpdate u : updates) {
            items.add(u.getSummary());
        }
        listActivities.setItems(items);
    }

    // ═══════════════════════════════════════════════════════════════
    // SETUP TABLES
    // ═══════════════════════════════════════════════════════════════

    private void setupTeamTable() {
        colTeamName.setCellValueFactory(new PropertyValueFactory<>("employeeName"));
        colTeamRole.setCellValueFactory(new PropertyValueFactory<>("role"));
        colTeamHours.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(cellData.getValue().getHoursProgress()));
        colTeamJoined.setCellValueFactory(new PropertyValueFactory<>("joinedDate"));

        tableTeam.setItems(teamList);
        tableTeam.getSelectionModel().selectedItemProperty().addListener(
                (obs, old, newVal) -> {
                    btnRemoveMember.setDisable(newVal == null);
                    btnEditMember.setDisable(newVal == null);
                });

        btnRemoveMember.setDisable(true);
        btnEditMember.setDisable(true);
    }

    private void setupMilestonesTable() {
        colMilestoneName.setCellValueFactory(new PropertyValueFactory<>("name"));
        colMilestoneStatus.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(
                        cellData.getValue().getStatusEmoji() + " " + cellData.getValue().getStatusLabel()));
        colMilestoneProgress.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(cellData.getValue().getProgressBar()));
        colMilestoneTarget.setCellValueFactory(new PropertyValueFactory<>("targetDate"));
        colMilestoneDays.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(cellData.getValue().getDaysInfo()));

        tableMilestones.setItems(milestonesList);
        tableMilestones.getSelectionModel().selectedItemProperty().addListener(
                (obs, old, newVal) -> {
                    boolean hasSelection = newVal != null;
                    btnEditMilestone.setDisable(!hasSelection);
                    btnDeleteMilestone.setDisable(!hasSelection);
                    btnCompleteMilestone.setDisable(!hasSelection ||
                            newVal.getStatus() == ProjectMilestone.Status.completed);
                });
    }

    private void setupKanbanColumns() {
        // Configuration des colonnes drag & drop
        setupDropTarget(kanbanTodo, ProjectTask.Status.todo);
        setupDropTarget(kanbanInProgress, ProjectTask.Status.in_progress);
        setupDropTarget(kanbanReview, ProjectTask.Status.review);
        setupDropTarget(kanbanDone, ProjectTask.Status.done);
    }

    private void setupDropTarget(VBox column, ProjectTask.Status targetStatus) {
        column.setOnDragOver(event -> {
            if (event.getGestureSource() != column && event.getDragboard().hasString()) {
                event.acceptTransferModes(TransferMode.MOVE);
                System.out.println("Survol de la colonne : " + targetStatus); // TEST 1
            }
            event.consume();
        });

        column.setOnDragDropped(event -> {
            System.out.println("LOG: Tentative de drop sur " + targetStatus); // TEST 2
            Dragboard db = event.getDragboard();
            if (db.hasString()) {
                int taskId = Integer.parseInt(db.getString());
                taskService.moveTask(taskId, targetStatus);
                loadTasks();
                loadProjectInfo(); // Rafraîchir l'avancement
                event.setDropCompleted(true);
            }
            event.consume();
        });
    }

    private void updateKanbanBoard() {
        kanbanTodo.getChildren().clear();
        kanbanInProgress.getChildren().clear();
        kanbanReview.getChildren().clear();
        kanbanDone.getChildren().clear();

        for (ProjectTask task : allTasks) {
            VBox taskCard = createTaskCard(task);
            switch (task.getStatus()) {
                case todo -> kanbanTodo.getChildren().add(taskCard);
                case in_progress -> kanbanInProgress.getChildren().add(taskCard);
                case review -> kanbanReview.getChildren().add(taskCard);
                case done -> kanbanDone.getChildren().add(taskCard);
            }
        }
    }

    private VBox createTaskCard(ProjectTask task) {
        VBox card = new VBox(5);
        card.setPadding(new Insets(10));
        card.setStyle("-fx-background-color: white; -fx-border-color: #ddd; -fx-border-radius: 6; " +
                "-fx-background-radius: 6; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.1), 4, 0, 0, 2);");
        card.setMaxWidth(200);

        Label title = new Label(task.getPriorityEmoji() + " " + task.getTitle());
        title.setWrapText(true);
        title.setStyle("-fx-font-weight: bold; -fx-font-size: 12px;");

        Label assignee = new Label(task.getAssignedToName() != null ?
                "👤 " + task.getAssignedToName() : "Non assigné");
        assignee.setStyle("-fx-font-size: 10px; -fx-text-fill: #666;");

        Label due = new Label(task.getDaysUntilDue());
        due.setStyle("-fx-font-size: 10px; -fx-text-fill: " +
                (task.isOverdue() ? "#e74c3c" : "#95a5a6") + ";");

        card.getChildren().addAll(title, assignee, due);

        // Drag & drop
        card.setOnDragDetected(event -> {
            Dragboard db = card.startDragAndDrop(TransferMode.MOVE);
            ClipboardContent content = new ClipboardContent();
            content.putString(String.valueOf(task.getId()));
            db.setContent(content);
            event.consume();
        });

        // Double-click pour éditer
        card.setOnMouseClicked(event -> {
            if (event.getClickCount() == 2) {
                handleEditTask(task);
            }
        });

        return card;
    }
    @FXML
    private void handleExportPDF() {
        Alert progress = new Alert(Alert.AlertType.INFORMATION);
        progress.setTitle("Export PDF");
        progress.setHeaderText("Génération du rapport en cours...");
        progress.setContentText("Veuillez patienter...");
        progress.show();

        // Exécuter dans un thread séparé pour ne pas bloquer l'UI
        new Thread(() -> {
            try {
                // Charger toutes les données nécessaires
                List<ProjectTask> tasks = taskService.getByProjectId(currentProject.getId());
                List<ProjectCollaborator> team = collaboratorService.getByProjectId(currentProject.getId());

                // Générer le PDF
                File pdfFile = pdfService.generateProjectReport(currentProject, tasks, team);

                javafx.application.Platform.runLater(() -> {
                    progress.close();

                    if (pdfFile != null && pdfFile.exists()) {
                        Alert success = new Alert(Alert.AlertType.CONFIRMATION);
                        success.setTitle("PDF Généré");
                        success.setHeaderText("✅ Rapport exporté avec succès !");
                        success.setContentText("Fichier: " + pdfFile.getName() +
                                "\n\nSouhaitez-vous ouvrir le PDF ?");

                        success.showAndWait().ifPresent(response -> {
                            if (response == ButtonType.OK) {
                                try {
                                    Desktop.getDesktop().open(pdfFile);
                                } catch (Exception e) {
                                    showAlert(Alert.AlertType.ERROR, "Erreur",
                                            "Impossible d'ouvrir le fichier.\nChemin: " +
                                                    pdfFile.getAbsolutePath());
                                    e.printStackTrace();
                                }
                            }
                        });
                    } else {
                        showAlert(Alert.AlertType.ERROR, "Erreur",
                                "Impossible de générer le PDF.\nVérifiez les logs de la console.");
                    }
                });

            } catch (Exception e) {
                javafx.application.Platform.runLater(() -> {
                    progress.close();
                    showAlert(Alert.AlertType.ERROR, "Erreur",
                            "Erreur lors de la génération du PDF:\n" + e.getMessage());
                });
                e.printStackTrace();
            }
        }).start();
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - ÉQUIPE
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleAddMember() {
        // Réutiliser la méthode de RHProjectController
        showAddCollaboratorDialog();
    }

    @FXML
    private void handleRemoveMember() {
        ProjectCollaborator selected = tableTeam.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setContentText("Retirer " + selected.getEmployeeName() + " du projet ?");
        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                collaboratorService.removeFromProject(selected.getId());
                loadTeam();
            }
        });
    }

    @FXML
    private void handleEditMember() {
        ProjectCollaborator selected = tableTeam.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        TextInputDialog roleDialog = new TextInputDialog(selected.getRole());
        roleDialog.setTitle("Modifier le rôle");
        roleDialog.setHeaderText(selected.getEmployeeName());
        roleDialog.setContentText("Nouveau rôle:");

        roleDialog.showAndWait().ifPresent(newRole -> {
            selected.setRole(newRole);
            if (collaboratorService.update(selected)) {
                loadTeam();
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Rôle modifié.");
            }
        });
    }

    private void showAddCollaboratorDialog() {
        // Code identique à RHProjectController
        // [Copier la méthode showAddCollaboratorDialog de RHProjectController]
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - TÂCHES
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleNewTask() {
        Dialog<ProjectTask> dialog = createTaskDialog(null);
        dialog.setTitle("Nouvelle Tâche");

        dialog.showAndWait().ifPresent(task -> {
            task.setProjectId(currentProject.getId());
            if (taskService.add(task)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Tâche créée !");
                loadTasks();
                loadProjectInfo();
            }
        });
    }

    private void handleEditTask(ProjectTask task) {
        Dialog<ProjectTask> dialog = createTaskDialog(task);
        dialog.setTitle("Modifier la Tâche");

        dialog.showAndWait().ifPresent(updatedTask -> {
            if (taskService.update(updatedTask)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Tâche modifiée !");
                loadTasks();
                loadProjectInfo();
            }
        });
    }

    private Dialog<ProjectTask> createTaskDialog(ProjectTask existing) {
        Dialog<ProjectTask> dialog = new Dialog<>();
        ButtonType saveType = new ButtonType("Sauvegarder", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20));

        TextField titleField = new TextField(existing != null ? existing.getTitle() : "");
        titleField.setPromptText("Titre de la tâche");

        TextArea descArea = new TextArea(existing != null ? existing.getDescription() : "");
        descArea.setPrefRowCount(3);
        descArea.setPromptText("Description");

        // --- FUSION : Logique d'assignation d'employé ---
        ComboBox<String> assigneeCombo = new ComboBox<>();
        List<String> employeeItems = new ArrayList<>();
        employeeItems.add("0:Non assigné");

        List<ProjectCollaborator> team = collaboratorService.getByProjectId(currentProject.getId());
        for (ProjectCollaborator collab : team) {
            employeeItems.add(collab.getEmployeeId() + ":" + collab.getEmployeeName());
        }

        assigneeCombo.setItems(FXCollections.observableArrayList(employeeItems));

        if (existing != null && existing.getAssignedTo() != null) {
            // On essaie de reconstruire la chaîne "ID:Nom" pour la sélection
            String selectedValue = existing.getAssignedTo() + ":" + existing.getAssignedToName();
            assigneeCombo.setValue(selectedValue);
        } else {
            assigneeCombo.setValue("0:Non assigné");
        }

        ComboBox<String> priorityCombo = new ComboBox<>(FXCollections.observableArrayList(
                "low", "medium", "high"));
        priorityCombo.setValue(existing != null ? existing.getPriority().name() : "medium");

        TextField hoursField = new TextField(
                existing != null && existing.getEstimatedHours() != null ?
                        existing.getEstimatedHours().toString() : "");
        hoursField.setPromptText("Ex: 8");

        DatePicker duePicker = new DatePicker(
                existing != null && existing.getDueDate() != null ?
                        existing.getDueDate().toLocalDate() : LocalDate.now().plusWeeks(1));

        // Organisation de la grille (Layout)
        grid.add(new Label("Titre:"), 0, 0);
        grid.add(titleField, 1, 0);
        grid.add(new Label("Description:"), 0, 1);
        grid.add(descArea, 1, 1);
        grid.add(new Label("Assigner à:"), 0, 2); // Ajouté
        grid.add(assigneeCombo, 1, 2);           // Ajouté
        grid.add(new Label("Priorité:"), 0, 3);
        grid.add(priorityCombo, 1, 3);
        grid.add(new Label("Heures estimées:"), 0, 4);
        grid.add(hoursField, 1, 4);
        grid.add(new Label("Date limite:"), 0, 5);
        grid.add(duePicker, 1, 5);

        dialog.getDialogPane().setContent(grid);
        dialog.getDialogPane().setPrefWidth(500);

        dialog.setResultConverter(btn -> {
            if (btn == saveType) {
                ProjectTask task = existing != null ? existing : new ProjectTask();

                // 1. Infos de base
                task.setTitle(titleField.getText().trim());
                task.setDescription(descArea.getText().trim());

                // 2. FUSION : Gestion du statut (IMPORTANT pour éviter le crash)
                if (existing == null) {
                    task.setStatus(ProjectTask.Status.todo);
                }

                // 3. FUSION : Récupération de l'employé depuis le ComboBox
                String assigneeValue = assigneeCombo.getValue();
                if (assigneeValue != null && !assigneeValue.startsWith("0:")) {
                    int employeeId = Integer.parseInt(assigneeValue.split(":")[0]);
                    task.setAssignedTo(employeeId);
                } else {
                    task.setAssignedTo(null);
                }

                // 4. Priorité et heures
                task.setPriority(ProjectTask.Priority.valueOf(priorityCombo.getValue()));
                if (!hoursField.getText().trim().isEmpty()) {
                    try {
                        task.setEstimatedHours(Integer.parseInt(hoursField.getText().trim()));
                    } catch (NumberFormatException e) {
                        System.err.println("Erreur format heures");
                    }
                }

                task.setDueDate(java.sql.Date.valueOf(duePicker.getValue()));

                return task;
            }
            return null;
        });

        return dialog;
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - JALONS
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleNewMilestone() {
        Dialog<ProjectMilestone> dialog = createMilestoneDialog(null);
        dialog.showAndWait().ifPresent(milestone -> {
            milestone.setProjectId(currentProject.getId());
            if (milestoneService.add(milestone)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Jalon créé !");
                loadMilestones();
            }
        });
    }

    @FXML
    private void handleEditMilestone() {
        ProjectMilestone selected = tableMilestones.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        Dialog<ProjectMilestone> dialog = createMilestoneDialog(selected);
        dialog.showAndWait().ifPresent(milestone -> {
            if (milestoneService.update(milestone)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Jalon modifié !");
                loadMilestones();
            }
        });
    }

    @FXML
    private void handleDeleteMilestone() {
        ProjectMilestone selected = tableMilestones.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setContentText("Supprimer le jalon '" + selected.getName() + "' ?");
        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK && milestoneService.delete(selected.getId())) {
                loadMilestones();
            }
        });
    }

    @FXML
    private void handleCompleteMilestone() {
        ProjectMilestone selected = tableMilestones.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        if (milestoneService.markAsCompleted(selected.getId())) {
            showAlert(Alert.AlertType.INFORMATION, "Succès", "Jalon marqué comme terminé !");
            loadMilestones();
        }
    }

    private Dialog<ProjectMilestone> createMilestoneDialog(ProjectMilestone existing) {
        Dialog<ProjectMilestone> dialog = new Dialog<>();
        dialog.setTitle(existing == null ? "Nouveau Jalon" : "Modifier le Jalon");

        ButtonType saveType = new ButtonType("Sauvegarder", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20));

        TextField nameField = new TextField(existing != null ? existing.getName() : "");
        TextArea descArea = new TextArea(existing != null ? existing.getDescription() : "");
        DatePicker targetPicker = new DatePicker(
                existing != null ? existing.getTargetDate().toLocalDate() : LocalDate.now().plusMonths(1));

        grid.add(new Label("Nom:"), 0, 0);
        grid.add(nameField, 1, 0);
        grid.add(new Label("Description:"), 0, 1);
        grid.add(descArea, 1, 1);
        grid.add(new Label("Date cible:"), 0, 2);
        grid.add(targetPicker, 1, 2);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(btn -> {
            if (btn == saveType) {
                ProjectMilestone milestone = existing != null ? existing : new ProjectMilestone();
                milestone.setName(nameField.getText().trim());
                milestone.setDescription(descArea.getText().trim());
                milestone.setTargetDate(Date.valueOf(targetPicker.getValue()));
                if (existing == null) {
                    // On définit le statut par défaut pour un nouveau jalon
                    milestone.setStatus(ProjectMilestone.Status.pending);
                }
                return milestone;
            }
            return null;
        });

        return dialog;
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - ACTIVITÉS
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleAddComment() {
        String comment = txtNewComment.getText().trim();
        if (comment.isEmpty()) return;

        ProjectUpdate update = new ProjectUpdate(
                currentProject.getId(),
                rhId,
                ProjectUpdate.UpdateType.comment,
                "Commentaire",
                comment
        );

        if (updateService.add(update)) {
            txtNewComment.clear();
            loadActivities();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - GÉNÉRAL
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleBack() {
        if (currentUser != null) {
            MainApp.showRHDashboard(currentUser);
        }
    }

    @FXML
    private void handleEdit() {
        // Ouvrir le dialog d'édition du projet
        showAlert(Alert.AlertType.INFORMATION, "TODO", "Fonctionnalité à implémenter");
    }

    @FXML
    private void handleDelete() {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setContentText("Supprimer le projet '" + currentProject.getName() + "' ?");
        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK && projectService.delete(currentProject.getId())) {
                handleBack();
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // UTILITAIRES
    // ═══════════════════════════════════════════════════════════════

    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}