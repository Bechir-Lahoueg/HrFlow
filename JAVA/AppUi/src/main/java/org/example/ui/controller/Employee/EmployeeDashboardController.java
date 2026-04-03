package org.example.ui.controller.Employee;

import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import org.example.model.Employee;
import org.example.ui.controller.Employee.Congé.EmployeeLeaveController;
import org.example.ui.controller.Rh.Congé.notification.AppNotification;
import org.example.ui.controller.Rh.Congé.notification.InAppNotificationService;

import java.io.IOException;
import java.util.function.Consumer;

/**
 * Contrôleur pour le dashboard Employé avec navigation dynamique
 */
public class EmployeeDashboardController {

    @FXML
    private Label sidebarUserLabel;

    @FXML
    private StackPane contentArea;

    @FXML
    private Button homeBtn;

    @FXML
    private Button leaveBtn;

    @FXML
    private Button requestsBtn;

    @FXML
    private Button feedbacksBtn;

    @FXML
    private Button projectsBtn;

    @FXML
    private Button formationBtn;

    @FXML
    private Button remunerationBtn;

    @FXML
    private Button settingsBtn;

    @FXML
    private Button recrutementBtn;
    @FXML
    private Button browseJobsBtn;

    @FXML private Button notifBellButton;
    @FXML private Label  notifBadgeLabel;

    private final InAppNotificationService notifService = InAppNotificationService.getInstance();
    private Consumer<Void> notifListener;

    private Employee currentEmployee;

    /**
     * Définit l'employé courant et charge la vue par défaut
     */
    public void setCurrentEmployee(Employee employee) {
        this.currentEmployee = employee;
        displayEmployeeInfo();
        // S'abonner aux notifications de cet employé
        notifListener = v -> updateNotifBadge();
        notifService.addGlobalListener(notifListener);
        // Charger les notifications persistées depuis la base de données
        notifService.loadEmployeeNotificationsFromDB(employee.getId());
        updateNotifBadge();
        showHome();
    }

    /** Rafraîchit le badge de la cloche Employé. */
    private void updateNotifBadge() {
        if (notifBadgeLabel == null || currentEmployee == null) return;
        long count = notifService.unreadCountEmployee(currentEmployee.getId());
        javafx.application.Platform.runLater(() -> {
            if (count > 0) {
                notifBadgeLabel.setText(count > 99 ? "99+" : String.valueOf(count));
                notifBadgeLabel.setVisible(true);
                notifBadgeLabel.setManaged(true);
                notifBellButton.setStyle(
                    "-fx-background-color: #312e26; -fx-text-fill: #fbbf24; "
                    + "-fx-background-radius: 8; -fx-padding: 10 16; "
                    + "-fx-font-size: 13px; -fx-cursor: hand; -fx-alignment: CENTER_LEFT;");
            } else {
                notifBadgeLabel.setVisible(false);
                notifBadgeLabel.setManaged(false);
                notifBellButton.setStyle(
                    "-fx-background-color: transparent; -fx-text-fill: #bdc3c7; "
                    + "-fx-background-radius: 8; -fx-padding: 10 16; "
                    + "-fx-font-size: 13px; -fx-cursor: hand; -fx-alignment: CENTER_LEFT;");
            }
        });
    }

