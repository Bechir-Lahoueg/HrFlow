package org.example.ui.controller.Employee;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import org.example.model.Employee;
import org.example.ui.MainApp;

/**
 * Contrôleur pour les paramètres de l'employé
 * À développer par l'équipe
 */
public class EmployeeSettingsViewController {

    private Employee currentEmployee;

    public void setCurrentEmployee(Employee employee) {
        this.currentEmployee = employee;
        // TODO: Implémenter l'affichage des paramètres
    }

    @FXML
    private void handleLogout() {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Déconnexion");
        alert.setHeaderText("Confirmer la déconnexion");
        alert.setContentText("Êtes-vous sûr de vouloir vous déconnecter ?");

        alert.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                MainApp.showLoginScreen();
            }
        });
    }
}
