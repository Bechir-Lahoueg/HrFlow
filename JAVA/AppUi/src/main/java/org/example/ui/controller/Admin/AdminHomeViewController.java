package org.example.ui.controller;

import javafx.animation.Animation;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.util.Duration;
import org.example.controller.EmployeeController;
import org.example.controller.UserController;
import org.example.model.User;

import java.time.LocalDate;
import java.time.LocalTime;
import java.time.format.DateTimeFormatter;
import java.util.List;

/**
 * Contrôleur pour la vue d'accueil Admin
 */
public class AdminHomeViewController {

    @FXML
    private Label totalUsersLabel;

    @FXML
    private Label totalEmployeesLabel;

    @FXML
    private Label adminCountLabel;

    @FXML
    private Label rhCountLabel;

    @FXML private Label timeLabel;
    @FXML private Label dateLabel;

    private final UserController userController = new UserController();
    private final EmployeeController employeeController = new EmployeeController();
    private User currentUser;
    private AdminDashboardController dashboardController;
    private Timeline clock;

    public void setDashboardController(AdminDashboardController dashboardController) {
        this.dashboardController = dashboardController;
    }

    @FXML
    private void handleNavigateToUsers() {
        if (dashboardController != null) dashboardController.showUsers();
    }

    @FXML
    private void handleNavigateToEmployees() {
        if (dashboardController != null) dashboardController.showEmployees();
    }

    @FXML
    private void handleNavigateToStatistics() {
        if (dashboardController != null) dashboardController.showStatistics();
    }

    @FXML
    private void initialize() {
        startClock();
        loadStats();
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
        loadStats();
    }

    /**
     * Charge les statistiques
     */
    private void loadStats() {
        if (currentUser != null) {
            List<User> allUsers = userController.handleListAllUsers(currentUser);
            List<User> allRH = userController.handleListAllRH(currentUser);
            
            // Count admins
            long adminCount = allUsers.stream()
                .filter(u -> u.getRole() == User.Role.ADMIN)
                .count();
            
            // Count employees
            int employeeCount = employeeController.handleListAllEmployees(currentUser).size();
            
            if (totalUsersLabel != null) {
                totalUsersLabel.setText(String.valueOf(allUsers.size()));
            }
            if (totalEmployeesLabel != null) {
                totalEmployeesLabel.setText(String.valueOf(employeeCount));
            }
            if (adminCountLabel != null) {
                adminCountLabel.setText(String.valueOf(adminCount));
            }
            if (rhCountLabel != null) {
                rhCountLabel.setText(String.valueOf(allRH.size()));
            }
        }
    }
}
