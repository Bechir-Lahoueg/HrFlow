package org.example.ui.controller.Employee;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import models.*;
import org.example.model.Employee;
import org.example.ui.MainApp;
import service.*;

import java.util.List;

/**
 * Contrôleur Employé pour voir ses projets et tâches
 */
public class EmployeeProjectController {

    // ═══════════════════════════════════════════════════════════════
    // FXML - MES PROJETS
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Project> tableMyProjects;
    @FXML private TableColumn<Project, String> colProjectName;
    @FXML private TableColumn<Project, String> colProjectStatus;
    @FXML private TableColumn<Project, String> colMyRole;
    @FXML private TableColumn<Project, String> colProgress;
    @FXML private TableColumn<Project, String> colMyHours;

    @FXML private Label lblMyProjects;
    @FXML private Label lblMyTasks;
    @FXML private Label lblMyHours;

    // ═══════════════════════════════════════════════════════════════
    // FXML - MES TÂCHES
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<ProjectTask> tableMyTasks;
    @FXML private TableColumn<ProjectTask, String> colTaskProject;
    @FXML private TableColumn<ProjectTask, String> colTaskTitle;
    @FXML private TableColumn<ProjectTask, String> colTaskStatus;
    @FXML private TableColumn<ProjectTask, String> colTaskPriority;
    @FXML private TableColumn<ProjectTask, String> colTaskDue;

    @FXML private ComboBox<String> filterTaskStatus;
    @FXML private Button btnViewTaskDetails;
    @FXML private Button btnLogHours;
    @FXML private Button backButton;

    // ═══════════════════════════════════════════════════════════════
    // SERVICES & DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private final ProjectService projectService = new ProjectService();
    private final ProjectTaskService taskService = new ProjectTaskService();
    private final ProjectCollaboratorService collaboratorService = new ProjectCollaboratorService();

    private ObservableList<Project> myProjectsList = FXCollections.observableArrayList();
    private ObservableList<ProjectTask> myTasksList = FXCollections.observableArrayList();

    private Employee currentEmployee;
    private int currentEmployeeId;

