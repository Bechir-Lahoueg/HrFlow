package org.example.ui.controller.Rh;

import javafx.beans.property.SimpleIntegerProperty;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.GridPane;
import org.example.controller.EmployeeController;
import org.example.model.Employee;
import org.example.model.User;

import java.util.List;
import java.util.Optional;

/**
 * Contrôleur pour la gestion des employés par le RH
 */
public class RHEmployeeManagementViewController {

    @FXML
    private Label totalEmployeesLabel;

    @FXML
    private Label avgAgeLabel;

    @FXML
    private Label jobTypesLabel;

    @FXML
    private TextField searchField;

    @FXML
    private TableView<Employee> employeesTable;

    @FXML
    private TableColumn<Employee, Integer> idColumn;

    @FXML
    private TableColumn<Employee, String> firstNameColumn;

    @FXML
    private TableColumn<Employee, String> lastNameColumn;

    @FXML
    private TableColumn<Employee, Integer> ageColumn;

    @FXML
    private TableColumn<Employee, String> jobTitleColumn;

    @FXML
    private TableColumn<Employee, String> emailColumn;

    @FXML
    private TableColumn<Employee, Void> actionsColumn;

    private User currentUser;
    private final EmployeeController employeeController = new EmployeeController();
    private ObservableList<Employee> employeesList = FXCollections.observableArrayList();

    /**
     * Définit l'utilisateur courant
     */
    public void setCurrentUser(User user) {
        this.currentUser = user;
        loadEmployeesList();
    }

    @FXML
    private void initialize() {
        // Configuration des colonnes
        idColumn.setCellValueFactory(new PropertyValueFactory<>("id"));
        firstNameColumn.setCellValueFactory(new PropertyValueFactory<>("firstName"));
        lastNameColumn.setCellValueFactory(new PropertyValueFactory<>("lastName"));
        ageColumn.setCellValueFactory(new PropertyValueFactory<>("age"));
        jobTitleColumn.setCellValueFactory(new PropertyValueFactory<>("jobTitle"));
        emailColumn.setCellValueFactory(new PropertyValueFactory<>("email"));

        // Recherche en temps réel
        searchField.textProperty().addListener((observable, oldValue, newValue) -> {
            filterEmployees(newValue);
        });
    }

    /**
     * Charge la liste des employés
     */
    private void loadEmployeesList() {
        if (currentUser == null) return;

        List<Employee> employees = employeeController.handleListMyEmployees(currentUser);
        if (employees != null) {
            employeesList.clear();
            employeesList.addAll(employees);
            employeesTable.setItems(employeesList);
            loadStats();
        }
    }

    /**
     * Charge les statistiques
     */
    private void loadStats() {
        int total = employeesList.size();
        totalEmployeesLabel.setText(String.valueOf(total));

        if (total > 0) {
            // Âge moyen
            double avgAge = employeesList.stream()
                    .mapToInt(Employee::getAge)
                    .average()
                    .orElse(0);
            avgAgeLabel.setText(String.format("%.1f", avgAge));

            // Types de postes uniques
            long uniqueJobs = employeesList.stream()
                    .map(Employee::getJobTitle)
                    .distinct()
                    .count();
            jobTypesLabel.setText(String.valueOf(uniqueJobs));
        } else {
            avgAgeLabel.setText("0");
            jobTypesLabel.setText("0");
        }
    }

    /**
     * Filtre les employés
     */
    private void filterEmployees(String searchText) {
        if (searchText == null || searchText.trim().isEmpty()) {
            employeesTable.setItems(employeesList);
            return;
        }

        String search = searchText.toLowerCase();
        ObservableList<Employee> filtered = employeesList.filtered(emp ->
                emp.getFirstName().toLowerCase().contains(search) ||
                emp.getLastName().toLowerCase().contains(search) ||
                emp.getEmail().toLowerCase().contains(search) ||
                emp.getJobTitle().toLowerCase().contains(search)
        );
        employeesTable.setItems(filtered);
    }

