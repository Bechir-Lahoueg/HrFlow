package org.example.controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.stage.Modality;
import javafx.stage.Stage;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.fxml.FXMLLoader;
import javafx.util.Callback;
import org.example.models.Formation;
import org.example.models.SessionFormation;
import org.example.services.SessionFormationService;
import javafx.beans.property.SimpleStringProperty;

import java.io.IOException;
import java.sql.SQLException;
import java.util.List;

public class FormationSessionsController {

    @FXML private Label lblFormationTitle;
    @FXML private Button btnAddSession, btnClose;
    @FXML private TableView<SessionFormation> tableAllSessions;

    @FXML private TableColumn<SessionFormation, String> colAllDateDebut, colAllDateFin, colAllLieu, colAllCapacite, colAllStatut;
    @FXML private TableColumn<SessionFormation, Void> colAllActions;

    @FXML private TableView<SessionFormation> tablePlannedSessions, tableInProgressSessions, tableFinishedSessions;

    private Formation formation;
    private final SessionFormationService sessionService = new SessionFormationService();

    public void setFormation(Formation formation) {
        this.formation = formation;
        lblFormationTitle.setText(formation.getTitre());
        loadSessions();
    }

    @FXML
    public void initialize() {
        colAllDateDebut.setCellValueFactory(cellData -> new SimpleStringProperty(cellData.getValue().getDateDebut().toString()));
        colAllDateFin.setCellValueFactory(cellData -> new SimpleStringProperty(cellData.getValue().getDateFin().toString()));
        colAllLieu.setCellValueFactory(cellData -> new SimpleStringProperty(cellData.getValue().getLieu()));
        colAllStatut.setCellValueFactory(cellData -> new SimpleStringProperty(cellData.getValue().getStatut()));
        colAllCapacite.setCellValueFactory(cellData -> new SimpleStringProperty(String.valueOf(cellData.getValue().getCapaciteMax())));

        setupActionsColumn();
    }

    private void setupActionsColumn() {
        Callback<TableColumn<SessionFormation, Void>, TableCell<SessionFormation, Void>> cellFactory = param -> new TableCell<>() {
            private final Button btnEdit = new Button("Modifier");
            private final Button btnDelete = new Button("Supprimer");
            private final Button btnManage = new Button("Inscrits"); // NOUVEAU BOUTON
            private final HBox container = new HBox(5, btnManage, btnEdit, btnDelete);

            {
                // Styles CSS
                btnManage.getStyleClass().add("info-button"); // Assure-toi d'avoir ce style ou utilise btnManage.setStyle(...)
                btnEdit.getStyleClass().add("primary-button");
                btnDelete.getStyleClass().add("delete-button");

                // Action : Gérer les inscriptions
                btnManage.setOnAction(event -> {
                    SessionFormation session = getTableView().getItems().get(getIndex());
                    openManageParticipants(session);
                });

                // Action : Modifier
                btnEdit.setOnAction(event -> {
                    SessionFormation session = getTableView().getItems().get(getIndex());
                    openEditSession(session);
                });

                // Action : Supprimer
                btnDelete.setOnAction(event -> {
                    SessionFormation session = getTableView().getItems().get(getIndex());
                    deleteSession(session);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        };

        if (colAllActions != null) {
            colAllActions.setCellFactory(cellFactory);
        }
    }

    // Nouvelle méthode pour ouvrir la gestion des participants
    private void openManageParticipants(SessionFormation session) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/views/ManageParticipants.fxml"));
            Parent root = loader.load();

            ManageParticipantsController ctrl = loader.getController();
            ctrl.setSession(session); // On passe la session sélectionnée au contrôleur de gestion

            Stage stage = new Stage();
            stage.setScene(new Scene(root));
            stage.setTitle("Gestion des participants - Session #" + session.getIdSession());
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.showAndWait();

            // Optionnel : rafraîchir les sessions si le nombre d'inscrits impacte l'affichage
            loadSessions();
        } catch (IOException e) {
            System.err.println("Erreur chargement ManageParticipants.fxml : " + e.getMessage());
            e.printStackTrace();
        }
    }

    @FXML
    private void loadSessions() {
        if (formation == null) return;

        List<SessionFormation> sessions = sessionService.getAllSessions()
                .stream()
                .filter(s -> s.getIdFormation() == formation.getIdFormation())
                .toList();

        tableAllSessions.setItems(FXCollections.observableArrayList(sessions));

        tablePlannedSessions.setItems(FXCollections.observableArrayList(
                sessions.stream().filter(s -> s.getStatut().equalsIgnoreCase("Planifiée")).toList()
        ));
        tableInProgressSessions.setItems(FXCollections.observableArrayList(
                sessions.stream().filter(s -> s.getStatut().equalsIgnoreCase("En cours")).toList()
        ));
        tableFinishedSessions.setItems(FXCollections.observableArrayList(
                sessions.stream().filter(s -> s.getStatut().equalsIgnoreCase("Terminée")).toList()
        ));
    }

    @FXML
    private void openAddSession() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/views/AddSession.fxml"));
            Parent root = loader.load();
            AddSessionController controller = loader.getController();
            controller.setFormation(this.formation);
            Stage stage = new Stage();
            stage.setTitle("Planifier une session - " + formation.getTitre());
            stage.setScene(new Scene(root));
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.showAndWait();
            loadSessions();
        } catch (Exception e) { e.printStackTrace(); }
    }

    @FXML
    private void openEditSession(SessionFormation session) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/views/AddSession.fxml"));
            Parent root = loader.load();
            AddSessionController controller = loader.getController();
            controller.setSessionToEdit(session, this.formation);
            Stage stage = new Stage();
            stage.setTitle("Modifier la session");
            stage.setScene(new Scene(root));
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.showAndWait();
            loadSessions();
        } catch (Exception e) { e.printStackTrace(); }
    }

    @FXML
    private void deleteSession(SessionFormation session) {
        Alert confirmation = new Alert(Alert.AlertType.CONFIRMATION);
        confirmation.setTitle("Confirmation de suppression");
        confirmation.setHeaderText("Supprimer la session ?");
        confirmation.setContentText("Voulez-vous vraiment supprimer la session prévue le " + session.getDateDebut() + " ?");

        confirmation.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                try {
                    sessionService.deleteSession(session.getIdSession());
                    loadSessions();
                    new Alert(Alert.AlertType.INFORMATION, "Session supprimée avec succès !").show();
                } catch (SQLException e) {
                    Alert error = new Alert(Alert.AlertType.ERROR, "Suppression impossible : participants liés.");
                    error.show();
                }
            }
        });
    }

    @FXML private void closeWindow() {
        ((Stage) btnClose.getScene().getWindow()).close();
    }
}