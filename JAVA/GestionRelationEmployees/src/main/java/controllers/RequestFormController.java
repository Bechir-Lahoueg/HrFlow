package controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.stage.Stage;
import models.Request;
import models.RequestType;
import service.RequestService;
import service.RequestTypeService;

import java.net.URL;
import java.util.List;
import java.util.ResourceBundle;

public class RequestFormController implements Initializable {

    @FXML private TextField          txtTitle;
    @FXML private TextArea           txtDescription;
    @FXML private ComboBox<RequestType> cbType;
    @FXML private ComboBox<String>   cbPriority;
    @FXML private Label              lblTitle;
    @FXML private Button             btnSave;
    @FXML private Button             btnCancel;

    private final RequestService     requestService     = new RequestService();
    private final RequestTypeService requestTypeService = new RequestTypeService();

    private Request     requestToEdit = null;   // null = mode Ajout
    private Runnable    onSaved;                // callback pour rafraîchir la liste

    // ─── Initialisation ──────────────────────────────────────────────

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        // Charger les types de demandes
        List<RequestType> types = requestTypeService.getAll();
        cbType.setItems(FXCollections.observableArrayList(types));

        // Priorités
        cbPriority.setItems(FXCollections.observableArrayList("low", "medium", "high"));
        cbPriority.setValue("medium");
    }

    // ─── Mode Ajout ou Édition ───────────────────────────────────────

    public void setRequest(Request r) {
        this.requestToEdit = r;
        if (r != null) {
            // Mode édition : pré-remplir les champs
            lblTitle.setText("Modifier la demande");
            txtTitle.setText(r.getTitle());
            txtDescription.setText(r.getDescription());
            cbPriority.setValue(r.getPriority().name());
            // Sélectionner le bon type
            cbType.getItems().stream()
                    .filter(t -> t.getId() == r.getRequestTypeId())
                    .findFirst()
                    .ifPresent(cbType::setValue);
        } else {
            lblTitle.setText("Nouvelle demande");
        }
    }

    // ─── Callback après sauvegarde ───────────────────────────────────

    public void setOnSaved(Runnable callback) {
        this.onSaved = callback;
    }

    // ─── Sauvegarde ──────────────────────────────────────────────────

    @FXML
    private void handleSave() {
        if (!validateInputs()) return;

        String title       = txtTitle.getText().trim();
        String description = txtDescription.getText().trim();
        RequestType type   = cbType.getValue();
        String priority    = cbPriority.getValue();

        if (requestToEdit == null) {
            // ── Mode AJOUT ──
            // user_id = 1 (à remplacer par l'utilisateur connecté)
            Request newRequest = new Request(
                    1, type.getId(), title, description,
                    Request.Priority.valueOf(priority)
            );
            boolean ok = requestService.add(newRequest);
            if (ok) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Demande ajoutée avec succès !");
                closeAndRefresh();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'ajouter la demande.");
            }
        } else {
            // ── Mode ÉDITION ──
            requestToEdit.setTitle(title);
            requestToEdit.setDescription(description);
            requestToEdit.setRequestTypeId(type.getId());
            requestToEdit.setPriority(Request.Priority.valueOf(priority));

            boolean ok = requestService.update(requestToEdit);
            if (ok) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Demande mise à jour !");
                closeAndRefresh();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de modifier la demande.");
            }
        }
    }

    // ─── Annuler ─────────────────────────────────────────────────────

    @FXML
    private void handleCancel() {
        closeWindow();
    }

    // ─── Validation ──────────────────────────────────────────────────

    private boolean validateInputs() {
        String title = txtTitle.getText().trim();
        if (title.isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Le titre est obligatoire.");
            txtTitle.requestFocus();
            return false;
        }
        if (cbType.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Veuillez choisir un type de demande.");
            return false;
        }
        if (cbPriority.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Veuillez choisir une priorité.");
            return false;
        }
        return true;
    }

    // ─── Utilitaires ─────────────────────────────────────────────────

    private void closeAndRefresh() {
        if (onSaved != null) onSaved.run();
        closeWindow();
    }

    private void closeWindow() {
        Stage stage = (Stage) btnCancel.getScene().getWindow();
        stage.close();
    }

    private void showAlert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type, message, ButtonType.OK);
        alert.setTitle(title);
        alert.showAndWait();
    }
}