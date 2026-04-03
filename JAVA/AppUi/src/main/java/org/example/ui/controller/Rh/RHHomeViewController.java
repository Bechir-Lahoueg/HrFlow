package org.example.ui.controller.Rh;

import javafx.animation.Animation;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.util.Duration;
import org.example.controller.EmployeeController;
import org.example.model.Employee;
import org.example.model.User;

import java.time.LocalDate;
import java.time.LocalTime;
import java.time.format.DateTimeFormatter;
import java.util.List;

/**
 * Contrôleur pour la vue d'accueil RH
 */
public class RHHomeViewController {

    @FXML
    private Label welcomeLabel;

    @FXML
    private Label totalEmployeesLabel;

    @FXML
    private Label pendingLeavesLabel;

    @FXML
    private Label formationsCountLabel;

    @FXML
    private Label recrutementCountLabel;

    @FXML private Label timeLabel;
    @FXML private Label dateLabel;

    private final EmployeeController employeeController = new EmployeeController();
    private final ObservableList<Employee> employeeList = FXCollections.observableArrayList();
    private User currentUser;
    private RHDashboardController dashboardController;
    private Timeline clock;

    public void setDashboardController(RHDashboardController dashboardController) {
        this.dashboardController = dashboardController;
    }

    @FXML
    private void handleNavigateToEmployees() {
        if (dashboardController != null) dashboardController.showEmployees();
    }

    @FXML
    private void handleNavigateToLeave() {
        if (dashboardController != null) dashboardController.showLeave();
    }

    @FXML
    private void handleNavigateToRecruitment() {
        if (dashboardController != null) dashboardController.showRecruitment();
    }

    @FXML
    private void initialize() {
        startClock();
        loadDashboardData();
    }

    private void startClock() {
        DateTimeFormatter timeFmt = DateTimeFormatter.ofPattern("HH:mm");
        DateTimeFormatter dateFmt = DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy", java.util.Locale.FRENCH);
        updateClockLabels(timeFmt, dateFmt);
        clock = new Timeline(new KeyFrame(Duration.seconds(1), e -> updateClockLabels(timeFmt, dateFmt)));
        clock.setCycleCount(Animation.INDEFINITE);
        clock.play();
    }

    private void updateClockLabels(DateTimeFormatter timeFmt, DateTimeFormatter dateFmt) {
        if (timeLabel != null) timeLabel.setText(LocalTime.now().format(timeFmt));
        if (dateLabel != null) {
            String raw = LocalDate.now().format(dateFmt);
            dateLabel.setText(raw.substring(0, 1).toUpperCase() + raw.substring(1));
        }
    }

    /**
     * Définit l'utilisateur courant
     */
    public void setCurrentUser(User user) {
        this.currentUser = user;
        if (welcomeLabel != null && user != null) {
            welcomeLabel.setText(user.getUsername());
        }
        loadDashboardData();
    }

    /**
     * Charge les données du dashboard
     */
    private void loadDashboardData() {
        if (currentUser != null) {
            loadEmployeeList();
            loadStats();
        }
    }

    /**
     * Charge la liste des employés sous responsabilité
     */
    private void loadEmployeeList() {
        List<Employee> employees = employeeController.handleListMyEmployees(currentUser);
        employeeList.clear();
        if (employees != null) {
            employeeList.addAll(employees);
        }
    }

    /**
     * Affiche les statistiques dans les cartes KPI
     */
    private void loadStats() {
        if (totalEmployeesLabel != null) {
            totalEmployeesLabel.setText(String.valueOf(employeeList.size()));
        }
        // Valeurs non connectées - à implémenter
        if (pendingLeavesLabel != null) {
            pendingLeavesLabel.setText("—");
        }
        if (formationsCountLabel != null) {
            formationsCountLabel.setText("—");
        }
        if (recrutementCountLabel != null) {
            recrutementCountLabel.setText("—");
        }
    }
}