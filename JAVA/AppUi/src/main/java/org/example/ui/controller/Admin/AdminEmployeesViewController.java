package org.example.ui.controller;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import org.example.controller.EmployeeController;
import org.example.model.Employee;
import org.example.model.User;

import java.util.List;

/**
 * Contrôleur pour la vue de gestion des employés Admin
 */
public class AdminEmployeesViewController {

    @FXML
    private Label totalEmployeesLabel;

    @FXML
    private Label activeEmployeesLabel;

    @FXML
    private Label departmentsLabel;

    @FXML
    private TableView<Employee> employeesTableView;

    @FXML
    private TableColumn<Employee, Integer> idColumn;

    @FXML
    private TableColumn<Employee, String> firstNameColumn;

    @FXML
    private TableColumn<Employee, String> lastNameColumn;

    @FXML
    private TableColumn<Employee, String> emailColumn;

    @FXML
    private TableColumn<Employee, String> jobTitleColumn;

    @FXML
    private TableColumn<Employee, Integer> ageColumn;

    @FXML
    private TableColumn<Employee, Integer> rhColumn;

    private final EmployeeController employeeController = new EmployeeController();
    private ObservableList<Employee> employeesList = FXCollections.observableArrayList();
    private User currentUser;

    @FXML
    private void initialize() {
        // Configuration des colonnes du tableau
        if (idColumn != null) {
            idColumn.setCellValueFactory(new PropertyValueFactory<>("id"));
        }
        if (firstNameColumn != null) {
            firstNameColumn.setCellValueFactory(new PropertyValueFactory<>("firstName"));
        }
        if (lastNameColumn != null) {
            lastNameColumn.setCellValueFactory(new PropertyValueFactory<>("lastName"));
        }
        if (emailColumn != null) {
            emailColumn.setCellValueFactory(new PropertyValueFactory<>("email"));
        }
        if (jobTitleColumn != null) {
            jobTitleColumn.setCellValueFactory(new PropertyValueFactory<>("jobTitle"));
        }
        if (ageColumn != null) {
            ageColumn.setCellValueFactory(new PropertyValueFactory<>("age"));
        }
        if (rhColumn != null) {
            rhColumn.setCellValueFactory(new PropertyValueFactory<>("rhId"));
        }

        if (employeesTableView != null) {
            employeesTableView.setItems(employeesList);
        }
    }

    /**
     * Définit l'utilisateur courant
     */
    public void setCurrentUser(User user) {
        this.currentUser = user;
        loadData();
    }

    /**
     * Charge les données
     */
    private void loadData() {
        if (currentUser != null) {
            loadEmployeesList();
            loadStats();
        }
    }

    /**
     * Charge la liste des employés
     */
    private void loadEmployeesList() {
        employeesList.clear();
        if (currentUser != null) {
            List<Employee> employees = employeeController.handleListAllEmployees(currentUser);
            employeesList.addAll(employees);
        }
    }

    /**
     * Charge les statistiques
     */
    private void loadStats() {
        int total = employeesList.size();
        
        if (totalEmployeesLabel != null) {
            totalEmployeesLabel.setText(String.valueOf(total));
        }
        if (activeEmployeesLabel != null) {
            activeEmployeesLabel.setText(String.valueOf(total)); // Tous actifs pour le moment
        }
        if (departmentsLabel != null) {
            // Compter les postes uniques comme départements
            long uniqueDepartments = employeesList.stream()
                .map(Employee::getJobTitle)
                .distinct()
                .count();
            departmentsLabel.setText(String.valueOf(uniqueDepartments));
        }
    }

    /**
     * Rafraîchit les données
     */
    @FXML
    private void handleRefresh() {
        loadData();
        showAlert("Succès", "Données actualisées", Alert.AlertType.INFORMATION);
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
