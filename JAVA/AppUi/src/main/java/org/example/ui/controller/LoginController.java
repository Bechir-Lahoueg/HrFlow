package org.example.ui.controller;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.stage.Stage;
import org.example.controller.EmployeeController;
import org.example.controller.UserController;
import org.example.model.Employee;
import org.example.model.User;
import org.example.ui.MainApp;
import org.example.ui.controller.AdminDashboardController;
import org.example.ui.controller.Rh.RHDashboardController;
import org.example.ui.controller.Employee.EmployeeDashboardController;

/**
 * Contrôleur pour l'écran de connexion
 */
public class LoginController {

    @FXML
    private TabPane loginTabPane;

    @FXML
    private TextField userUsernameField;

    @FXML
    private PasswordField userPasswordField;

    @FXML
    private TextField employeeEmailField;

    @FXML
    private PasswordField employeePasswordField;

    @FXML
    private Label userErrorLabel;

    @FXML
    private Label employeeErrorLabel;

    private final UserController userController = new UserController();
    private final EmployeeController employeeController = new EmployeeController();

    @FXML
    private void initialize() {
        // Initialisation si nécessaire
        userErrorLabel.setText("");
        employeeErrorLabel.setText("");
        
        // Ajouter des listeners pour validation en temps réel
        employeeEmailField.textProperty().addListener((obs, oldVal, newVal) -> {
            if (!newVal.trim().isEmpty() && !isValidEmail(newVal)) {
                employeeEmailField.setStyle("-fx-border-color: #dc3545; -fx-border-width: 2px;");
            } else {
                employeeEmailField.setStyle("");
            }
        });
    }
    
    /**
     * Valide le format d'un email
     */
    private boolean isValidEmail(String email) {
        if (email == null || email.trim().isEmpty()) {
            return false;
        }
        String emailRegex = "^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$";
        return email.matches(emailRegex);
    }
    
    /**
     * Valide la force du mot de passe (minimum 4 caractères)
     */
    private boolean isValidPassword(String password) {
        return password != null && password.length() >= 4;
    }

    /**
     * Gère la connexion Admin/RH
     */
    @FXML
    private void handleUserLogin() {
        String username = userUsernameField.getText().trim();
        String password = userPasswordField.getText();

        // Validation des champs vides
        if (username.isEmpty() || password.isEmpty()) {
            userErrorLabel.setText("⚠️ Veuillez remplir tous les champs");
            userErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            return;
        }
        
        // Validation de la longueur du username
        if (username.length() < 3) {
            userErrorLabel.setText("⚠️ Le nom d'utilisateur doit contenir au moins 3 caractères");
            userErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            return;
        }
        
        // Validation du mot de passe
        if (!isValidPassword(password)) {
            userErrorLabel.setText("⚠️ Le mot de passe doit contenir au moins 4 caractères");
            userErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            return;
        }

        User user = userController.handleLogin(username, password);

        if (user != null) {
            userErrorLabel.setText("");
            openDashboard(user);
        } else {
            userErrorLabel.setText("❌ Identifiants incorrects");
            userErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            userPasswordField.clear();
        }
    }

    /**
     * Gère la connexion Employé
     */
    @FXML
    private void handleEmployeeLogin() {
        String email = employeeEmailField.getText().trim();
        String password = employeePasswordField.getText();

        // Validation des champs vides
        if (email.isEmpty() || password.isEmpty()) {
            employeeErrorLabel.setText("⚠️ Veuillez remplir tous les champs");
            employeeErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            return;
        }
        
        // Validation du format email
        if (!isValidEmail(email)) {
            employeeErrorLabel.setText("⚠️ Format d'email invalide (exemple: nom@domaine.com)");
            employeeErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            employeeEmailField.setStyle("-fx-border-color: #dc3545; -fx-border-width: 2px;");
            return;
        }
        
        // Validation du mot de passe
        if (!isValidPassword(password)) {
            employeeErrorLabel.setText("⚠️ Le mot de passe doit contenir au moins 4 caractères");
            employeeErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            return;
        }

        // Réinitialiser le style du champ email
        employeeEmailField.setStyle("");
        
        Employee employee = employeeController.handleEmployeeLogin(email, password);

        if (employee != null) {
            employeeErrorLabel.setText("");
            openEmployeeDashboard(employee);
        } else {
            employeeErrorLabel.setText("❌ Email ou mot de passe incorrect");
            employeeErrorLabel.setStyle("-fx-text-fill: #dc3545; -fx-font-weight: bold;");
            employeePasswordField.clear();
        }
    }

    /**
     * Ouvre le dashboard approprié selon le rôle de l'utilisateur
     */
    private void openDashboard(User user) {
        try {
            FXMLLoader loader;
            
            if (user.isAdmin()) {
                loader = new FXMLLoader(getClass().getResource("/fxml/views/Admin-dashboard/AdminDashboard.fxml"));
            } else if (user.isRH()) {
                loader = new FXMLLoader(getClass().getResource("/fxml/views/Rh-dashboard/RHDashboard.fxml"));
            } else {
                userErrorLabel.setText("Rôle utilisateur non reconnu");
                return;
            }

            Parent root = loader.load();
            
            // Passer l'utilisateur au contrôleur du dashboard
            if (user.isAdmin()) {
                AdminDashboardController controller = loader.getController();
                controller.setCurrentUser(user);
            } else if (user.isRH()) {
                RHDashboardController controller = loader.getController();
                controller.setCurrentUser(user);
            }

            Scene scene = new Scene(root);
            scene.getStylesheets().add(getClass().getResource("/css/style.css").toExternalForm());
            
            Stage stage = MainApp.getPrimaryStage();
            stage.setScene(scene);
            stage.setTitle("Workforce Platform - Dashboard " + user.getRole());
            
        } catch (Exception e) {
            e.printStackTrace();
            userErrorLabel.setText("Erreur lors de l'ouverture du dashboard");
        }
    }

    /**
     * Ouvre le dashboard employé
     */
    private void openEmployeeDashboard(Employee employee) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/views/Employee-dashboard/EmployeeDashboard.fxml"));
            Parent root = loader.load();
            
            // Passer l'employé au contrôleur du dashboard
            EmployeeDashboardController controller = loader.getController();
            controller.setCurrentEmployee(employee);

            Scene scene = new Scene(root);
            scene.getStylesheets().add(getClass().getResource("/css/style.css").toExternalForm());
            
            Stage stage = MainApp.getPrimaryStage();
            stage.setScene(scene);
            stage.setTitle("Workforce Platform - Espace Employé");
            
        } catch (Exception e) {
            e.printStackTrace();
            employeeErrorLabel.setText("Erreur lors de l'ouverture du dashboard");
        }
    }

    /**
     * Gère la touche Enter pour la connexion utilisateur
     */
    @FXML
    private void handleUserEnterKey(javafx.scene.input.KeyEvent event) {
        if (event.getCode() == javafx.scene.input.KeyCode.ENTER) {
            handleUserLogin();
        }
    }

    /**
     * Gère la touche Enter pour la connexion employé
     */
    @FXML
    private void handleEmployeeEnterKey(javafx.scene.input.KeyEvent event) {
        if (event.getCode() == javafx.scene.input.KeyCode.ENTER) {
            handleEmployeeLogin();
        }
    }
}
