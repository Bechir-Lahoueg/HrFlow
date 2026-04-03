package org.example.ui.controller.Employee;

import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.util.Duration;
import org.example.model.Employee;

import java.time.LocalDate;
import java.time.LocalTime;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

/**
 * Contrôleur pour la vue d'accueil Employé
 */
public class EmployeeHomeViewController {

    @FXML private Label welcomeLabel;
    @FXML private Label totalLeaveLabel;
    @FXML private Label totalRequestsLabel;
    @FXML private Label totalFormationsLabel;
    @FXML private Label totalNotificationsLabel;
    @FXML private Label timeLabel;
    @FXML private Label dateLabel;

    private Employee currentEmployee;
    private EmployeeDashboardController dashboardController;
    private Timeline clock;

    public void setDashboardController(EmployeeDashboardController controller) {
        this.dashboardController = controller;
    }

    @FXML
    private void initialize() {
        setDefaults();
        startClock();
    }

    private void startClock() {
        updateClockLabels();
        clock = new Timeline(new KeyFrame(Duration.seconds(1), e -> updateClockLabels()));
        clock.setCycleCount(Timeline.INDEFINITE);
        clock.play();
    }

    private void updateClockLabels() {
        if (timeLabel != null) {
            timeLabel.setText(LocalTime.now().format(DateTimeFormatter.ofPattern("HH:mm")));
        }
        if (dateLabel != null) {
            String raw = LocalDate.now().format(DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy", Locale.FRENCH));
            dateLabel.setText(raw.substring(0, 1).toUpperCase() + raw.substring(1));
        }
    }

    @FXML
    private void handleNavigateToLeave() {
        if (dashboardController != null) dashboardController.showLeave();
    }

    @FXML
    private void handleNavigateToFormations() {
        if (dashboardController != null) dashboardController.showFormations();
    }

    @FXML
    private void handleNavigateToRequests() {
        if (dashboardController != null) dashboardController.showRequests();
    }

    /**
     * Définit l'employé courant et rafraîchit l'affichage
     */
    public void setCurrentEmployee(Employee employee) {
        this.currentEmployee = employee;
        if (welcomeLabel != null && employee != null) {
            String name = (employee.getFirstName() != null ? employee.getFirstName() : "")
                        + " " + (employee.getLastName() != null ? employee.getLastName() : "");
            welcomeLabel.setText(name.trim().isEmpty() ? "Employé" : name.trim());
        }
        loadStats();
    }

    /**
     * Valeurs par défaut avant chargement
     */
    private void setDefaults() {
        if (totalLeaveLabel != null) totalLeaveLabel.setText("—");
        if (totalRequestsLabel != null) totalRequestsLabel.setText("—");
        if (totalFormationsLabel != null) totalFormationsLabel.setText("—");
        if (totalNotificationsLabel != null) totalNotificationsLabel.setText("—");
    }

    /**
     * Charge les statistiques de l'employé
     * À connecter aux services métier selon les besoins
     */
    private void loadStats() {
        // Placeholder - à implémenter avec les services
        setDefaults();
    }
}