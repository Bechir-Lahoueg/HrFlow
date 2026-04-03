package org.example.ui.controller.Employee.Congé;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import org.example.model.LeaveRequest;
import org.example.service.LeaveRequestService;

import java.time.LocalDate;

/**
 * Contrôleur de l'historique des demandes de congé (côté Employé).
 * Gère : table, menu contextuel, suppression, détails.
 */
public class EmployeeLeaveHistoryController {

    @FXML private TableView<LeaveRequest> leaveRequestsTable;
    @FXML private TableColumn<LeaveRequest, Integer>   idColumn;
    @FXML private TableColumn<LeaveRequest, LocalDate> startDateColumn;
    @FXML private TableColumn<LeaveRequest, LocalDate> endDateColumn;
    @FXML private TableColumn<LeaveRequest, String>    leaveTypeColumn;
    @FXML private TableColumn<LeaveRequest, Integer>   daysColumn;
    @FXML private TableColumn<LeaveRequest, String>    statusColumn;
    @FXML private TableColumn<LeaveRequest, LocalDate> requestDateColumn;

    @FXML private Button viewDetailsButton;
    @FXML private Button deleteButton;
    @FXML private Label  tableCountLabel;

    private final LeaveRequestService leaveRequestService = new LeaveRequestService();
    private final ObservableList<LeaveRequest> leaveRequestsList = FXCollections.observableArrayList();

    private int currentEmployeeId;
    private Runnable onChangeCallback;

    @FXML
    public void initialize() {
        setupTable();
        setupContextMenu();
        setupActionButtons();
    }

    /**
     * Initialise le contrôleur avec l'ID de l'employé.
     *
     * @param onChangeCallback appelé après suppression (rafraîchir stats/solde).
     */
    public void initData(int employeeId, Runnable onChangeCallback) {
        this.currentEmployeeId = employeeId;
        this.onChangeCallback  = onChangeCallback;
        refresh();
    }

    /** Recharge l'historique depuis la base de données. */
    public void refresh() {
        leaveRequestsList.clear();
        leaveRequestsList.addAll(leaveRequestService.getEmployeeLeaveRequests(currentEmployeeId));
        if (tableCountLabel != null) tableCountLabel.setText(leaveRequestsList.size() + " demande(s)");
    }

    /** Retourne la liste observable (utilisée par le parent pour les stats). */
    public ObservableList<LeaveRequest> getLeaveRequestsList() {
        return leaveRequestsList;
    }

    // ─── Table ───────────────────────────────────────────────────────────────────

    private void setupTable() {
        idColumn.setCellValueFactory(new PropertyValueFactory<>("id"));
        startDateColumn.setCellValueFactory(new PropertyValueFactory<>("startDate"));
        endDateColumn.setCellValueFactory(new PropertyValueFactory<>("endDate"));
        leaveTypeColumn.setCellValueFactory(new PropertyValueFactory<>("leaveType"));
        daysColumn.setCellValueFactory(new PropertyValueFactory<>("daysCount"));
        requestDateColumn.setCellValueFactory(new PropertyValueFactory<>("requestDate"));

        statusColumn.setCellValueFactory(cellData ->
                new javafx.beans.property.SimpleStringProperty(
                        cellData.getValue().getStatus().getDisplayName()));

        statusColumn.setCellFactory(column -> new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setText(null); setStyle(""); return; }
                setText(item);
                switch (item) {
                    case "Accepté" -> setStyle("-fx-background-color: #d4edda; -fx-text-fill: #155724;");
                    case "Refusé"  -> setStyle("-fx-background-color: #f8d7da; -fx-text-fill: #721c24;");
                    default        -> setStyle("-fx-background-color: #fff3cd; -fx-text-fill: #856404;");
                }
            }
        });

        leaveRequestsTable.setItems(leaveRequestsList);

        leaveRequestsTable.getSelectionModel().selectedItemProperty().addListener(
                (obs, o, n) -> updateActionButtonsState(n));
    }

    // ─── Menu contextuel ─────────────────────────────────────────────────────────

    private void setupContextMenu() {
        ContextMenu contextMenu = new ContextMenu();
        MenuItem viewItem   = new MenuItem("Voir détails");
        MenuItem deleteItem = new MenuItem("Supprimer");

        viewItem.setOnAction(e -> {
            LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
            if (sel != null) showLeaveRequestDetails(sel);
        });
        deleteItem.setOnAction(e -> {
            LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
            if (sel != null) deleteLeaveRequest(sel);
        });

        contextMenu.getItems().addAll(viewItem, deleteItem);
        leaveRequestsTable.setContextMenu(contextMenu);
    }

    // ─── Boutons d'action ────────────────────────────────────────────────────────

    private void setupActionButtons() {
        viewDetailsButton.setDisable(true);
        deleteButton.setDisable(true);

        viewDetailsButton.setOnAction(e -> {
            LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
            if (sel != null) showLeaveRequestDetails(sel);
        });
        deleteButton.setOnAction(e -> {
            LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
            if (sel != null) deleteLeaveRequest(sel);
        });
    }

    private void updateActionButtonsState(LeaveRequest selected) {
        boolean has = selected != null;
        viewDetailsButton.setDisable(!has);
        deleteButton.setDisable(!has || selected.getStatus() != LeaveRequest.LeaveStatus.ATTENTE);
    }

    // ─── Suppression ─────────────────────────────────────────────────────────────

    private void deleteLeaveRequest(LeaveRequest request) {
        if (request.getStatus() != LeaveRequest.LeaveStatus.ATTENTE) {
            showAlert(Alert.AlertType.WARNING, "Attention",
                    "Vous ne pouvez supprimer que les demandes en attente.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer la demande de congé?");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer cette demande?");
        applyDialogStyles(confirm.getDialogPane());

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                boolean ok = leaveRequestService.deleteLeaveRequest(request.getId(), currentEmployeeId);
                if (ok) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "La demande a été supprimée.");
                    refresh();
                    if (onChangeCallback != null) onChangeCallback.run();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de supprimer la demande.");
                }
            }
        });
    }

    // ─── Détails ─────────────────────────────────────────────────────────────────

    private void showLeaveRequestDetails(LeaveRequest request) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails de la demande");
        alert.setHeaderText("Demande #" + request.getId());
        alert.setContentText(String.format(
                "Type: %s\nPériode: %s au %s\nNombre de jours: %d\nDate de demande: %s\nStatut: %s\nRaison: %s\n%s",
                request.getLeaveType(),
                request.getStartDate(), request.getEndDate(),
                request.getDaysCount(), request.getRequestDate(),
                request.getStatus().getDisplayName(),
                request.getReason() != null ? request.getReason() : "Aucune",
                request.getRhComment() != null ? "\nCommentaire RH: " + request.getRhComment() : ""));
        alert.setResizable(true);
        applyDialogStyles(alert.getDialogPane());
        alert.showAndWait();
    }

    // ─── Utilitaires ─────────────────────────────────────────────────────────────

    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        applyDialogStyles(alert.getDialogPane());
        alert.showAndWait();
    }

    private void applyDialogStyles(DialogPane dialogPane) {
        try {
            String css = getClass().getResource("/css/style.css").toExternalForm();
            dialogPane.getStylesheets().add(css);
        } catch (Exception ignored) {}
    }
}
