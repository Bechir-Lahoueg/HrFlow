package org.example.ui.controller.Rh;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Insets;
import javafx.scene.Node;
import javafx.scene.control.*;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.animation.Timeline;
import javafx.animation.KeyFrame;
import javafx.util.Duration;
import javafx.application.Platform;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import javafx.stage.Popup;
import javafx.stage.Stage;
import org.example.controller.EmployeeController;
import org.example.model.Employee;
import org.example.model.User;
import org.example.ui.MainApp;
import org.example.ui.controller.Rh.Congé.RHLeaveController;
import org.example.ui.controller.Rh.RHFormationController;
import org.example.ui.controller.Rh.Recrutement.JobOffersController;
import org.example.ui.controller.Rh.Congé.notification.AppNotification;
import org.example.ui.controller.Rh.Congé.notification.InAppNotificationService;

import java.io.IOException;
import java.util.List;
import java.util.Optional;
import java.util.function.Consumer;

/**
 * Contrôleur pour le dashboard RH
 */
public class RHDashboardController {

    @FXML
    private Label welcomeLabel;

    @FXML
    private Label sidebarUserLabel;

    @FXML
    private StackPane contentArea;
    
    @FXML
    private Button homeBtn;
    
    @FXML
    private Button employeesBtn;
    
    @FXML
    private Button recruitmentBtn;
    
    @FXML
    private Button relationsBtn;

    @FXML
    private Button projectsBtn;
    
    @FXML
    private Button remunerationBtn;
    
    @FXML
    private Button formationBtn;
    
    @FXML
    private Button leaveBtn;

    @FXML private Button notifBellButton;
    @FXML private Label  notifBadgeLabel;

    private final InAppNotificationService notifService = InAppNotificationService.getInstance();
    private Consumer<Void> notifListener;

    @FXML Button dataAnalysisBtn;
    
    @FXML
    private Button csvImportExportBtn;
    
    private User currentUser;
    private final EmployeeController employeeController = new EmployeeController();

    /**
     * Définit l'utilisateur courant
     */
    public void setCurrentUser(User user) {
        this.currentUser = user;
        welcomeLabel.setText("Bienvenue, " + user.getUsername());
        sidebarUserLabel.setText("👤 " + user.getUsername());
        showHome(); // Charger la vue d'accueil par défaut
    }

    @FXML
    private void initialize() {
        // S'abonner aux notifications RH en temps réel
        notifListener = v -> updateNotifBadge();
        notifService.addGlobalListener(notifListener);
    }

    /** Rafraîchit le badge de la cloche RH. */
    private void updateNotifBadge() {
        if (notifBadgeLabel == null) return;
        long count = notifService.unreadCountRH();
        javafx.application.Platform.runLater(() -> {
            if (count > 0) {
                notifBadgeLabel.setText(count > 99 ? "99+" : String.valueOf(count));
                notifBadgeLabel.setVisible(true);
                notifBadgeLabel.setManaged(true);
            } else {
                notifBadgeLabel.setVisible(false);
                notifBadgeLabel.setManaged(false);
            }
        });
    }

    /** Popup liste des notifications RH. */
    @FXML
    private void showNotificationPopup() {
        ObservableList<AppNotification> notifications = notifService.getRHNotifications();
        notifService.markAllReadRH();
        updateNotifBadge();

        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("🔔  Notifications RH");
        dialog.setHeaderText("Demandes de congés reçues");
        dialog.getDialogPane().getButtonTypes().add(ButtonType.CLOSE);

        if (notifications.isEmpty()) {
            dialog.getDialogPane().setContent(new Label("✉️  Aucune notification pour le moment."));
        } else {
            ListView<String> listView = new ListView<>();
            listView.setPrefWidth(480);
            listView.setPrefHeight(Math.min(notifications.size() * 60.0 + 20, 360));
            notifications.forEach(n -> listView.getItems()
                    .add(n.getType().icon + "  " + n.getMessage()
                    + "\n       "
                    + n.getTimestamp().format(java.time.format.DateTimeFormatter.ofPattern("dd/MM HH:mm"))));
            listView.setStyle("-fx-font-size: 12px;");
            dialog.getDialogPane().setContent(listView);
        }
        try {
            String css = getClass().getResource("/css/style.css").toExternalForm();
            dialog.getDialogPane().getStylesheets().add(css);
        } catch (Exception ignored) {}
        dialog.showAndWait();
    }

    /**
     * Affiche la vue d'accueil
     */
    @FXML
    private void showHome() {
        loadView("/fxml/views/Rh-dashboard/RHHomeView.fxml");
        setActiveButton(homeBtn);
    }

    /**
     * Affiche la vue de gestion des employés
     */
    @FXML
    public void showEmployees() {
        loadView("/fxml/views/Rh-dashboard/RHEmployeeManagementView.fxml");
        setActiveButton(employeesBtn);
    }

