package org.example.ui.controller;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.chart.*;
import javafx.scene.control.Label;
import org.example.controller.EmployeeController;
import org.example.controller.UserController;
import org.example.model.Employee;
import org.example.model.User;

import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

/**
 * Contrôleur pour la vue des statistiques Admin
 */
public class AdminStatisticsViewController {

    @FXML
    private Label totalUsersLabel;

    @FXML
    private Label totalEmployeesLabel;

    @FXML
    private Label totalRHLabel;

    @FXML
    private Label totalAdminsLabel;

    @FXML
    private PieChart userDistributionChart;

    @FXML
    private BarChart<String, Number> employeesByRHChart;

    @FXML
    private BarChart<String, Number> ageDistributionChart;

    @FXML
    private PieChart jobDistributionChart;

    private final UserController userController = new UserController();
    private final EmployeeController employeeController = new EmployeeController();
    private User currentUser;

    @FXML
    private void initialize() {
        // Will load data after setCurrentUser is called
    }

    /**
     * Définit l'utilisateur courant
     */
    public void setCurrentUser(User user) {
        this.currentUser = user;
        loadAllStatistics();
    }

    /**
     * Charge toutes les statistiques
     */
    private void loadAllStatistics() {
        if (currentUser != null) {
            List<User> allUsers = userController.handleListAllUsers(currentUser);
            List<User> allRH = userController.handleListAllRH(currentUser);
            List<Employee> allEmployees = employeeController.handleListAllEmployees(currentUser);

            // Count admins
            long adminCount = allUsers.stream()
                    .filter(u -> u.getRole() == User.Role.ADMIN)
                    .count();

            // Update labels
            if (totalUsersLabel != null) {
                totalUsersLabel.setText(String.valueOf(allUsers.size()));
            }
            if (totalEmployeesLabel != null) {
                totalEmployeesLabel.setText(String.valueOf(allEmployees.size()));
            }
            if (totalRHLabel != null) {
                totalRHLabel.setText(String.valueOf(allRH.size()));
            }
            if (totalAdminsLabel != null) {
                totalAdminsLabel.setText(String.valueOf(adminCount));
            }

            // Load charts
            loadUserDistributionChart(allUsers, adminCount, allRH.size());
            loadEmployeesByRHChart(allEmployees, allRH);
            loadAgeDistributionChart(allEmployees);
            loadJobDistributionChart(allEmployees);
        }
    }

    /**
     * Charge le graphique de répartition des utilisateurs
     */
    private void loadUserDistributionChart(List<User> allUsers, long adminCount, int rhCount) {
        if (userDistributionChart != null) {
            ObservableList<PieChart.Data> pieChartData = FXCollections.observableArrayList(
                    new PieChart.Data("Administrateurs", adminCount),
                    new PieChart.Data("Responsables RH", rhCount),
                    new PieChart.Data("Autres", allUsers.size() - adminCount - rhCount)
            );
            userDistributionChart.setData(pieChartData);
        }
    }

    /**
     * Charge le graphique des employés par RH
     */
    private void loadEmployeesByRHChart(List<Employee> allEmployees, List<User> allRH) {
        if (employeesByRHChart != null) {
            // Count employees by RH
            Map<Integer, Long> employeesByRH = allEmployees.stream()
                    .collect(Collectors.groupingBy(Employee::getRhId, Collectors.counting()));

            XYChart.Series<String, Number> series = new XYChart.Series<>();
            series.setName("Employés");

            // Add data for each RH
            for (User rh : allRH) {
                long count = employeesByRH.getOrDefault(rh.getId(), 0L);
                String rhName = rh.getUsername();
                series.getData().add(new XYChart.Data<>(rhName, count));
            }

            employeesByRHChart.getData().clear();
            employeesByRHChart.getData().add(series);
        }
    }

    /**
     * Charge le graphique de répartition des âges
     */
    private void loadAgeDistributionChart(List<Employee> allEmployees) {
        if (ageDistributionChart != null) {
            // Group employees by age ranges
            Map<String, Long> ageRanges = new HashMap<>();
            ageRanges.put("18-25", allEmployees.stream().filter(e -> e.getAge() >= 18 && e.getAge() <= 25).count());
            ageRanges.put("26-35", allEmployees.stream().filter(e -> e.getAge() >= 26 && e.getAge() <= 35).count());
            ageRanges.put("36-45", allEmployees.stream().filter(e -> e.getAge() >= 36 && e.getAge() <= 45).count());
            ageRanges.put("46-55", allEmployees.stream().filter(e -> e.getAge() >= 46 && e.getAge() <= 55).count());
            ageRanges.put("56+", allEmployees.stream().filter(e -> e.getAge() >= 56).count());

            XYChart.Series<String, Number> series = new XYChart.Series<>();
            series.setName("Employés");

            for (Map.Entry<String, Long> entry : ageRanges.entrySet()) {
                series.getData().add(new XYChart.Data<>(entry.getKey(), entry.getValue()));
            }

            ageDistributionChart.getData().clear();
            ageDistributionChart.getData().add(series);
        }
    }

    /**
     * Charge le graphique de répartition par poste
     */
    private void loadJobDistributionChart(List<Employee> allEmployees) {
        if (jobDistributionChart != null) {
            // Count employees by job title
            Map<String, Long> jobCounts = allEmployees.stream()
                    .collect(Collectors.groupingBy(Employee::getJobTitle, Collectors.counting()));

            ObservableList<PieChart.Data> pieChartData = FXCollections.observableArrayList();
            
            for (Map.Entry<String, Long> entry : jobCounts.entrySet()) {
                pieChartData.add(new PieChart.Data(entry.getKey(), entry.getValue()));
            }

            jobDistributionChart.setData(pieChartData);
        }
    }
}
