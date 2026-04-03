package controllers;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import models.RequestType;
import service.RequestTypeService;

import java.net.URL;
import java.util.List;
import java.util.ResourceBundle;

public class RequestTypeController implements Initializable {

    // ─── TableView ────────────────────────────────────────────────────
    @FXML private TableView<RequestType>              tableTypes;
    @FXML private TableColumn<RequestType, Integer>   colId;
    @FXML private TableColumn<RequestType, String>    colName;
    @FXML private TableColumn<RequestType, String>    colDescription;
    @FXML private TableColumn<RequestType, Boolean>   colRequiresApproval;
    @FXML private TableColumn<RequestType, String>    colCreatedAt;

    // ─── Formulaire inline ───────────────────────────────────────────
    @FXML private TextField  txtName;
    @FXML private TextArea   txtDescription;
    @FXML private CheckBox   chkRequiresApproval;
    @FXML private Label      lblFormTitle;

    // ─── Boutons ──────────────────────────────────────────────────────
    @FXML private Button btnSave;
    @FXML private Button btnClear;
    @FXML private Button btnDelete;

    // ─── Label stats ──────────────────────────────────────────────────
    @FXML private Label lblTotal;

    private final RequestTypeService service = new RequestTypeService();
    private ObservableList<RequestType> typeList = FXCollections.observableArrayList();

    // ID du type en cours d'édition (null = mode Ajout)
    private RequestType selectedType = null;

    // ─── Initialisation ──────────────────────────────────────────────

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        setupColumns();
        loadData();
        setupTableSelection();
    }

    private void setupColumns() {
        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colName.setCellValueFactory(new PropertyValueFactory<>("name"));
        colDescription.setCellValueFactory(new PropertyValueFactory<>("description"));
        colRequiresApproval.setCellValueFactory(new PropertyValueFactory<>("requiresApproval"));
        colCreatedAt.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        // Colonne "Approbation requise" : afficher Oui / Non avec couleur
        colRequiresApproval.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(Boolean value, boolean empty) {
                super.updateItem(value, empty);
                if (empty || value == null) {
                    setText(null);
                    setStyle("");
                } else if (value) {
                    setText("✅ Oui");
                    setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;");
                } else {
                    setText("❌ Non");
                    setStyle("-fx-text-fill: #e74c3c; -fx-font-weight: bold;");
                }
            }
        });
    }

    // Quand on clique sur une ligne → pré-remplir le formulaire
    private void setupTableSelection() {
        tableTypes.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> {
                    if (newVal != null) {
                        selectedType = newVal;
                        fillForm(newVal);
                        lblFormTitle.setText("✏️ Modifier le type");
                        btnSave.setText("💾 Modifier");
                    }
                }
        );
    }

    // ─── Chargement données ──────────────────────────────────────────

    private void loadData() {
        List<RequestType> list = service.getAll();
        typeList.setAll(list);
        tableTypes.setItems(typeList);
        lblTotal.setText("Total : " + list.size() + " types");
    }

    // ─── Remplir le formulaire avec un type sélectionné ─────────────

    private void fillForm(RequestType rt) {
        txtName.setText(rt.getName());
        txtDescription.setText(rt.getDescription());
        chkRequiresApproval.setSelected(rt.isRequiresApproval());
    }

    // ─── Sauvegarder (Ajout ou Modification) ────────────────────────

    @FXML
    private void handleSave() {
        if (!validateInputs()) return;

        String  name        = txtName.getText().trim();
        String  description = txtDescription.getText().trim();
        boolean approval    = chkRequiresApproval.isSelected();

        if (selectedType == null) {
            // ── Mode AJOUT ──
            RequestType newType = new RequestType(name, description, approval);
            boolean ok = service.add(newType);
            if (ok) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Type ajouté avec succès !");
                handleClear();
                loadData();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'ajouter le type.");
            }
        } else {
            // ── Mode ÉDITION ──
            selectedType.setName(name);
            selectedType.setDescription(description);
            selectedType.setRequiresApproval(approval);

            boolean ok = service.update(selectedType);
            if (ok) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Type mis à jour avec succès !");
                handleClear();
                loadData();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de modifier le type.");
            }
        }
    }

    // ─── Supprimer ───────────────────────────────────────────────────

    @FXML
    private void handleDelete() {
        RequestType selected = tableTypes.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Sélection",
                    "Veuillez sélectionner un type à supprimer.");
            return;
        }

        // Confirmation avant suppression
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Supprimer le type : \"" + selected.getName() + "\" ?\n\n" +
                        "⚠️ Attention : toutes les demandes liées à ce type seront affectées.",
                ButtonType.YES, ButtonType.NO);
        confirm.setTitle("Confirmation de suppression");
        confirm.showAndWait().ifPresent(bt -> {
            if (bt == ButtonType.YES) {
                boolean ok = service.delete(selected.getId());
                if (ok) {
                    showAlert(Alert.AlertType.INFORMATION, "Supprimé",
                            "Type supprimé avec succès.");
                    handleClear();
                    loadData();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur",
                            "Impossible de supprimer ce type.\n" +
                                    "Il est peut-être utilisé par des demandes existantes.");
                }
            }
        });
    }

    // ─── Réinitialiser le formulaire ─────────────────────────────────

    @FXML
    private void handleClear() {
        txtName.clear();
        txtDescription.clear();
        chkRequiresApproval.setSelected(true);
        selectedType = null;
        tableTypes.getSelectionModel().clearSelection();
        lblFormTitle.setText("➕ Nouveau type");
        btnSave.setText("💾 Ajouter");
    }

    // ─── Validation ──────────────────────────────────────────────────

    private boolean validateInputs() {
        if (txtName.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Le nom du type est obligatoire.");
            txtName.requestFocus();
            return false;
        }
        return true;
    }

    // ─── Utilitaire Alert ────────────────────────────────────────────

    private void showAlert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type, message, ButtonType.OK);
        alert.setTitle(title);
        alert.showAndWait();
    }
}