    /**
     * Affiche la vue de gestion des recrutements
     */
    @FXML
    public void showRecruitment() {
        loadView("/fxml/views/Rh-dashboard/Recrutement/MainView.fxml");
        setActiveButton(recruitmentBtn);
    }

    /**
     * Affiche la vue de gestion des relations employés
     */
    @FXML
    private void showRelations() {
        loadView("/fxml/views/Rh-dashboard/RHRelationEmployeesView.fxml");
        setActiveButton(relationsBtn);
    }

    @FXML
    private void showProjects() {
        loadView("/fxml/views/Rh-dashboard/RHProjectView.fxml");
        setActiveButton(projectsBtn);
    }

    /**
     * Affiche la vue de gestion de la rémunération
     */
    @FXML
    private void showRemuneration() {
        loadView("/fxml/views/Rh-dashboard/RHRemunerationView.fxml");
        setActiveButton(remunerationBtn);
    }

    /**
     * Affiche la vue de gestion de la formation
     */
    @FXML
    private void showFormation() {
        loadView("/fxml/views/Rh-dashboard/RHFormationView.fxml");
        setActiveButton(formationBtn);
    }

    /**
     * Affiche la vue de gestion des congés
     */
    @FXML
    public void showLeave() {
        loadView("/fxml/views/Rh-dashboard/Congé/RHLeaveContentView.fxml");
        setActiveButton(leaveBtn);
    }

    @FXML
    private void showDataAnalysis() {
        loadView("/fxml/views/Rh-dashboard/Ai/DataAnalysis.fxml");
        setActiveButton(dataAnalysisBtn);
    }
    
    @FXML
    private void showCsvImportExport() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/views/Rh-dashboard/RHCsvImportExportView.fxml"));
            contentArea.getChildren().clear();
            contentArea.getChildren().add(loader.load());
            
            // Get the controller and set the current stage
            RHCsvImportExportController controller = loader.getController();
            if (getCurrentStage() != null) {
                controller.setCurrentStage(getCurrentStage());
            }
            
            // Set callback to refresh job offers table after import
            controller.setOnJobOffersImportCallback(() -> {
                // Refresh the job offers view if it's currently active
                refreshCurrentJobOffersView();
            });
            