    /**
     * Ajoute un nouvel employé
     */
    @FXML
    private void handleAddEmployee() {
        Dialog<Employee> dialog = new Dialog<>();
        dialog.setTitle("Ajouter un employé");
        dialog.setHeaderText("Créer un nouveau compte employé");

        ButtonType addButtonType = new ButtonType("Ajouter", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(addButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        TextField firstNameField = new TextField();
        firstNameField.setPromptText("Prénom");
        TextField lastNameField = new TextField();
        lastNameField.setPromptText("Nom");
        TextField ageField = new TextField();
        ageField.setPromptText("Âge");
        TextField jobTitleField = new TextField();
        jobTitleField.setPromptText("Poste");
        TextField emailField = new TextField();
        emailField.setPromptText("Email");
        PasswordField passwordField = new PasswordField();
        passwordField.setPromptText("Mot de passe");

        grid.add(new Label("Prénom:"), 0, 0);
        grid.add(firstNameField, 1, 0);
        grid.add(new Label("Nom:"), 0, 1);
        grid.add(lastNameField, 1, 1);
        grid.add(new Label("Âge:"), 0, 2);
        grid.add(ageField, 1, 2);
        grid.add(new Label("Poste:"), 0, 3);
        grid.add(jobTitleField, 1, 3);
        grid.add(new Label("Email:"), 0, 4);
        grid.add(emailField, 1, 4);
        grid.add(new Label("Mot de passe:"), 0, 5);
        grid.add(passwordField, 1, 5);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == addButtonType) {
                try {
                    String firstName = firstNameField.getText().trim();
                    String lastName = lastNameField.getText().trim();
                    int age = Integer.parseInt(ageField.getText().trim());
                    String jobTitle = jobTitleField.getText().trim();
                    String email = emailField.getText().trim();
                    String password = passwordField.getText();

                    if (firstName.isEmpty() || lastName.isEmpty() || jobTitle.isEmpty() || 
                        email.isEmpty() || password.isEmpty()) {
                        showAlert("Erreur", "Tous les champs sont obligatoires", Alert.AlertType.ERROR);
                        return null;
                    }

                    if (age <= 0 || age > 150) {
                        showAlert("Erreur", "L'âge doit être valide", Alert.AlertType.ERROR);
                        return null;
                    }

                    if (employeeController.handleAddEmployee(currentUser, firstName, lastName, 
                                                             age, jobTitle, email, password)) {
                        showAlert("Succès", "Employé ajouté avec succès!", Alert.AlertType.INFORMATION);
                        loadEmployeesList();
                    } else {
                        showAlert("Erreur", "Échec de l'ajout de l'employé. L'email existe peut-être déjà.", 
                                 Alert.AlertType.ERROR);
                    }
                } catch (NumberFormatException e) {
                    showAlert("Erreur", "L'âge doit être un nombre valide", Alert.AlertType.ERROR);
                }
            }
            return null;
        });

        dialog.showAndWait();
    }

    /**
     * Modifie un employé
     */
    @FXML
    private void handleEditEmployee() {
        Employee selected = employeesTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert("Attention", "Veuillez sélectionner un employé à modifier", Alert.AlertType.WARNING);
            return;
        }

        Dialog<Employee> dialog = new Dialog<>();
        dialog.setTitle("Modifier un employé");
        dialog.setHeaderText("Modifier les informations de " + selected.getFullName());

        ButtonType updateButtonType = new ButtonType("Modifier", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(updateButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        TextField firstNameField = new TextField(selected.getFirstName());
        TextField lastNameField = new TextField(selected.getLastName());
        TextField ageField = new TextField(String.valueOf(selected.getAge()));
        TextField jobTitleField = new TextField(selected.getJobTitle());
        TextField emailField = new TextField(selected.getEmail());

        grid.add(new Label("Prénom:"), 0, 0);
        grid.add(firstNameField, 1, 0);
        grid.add(new Label("Nom:"), 0, 1);
        grid.add(lastNameField, 1, 1);
        grid.add(new Label("Âge:"), 0, 2);
        grid.add(ageField, 1, 2);
        grid.add(new Label("Poste:"), 0, 3);
        grid.add(jobTitleField, 1, 3);
        grid.add(new Label("Email:"), 0, 4);
        grid.add(emailField, 1, 4);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == updateButtonType) {
                try {
                    String firstName = firstNameField.getText().trim();
                    String lastName = lastNameField.getText().trim();
                    int age = Integer.parseInt(ageField.getText().trim());
                    String jobTitle = jobTitleField.getText().trim();
                    String email = emailField.getText().trim();

                    if (firstName.isEmpty() || lastName.isEmpty() || jobTitle.isEmpty() || email.isEmpty()) {
                        showAlert("Erreur", "Tous les champs sont obligatoires", Alert.AlertType.ERROR);
                        return null;
                    }

                    if (employeeController.handleUpdateEmployee(currentUser, selected.getId(), 
                                                               firstName, lastName, age, jobTitle, email)) {
                        showAlert("Succès", "Employé modifié avec succès!", Alert.AlertType.INFORMATION);
                        loadEmployeesList();
                    } else {
                        showAlert("Erreur", "Échec de la modification de l'employé", Alert.AlertType.ERROR);
                    }
                } catch (NumberFormatException e) {
                    showAlert("Erreur", "L'âge doit être un nombre valide", Alert.AlertType.ERROR);
                }
            }
            return null;
        });

        dialog.showAndWait();
    }

    /**
     * Supprime un employé
     */
    @FXML
    private void handleDeleteEmployee() {
        Employee selected = employeesTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert("Attention", "Veuillez sélectionner un employé à supprimer", Alert.AlertType.WARNING);
            return;
        }

        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Confirmation");
        alert.setHeaderText("Supprimer l'employé");
        alert.setContentText("Êtes-vous sûr de vouloir supprimer " + selected.getFullName() + " ?");

        Optional<ButtonType> result = alert.showAndWait();
        if (result.isPresent() && result.get() == ButtonType.OK) {
            if (employeeController.handleDeleteEmployee(currentUser, selected.getId())) {
                showAlert("Succès", "Employé supprimé avec succès!", Alert.AlertType.INFORMATION);
                loadEmployeesList();
            } else {
                showAlert("Erreur", "Échec de la suppression de l'employé", Alert.AlertType.ERROR);
            }
        }
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
