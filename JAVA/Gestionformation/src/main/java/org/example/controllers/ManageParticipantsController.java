package org.example.controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import org.example.models.ParticipationFormation;
import org.example.models.SessionFormation;
import org.example.services.ParticipationFormationService;
import java.util.List;

public class ManageParticipantsController {

    @FXML private TableView<ParticipationFormation> tableParticipants;
    @FXML private TableColumn<ParticipationFormation, String> colNom, colStatut, colDate;
    @FXML private TableColumn<ParticipationFormation, Void> colActions;
    @FXML private Label lblTitle;

    private final ParticipationFormationService service = new ParticipationFormationService();
    private SessionFormation selectedSession;

    public void setSession(SessionFormation session) {
        this.selectedSession = session;
        lblTitle.setText("Inscriptions - Session #" + session.getIdSession());
        loadData();
    }

    @FXML
    public void initialize() {
        colNom.setCellValueFactory(d -> new SimpleStringProperty(d.getValue().getNomEmployee()));
        colDate.setCellValueFactory(d -> new SimpleStringProperty(d.getValue().getDateInscription().toString()));
        colStatut.setCellValueFactory(d -> new SimpleStringProperty(d.getValue().getStatutParticipation()));

        // Design des badges de statut
        colStatut.setCellFactory(column -> new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setText(null); setStyle(""); }
                else {
                    setText(item); setAlignment(Pos.CENTER);
                    if (item.equalsIgnoreCase("Acceptée")) setStyle("-fx-background-color: #d4edda; -fx-text-fill: #155724; -fx-background-radius: 10;");
                    else if (item.equalsIgnoreCase("Refusée")) setStyle("-fx-background-color: #f8d7da; -fx-text-fill: #721c24; -fx-background-radius: 10;");
                    else setStyle("-fx-background-color: #fff3cd; -fx-text-fill: #856404; -fx-background-radius: 10;");
                }
            }
        });
        setupActions();
    }

    private void setupActions() {
        colActions.setCellFactory(param -> new TableCell<>() {
            private final Button btnOk = new Button("✔");
            private final Button btnNo = new Button("✘");
            private final HBox container = new HBox(8, btnOk, btnNo);
            {
                btnOk.setStyle("-fx-background-color: #2ecc71; -fx-text-fill: white; -fx-cursor: hand;");
                btnNo.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-cursor: hand;");
                container.setAlignment(Pos.CENTER);
                btnOk.setOnAction(e -> update(getTableView().getItems().get(getIndex()), "Acceptée"));
                btnNo.setOnAction(e -> update(getTableView().getItems().get(getIndex()), "Refusée"));
            }
            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) setGraphic(null);
                else {
                    ParticipationFormation p = getTableView().getItems().get(getIndex());
                    setGraphic(p.getStatutParticipation().equalsIgnoreCase("En attente") ? container : null);
                }
            }
        });
    }

    private void update(ParticipationFormation p, String statut) {
        p.setStatutParticipation(statut);
        service.updateParticipation(p);
        loadData();
    }

    private void loadData() {
        List<ParticipationFormation> data = service.getParticipationsBySession(selectedSession.getIdSession());
        tableParticipants.setItems(FXCollections.observableArrayList(data));
    }
}