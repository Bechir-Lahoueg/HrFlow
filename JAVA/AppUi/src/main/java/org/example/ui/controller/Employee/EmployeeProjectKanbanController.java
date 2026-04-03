package org.example.ui.controller.Employee;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.input.*;
import javafx.scene.layout.*;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.util.Duration;
import models.*;
import org.example.model.Employee;
import org.example.ui.MainApp;
import service.*;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;

/**
 * Controller Employé amélioré avec Kanban et actions sur les tâches
 */
public class EmployeeProjectKanbanController {

    // ═══════════════════════════════════════════════════════════════
    // FXML - VUE PROJET
    // ═══════════════════════════════════════════════════════════════

    @FXML private Label lblProjectName;
    @FXML private Label lblMyRole;
    @FXML private Label lblProgress;
    @FXML private ProgressBar progressBar;
    @FXML private Label lblMyHours;
    @FXML private Button btnBack;
    @FXML private Label lblTimer;
    @FXML private Button btnStartTimer;
    @FXML private Button btnStopTimer;

    // ═══════════════════════════════════════════════════════════════
    // FXML - KANBAN
    // ═══════════════════════════════════════════════════════════════

    @FXML private VBox kanbanTodo;
    @FXML private VBox kanbanInProgress;
    @FXML private VBox kanbanReview;
    @FXML private VBox kanbanDone;

    @FXML private Label lblTaskStats;

    // ═══════════════════════════════════════════════════════════════
    // FXML - ACTIVITÉS
    // ═══════════════════════════════════════════════════════════════

    @FXML private ListView<String> listActivities;
    @FXML private TextArea txtComment;
    @FXML private Button btnAddComment;

    // ═══════════════════════════════════════════════════════════════
    // SERVICES & DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private final ProjectService projectService = new ProjectService();
    private final ProjectTaskService taskService = new ProjectTaskService();
    private final ProjectCollaboratorService collaboratorService = new ProjectCollaboratorService();
    private final ProjectUpdateService updateService = new ProjectUpdateService();

    private List<ProjectTask> myTasks = new ArrayList<>();
    private Project currentProject;
    private Employee currentEmployee;
    private int employeeId;
    private ProjectCollaborator myCollaboration;
    private Timeline timeline;
    private int secondsPassed = 0;
    private final ClockifyService clockifyService = new ClockifyService();