    /** Popup liste des notifications employé. */
    @FXML
    private void showNotificationPopup() {
        if (currentEmployee == null) return;
        ObservableList<AppNotification> notifications =
                notifService.getEmployeeNotifications(currentEmployee.getId());
        notifService.markAllReadEmployee(currentEmployee.getId());
        updateNotifBadge();

        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("🔔  Mes Notifications");
        dialog.setHeaderText("Statut de vos demandes de congés");
        dialog.getDialogPane().getButtonTypes().add(ButtonType.CLOSE);

        if (notifications.isEmpty()) {
            dialog.getDialogPane().setContent(
                    new Label("✉️  Aucune notification pour le moment."));
        } else {
            ListView<String> listView = new ListView<>();
            listView.setPrefWidth(480);
            listView.setPrefHeight(Math.min(notifications.size() * 60.0 + 20, 360));
            notifications.forEach(n -> listView.getItems()
                    .add(n.getType().icon + "  " + n.getMessage()
                    + "\n       "
                    + n.getTimestamp().format(
                            java.time.format.DateTimeFormatter.ofPattern("dd/MM HH:mm"))));
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
     * Affiche les informations de l'employé dans la sidebar
     */
    private void displayEmployeeInfo() {
        if (currentEmployee != null) {
            sidebarUserLabel.setText("👤 " + currentEmployee.getFullName());
        }
    }

    /**
     * Affiche la vue Home
     */
    @FXML
    private void showHome() {
        loadView("/fxml/views/Employee-dashboard/EmployeeHomeView.fxml");
        setActiveButton(homeBtn);
    }

    @FXML
    private void showRecruitment() {
        loadView("/fxml/views/Employee-dashboard/Recrutement/EmployeeMainView.fxml");
        setActiveButton(recrutementBtn);
    }
    
    @FXML
    private void handleBrowseJobs() {
        loadView("/fxml/views/Employee-dashboard/Recrutement/EmployeeBrowseJobsView.fxml");
        setActiveButton(browseJobsBtn);
    }

    /**
     * Affiche la vue Congés
     */
    @FXML
    public void showLeave() {
        loadView("/fxml/views/Employee-dashboard/Congé/EmployeeLeaveView.fxml");
        setActiveButton(leaveBtn);
    }

    /**
     * Affiche la vue Demandes
     */
    @FXML
    public void showRequests() {
        loadView("/fxml/views/Employee-dashboard/EmployeeRequestView.fxml");
        setActiveButton(requestsBtn);
    }

    /// ////
    @FXML
    private void showFeedbacks() {
        loadView("/fxml/views/Employee-dashboard/EmployeeFeedback.fxml");
        setActiveButton(feedbacksBtn);
    }

    /**
     * Affiche la vue Projets
     */
    @FXML
    private void showProjects() {
        loadView("/fxml/views/Employee-dashboard/EmployeeProjectView.fxml");
        setActiveButton(projectsBtn);
    }

    /**
     * Affiche la vue Formations
     */
    @FXML
    public void showFormations() {
        loadView("/fxml/views/Employee-dashboard/EmployeeFormationView.fxml");
        setActiveButton(formationBtn);
    }

    /**
     * Affiche la vue Rémunération
     */
    @FXML
    private void showRemuneration() {
        loadView("/fxml/views/Employee-dashboard/EmployeeRemunerationView.fxml");
        setActiveButton(remunerationBtn);
    }

    /**
     * Affiche la vue Paramètres
     */
    @FXML
    private void showSettings() {
        loadView("/fxml/views/Employee-dashboard/EmployeeSettingsView.fxml");
        setActiveButton(settingsBtn);
    }

    /**
     * Charge une vue FXML dynamiquement dans le contentArea
     */
    private void loadView(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            contentArea.getChildren().clear();
            contentArea.getChildren().add(loader.load());

            // Pass current employee to the loaded view controller
            Object controller = loader.getController();
            if (controller instanceof EmployeeHomeViewController) {
                ((EmployeeHomeViewController) controller).setCurrentEmployee(currentEmployee);
                ((EmployeeHomeViewController) controller).setDashboardController(this);
            } else if (controller instanceof EmployeeLeaveController) {
                ((EmployeeLeaveController) controller).initData(
                        currentEmployee.getId(),
                        currentEmployee.getFullName(),
                        currentEmployee);
            } else if (controller instanceof EmployeeRequestController) {
                ((EmployeeRequestController) controller).initData(
                        currentEmployee.getId(),
                        currentEmployee.getFullName(),
                        currentEmployee);
            } else if (controller instanceof EmployeeFormationController) {
                ((EmployeeFormationController) controller).setCurrentEmployee(currentEmployee);
            } else if (controller instanceof EmployeeRemunerationController) {
                ((EmployeeRemunerationController) controller).setCurrentEmployee(currentEmployee);
            } else if (controller instanceof EmployeeSettingsViewController) {
                ((EmployeeSettingsViewController) controller).setCurrentEmployee(currentEmployee);
            } else if (controller instanceof EmployeeFeedbackController) {
                ((EmployeeFeedbackController) controller).initData(
                        currentEmployee.getId(),
                        currentEmployee.getFullName(),
                        currentEmployee);
            } else if (controller instanceof org.example.ui.controller.Employee.Recrutement.EmployeeMainController) {
                ((org.example.ui.controller.Employee.Recrutement.EmployeeMainController) controller)
                        .setCurrentEmployee(currentEmployee);
            }
            else if (controller instanceof EmployeeProjectController) {
                ((EmployeeProjectController) controller).initData(
                        currentEmployee.getId(),
                        currentEmployee.getFullName(),
                        currentEmployee
                );
            }

        } catch (IOException e) {
            e.printStackTrace();
            showAlert("Erreur", "Impossible de charger la vue: " + fxmlPath, Alert.AlertType.ERROR);
        }
    }

    /**
     * Met en surbrillance le bouton actif dans la sidebar (style dark sidebar moderne)
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
        leaveBtn.setStyle(inactiveStyle);
        requestsBtn.setStyle(inactiveStyle);
        feedbacksBtn.setStyle(inactiveStyle);
        projectsBtn.setStyle(inactiveStyle);
        formationBtn.setStyle(inactiveStyle);
        remunerationBtn.setStyle(inactiveStyle);
        recrutementBtn.setStyle(inactiveStyle);
        settingsBtn.setStyle(
                "-fx-background-color: rgba(107,114,128,0.08); " +
                "-fx-text-fill: #9ca3af; " +
                "-fx-background-radius: 10; " +
                "-fx-padding: 11 17; " +
                "-fx-font-size: 13px; " +
                "-fx-font-weight: normal; " +
                "-fx-cursor: hand; " +
                "-fx-alignment: CENTER_LEFT; " +
                "-fx-border-color: rgba(107,114,128,0.15); " +
                "-fx-border-radius: 10; " +
                "-fx-border-width: 1;");

        // Appliquer le style actif
        if (activeButton == homeBtn) {
            activeButton.setStyle(homeBtnActive);
        } else if (activeButton == settingsBtn) {
            activeButton.setStyle(
                    "-fx-background-color: rgba(59,130,246,0.15); " +
                    "-fx-text-fill: #60a5fa; " +
                    "-fx-background-radius: 10; " +
                    "-fx-padding: 11 17; " +
                    "-fx-font-size: 13px; " +
                    "-fx-font-weight: bold; " +
                    "-fx-cursor: hand; " +
                    "-fx-alignment: CENTER_LEFT; " +
                    "-fx-border-color: rgba(59,130,246,0.30); " +
                    "-fx-border-radius: 10; " +
                    "-fx-border-width: 1;");
        } else {
            activeButton.setStyle(activeStyle);
        }
    }

    /**     * Déconnecte l'utilisateur
     */
    @FXML
    private void handleLogout() {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Déconnexion");
        alert.setHeaderText("Confirmer la déconnexion");
        alert.setContentText("Êtes-vous sûr de vouloir vous déconnecter ?");

        alert.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                currentEmployee = null;
                org.example.ui.MainApp.showLoginScreen();
            }
        });
    }

    /**     * Affiche une alerte
     */
    private void showAlert(String title, String content, Alert.AlertType type) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
