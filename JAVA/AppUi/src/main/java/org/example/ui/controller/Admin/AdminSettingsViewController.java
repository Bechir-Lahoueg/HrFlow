package org.example.ui.controller;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.stage.Stage;
import org.example.controller.UserController;
import org.example.model.User;

import java.io.IOException;

/**
 * Contrôleur pour la vue des paramètres Admin
 */
public class AdminSettingsViewController {

    @FXML
    private Label usernameLabel;

    @FXML
    private Label emailLabel;

    @FXML
    private Label roleLabel;

    @FXML
    private Label userIdLabel;

    @FXML
    private Label createdAtLabel;

    @FXML
    private PasswordField currentPasswordField;

    @FXML
    private PasswordField newPasswordField;

    @FXML
    private PasswordField confirmPasswordField;

    private final UserController userController = new UserController();
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
        loadUserProfile();
    }

    /**
     * Charge le profil de l'utilisateur
     */
    private void loadUserProfile() {
        if (currentUser != null) {
            if (usernameLabel != null) {
                usernameLabel.setText(currentUser.getUsername());
            }
            if (emailLabel != null) {
                emailLabel.setText(currentUser.getEmail() != null ? currentUser.getEmail() : "Non défini");
            }
            if (roleLabel != null) {
                String roleText = "";
                switch (currentUser.getRole()) {
                    case ADMIN:
                        roleText = "ADMINISTRATEUR";
                        roleLabel.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-padding: 5 15; -fx-background-radius: 5;");
                        break;
                    case RH:
                        roleText = "RESPONSABLE RH";
                        roleLabel.setStyle("-fx-background-color: #3498db; -fx-text-fill: white; -fx-padding: 5 15; -fx-background-radius: 5;");
                        break;
                    case EMPLOYEE:
                        roleText = "EMPLOYÉ";
                        roleLabel.setStyle("-fx-background-color: #95a5a6; -fx-text-fill: white; -fx-padding: 5 15; -fx-background-radius: 5;");
                        break;
                }
                roleLabel.setText(roleText);
            }
            if (userIdLabel != null) {
                userIdLabel.setText("#" + currentUser.getId());
            }
            if (createdAtLabel != null) {
                createdAtLabel.setText("Non disponible");
            }
        }
    }

    /**
     * Gère le changement de mot de passe
     */
    @FXML
    private void handleChangePassword() {
        String currentPassword = currentPasswordField.getText();
        String newPassword = newPasswordField.getText();
        String confirmPassword = confirmPasswordField.getText();

        // Validation
        if (currentPassword.isEmpty() || newPassword.isEmpty() || confirmPassword.isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Champs manquants", 
                     "Veuillez remplir tous les champs.");
            return;
        }

        if (!newPassword.equals(confirmPassword)) {
            showAlert(Alert.AlertType.ERROR, "Erreur", 
                     "Le nouveau mot de passe et la confirmation ne correspondent pas.");
            return;
        }

        if (newPassword.length() < 6) {
            showAlert(Alert.AlertType.ERROR, "Erreur", 
                     "Le nouveau mot de passe doit contenir au moins 6 caractères.");
            return;
        }

        // Attempt to change password
        boolean success = userController.handleChangePassword(currentUser, currentPassword, newPassword);

        if (success) {
            showAlert(Alert.AlertType.INFORMATION, "Succès", 
                     "Mot de passe modifié avec succès !");
            // Clear fields
            currentPasswordField.clear();
            newPasswordField.clear();
            confirmPasswordField.clear();
        } else {
            showAlert(Alert.AlertType.ERROR, "Erreur", 
                     "Échec du changement de mot de passe. Vérifiez votre mot de passe actuel.");
        }
    }

    /**
     * Gère la déconnexion
     */
    @FXML
    private void handleLogout() {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Confirmation");
        alert.setHeaderText("Déconnexion");
        alert.setContentText("Êtes-vous sûr de vouloir vous déconnecter ?");

        alert.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                try {
                    // Load login view
                    FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Login.fxml"));
                    Parent root = loader.load();
                    
                    Stage stage = (Stage) usernameLabel.getScene().getWindow();
                    stage.setScene(new Scene(root));
                    stage.setTitle("Connexion - Workforce Platform");
                    stage.centerOnScreen();
                    
                    System.out.println("✓ Déconnexion réussie");
                } catch (IOException e) {
                    System.err.println("✗ Erreur lors du retour à la page de connexion : " + e.getMessage());
                    e.printStackTrace();
                }
            }
        });
    }

    /**
     * Affiche une alerte
     */
    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
