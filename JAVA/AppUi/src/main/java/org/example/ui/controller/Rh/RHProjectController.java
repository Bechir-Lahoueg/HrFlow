package org.example.ui.controller.Rh;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.scene.Parent;
import models.*;
import org.example.model.User;
import org.example.ui.MainApp;
import service.*;
import service.PDFExportService;
import java.awt.Desktop;
import java.io.File;

import java.math.BigDecimal;
import java.sql.Date;
import java.time.LocalDate;
import java.util.List;
import java.util.ArrayList;
import java.util.Optional;

/**
 * Contrôleur RH pour la gestion des projets et collaborateurs
 */
public class RHProjectController {

    // ═══════════════════════════════════════════════════════════════
    // FXML - LISTE DES PROJETS
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Project> tableProjects;
    @FXML private TableColumn<Project, Integer> colId;
    @FXML private TableColumn<Project, String> colName;
    @FXML private TableColumn<Project, String> colStatus;
    @FXML private TableColumn<Project, String> colPriority;
    @FXML private TableColumn<Project, String> colProgress;
    @FXML private TableColumn<Project, Date> colEndDate;
    @FXML private TableColumn<Project, Integer> colCollabCount;

    @FXML private ComboBox<String> filterStatus;
    @FXML private ComboBox<String> filterPriority;
    @FXML private TextField searchField;

    @FXML private Button btnNewProject;
    @FXML private Button btnViewDetails;
    @FXML private Button btnEditProject;
    @FXML private Button btnDeleteProject;

    @FXML private Label lblTotalProjects;
    @FXML private Label lblActiveProjects;
    @FXML private Label lblDelayedProjects;
    @FXML private Label lblCompletedProjects;
    @FXML private Label lblAtRisk;
    @FXML private Label lblSuccessRate;
    @FXML private Label lblBudgetUsage;
    @FXML private VBox vboxTopPerformers;

    // ═══════════════════════════════════════════════════════════════
    // SERVICES & DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private final ProjectService projectService = new ProjectService();
    private final ProjectTaskService taskService = new ProjectTaskService();
    private final ProjectCollaboratorService collaboratorService = new ProjectCollaboratorService();
    private final ProjectMilestoneService milestoneService = new ProjectMilestoneService();
    private final ProjectAnalyticsService analyticsService = new ProjectAnalyticsService();
    private final PDFExportService pdfService = new PDFExportService();

    private ObservableList<Project> projectsList = FXCollections.observableArrayList();
    private User currentUser;
    private int rhId;