            setActiveButton(csvImportExportBtn);
        } catch (Exception e) {
            System.err.println("Error loading CSV Import/Export view: " + e.getMessage());
            showErrorAlert("Error loading CSV Import/Export view: " + e.getMessage());
        }
    }


    /**
     * Charge une vue FXML dans le contentArea
     */
    private void loadView(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            Node view = loader.load();

            // Passer le currentUser au contrôleur de la vue si possible
            Object controller = loader.getController();
            if (controller instanceof RHHomeViewController) {
                ((RHHomeViewController) controller).setCurrentUser(currentUser);
                ((RHHomeViewController) controller).setDashboardController(this);
                ((RHHomeViewController) controller).setDashboardController(this);
            } else if (controller instanceof RHEmployeeManagementViewController) {
                ((RHEmployeeManagementViewController) controller).setCurrentUser(currentUser);
            } else if (controller instanceof RHRelationEmployeesController) {
                ((RHRelationEmployeesController) controller).setCurrentUser(currentUser);
            } else if (controller instanceof RHProjectController) {
                ((RHProjectController) controller).setCurrentUser(currentUser);
            } else if (controller instanceof RHLeaveController) {
                ((RHLeaveController) controller).initData(currentUser.getId(), currentUser);
            } else if (controller instanceof RHFormationController) {
                ((RHFormationController) controller).setCurrentUser(currentUser);
            } else if (controller instanceof RHRemunerationController) {
                ((RHRemunerationController) controller).setCurrentUser(currentUser);
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(view);
        } catch (IOException e) {
            e.printStackTrace();
            showAlert("Erreur", "Impossible de charger la vue: " + fxmlPath, Alert.AlertType.ERROR);
        }
    }

    /**
     * Charge une vue placeholder pour les modules non encore implémentés
     */
    private void loadPlaceholderView(String title, String message) {
        javafx.scene.layout.VBox placeholder = new javafx.scene.layout.VBox(20);
        placeholder.setAlignment(javafx.geometry.Pos.CENTER);
        placeholder.setStyle("-fx-padding: 50;");

        Label titleLabel = new Label(title);
        titleLabel.setStyle("-fx-font-size: 26px; -fx-font-weight: bold;");
        titleLabel.getStyleClass().add("page-title");

        Label messageLabel = new Label(message);
        messageLabel.setStyle("-fx-font-size: 16px; -fx-text-fill: #666;");

        placeholder.getChildren().addAll(titleLabel, messageLabel);

        contentArea.getChildren().clear();
        contentArea.getChildren().add(placeholder);
    }

    /**
     * Définit le bouton actif dans la navigation (style dark sidebar moderne)
     */
    private void setActiveButton(Button activeButton) {
        String inactiveStyle =
                "-fx-background-color: transparent; " +
                "-fx-text-fill: #6b7280; " +
                "-fx-background-radius: 10; " +
                "-fx-padding: 11 17; " +
                "-fx-font-size: 13px; " +
                "-fx-font-weight: normal; " +
                "-fx-cursor: hand; " +
                "-fx-alignment: CENTER_LEFT;";

        String activeStyle =
                "-fx-background-color: rgba(59,130,246,0.15); " +
                "-fx-text-fill: #60a5fa; " +
                "-fx-background-radius: 10; " +
                "-fx-padding: 11 17; " +
                "-fx-font-size: 13px; " +
                "-fx-font-weight: bold; " +
                "-fx-cursor: hand; " +
                "-fx-alignment: CENTER_LEFT;";

        // homeBtn est dans un HBox avec un Region de 3px → padding ajusté
        String homeBtnInactive =
                "-fx-background-color: transparent; " +
                "-fx-text-fill: #6b7280; " +
                "-fx-background-radius: 0 10 10 0; " +
                "-fx-padding: 11 14; " +
                "-fx-font-size: 13px; " +
                "-fx-font-weight: normal; " +
                "-fx-cursor: hand; " +
                "-fx-alignment: CENTER_LEFT;";

        String homeBtnActive =
                "-fx-background-color: rgba(59,130,246,0.12); " +
                "-fx-text-fill: #60a5fa; " +
                "-fx-background-radius: 0 10 10 0; " +
                "-fx-padding: 11 14; " +
                "-fx-font-size: 13px; " +
                "-fx-font-weight: bold; " +
                "-fx-cursor: hand; " +
                "-fx-alignment: CENTER_LEFT;";

        // Réinitialiser tous les boutons
        homeBtn.setStyle(homeBtnInactive);
        employeesBtn.setStyle(inactiveStyle);
        recruitmentBtn.setStyle(inactiveStyle);
        relationsBtn.setStyle(inactiveStyle);
        projectsBtn.setStyle(inactiveStyle);
        remunerationBtn.setStyle(inactiveStyle);
        formationBtn.setStyle(inactiveStyle);
        leaveBtn.setStyle(inactiveStyle);
        csvImportExportBtn.setStyle(inactiveStyle);
        dataAnalysisBtn.setStyle(
                "-fx-background-color: rgba(99,102,241,0.10); " +
                "-fx-text-fill: #818cf8; " +
                "-fx-background-radius: 10; " +
                "-fx-padding: 11 17; " +
                "-fx-font-size: 13px; " +
                "-fx-font-weight: normal; " +
                "-fx-cursor: hand; " +
                "-fx-alignment: CENTER_LEFT; " +
                "-fx-border-color: rgba(99,102,241,0.18); " +
                "-fx-border-radius: 10; " +
                "-fx-border-width: 1;");

        // Appliquer le style actif
        if (activeButton == homeBtn) {
            activeButton.setStyle(homeBtnActive);
        } else if (activeButton == dataAnalysisBtn) {
            activeButton.setStyle(
                    "-fx-background-color: rgba(99,102,241,0.25); " +
                    "-fx-text-fill: #a5b4fc; " +
                    "-fx-background-radius: 10; " +
                    "-fx-padding: 11 17; " +
                    "-fx-font-size: 13px; " +
                    "-fx-font-weight: bold; " +
                    "-fx-cursor: hand; " +
                    "-fx-alignment: CENTER_LEFT; " +
                    "-fx-border-color: rgba(99,102,241,0.40); " +
                    "-fx-border-radius: 10; " +
                    "-fx-border-width: 1;");
        } else {
            activeButton.setStyle(activeStyle);
        }
    }
    
    /**
     * Get the current stage from MainApp
     */
    private Stage getCurrentStage() {
        return MainApp.getPrimaryStage();
    }
    
    /**
     * Refresh the current job offers view if it's active
     */
    private void refreshCurrentJobOffersView() {
        // For now, just show a success message
        // The user can manually refresh by navigating to Job Offers section
        Platform.runLater(() -> {
            Alert alert = new Alert(Alert.AlertType.INFORMATION);
            alert.setTitle("Import Successful");
            alert.setHeaderText(null);
            alert.setContentText("Job offers imported successfully! Please navigate to the Job Offers section to see the updated data.");
            alert.showAndWait();
        });
    }
    
    /**
     * Show error alert dialog
     */
    private void showErrorAlert(String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle("Error");
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    /**
     * Déconnecte l'utilisateur
     */
    @FXML
    private void handleLogout() {
        currentUser = null;
        MainApp.showLoginScreen();
    }

    /**
     * Affiche une alerte
     */
    private void showAlert(String title, String content, Alert.AlertType type) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
