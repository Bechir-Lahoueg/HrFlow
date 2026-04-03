package controllers;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.stage.Modality;
import javafx.stage.Stage;
import models.Request;
import service.RequestService;

import java.io.IOException;
import java.net.URL;
import java.util.List;
import java.util.ResourceBundle;

public class RequestController implements Initializable {

    // ─── TableView ────────────────────────────────────────────────────
    @FXML private TableView<Request>            tableRequests;
    @FXML private TableColumn<Request, Integer> colId;
    @FXML private TableColumn<Request, String>  colTitle;
    @FXML private TableColumn<Request, String>  colType;
    @FXML private TableColumn<Request, String>  colPriority;
    @FXML private TableColumn<Request, Request.Status>  colStatus;
    @FXML private TableColumn<Request, String>  colDate;

    // ─── Filtres ──────────────────────────────────────────────────────
    @FXML private ComboBox<String> filterStatus;
    @FXML private TextField        searchField;

    // ─── Boutons ──────────────────────────────────────────────────────
    @FXML private Button btnAdd;
    @FXML private Button btnEdit;
    @FXML private Button btnDelete;
    @FXML private Button btnApprove;
    @FXML private Button btnReject;

    // ─── Labels stats ─────────────────────────────────────────────────
    @FXML private Label lblTotal;
    @FXML private Label lblPending;
    @FXML private Label lblApproved;

    private final RequestService requestService = new RequestService();
    private ObservableList<Request> requestList  = FXCollections.observableArrayList();

    // ─── Initialisation ──────────────────────────────────────────────

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        setupColumns();
        setupFilterCombo();
        loadData();
        setupSearchListener();
    }

    private void setupColumns() {
        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colType.setCellValueFactory(new PropertyValueFactory<>("requestTypeName"));
        colPriority.setCellValueFactory(new PropertyValueFactory<>("priority"));
        colStatus.setCellValueFactory(new PropertyValueFactory<>("status"));
        colDate.setCellValueFactory(new PropertyValueFactory<>("submittedDate"));

        // Colorer les statuts
        colStatus.setCellFactory(col -> new TableCell<Request, Request.Status>() { // Utilise l'Enum ici
            @Override
            protected void updateItem(Request.Status status, boolean empty) { // Reçoit l'Enum
                super.updateItem(status, empty);

                if (empty || status == null) {
                    setText(null);
                    setStyle("");
                } else {
                    // On transforme l'Enum en texte pour l'affichage
                    String statusText = status.name().toLowerCase();
                    setText(statusText);

                    // On applique le style selon la valeur de l'Enum
                    switch (status) {
                        case pending   -> setStyle("-fx-text-fill: #f39c12; -fx-font-weight: bold;");
                        case approved  -> setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;");
                        case rejected  -> setStyle("-fx-text-fill: #e74c3c; -fx-font-weight: bold;");
                        case cancelled -> setStyle("-fx-text-fill: #95a5a6; -fx-font-weight: bold;");
                        default        -> setStyle("");
                    }
                }
            }
        });
    }

    private void setupFilterCombo() {
        filterStatus.setItems(FXCollections.observableArrayList(
                "Tous", "pending", "approved", "rejected", "cancelled"
        ));
        filterStatus.setValue("Tous");
        filterStatus.setOnAction(e -> applyFilters());
    }

    private void setupSearchListener() {
        searchField.textProperty().addListener((obs, oldVal, newVal) -> applyFilters());
    }

    // ─── Chargement données ──────────────────────────────────────────

    private void loadData() {
        List<Request> list = requestService.getAll();
        requestList.setAll(list);
        tableRequests.setItems(requestList);
        updateStats(list);
    }

    private void applyFilters() {
        String statusFilter = filterStatus.getValue();
        String search       = searchField.getText().toLowerCase();

        List<Request> all = requestService.getAll();

        List<Request> filtered = all.stream()
                .filter(r -> statusFilter.equals("Tous") || r.getStatus().name().equals(statusFilter))
                .filter(r -> search.isEmpty()
                        || r.getTitle().toLowerCase().contains(search)
                        || (r.getRequestTypeName() != null && r.getRequestTypeName().toLowerCase().contains(search)))
                .toList();

        requestList.setAll(filtered);
        tableRequests.setItems(requestList);
        updateStats(filtered);
    }

    private void updateStats(List<Request> list) {
        long pending  = list.stream().filter(r -> r.getStatus() == Request.Status.pending).count();
        long approved = list.stream().filter(r -> r.getStatus() == Request.Status.approved).count();
        lblTotal.setText("Total : " + list.size());
        lblPending.setText("En attente : " + pending);
        lblApproved.setText("Approuvées : " + approved);
    }

    // ─── Actions boutons ─────────────────────────────────────────────

    @FXML
    private void handleAdd() {
        openFormDialog(null);
    }

    @FXML
    private void handleEdit() {
        Request selected = tableRequests.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Sélection", "Veuillez sélectionner une demande.");
            return;
        }
        if (selected.getStatus() != Request.Status.pending) {
            showAlert(Alert.AlertType.WARNING, "Modification impossible",
                    "Seules les demandes 'pending' peuvent être modifiées.");
            return;
        }
        openFormDialog(selected);
    }

    @FXML
    private void handleDelete() {
        Request selected = tableRequests.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Sélection", "Veuillez sélectionner une demande.");
            return;
        }
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Supprimer la demande : " + selected.getTitle() + " ?",
                ButtonType.YES, ButtonType.NO);
        confirm.setTitle("Confirmation");
        confirm.showAndWait().ifPresent(bt -> {
            if (bt == ButtonType.YES) {
                requestService.delete(selected.getId());
                loadData();
            }
        });
    }

    @FXML
    private void handleApprove() {
        updateSelectedStatus(Request.Status.approved);
    }

    @FXML
    private void handleReject() {
        updateSelectedStatus(Request.Status.rejected);
    }

    private void updateSelectedStatus(Request.Status newStatus) {
        Request selected = tableRequests.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Sélection", "Veuillez sélectionner une demande.");
            return;
        }
        if (selected.getStatus() != Request.Status.pending) {
            showAlert(Alert.AlertType.WARNING, "Action impossible",
                    "Seules les demandes 'pending' peuvent être traitées.");
            return;
        }
        // Demander un commentaire
        TextInputDialog dialog = new TextInputDialog();
        dialog.setTitle("Commentaire");
        dialog.setHeaderText("Commentaire (optionnel) :");
        dialog.showAndWait().ifPresent(comment -> {
            // reviewer_id = 1 (à remplacer par l'id de l'utilisateur connecté)
            requestService.updateStatus(selected.getId(), newStatus, 1, comment);
            loadData();
        });
    }

    // ─── Ouvrir le formulaire (Add / Edit) ───────────────────────────

    private void openFormDialog(Request requestToEdit) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/RequestFormView.fxml")
            );
            Parent root = loader.load();

            RequestFormController formCtrl = loader.getController();
            formCtrl.setRequest(requestToEdit);
            formCtrl.setOnSaved(this::loadData); // callback refresh

            Stage stage = new Stage();
            stage.setTitle(requestToEdit == null ? "Nouvelle Demande" : "Modifier Demande");
            stage.setScene(new Scene(root, 600, 500));
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.showAndWait();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    // ─── Utilitaire ──────────────────────────────────────────────────

    private void showAlert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type, message, ButtonType.OK);
        alert.setTitle(title);
        alert.showAndWait();
    }
}