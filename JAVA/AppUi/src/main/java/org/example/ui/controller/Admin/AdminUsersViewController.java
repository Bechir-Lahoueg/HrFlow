package org.example.ui.controller;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.GridPane;
import org.example.controller.UserController;
import org.example.model.User;

import java.util.List;
import java.util.Optional;

/**
 * Contrôleur pour la vue de gestion des utilisateurs Admin
 */
public class AdminUsersViewController {

    @FXML
    private TableView<User> rhTableView;

    @FXML
    private TableColumn<User, Integer> idColumn;

    @FXML
    private TableColumn<User, String> usernameColumn;

    @FXML
    private TableColumn<User, String> roleColumn;

    @FXML
    private TableView<User> allUsersTableView;

    @FXML
    private TableColumn<User, Integer> allIdColumn;

    @FXML
    private TableColumn<User, String> allUsernameColumn;

    @FXML
    private TableColumn<User, String> allRoleColumn;

    private final UserController userController = new UserController();
    private ObservableList<User> rhList = FXCollections.observableArrayList();
    private ObservableList<User> allUsersList = FXCollections.observableArrayList();
    private User currentUser;

    @FXML
    private void initialize() {
        // Configuration des colonnes du tableau RH
        if (idColumn != null) {
            idColumn.setCellValueFactory(new PropertyValueFactory<>("id"));
        }
        if (usernameColumn != null) {
            usernameColumn.setCellValueFactory(new PropertyValueFactory<>("username"));
        }
        if (roleColumn != null) {
            roleColumn.setCellValueFactory(new PropertyValueFactory<>("role"));
        }

        if (rhTableView != null) {
            rhTableView.setItems(rhList);
        }

        // Configuration des colonnes du tableau tous les utilisateurs
        if (allIdColumn != null) {
            allIdColumn.setCellValueFactory(new PropertyValueFactory<>("id"));
        }
        if (allUsernameColumn != null) {
            allUsernameColumn.setCellValueFactory(new PropertyValueFactory<>("username"));
        }
        if (allRoleColumn != null) {
            allRoleColumn.setCellValueFactory(new PropertyValueFactory<>("role"));
        }

        if (allUsersTableView != null) {
            allUsersTableView.setItems(allUsersList);
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
            loadRHList();
            loadAllUsersList();
        }
    }

    /**
     * Charge la liste des RH
     */
    private void loadRHList() {
        List<User> users = userController.handleListAllRH(currentUser);
        rhList.clear();
        rhList.addAll(users);
    }

    /**
     * Charge la liste de tous les utilisateurs
     */
    private void loadAllUsersList() {
        List<User> users = userController.handleListAllUsers(currentUser);
        allUsersList.clear();
        allUsersList.addAll(users);
    }

    /**
     * Ajoute un nouveau RH
     */
    @FXML
    private void handleAddRH() {
        Dialog<User> dialog = new Dialog<>();
        dialog.setTitle("Ajouter un RH");
        dialog.setHeaderText("Créer un nouveau compte RH");

        ButtonType addButtonType = new ButtonType("Ajouter", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(addButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        TextField usernameField = new TextField();
        usernameField.setPromptText("Username");
        TextField emailField = new TextField();
        emailField.setPromptText("Email (optionnel)");
        PasswordField passwordField = new PasswordField();
        passwordField.setPromptText("Mot de passe");

        grid.add(new Label("Username:"), 0, 0);
        grid.add(usernameField, 1, 0);
        grid.add(new Label("Email:"), 0, 1);
        grid.add(emailField, 1, 1);
        grid.add(new Label("Mot de passe:"), 0, 2);
        grid.add(passwordField, 1, 2);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == addButtonType) {
                String username = usernameField.getText().trim();
                String email = emailField.getText().trim();
                String password = passwordField.getText();

                if (!username.isEmpty() && !password.isEmpty()) {
                    if (userController.handleAddRH(currentUser, username, email.isEmpty() ? null : email, password)) {
                        return new User(username, email, password, User.Role.RH);
                    }
                }
            }
            return null;
        });

        Optional<User> result = dialog.showAndWait();
        result.ifPresent(user -> {
            showAlert("Succès", "RH ajouté avec succès!", Alert.AlertType.INFORMATION);
            loadData();
        });
    }

    /**
     * Modifie un RH sélectionné
     */
    @FXML
    private void handleUpdateRH() {
        User selectedUser = rhTableView.getSelectionModel().getSelectedItem();
        
        if (selectedUser == null) {
            showAlert("Erreur", "Veuillez sélectionner un RH à modifier", Alert.AlertType.WARNING);
            return;
        }

        Dialog<User> dialog = new Dialog<>();
        dialog.setTitle("Modifier un RH");
        dialog.setHeaderText("Modifier: " + selectedUser.getUsername());

        ButtonType updateButtonType = new ButtonType("Modifier", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(updateButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        TextField usernameField = new TextField(selectedUser.getUsername());
        TextField emailField = new TextField(selectedUser.getEmail() != null ? selectedUser.getEmail() : "");
        emailField.setPromptText("Email (optionnel)");
        PasswordField passwordField = new PasswordField();
        passwordField.setPromptText("Nouveau mot de passe (laisser vide pour ne pas changer)");

        grid.add(new Label("Username:"), 0, 0);
        grid.add(usernameField, 1, 0);
        grid.add(new Label("Email:"), 0, 1);
        grid.add(emailField, 1, 1);
        grid.add(new Label("Nouveau mot de passe:"), 0, 2);
        grid.add(passwordField, 1, 2);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == updateButtonType) {
                String username = usernameField.getText().trim();
                String email = emailField.getText().trim();
                String password = passwordField.getText();

                if (!username.isEmpty()) {
                    if (userController.handleUpdateRH(currentUser, selectedUser.getId(), username, email.isEmpty() ? null : email, password)) {
                        return selectedUser;
                    }
                }
            }
            return null;
        });

        Optional<User> result = dialog.showAndWait();
        result.ifPresent(user -> {
            showAlert("Succès", "RH modifié avec succès!", Alert.AlertType.INFORMATION);
            loadData();
        });
    }

    /**
     * Supprime un RH sélectionné
     */
    @FXML
    private void handleDeleteRH() {
        User selectedUser = rhTableView.getSelectionModel().getSelectedItem();
        
        if (selectedUser == null) {
            showAlert("Erreur", "Veuillez sélectionner un RH à supprimer", Alert.AlertType.WARNING);
            return;
        }

        Alert confirmation = new Alert(Alert.AlertType.CONFIRMATION);
        confirmation.setTitle("Confirmation");
        confirmation.setHeaderText("Supprimer le RH: " + selectedUser.getUsername());
        confirmation.setContentText("Êtes-vous sûr de vouloir supprimer cet utilisateur?");

        Optional<ButtonType> result = confirmation.showAndWait();
        if (result.isPresent() && result.get() == ButtonType.OK) {
            if (userController.handleDeleteRH(currentUser, selectedUser.getId())) {
                showAlert("Succès", "RH supprimé avec succès!", Alert.AlertType.INFORMATION);
                loadData();
            } else {
                showAlert("Erreur", "Échec de la suppression du RH", Alert.AlertType.ERROR);
            }
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