    // ═══════════════════════════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    public void initialize() {
        setupProjectsTable();
        setupTasksTable();
        setupFilters();
        disableActionButtons();
    }

    public void initData(int employeeId, String employeeName, Employee employee) {
        this.currentEmployeeId = employeeId;
        this.currentEmployee = employee;
        loadMyProjects();
        loadMyTasks();
        updateStatistics();
    }

    @FXML
    private void handleBackToDashboard() {
        if (currentEmployee != null) {
            MainApp.showEmployeeDashboard(currentEmployee);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SETUP
    // ═══════════════════════════════════════════════════════════════

    private void setupProjectsTable() {
        colProjectName.setCellValueFactory(new PropertyValueFactory<>("name"));
        colProjectStatus.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(
                        cellData.getValue().getStatusEmoji() + " " + cellData.getValue().getStatus().name()));
        colProgress.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(cellData.getValue().getProgressBar()));

        // Rôle et heures
        colMyRole.setCellValueFactory(cellData -> {
            List<ProjectCollaborator> collabs = collaboratorService.getByProjectId(cellData.getValue().getId());
            String role = collabs.stream()
                    .filter(c -> c.getEmployeeId() == currentEmployeeId)
                    .map(ProjectCollaborator::getRole)
                    .findFirst()
                    .orElse("N/A");
            return new javafx.beans.property.SimpleStringProperty(role);
        });

        colMyHours.setCellValueFactory(cellData -> {
            List<ProjectCollaborator> collabs = collaboratorService.getByProjectId(cellData.getValue().getId());
            String hours = collabs.stream()
                    .filter(c -> c.getEmployeeId() == currentEmployeeId)
                    .map(ProjectCollaborator::getHoursProgress)
                    .findFirst()
                    .orElse("0h");
            return new javafx.beans.property.SimpleStringProperty(hours);
        });

        tableMyProjects.setItems(myProjectsList);

        // ✅ AJOUTEZ CETTE PARTIE : Double-click pour ouvrir le Kanban
        tableMyProjects.setOnMouseClicked(event -> {
            if (event.getClickCount() == 2 && tableMyProjects.getSelectionModel().getSelectedItem() != null) {
                openProjectKanban(tableMyProjects.getSelectionModel().getSelectedItem());
            }
        });
    }

    // ✅ AJOUTEZ CETTE MÉTHODE
    private void openProjectKanban(Project project) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(
                    "/fxml/views/Employee-dashboard/EmployeeProjectKanbanView.fxml"));
            Parent root = loader.load();

            EmployeeProjectKanbanController controller = loader.getController();
            controller.initData(project, currentEmployee);

            // Remplacer la vue actuelle
            javafx.scene.Scene scene = backButton.getScene();
            scene.setRoot(root);

        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger le projet.");
        }
    }

    private void setupTasksTable() {
        colTaskProject.setCellValueFactory(new PropertyValueFactory<>("projectName"));
        colTaskTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colTaskStatus.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(
                        cellData.getValue().getStatusEmoji() + " " + cellData.getValue().getStatusLabel()));
        colTaskPriority.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(
                        cellData.getValue().getPriorityEmoji() + " " + cellData.getValue().getPriority().name()));
        colTaskDue.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(cellData.getValue().getDaysUntilDue()));

        // Style pour les tâches en retard
        colTaskDue.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String val, boolean empty) {
                super.updateItem(val, empty);
                if (empty || val == null) {
                    setText(null);
                    setStyle("");
                } else {
                    setText(val);
                    if (val.contains("retard")) {
                        setStyle("-fx-text-fill: #e74c3c; -fx-font-weight: bold;");
                    } else if (val.contains("Aujourd'hui") || val.contains("Demain")) {
                        setStyle("-fx-text-fill: #f39c12; -fx-font-weight: bold;");
                    } else {
                        setStyle("");
                    }
                }
            }
        });

        tableMyTasks.setItems(myTasksList);
        tableMyTasks.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> updateTaskButtonStates(newVal));
    }

    private void setupFilters() {
        filterTaskStatus.setItems(FXCollections.observableArrayList(
                "Toutes", "todo", "in_progress", "review", "done"));
        filterTaskStatus.setValue("Toutes");
        filterTaskStatus.setOnAction(e -> applyTaskFilters());
    }

    private void disableActionButtons() {
        btnViewTaskDetails.setDisable(true);
        btnLogHours.setDisable(true);
    }

    private void updateTaskButtonStates(ProjectTask task) {
        boolean hasSelection = task != null;
        btnViewTaskDetails.setDisable(!hasSelection);
        btnLogHours.setDisable(!hasSelection || task.getStatus() == ProjectTask.Status.done);
    }

    // ═══════════════════════════════════════════════════════════════
    // CHARGEMENT & FILTRES
    // ═══════════════════════════════════════════════════════════════

    private void loadMyProjects() {
        myProjectsList.clear();
        myProjectsList.addAll(projectService.getByEmployeeId(currentEmployeeId));
    }

    private void loadMyTasks() {
        myTasksList.clear();
        myTasksList.addAll(taskService.getByEmployeeId(currentEmployeeId));
        applyTaskFilters();
    }

    private void applyTaskFilters() {
        String statusFilter = filterTaskStatus.getValue();
        List<ProjectTask> all = taskService.getByEmployeeId(currentEmployeeId);

        if (!statusFilter.equals("Toutes")) {
            all = all.stream()
                    .filter(t -> t.getStatus().name().equals(statusFilter))
                    .toList();
        }

        myTasksList.setAll(all);
    }

    private void updateStatistics() {
        lblMyProjects.setText("Mes projets : " + myProjectsList.size());
        lblMyTasks.setText("Mes tâches : " + myTasksList.size());

        // Calculer total heures
        int totalHours = 0;
        for (Project p : myProjectsList) {
            List<ProjectCollaborator> collabs = collaboratorService.getByProjectId(p.getId());
            totalHours += collabs.stream()
                    .filter(c -> c.getEmployeeId() == currentEmployeeId)
                    .mapToInt(ProjectCollaborator::getWorkedHours)
                    .sum();
        }
        lblMyHours.setText("Total heures : " + totalHours + "h");
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleRefresh() {
        loadMyProjects();
        loadMyTasks();
        updateStatistics();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Données actualisées.");
    }

    @FXML
    private void handleViewTaskDetails() {
        ProjectTask selected = tableMyTasks.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        StringBuilder details = new StringBuilder();
        details.append("═══ TÂCHE #").append(selected.getId()).append(" ═══\n\n");
        details.append("Projet: ").append(selected.getProjectName()).append("\n");
        details.append("Titre: ").append(selected.getTitle()).append("\n");
        details.append("Statut: ").append(selected.getStatusLabel()).append("\n");
        details.append("Priorité: ").append(selected.getPriority().name()).append("\n\n");

        if (selected.getDescription() != null && !selected.getDescription().isEmpty()) {
            details.append("Description:\n").append(selected.getDescription()).append("\n\n");
        }

        if (selected.getEstimatedHours() != null) {
            details.append("Heures estimées: ").append(selected.getEstimatedHours()).append("h\n");
        }
        details.append("Heures travaillées: ").append(selected.getActualHours()).append("h\n");

        if (selected.getDueDate() != null) {
            details.append("Échéance: ").append(selected.getDueDate())
                    .append(" (").append(selected.getDaysUntilDue()).append(")\n");
        }

        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails de la tâche");
        alert.setHeaderText(selected.getStatusEmoji() + " " + selected.getTitle());
        alert.setContentText(details.toString());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(500);
        alert.showAndWait();
    }

    @FXML
    private void handleLogHours() {
        ProjectTask selected = tableMyTasks.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        TextInputDialog dialog = new TextInputDialog();
        dialog.setTitle("Logger des heures");
        dialog.setHeaderText("Tâche: " + selected.getTitle());
        dialog.setContentText("Nombre d'heures travaillées:");

        dialog.showAndWait().ifPresent(hoursStr -> {
            try {
                int hours = Integer.parseInt(hoursStr.trim());
                if (hours <= 0) {
                    showAlert(Alert.AlertType.WARNING, "Attention", "Le nombre d'heures doit être positif.");
                    return;
                }

                if (taskService.logHours(selected.getId(), hours)) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès",
                            hours + " heure(s) ajoutée(s) à la tâche.");
                    loadMyTasks();
                    updateStatistics();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de logger les heures.");
                }
            } catch (NumberFormatException e) {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Veuillez entrer un nombre valide.");
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