    // ═══════════════════════════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    public void initialize() {
        setupKanbanColumns();
    }

    public void initData(Project project, Employee employee) {
        this.currentProject = project;
        this.currentEmployee = employee;
        this.employeeId = employee.getId();

        loadProjectInfo();
        loadMyTasks();
        loadActivities();
    }

    // ═══════════════════════════════════════════════════════════════
    // CHARGEMENT DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private void loadProjectInfo() {
        currentProject = projectService.getById(currentProject.getId());

        lblProjectName.setText(currentProject.getStatusEmoji() + " " + currentProject.getName());
        lblProgress.setText("Avancement: " + currentProject.getCompletionRate() + "%");
        progressBar.setProgress(currentProject.getCompletionRate() / 100.0);

        // Récupérer mon rôle et mes heures
        List<ProjectCollaborator> collabs = collaboratorService.getByProjectId(currentProject.getId());
        myCollaboration = collabs.stream()
                .filter(c -> c.getEmployeeId() == employeeId)
                .findFirst()
                .orElse(null);

        if (myCollaboration != null) {
            lblMyRole.setText("Mon rôle: " + myCollaboration.getRole());
            lblMyHours.setText("Mes heures: " + myCollaboration.getHoursProgress());
        }
    }

    private void loadMyTasks() {
        // On récupère TOUTES les tâches de l'employé pour ce projet
        List<ProjectTask> allMyTasks = taskService.getByEmployeeId(employeeId);

        // On filtre pour ne garder que celles du projet actuel
        myTasks = allMyTasks.stream()
                .filter(t -> t.getProjectId() == currentProject.getId())
                .collect(Collectors.toList());

        updateKanbanBoard();

        // Calcul des stats sur la liste filtrée
        int total = myTasks.size();
        long doneCount = myTasks.stream()
                .filter(t -> t.getStatus() == ProjectTask.Status.done)
                .count();

        lblTaskStats.setText(String.format("Mes tâches: %d/%d terminées", doneCount, total));
    }

    private void loadActivities() {
        List<ProjectUpdate> updates = updateService.getByProjectId(currentProject.getId(), 15);
        ObservableList<String> items = FXCollections.observableArrayList();
        for (ProjectUpdate u : updates) {
            items.add(u.getSummary());
        }
        listActivities.setItems(items);
    }

    // ═══════════════════════════════════════════════════════════════
    // SETUP KANBAN
    // ═══════════════════════════════════════════════════════════════

    private void setupKanbanColumns() {
        setupDropTarget(kanbanTodo, ProjectTask.Status.todo);
        setupDropTarget(kanbanInProgress, ProjectTask.Status.in_progress);
        setupDropTarget(kanbanReview, ProjectTask.Status.review);
        setupDropTarget(kanbanDone, ProjectTask.Status.done);
    }

    private void setupDropTarget(VBox column, ProjectTask.Status targetStatus) {
        column.setOnDragOver(event -> {
            if (event.getGestureSource() != column && event.getDragboard().hasString()) {
                event.acceptTransferModes(TransferMode.MOVE);
            }
            event.consume();
        });

        column.setOnDragDropped(event -> {
            Dragboard db = event.getDragboard();
            if (db.hasString()) {
                try {
                    int taskId = Integer.parseInt(db.getString());

                    // 1. On cherche d'abord la tâche dans la liste ACTUELLE AVANT le reload
                    ProjectTask taskToMove = myTasks.stream()
                            .filter(t -> t.getId() == taskId)
                            .findFirst()
                            .orElse(null);

                    // 2. On exécute le déplacement en Base de Données
                    if (taskService.moveTask(taskId, targetStatus)) {

                        // 3. ON RECHARGE TOUT TOUT DE SUITE
                        // Cela va appeler getByEmployeeId (sans le filtre 'done' que vous avez enlevé)
                        loadMyTasks();
                        loadProjectInfo();
                        loadActivities();

                        // 4. On ajoute l'activité seulement si on a trouvé la tâche
                        if (taskToMove != null) {
                            ProjectUpdate update = new ProjectUpdate(
                                    currentProject.getId(),
                                    employeeId,
                                    ProjectUpdate.UpdateType.task,
                                    "Tâche déplacée",
                                    taskToMove.getTitle() + " → " + targetStatus.name()
                            );
                            updateService.add(update);
                        }
                    }
                    event.setDropCompleted(true);
                } catch (Exception e) {
                    System.err.println("❌ Erreur lors du drop : " + e.getMessage());
                    event.setDropCompleted(false);
                }
            }
            event.consume();
        });
    }

    private void updateKanbanBoard() {
        kanbanTodo.getChildren().clear();
        kanbanInProgress.getChildren().clear();
        kanbanReview.getChildren().clear();
        kanbanDone.getChildren().clear();
        System.out.println("DEBUG: Nombre de tâches à afficher : " + myTasks.size());

        for (ProjectTask task : myTasks) {
            System.out.println("DEBUG: Tâche " + task.getTitle() + " Statut: [" + task.getStatus() + "]");
            VBox taskCard = createTaskCard(task);
            if (task.getStatus() == null) {
                System.err.println("ERREUR: Le statut de la tâche " + task.getId() + " est NULL");
                continue;
            }
            switch (task.getStatus()) {
                case todo -> kanbanTodo.getChildren().add(taskCard);
                case in_progress -> kanbanInProgress.getChildren().add(taskCard);
                case review -> kanbanReview.getChildren().add(taskCard);
                case done -> {
                    System.out.println("DEBUG: Ajout de la carte dans kanbanDone");
                    kanbanDone.getChildren().add(taskCard);
                }
            }
        }
    }

    private VBox createTaskCard(ProjectTask task) {
        VBox card = new VBox(8);
        card.setPadding(new Insets(12));
        card.setStyle("-fx-background-color: white; -fx-border-color: #ddd; -fx-border-radius: 8; " +
                "-fx-background-radius: 8; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.15), 5, 0, 0, 2);");
        card.setMaxWidth(220);
        card.setCursor(javafx.scene.Cursor.HAND);

        // Titre avec priorité
        Label title = new Label(task.getPriorityEmoji() + " " + task.getTitle());
        title.setWrapText(true);
        title.setStyle("-fx-font-weight: bold; -fx-font-size: 13px;");

        // Date limite
        Label due = new Label("📅 " + task.getDaysUntilDue());
        due.setStyle("-fx-font-size: 11px; -fx-text-fill: " +
                (task.isOverdue() ? "#e74c3c" : "#7f8c8d") + ";");

        // Heures
        String hoursText = task.getActualHours() + "h";
        if (task.getEstimatedHours() != null) {
            hoursText += " / " + task.getEstimatedHours() + "h";
        }
        Label hours = new Label("⏱️ " + hoursText);
        hours.setStyle("-fx-font-size: 11px; -fx-text-fill: #7f8c8d;");

        // Boutons d'action
        HBox actions = new HBox(5);
        Button btnDetails = new Button("👁️");
        btnDetails.setStyle("-fx-background-color: #3498db; -fx-text-fill: white; -fx-padding: 3 8; -fx-font-size: 10px;");
        btnDetails.setOnAction(e -> showTaskDetails(task));

        Button btnLog = new Button("⏱️");
        btnLog.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-padding: 3 8; -fx-font-size: 10px;");
        btnLog.setOnAction(e -> logHours(task));

        if (task.getStatus() == ProjectTask.Status.todo) {
            Button btnStart = new Button("▶️ Démarrer");
            btnStart.setStyle("-fx-background-color: #f39c12; -fx-text-fill: white; -fx-padding: 3 8; -fx-font-size: 10px;");
            btnStart.setOnAction(e -> changeTaskStatus(task, ProjectTask.Status.in_progress));
            actions.getChildren().add(btnStart);
        } else if (task.getStatus() == ProjectTask.Status.in_progress) {
            Button btnReview = new Button("👁️ Review");
            btnReview.setStyle("-fx-background-color: #9b59b6; -fx-text-fill: white; -fx-padding: 3 8; -fx-font-size: 10px;");
            btnReview.setOnAction(e -> changeTaskStatus(task, ProjectTask.Status.review));
            actions.getChildren().add(btnReview);
        } else if (task.getStatus() == ProjectTask.Status.review) {
            Button btnDone = new Button("✅ Terminer");
            btnDone.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-padding: 3 8; -fx-font-size: 10px;");
            btnDone.setOnAction(e -> changeTaskStatus(task, ProjectTask.Status.done));
            actions.getChildren().add(btnDone);
        }

        actions.getChildren().addAll(btnDetails, btnLog);

        card.getChildren().addAll(title, due, hours, new Separator(), actions);

        // Drag & drop
        card.setOnDragDetected(event -> {
            Dragboard db = card.startDragAndDrop(TransferMode.MOVE);
            ClipboardContent content = new ClipboardContent();
            content.putString(String.valueOf(task.getId()));
            db.setContent(content);
            event.consume();
        });

        return card;
    }

    // ═══════════════════════════════════════════════════════════════
    // ACTIONS SUR LES TÂCHES
    // ═══════════════════════════════════════════════════════════════

    private void changeTaskStatus(ProjectTask task, ProjectTask.Status newStatus) {
        if (taskService.moveTask(task.getId(), newStatus)) {
            // Ajouter une activité
            ProjectUpdate update = new ProjectUpdate(
                    currentProject.getId(),
                    employeeId,
                    ProjectUpdate.UpdateType.task,
                    "Changement de statut",
                    task.getTitle() + " → " + newStatus.name()
            );
            updateService.add(update);

            loadMyTasks();
            loadProjectInfo();
            loadActivities();
            showAlert(Alert.AlertType.INFORMATION, "Succès", "Statut mis à jour !");
        }
    }

    private void showTaskDetails(ProjectTask task) {
        StringBuilder details = new StringBuilder();
        details.append("═══ TÂCHE #").append(task.getId()).append(" ═══\n\n");
        details.append("Titre: ").append(task.getTitle()).append("\n");
        details.append("Statut: ").append(task.getStatusLabel()).append("\n");
        details.append("Priorité: ").append(task.getPriority().name()).append("\n\n");

        if (task.getDescription() != null && !task.getDescription().isEmpty()) {
            details.append("Description:\n").append(task.getDescription()).append("\n\n");
        }

        if (task.getEstimatedHours() != null) {
            details.append("Heures estimées: ").append(task.getEstimatedHours()).append("h\n");
        }
        details.append("Heures travaillées: ").append(task.getActualHours()).append("h\n");

        if (task.getDueDate() != null) {
            details.append("Échéance: ").append(task.getDueDate())
                    .append(" (").append(task.getDaysUntilDue()).append(")\n");
        }

        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails de la tâche");
        alert.setHeaderText(task.getStatusEmoji() + " " + task.getTitle());
        alert.setContentText(details.toString());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(500);
        alert.showAndWait();
    }

    private void logHours(ProjectTask task) {
        TextInputDialog dialog = new TextInputDialog();
        dialog.setTitle("Logger des heures");
        dialog.setHeaderText("Tâche: " + task.getTitle());
        dialog.setContentText("Nombre d'heures:");

        dialog.showAndWait().ifPresent(hoursStr -> {
            try {
                int hours = Integer.parseInt(hoursStr.trim());
                if (hours <= 0) {
                    showAlert(Alert.AlertType.WARNING, "Attention", "Le nombre d'heures doit être positif.");
                    return;
                }

                if (taskService.logHours(task.getId(), hours)) {
                    // Mettre à jour aussi les heures du collaborateur
                    if (myCollaboration != null) {
                        collaboratorService.logWorkedHours(myCollaboration.getId(), hours);
                    }

                    // Ajouter une activité
                    ProjectUpdate update = new ProjectUpdate(
                            currentProject.getId(),
                            employeeId,
                            ProjectUpdate.UpdateType.task,
                            "Heures loggées",
                            hours + "h ajoutées sur: " + task.getTitle()
                    );
                    updateService.add(update);

                    showAlert(Alert.AlertType.INFORMATION, "Succès",
                            hours + " heure(s) ajoutée(s) !");
                    loadMyTasks();
                    loadProjectInfo();
                    loadActivities();
                }
            } catch (NumberFormatException e) {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Veuillez entrer un nombre valide.");
            }
        });
    }
    @FXML
    private void handleStartTimer() {
        // 1. Désactiver/Activer les boutons
        btnStartTimer.setDisable(true);
        btnStopTimer.setDisable(false);

        // 2. Lancer le décompte visuel
        secondsPassed = 0;
        timeline = new Timeline(new KeyFrame(Duration.seconds(1), e -> {
            secondsPassed++;
            int h = secondsPassed / 3600;
            int m = (secondsPassed % 3600) / 60;
            int s = secondsPassed % 60;
            lblTimer.setText(String.format("%02d:%02d:%02d", h, m, s));
        }));
        timeline.setCycleCount(Timeline.INDEFINITE);
        timeline.play();

        new Thread(() -> {
            try {
                clockifyService.startTimer("Travail sur le projet : " + currentProject.getName());
            } catch (Exception e) {
                // On affiche l'erreur en console si l'API ne répond pas
                System.err.println("❌ Erreur API Clockify : " + e.getMessage());
                e.printStackTrace();
            }
        }).start();
    }

    @FXML
    private void handleStopTimer() {
        // 1. Arrêter le décompte visuel
        if (timeline != null) {
            timeline.stop();
        }

        // 2. Reset interface
        btnStartTimer.setDisable(false);
        btnStopTimer.setDisable(true);

        // Appel API pour arrêter en arrière-plan
        new Thread(() -> {
            try {
                clockifyService.stopTimer();
            } catch (Exception e) {
                System.err.println("❌ Erreur lors de l'arrêt Clockify : " + e.getMessage());
            }
        }).start();

        showAlert(Alert.AlertType.INFORMATION, "Timer", "Session terminée : " + lblTimer.getText());
        lblTimer.setText("00:00:00");
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - ACTIVITÉS
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleAddComment() {
        String comment = txtComment.getText().trim();
        if (comment.isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Le commentaire ne peut pas être vide.");
            return;
        }

        ProjectUpdate update = new ProjectUpdate(
                currentProject.getId(),
                employeeId,
                ProjectUpdate.UpdateType.comment,
                "Commentaire",
                comment
        );

        if (updateService.add(update)) {
            txtComment.clear();
            loadActivities();
            showAlert(Alert.AlertType.INFORMATION, "Succès", "Commentaire publié !");
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - GÉNÉRAL
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleBack() {
        if (currentEmployee != null) {
            MainApp.showEmployeeDashboard(currentEmployee);
        }
    }

    @FXML
    private void handleRefresh() {
        loadProjectInfo();
        loadMyTasks();
        loadActivities();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Données actualisées.");
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