    // ═══════════════════════════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    public void initialize() {
        setupProjectsTable();
        setupFilters();
        disableActionButtons();
    }

    public void setCurrentUser(User user) {
        this.currentUser = user;
        this.rhId = user.getId();
        loadProjects();
        updateStatistics();
    }

    @FXML
    private void handleBackToDashboard() {
        if (currentUser != null) {
            MainApp.showRHDashboard(currentUser);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SETUP
    // ═══════════════════════════════════════════════════════════════

    private void setupProjectsTable() {
        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colName.setCellValueFactory(new PropertyValueFactory<>("name"));
        colStatus.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(
                        cellData.getValue().getStatusEmoji() + " " + cellData.getValue().getStatus().name()));
        colPriority.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(
                        cellData.getValue().getPriorityEmoji() + " " + cellData.getValue().getPriority().name()));
        colProgress.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(cellData.getValue().getProgressBar()));
        colEndDate.setCellValueFactory(new PropertyValueFactory<>("endDate"));
        colCollabCount.setCellValueFactory(new PropertyValueFactory<>("collaboratorCount"));

        // Style pour le statut
        colStatus.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String status, boolean empty) {
                super.updateItem(status, empty);
                if (empty || status == null) {
                    setText(null);
                    setStyle("");
                } else {
                    setText(status);
                    if (status.contains("in_progress")) setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;");
                    else if (status.contains("completed")) setStyle("-fx-text-fill: #3498db; -fx-font-weight: bold;");
                    else if (status.contains("on_hold")) setStyle("-fx-text-fill: #f39c12; -fx-font-weight: bold;");
                    else if (status.contains("cancelled")) setStyle("-fx-text-fill: #95a5a6; -fx-font-weight: bold;");
                }
            }
        });

        tableProjects.setItems(projectsList);
        tableProjects.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> updateButtonStates(newVal));
    }

    private void setupFilters() {
        filterStatus.setItems(FXCollections.observableArrayList(
                "Tous", "planning", "in_progress", "on_hold", "completed", "cancelled"));
        filterStatus.setValue("Tous");
        filterStatus.setOnAction(e -> applyFilters());

        filterPriority.setItems(FXCollections.observableArrayList(
                "Toutes", "low", "medium", "high", "critical"));
        filterPriority.setValue("Toutes");
        filterPriority.setOnAction(e -> applyFilters());

        searchField.textProperty().addListener((obs, old, newVal) -> applyFilters());
    }

    private void disableActionButtons() {
        btnViewDetails.setDisable(true);
        btnEditProject.setDisable(true);
        btnDeleteProject.setDisable(true);
    }

    private void updateButtonStates(Project project) {
        boolean hasSelection = project != null;
        btnViewDetails.setDisable(!hasSelection);
        btnEditProject.setDisable(!hasSelection);
        btnDeleteProject.setDisable(!hasSelection);
    }

    // ═══════════════════════════════════════════════════════════════
    // CHARGEMENT & FILTRES
    // ═══════════════════════════════════════════════════════════════

    private void loadProjects() {
        projectsList.clear();
        projectsList.addAll(projectService.getByRhId(rhId));
        applyFilters();
    }

    private void applyFilters() {
        String statusFilter = filterStatus.getValue();
        String priorityFilter = filterPriority.getValue();
        String search = searchField.getText().toLowerCase().trim();

        List<Project> all = projectService.getByRhId(rhId);
        List<Project> filtered = all.stream()
                .filter(p -> statusFilter.equals("Tous") || p.getStatus().name().equals(statusFilter))
                .filter(p -> priorityFilter.equals("Toutes") || p.getPriority().name().equals(priorityFilter))
                .filter(p -> search.isEmpty() ||
                        p.getName().toLowerCase().contains(search) ||
                        (p.getDescription() != null && p.getDescription().toLowerCase().contains(search)))
                .toList();

        projectsList.setAll(filtered);
        updateStatistics();
    }

    private void updateStatistics() {
        int total = projectsList.size();
        long active = projectsList.stream().filter(p -> p.getStatus() == Project.Status.in_progress).count();
        long delayed = projectsList.stream().filter(Project::isDelayed).count();
        long completed = projectsList.stream().filter(p -> p.getStatus() == Project.Status.completed).count();

        lblTotalProjects.setText("Total : " + total);
        lblActiveProjects.setText("Actifs : " + active);
        lblDelayedProjects.setText("En retard : " + delayed);
        lblCompletedProjects.setText("Terminés : " + completed);

        // ✅ STATS AVANCÉES
        ProjectAnalyticsService.RHDashboardStats globalStats =
                analyticsService.getGlobalStats(rhId);

        if (globalStats != null && lblAtRisk != null) {
            lblAtRisk.setText("À risque : " + globalStats.getAtRiskProjects());
            lblSuccessRate.setText(String.format("Taux de succès : %.1f%%", globalStats.getSuccessRate()));
            lblBudgetUsage.setText(String.format("Budget : %.1f%%", globalStats.getBudgetUsageRate()));
        }

        // ✅ TOP PERFORMERS
        if (vboxTopPerformers != null) {
            List<ProjectAnalyticsService.EmployeePerformance> topPerformers =
                    analyticsService.getTopPerformers(rhId, 5);

            vboxTopPerformers.getChildren().clear();

            if (topPerformers.isEmpty()) {
                Label noData = new Label("Aucune donnée de performance disponible");
                noData.setStyle("-fx-text-fill: #95a5a6; -fx-font-style: italic;");
                vboxTopPerformers.getChildren().add(noData);
                System.out.println("DEBUG: TopPerformers est vide pour RH ID: " + rhId);
            } else {
                for (int i = 0; i < topPerformers.size(); i++) {
                    ProjectAnalyticsService.EmployeePerformance perf = topPerformers.get(i);

                    String medal = switch (i) {
                        case 0 -> "🥇";
                        case 1 -> "🥈";
                        case 2 -> "🥉";
                        default -> "🏆";
                    };

                    Label perfLabel = new Label(medal + " " + perf.getEmployeeName() + ": " +
                            perf.getCompletedTasks() + " tâches terminées (" + perf.getTotalHours() + "h)");
                    perfLabel.setStyle("-fx-text-fill: #2c3e50; -fx-font-size: 14px; -fx-padding: 5;");
                    //perfLabel.setStyle("-fx-font-size: 13px; -fx-padding: 5; -fx-font-weight: " +
                            //(i < 3 ? "bold" : "normal") + ";");
                    vboxTopPerformers.getChildren().add(perfLabel);
            }
        }
    }
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - PROJETS
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleNewProject() {
        Dialog<Project> dialog = createProjectDialog(null);
        dialog.setTitle("Nouveau Projet");
        dialog.setHeaderText("Créer un nouveau projet");

        Optional<Project> result = dialog.showAndWait();
        result.ifPresent(project -> {
            project.setRhId(rhId);
            if (projectService.add(project)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Projet créé avec succès !");
                loadProjects();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de créer le projet.");
            }
        });
    }

    @FXML
    private void handleEditProject() {
        Project selected = tableProjects.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un projet.");
            return;
        }

        Dialog<Project> dialog = createProjectDialog(selected);
        dialog.setTitle("Modifier le Projet");
        dialog.setHeaderText("Modifier : " + selected.getName());

        Optional<Project> result = dialog.showAndWait();
        result.ifPresent(project -> {
            if (projectService.update(project)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Projet modifié avec succès !");
                loadProjects();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de modifier le projet.");
            }
        });
    }

    @FXML
    private void handleDeleteProject() {
        Project selected = tableProjects.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un projet.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer le projet");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer le projet '" + selected.getName() +
                "' ?\n\nToutes les tâches, collaborateurs et jalons seront supprimés.");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                if (projectService.delete(selected.getId())) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "Projet supprimé.");
                    loadProjects();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de supprimer le projet.");
                }
            }
        });
    }
    // Modifier handleViewDetails pour ouvrir la page complète au lieu d'un popup
    @FXML
    private void handleViewDetails() {
        Project selected = tableProjects.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(
                    "/fxml/views/Rh-dashboard/RHProjectDetailsView.fxml"));
            Parent root = loader.load();

            RHProjectDetailsController controller = loader.getController();
            controller.initData(selected, currentUser);

            // Remplacer la vue actuelle
            javafx.scene.Scene scene = btnViewDetails.getScene();
            scene.setRoot(root);

        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger les détails.");
        }
    }
    @FXML
    private void handleQuickExportPDF() {
        Project selected = tableProjects.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un projet.");
            return;
        }

        Alert progress = new Alert(Alert.AlertType.INFORMATION);
        progress.setTitle("Export PDF");
        progress.setContentText("Génération en cours...");
        progress.show();

        new Thread(() -> {
            try {
                List<ProjectTask> tasks = taskService.getByProjectId(selected.getId());
                List<ProjectCollaborator> team = collaboratorService.getByProjectId(selected.getId());

                File pdfFile = pdfService.generateProjectReport(selected, tasks, team);

                javafx.application.Platform.runLater(() -> {
                    progress.close();
                    if (pdfFile != null) {
                        try {
                            java.awt.Desktop.getDesktop().open(pdfFile);
                            showAlert(Alert.AlertType.INFORMATION, "Succès",
                                    "Rapport PDF ouvert !");
                        } catch (Exception e) {
                            showAlert(Alert.AlertType.ERROR, "Erreur",
                                    "PDF généré mais impossible de l'ouvrir.\nChemin: " +
                                            pdfFile.getAbsolutePath());
                        }
                    }
                });
            } catch (Exception e) {
                javafx.application.Platform.runLater(() -> progress.close());
                e.printStackTrace();
            }
        }).start();
    }

    @FXML
    private void handleTestEmail() {
        TextInputDialog dialog = new TextInputDialog(currentUser.getEmail());
        dialog.setTitle("Test Email");
        dialog.setHeaderText("Tester la configuration email");
        dialog.setContentText("Envoyer un email de test à :");

        dialog.showAndWait().ifPresent(email -> {
            EmailNotificationService emailService = new EmailNotificationService();
            if (emailService.sendTestEmail(email, currentUser.getUsername())) {
                showAlert(Alert.AlertType.INFORMATION, "Succès",
                        "Email de test envoyé à " + email + " !\nVérifiez votre boîte de réception.");
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur",
                        "Impossible d'envoyer l'email.\nVérifiez la configuration dans email-config.properties");
            }
        });
    }
    // ✅ AJOUTEZ pour déclencher les vérifications manuellement

    @FXML
    private void handleCheckAlerts() {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Vérification des alertes");
        confirm.setHeaderText("Envoyer les notifications par email ?");
        confirm.setContentText("Les employés et vous-même recevrez des emails pour :\n" +
                "- Tâches en retard\n- Tâches à échéance demain");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                // Afficher un indicateur de chargement
                Alert progress = new Alert(Alert.AlertType.INFORMATION);
                progress.setTitle("Envoi en cours...");
                progress.setHeaderText("Vérification des tâches et envoi des emails");
                progress.setContentText("Veuillez patienter...");
                progress.show();

                // Exécuter dans un thread séparé pour ne pas bloquer l'UI
                new Thread(() -> {
                    TaskAlertScheduler scheduler = new TaskAlertScheduler();
                    TaskAlertScheduler.AlertReport report = scheduler.checkAndNotifyForRH(rhId);

                    // Fermer le progress et afficher le résultat
                    javafx.application.Platform.runLater(() -> {
                        progress.close();

                        Alert result = new Alert(Alert.AlertType.INFORMATION);
                        result.setTitle("Alertes envoyées");
                        result.setHeaderText("Vérification terminée");
                        result.setContentText(
                                "📧 Emails envoyés: " + report.emailsSent + "\n" +
                                        "🔴 Tâches en retard: " + report.overdueTasks + "\n" +
                                        "⏰ Échéance demain: " + report.dueSoonTasks
                        );
                        result.showAndWait();

                        // Log dans la console
                        System.out.println("✅ Alertes envoyées avec succès!");
                    });
                }).start();
            }
        });
    }

    // Dans RHProjectController.java - AJOUTEZ APRÈS handleViewDetails()

    @FXML
    private void handleManageTeam() {
        Project selected = tableProjects.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un projet.");
            return;
        }

        showTeamManagementDialog(selected);
    }

    private void showTeamManagementDialog(Project project) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("Gestion de l'équipe");
        dialog.setHeaderText("Projet: " + project.getName());

        ButtonType closeButtonType = new ButtonType("Fermer", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().add(closeButtonType);

        VBox content = new VBox(15);
        content.setPadding(new Insets(20));

        // Liste actuelle de l'équipe
        Label lblTeam = new Label("Équipe actuelle (" + project.getCollaboratorCount() + ")");
        lblTeam.setStyle("-fx-font-weight: bold; -fx-font-size: 14px;");

        TableView<ProjectCollaborator> tableTeam = new TableView<>();
        TableColumn<ProjectCollaborator, String> colName = new TableColumn<>("Nom");
        colName.setCellValueFactory(new PropertyValueFactory<>("employeeName"));
        TableColumn<ProjectCollaborator, String> colRole = new TableColumn<>("Rôle");
        colRole.setCellValueFactory(new PropertyValueFactory<>("role"));
        TableColumn<ProjectCollaborator, String> colHours = new TableColumn<>("Heures");
        colHours.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(cellData.getValue().getHoursProgress()));
        tableTeam.getColumns().addAll(colName, colRole, colHours);
        tableTeam.setPrefHeight(200);

        ObservableList<ProjectCollaborator> teamList = FXCollections.observableArrayList(
                collaboratorService.getByProjectId(project.getId())
        );
        tableTeam.setItems(teamList);

        // Boutons d'action
        HBox actions = new HBox(10);
        Button btnAdd = new Button("➕ Ajouter un membre");
        btnAdd.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white;");
        btnAdd.setOnAction(e -> {
            showAddCollaboratorDialog(project);
            // Rafraîchir la liste
            teamList.setAll(collaboratorService.getByProjectId(project.getId()));
            loadProjects(); // Mettre à jour le count
        });

        Button btnRemove = new Button("🗑️ Retirer");
        btnRemove.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white;");
        btnRemove.setDisable(true);
        tableTeam.getSelectionModel().selectedItemProperty().addListener(
                (obs, old, newVal) -> btnRemove.setDisable(newVal == null));
        btnRemove.setOnAction(e -> {
            ProjectCollaborator selected = tableTeam.getSelectionModel().getSelectedItem();
            if (selected != null) {
                Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
                confirm.setContentText("Retirer " + selected.getEmployeeName() + " du projet ?");
                confirm.showAndWait().ifPresent(response -> {
                    if (response == ButtonType.OK) {
                        collaboratorService.removeFromProject(selected.getId());
                        teamList.setAll(collaboratorService.getByProjectId(project.getId()));
                        loadProjects();
                    }
                });
            }
        });

        actions.getChildren().addAll(btnAdd, btnRemove);

        content.getChildren().addAll(lblTeam, tableTeam, actions);
        dialog.getDialogPane().setContent(content);
        dialog.getDialogPane().setPrefWidth(600);
        dialog.showAndWait();
    }

    private void showAddCollaboratorDialog(Project project) {
        Dialog<ProjectCollaborator> dialog = new Dialog<>();
        dialog.setTitle("Ajouter un collaborateur");
        dialog.setHeaderText("Projet: " + project.getName());

        ButtonType addButtonType = new ButtonType("Ajouter", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(addButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        // Liste des employés disponibles
        ComboBox<String> employeeCombo = new ComboBox<>();
        List<Integer> availableIds = collaboratorService.getAvailableEmployees(project.getId(), rhId);

        // Charger les noms depuis la base
        List<String> employeeItems = new ArrayList<>();
        String sql = "SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM employees WHERE id IN (" +
                availableIds.stream().map(String::valueOf).collect(java.util.stream.Collectors.joining(",")) + ")";
        try (java.sql.Statement st = utils.Mydb.getInstance().getConnection().createStatement();
             java.sql.ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                employeeItems.add(rs.getInt("id") + ":" + rs.getString("name"));
            }
        } catch (java.sql.SQLException e) {
            e.printStackTrace();
        }

        employeeCombo.setItems(FXCollections.observableArrayList(employeeItems));

        TextField roleField = new TextField();
        roleField.setPromptText("Ex: Développeur, Designer, Chef de projet");

        TextField hoursField = new TextField();
        hoursField.setPromptText("Heures allouées");

        grid.add(new Label("Employé:"), 0, 0);
        grid.add(employeeCombo, 1, 0);
        grid.add(new Label("Rôle:"), 0, 1);
        grid.add(roleField, 1, 1);
        grid.add(new Label("Heures allouées:"), 0, 2);
        grid.add(hoursField, 1, 2);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == addButtonType && employeeCombo.getValue() != null) {
                try {
                    int employeeId = Integer.parseInt(employeeCombo.getValue().split(":")[0]);
                    String role = roleField.getText().trim();
                    Integer hours = hoursField.getText().trim().isEmpty()
                            ? null
                            : Integer.parseInt(hoursField.getText().trim());

                    ProjectCollaborator collab = new ProjectCollaborator(
                            project.getId(),
                            employeeId,
                            role,
                            hours,
                            java.sql.Date.valueOf(java.time.LocalDate.now())
                    );
                    return collab;
                } catch (Exception e) {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Données invalides.");
                }
            }
            return null;
        });

        dialog.showAndWait().ifPresent(collab -> {
            if (collaboratorService.add(collab)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Collaborateur ajouté au projet !");
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'ajouter le collaborateur.");
            }
        });
    }

    @FXML
    private void handleRefresh() {
        loadProjects();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Les projets ont été actualisés.");
    }

    // ═══════════════════════════════════════════════════════════════
    // DIALOGS
    // ═══════════════════════════════════════════════════════════════

    private Dialog<Project> createProjectDialog(Project existing) {
        Dialog<Project> dialog = new Dialog<>();
        ButtonType saveButtonType = new ButtonType(existing == null ? "Créer" : "Sauvegarder",
                ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        TextField nameField = new TextField(existing != null ? existing.getName() : "");
        nameField.setPromptText("Nom du projet");

        TextArea descArea = new TextArea(existing != null ? existing.getDescription() : "");
        descArea.setPromptText("Description");
        descArea.setPrefRowCount(3);

        ComboBox<String> statusCombo = new ComboBox<>(FXCollections.observableArrayList(
                "planning", "in_progress", "on_hold", "completed", "cancelled"));
        statusCombo.setValue(existing != null ? existing.getStatus().name() : "planning");

        ComboBox<String> priorityCombo = new ComboBox<>(FXCollections.observableArrayList(
                "low", "medium", "high", "critical"));
        priorityCombo.setValue(existing != null ? existing.getPriority().name() : "medium");

        DatePicker startDatePicker = new DatePicker(existing != null && existing.getStartDate() != null
                ? existing.getStartDate().toLocalDate() : LocalDate.now());

        DatePicker endDatePicker = new DatePicker(existing != null && existing.getEndDate() != null
                ? existing.getEndDate().toLocalDate() : LocalDate.now().plusMonths(3));

        TextField estimatedHoursField = new TextField(
                existing != null && existing.getEstimatedHours() != null
                        ? String.valueOf(existing.getEstimatedHours()) : "");
        estimatedHoursField.setPromptText("Heures estimées");

        TextField budgetField = new TextField(
                existing != null && existing.getBudget() != null
                        ? existing.getBudget().toString() : "");
        budgetField.setPromptText("Budget (€)");

        grid.add(new Label("Nom:"), 0, 0);
        grid.add(nameField, 1, 0);
        grid.add(new Label("Description:"), 0, 1);
        grid.add(descArea, 1, 1);
        grid.add(new Label("Statut:"), 0, 2);
        grid.add(statusCombo, 1, 2);
        grid.add(new Label("Priorité:"), 0, 3);
        grid.add(priorityCombo, 1, 3);
        grid.add(new Label("Date de début:"), 0, 4);
        grid.add(startDatePicker, 1, 4);
        grid.add(new Label("Date de fin:"), 0, 5);
        grid.add(endDatePicker, 1, 5);
        grid.add(new Label("Heures estimées:"), 0, 6);
        grid.add(estimatedHoursField, 1, 6);
        grid.add(new Label("Budget (€):"), 0, 7);
        grid.add(budgetField, 1, 7);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == saveButtonType) {
                try {
                    Project project = existing != null ? existing : new Project();
                    project.setName(nameField.getText().trim());
                    project.setDescription(descArea.getText().trim());
                    project.setStatus(Project.Status.valueOf(statusCombo.getValue()));
                    project.setPriority(Project.Priority.valueOf(priorityCombo.getValue()));
                    project.setStartDate(Date.valueOf(startDatePicker.getValue()));
                    project.setEndDate(Date.valueOf(endDatePicker.getValue()));

                    if (!estimatedHoursField.getText().trim().isEmpty()) {
                        project.setEstimatedHours(Integer.parseInt(estimatedHoursField.getText().trim()));
                    }

                    if (!budgetField.getText().trim().isEmpty()) {
                        project.setBudget(new BigDecimal(budgetField.getText().trim()));
                    }

                    return project;
                } catch (Exception e) {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Données invalides : " + e.getMessage());
                }
            }
            return null;
        });

        return dialog;
    }

    private void showProjectDetailsDialog(Project project) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails du Projet");
        alert.setHeaderText(project.getStatusEmoji() + " " + project.getName());

        StringBuilder details = new StringBuilder();
        details.append("ID: ").append(project.getId()).append("\n");
        details.append("Statut: ").append(project.getStatus().name()).append("\n");
        details.append("Priorité: ").append(project.getPriority().name()).append("\n");
        details.append("Avancement: ").append(project.getCompletionRate()).append("%\n\n");

        details.append("Dates:\n");
        details.append("  Début: ").append(project.getStartDate()).append("\n");
        details.append("  Fin: ").append(project.getEndDate()).append("\n");
        if (project.isDelayed()) {
            details.append("  ⚠️ PROJET EN RETARD\n");
        }
        details.append("\n");

        details.append("Budget:\n");
        if (project.getBudget() != null) {
            details.append("  Budget: ").append(project.getBudget()).append("€\n");
        }
        details.append("\n");

        details.append("Heures:\n");
        if (project.getEstimatedHours() != null) {
            details.append("  Estimées: ").append(project.getEstimatedHours()).append("h\n");
        }
        details.append("  Réelles: ").append(project.getActualHours()).append("h\n");
        details.append("\n");

        details.append("Équipe:\n");
        details.append("  Collaborateurs: ").append(project.getCollaboratorCount()).append("\n");
        details.append("  Tâches: ").append(project.getCompletedTaskCount())
                .append("/").append(project.getTaskCount()).append(" terminées\n\n");

        if (project.getDescription() != null && !project.getDescription().isEmpty()) {
            details.append("Description:\n").append(project.getDescription());
        }

        alert.setContentText(details.toString());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(600);
        alert.getDialogPane().setPrefHeight(500);
        alert.showAndWait();
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