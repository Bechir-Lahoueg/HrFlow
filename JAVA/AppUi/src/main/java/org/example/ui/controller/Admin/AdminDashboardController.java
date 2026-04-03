package org.example.ui.controller;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Node;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import org.example.model.User;
import org.example.ui.MainApp;

import java.io.IOException;

/**
 * Contrôleur pour le dashboard Admin
 */
public class AdminDashboardController {

    @FXML
    private Label welcomeLabel;

    @FXML
    private Label sidebarUserLabel;

    @FXML
    private StackPane contentArea;
    
    @FXML
    private Button homeBtn;
    
    @FXML
    private Button usersBtn;
    
    @FXML
    private Button employeesBtn;
    
    @FXML
    private Button statsBtn;
    
    @FXML
    private Button settingsBtn;

    private User currentUser;

    @FXML
    private Button DataAnalysisBtn ; 




    /**
     * Définit l'utilisateur courant
     */
    public void setCurrentUser(User user) {
        this.currentUser = user;
        welcomeLabel.setText("Bienvenue, " + user.getUsername());
        sidebarUserLabel.setText("👤 " + user.getUsername());
        showHome();  // Charger la vue d'accueil par défaut
    }

    @FXML
    private void initialize() {
        // Initialisation - la vue sera chargée après setCurrentUser
    }

    /**
     * Affiche la vue d'accueil
     */
    @FXML
    private void showHome() {
        loadView("/fxml/views/Admin-dashboard/AdminHomeView.fxml");
        setActiveButton(homeBtn);
    }

    /** 
     * Affiche la vue de gestion des utilisateurs
     */
    @FXML
    public void showUsers() {
        loadView("/fxml/views/Admin-dashboard/AdminUsersView.fxml");
        setActiveButton(usersBtn);
    }

    /**
     * Affiche la vue de gestion des employés
     */
    @FXML
    public void showEmployees() {
        loadView("/fxml/views/Admin-dashboard/AdminEmployeesView.fxml");
        setActiveButton(employeesBtn);
    }

    /**
     * Affiche la vue des statistiques
     */
    @FXML
    public void showStatistics() {
        loadView("/fxml/views/Admin-dashboard/AdminStatisticsView.fxml");
        setActiveButton(statsBtn);
    }

    /**
     * Affiche la vue des paramètres
     */
    @FXML
    private void showSettings() {
        loadView("/fxml/views/Admin-dashboard/AdminSettingsView.fxml");
        setActiveButton(settingsBtn);
    }

    @FXML
    private void showDataAnalysis() {
        loadView("/fxml/views/Admin-dashboard/AdminDataAnalysisView.fxml");
        setActiveButton(DataAnalysisBtn);
    }

    /**
     * Charge une vue FXML dans le contentArea
     */
    private void loadView(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            Node view = loader.load();
            
            // Passer l'utilisateur courant au contrôleur de la vue
            Object controller = loader.getController();
            if (controller instanceof AdminHomeViewController) {
                ((AdminHomeViewController) controller).setCurrentUser(currentUser);
                ((AdminHomeViewController) controller).setDashboardController(this);
            } else if (controller instanceof AdminUsersViewController) {
                ((AdminUsersViewController) controller).setCurrentUser(currentUser);
            } else if (controller instanceof AdminEmployeesViewController) {
                ((AdminEmployeesViewController) controller).setCurrentUser(currentUser);
            } else if (controller instanceof AdminStatisticsViewController) {
                ((AdminStatisticsViewController) controller).setCurrentUser(currentUser);
            } else if (controller instanceof AdminSettingsViewController) {
                ((AdminSettingsViewController) controller).setCurrentUser(currentUser);
            }
            
            contentArea.getChildren().clear();
            contentArea.getChildren().add(view);
        } catch (IOException e) {
            e.printStackTrace();
            showAlert("Erreur", "Impossible de charger la vue: " + fxmlPath, Alert.AlertType.ERROR);
        }
    }

    /**
     * Charge une vue placeholder pour les modules non encore implémentés
     */
    private void loadPlaceholderView(String title, String message) {
        javafx.scene.layout.VBox placeholder = new javafx.scene.layout.VBox(20);
        placeholder.setAlignment(javafx.geometry.Pos.CENTER);
        placeholder.setStyle("-fx-padding: 50;");
        
        Label titleLabel = new Label(title);
        titleLabel.setStyle("-fx-font-size: 26px; -fx-font-weight: bold;");
        titleLabel.getStyleClass().add("page-title");
        
        Label messageLabel = new Label(message);
        messageLabel.setStyle("-fx-font-size: 16px; -fx-text-fill: #666;");
        
        placeholder.getChildren().addAll(titleLabel, messageLabel);
        
        contentArea.getChildren().clear();
        contentArea.getChildren().add(placeholder);
    }

    /**
     * Définit le bouton actif dans la navigation
     */
    private void setActiveButton(Button activeButton) {
        String inactiveStyle = "-fx-background-color: transparent; -fx-text-fill: #bdc3c7; " +
                               "-fx-background-radius: 8; -fx-padding: 10 16; " +
                               "-fx-font-size: 13px; -fx-cursor: hand; " +
                               "-fx-alignment: CENTER_LEFT;";
        String activeStyle = "-fx-background-color: #3498db; -fx-text-fill: white; " +
                             "-fx-background-radius: 8; -fx-padding: 10 16; " +
                             "-fx-font-size: 13px; -fx-cursor: hand; " +
                             "-fx-alignment: CENTER_LEFT;";
        
        // Appliquer le style inactif à tous les boutons
        homeBtn.setStyle(inactiveStyle);
        usersBtn.setStyle(inactiveStyle);
        employeesBtn.setStyle(inactiveStyle);
        statsBtn.setStyle(inactiveStyle);
        settingsBtn.setStyle(inactiveStyle);
        
        // Appliquer le style actif au bouton sélectionné
        activeButton.setStyle(activeStyle);
    }

    /**
     * Déconnecte l'utilisateur
     */
    @FXML
    private void handleLogout() {
        currentUser = null;
        MainApp.showLoginScreen();
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
