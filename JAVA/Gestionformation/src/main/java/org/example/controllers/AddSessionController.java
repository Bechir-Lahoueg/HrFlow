package org.example.controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.stage.Stage;
import org.example.models.Formation;
import org.example.models.SessionFormation;
import org.example.services.SessionFormationService;

import java.time.LocalDate;

public class AddSessionController {

    @FXML private Label lblTitle;
    @FXML private DatePicker dpDateDebut, dpDateFin;
    @FXML private TextField txtLieu, txtCapacite;
    @FXML private ComboBox<String> cbMode, cbStatut;
    @FXML private Button btnSave, btnCancel;

    private Formation formation;
    private SessionFormation sessionToEdit;
    private final SessionFormationService sessionService = new SessionFormationService();

    // Appelé pour un nouvel ajout
    public void setFormation(Formation formation) {
        this.formation = formation;
    }

    // Appelé pour une modification
    // Cette méthode est appelée par le controller parent
    public void setSessionToEdit(SessionFormation session, Formation formation) {
        this.sessionToEdit = session;
        this.formation = formation;

        // Changer le titre de la fenêtre pour l'utilisateur
        lblTitle.setText("Modifier la session de : " + formation.getTitre());

        // Remplir les champs avec les données actuelles
        dpDateDebut.setValue(session.getDateDebut());
        dpDateFin.setValue(session.getDateFin());
        txtLieu.setText(session.getLieu());
        txtCapacite.setText(String.valueOf(session.getCapaciteMax()));

        // Sélectionner les bonnes valeurs dans les ComboBox
        cbMode.setValue(session.getMode());
        cbStatut.setValue(session.getStatut());

        // Optionnel : Changer le texte du bouton de validation
        btnSave.setText("Mettre à jour");
    }

    @FXML
    public void initialize() {
        // Initialiser les choix des ComboBox
        cbMode.setItems(FXCollections.observableArrayList("Présentiel", "À distance", "Hybride"));
        cbStatut.setItems(FXCollections.observableArrayList("Planifiée", "En cours", "Terminée", "Annulée"));

        btnSave.setOnAction(e -> handleSave());
        btnCancel.setOnAction(e -> closeWindow());
    }

    private void handleSave() {
        if (!validateInput()) return;

        try {
            if (sessionToEdit == null) {
                // MODE AJOUT
                SessionFormation newSession = new SessionFormation(
                        formation.getIdFormation(),
                        dpDateDebut.getValue(),
                        dpDateFin.getValue(),
                        txtLieu.getText(),
                        cbMode.getValue(),
                        Integer.parseInt(txtCapacite.getText()),
                        cbStatut.getValue()
                );
                sessionService.addSession(newSession);
            } else {
                // MODE MODIFICATION
                sessionToEdit.setDateDebut(dpDateDebut.getValue());
                sessionToEdit.setDateFin(dpDateFin.getValue());
                sessionToEdit.setLieu(txtLieu.getText());
                sessionToEdit.setMode(cbMode.getValue());
                sessionToEdit.setCapaciteMax(Integer.parseInt(txtCapacite.getText()));
                sessionToEdit.setStatut(cbStatut.getValue());
                sessionService.updateSession(sessionToEdit);
            }
            closeWindow();
        } catch (Exception ex) {
            showError("Erreur lors de l'enregistrement : " + ex.getMessage());
        }
    }

    private boolean validateInput() {
        if (dpDateDebut.getValue() == null || cbStatut.getValue() == null || txtLieu.getText().isEmpty()) {
            showError("Veuillez remplir tous les champs obligatoires.");
            return false;
        }
        try {
            Integer.parseInt(txtCapacite.getText());
        } catch (NumberFormatException e) {
            showError("La capacité doit être un nombre valide.");
            return false;
        }
        return true;
    }

    private void showError(String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR, message);
        alert.showAndWait();
    }

    private void closeWindow() {
        Stage stage = (Stage) btnSave.getScene().getWindow();
        stage.close();
    }
}