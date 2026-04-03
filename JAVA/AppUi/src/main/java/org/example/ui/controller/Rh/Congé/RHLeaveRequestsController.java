package org.example.ui.controller.Rh.Congé;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import org.example.model.LeaveRequest;
import org.example.model.LeaveRequest.LeaveStatus;
import org.example.model.User;
import org.example.service.ConflictResult;
import org.example.service.LeaveRequestService;
import org.example.ui.controller.Rh.Congé.notification.AppNotification;
import org.example.ui.controller.Rh.Congé.notification.InAppNotificationService;

import java.time.LocalDate;
import java.util.List;

/**
 * Contrôleur de la liste des demandes de congé + actions RH.
 * Gère : filtres, table, approve/reject/delete, conflits, détails.
 */
public class RHLeaveRequestsController {

    @FXML private TableView<LeaveRequest> leaveRequestsTable;
    @FXML private TableColumn<LeaveRequest, Integer>   idColumn;
    @FXML private TableColumn<LeaveRequest, String>    employeeColumn;
    @FXML private TableColumn<LeaveRequest, LocalDate> startDateColumn;
    @FXML private TableColumn<LeaveRequest, LocalDate> endDateColumn;
    @FXML private TableColumn<LeaveRequest, String>    leaveTypeColumn;
    @FXML private TableColumn<LeaveRequest, Integer>   daysColumn;
    @FXML private TableColumn<LeaveRequest, String>    statusColumn;
    @FXML private TableColumn<LeaveRequest, LocalDate> requestDateColumn;

    @FXML private ComboBox<String> filterComboBox;
    @FXML private TextField searchField;
    @FXML private Button approveButton;
    @FXML private Button rejectButton;
    @FXML private Button deleteButton;
    @FXML private Button viewDetailsButton;
    @FXML private Button checkConflictsButton;
    @FXML private Label  tableCountLabel;
    @FXML private Label  conflictLabel;
    @FXML private TextArea detailsTextArea;

    private final LeaveRequestService leaveRequestService = new LeaveRequestService();
    private final ObservableList<LeaveRequest> allRequests = FXCollections.observableArrayList();
    private final ObservableList<LeaveRequest> filteredList = FXCollections.observableArrayList();

    private int rhId;
    private User currentRHUser;
    private Runnable onChangeCallback;

    @FXML
    public void initialize() {
        setupTable();
        setupFilters();
        setupButtons();
    }

    /**
     * Initialise avec les données RH.
     *
     * @param onChangeCallback appelé après approve/reject/delete (pour rafraîchir stats + balances).
     */
    public void initData(int rhId, User rhUser, Runnable onChangeCallback) {
        this.rhId = rhId;
        this.currentRHUser = rhUser;
        this.onChangeCallback = onChangeCallback;
        refresh();
    }

    /** Recharge les demandes des employés rattachés à ce RH. */
    public void refresh() {
        allRequests.clear();
        if (rhId > 0) {
            allRequests.addAll(leaveRequestService.getLeaveRequestsByRH(rhId));
        } else {
            // fallback si rhId non encore initialisé
            allRequests.addAll(leaveRequestService.getAllLeaveRequests());
        }
        applyFilter();
    }

    /** Retourne la liste complète (pour les stats du parent). */
    public ObservableList<LeaveRequest> getAllRequests() { return allRequests; }

    // ─── Table ───────────────────────────────────────────────────────────────────

    private void setupTable() {
        idColumn.setCellValueFactory(new PropertyValueFactory<>("id"));
        employeeColumn.setCellValueFactory(new PropertyValueFactory<>("employeeName"));
        startDateColumn.setCellValueFactory(new PropertyValueFactory<>("startDate"));
        endDateColumn.setCellValueFactory(new PropertyValueFactory<>("endDate"));
        leaveTypeColumn.setCellValueFactory(new PropertyValueFactory<>("leaveType"));
        daysColumn.setCellValueFactory(new PropertyValueFactory<>("daysCount"));
        requestDateColumn.setCellValueFactory(new PropertyValueFactory<>("requestDate"));

        statusColumn.setCellValueFactory(cd ->
                new javafx.beans.property.SimpleStringProperty(cd.getValue().getStatus().getDisplayName()));

        statusColumn.setCellFactory(column -> new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setText(null); setStyle(""); return; }
                setText(item);
                switch (item) {
                    case "Accepté" -> setStyle("-fx-background-color: #d4edda; -fx-text-fill: #155724; -fx-font-weight: bold;");
                    case "Refusé"  -> setStyle("-fx-background-color: #f8d7da; -fx-text-fill: #721c24; -fx-font-weight: bold;");
                    default        -> setStyle("-fx-background-color: #fff3cd; -fx-text-fill: #856404; -fx-font-weight: bold;");
                }
            }
        });

        leaveRequestsTable.setItems(filteredList);
        leaveRequestsTable.getSelectionModel().selectedItemProperty().addListener((obs, o, n) -> {
            if (n != null) { showRequestDetails(n); updateButtonStates(n); }
        });
    }

    // ─── Filtres ─────────────────────────────────────────────────────────────────

    private void setupFilters() {
        filterComboBox.setItems(FXCollections.observableArrayList(
                "Toutes les demandes", "En attente", "Acceptées", "Refusées"));
        filterComboBox.getSelectionModel().selectFirst();
        filterComboBox.setOnAction(e -> applyFilter());
        searchField.textProperty().addListener((obs, o, n) -> applyFilter());
    }

    private void applyFilter() {
        filteredList.clear();
        String filter = filterComboBox.getValue();
        String search = searchField.getText().toLowerCase().trim();

        List<LeaveRequest> result = allRequests.stream().filter(r -> {
            boolean statusMatch = switch (filter) {
                case "En attente" -> r.getStatus() == LeaveStatus.ATTENTE;
                case "Acceptées"  -> r.getStatus() == LeaveStatus.ACCEPTE;
                case "Refusées"   -> r.getStatus() == LeaveStatus.REFUSE;
                default -> true;
            };
            boolean searchMatch = search.isEmpty()
                    || r.getEmployeeName().toLowerCase().contains(search)
                    || r.getLeaveType().toLowerCase().contains(search);
            return statusMatch && searchMatch;
        }).toList();

        filteredList.addAll(result);
        if (tableCountLabel != null) tableCountLabel.setText(result.size() + " demande(s)");
    }

    // ─── Boutons ─────────────────────────────────────────────────────────────────

    private void setupButtons() {
        approveButton.setDisable(true);
        rejectButton.setDisable(true);
        if (deleteButton != null) deleteButton.setDisable(true);
        viewDetailsButton.setDisable(true);

        approveButton.setOnAction(e -> approveSelectedRequest());
        rejectButton.setOnAction(e -> rejectSelectedRequest());
        if (deleteButton != null) deleteButton.setOnAction(e -> deleteSelectedRequest());
        viewDetailsButton.setOnAction(e -> showDetailedView());

        if (checkConflictsButton != null) {
            checkConflictsButton.setDisable(true);
            checkConflictsButton.setOnAction(e -> checkConflictsForSelectedRequest());
        }
        if (conflictLabel != null) { conflictLabel.setVisible(false); conflictLabel.setManaged(false); }
    }

    private void updateButtonStates(LeaveRequest request) {
        boolean isPending   = request.getStatus() == LeaveStatus.ATTENTE;
        boolean isDeletable = isPending || request.getStatus() == LeaveStatus.ACCEPTE;
        approveButton.setDisable(!isPending);
        rejectButton.setDisable(!isPending);
        if (deleteButton != null) deleteButton.setDisable(!isDeletable);
        if (checkConflictsButton != null) checkConflictsButton.setDisable(false);
        viewDetailsButton.setDisable(false);
        autoCheckConflicts(request);
    }

    // ─── Conflits ────────────────────────────────────────────────────────────────

    private void autoCheckConflicts(LeaveRequest request) {
        if (conflictLabel == null) return;
        try {
            applyConflictLabel(leaveRequestService.detectConflicts(request));
        } catch (Exception ex) {
            conflictLabel.setVisible(false); conflictLabel.setManaged(false);
        }
    }

    private void checkConflictsForSelectedRequest() {
        LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
        if (sel == null) return;
        ConflictResult result = leaveRequestService.detectConflicts(sel);
        Alert.AlertType type = switch (result.getLevel()) {
            case CRITICAL -> Alert.AlertType.ERROR;
            case WARNING  -> Alert.AlertType.WARNING;
            case OK       -> Alert.AlertType.INFORMATION;
        };
        Alert alert = new Alert(type);
        alert.setTitle("Détection de Conflits – Demande #" + sel.getId());
        alert.setHeaderText("Analyse de conflit pour " + sel.getEmployeeName()
                + "  " + sel.getStartDate() + " → " + sel.getEndDate());
        alert.setContentText(result.getDetailedConflictSummary());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(560);
        applyDialogStyles(alert.getDialogPane());
        alert.showAndWait();
    }

    private void applyConflictLabel(ConflictResult result) {
        if (conflictLabel == null) return;
        switch (result.getLevel()) {
            case CRITICAL -> {
                conflictLabel.setText("🔴  CONFLIT CRITIQUE : " + result.getConcurrentAbsences()
                        + " absence(s) simultanée(s) détectée(s) sur cette période !");
                conflictLabel.setStyle("-fx-background-color: #fde8e8; -fx-text-fill: #c53030; "
                        + "-fx-font-weight: bold; -fx-padding: 8 14; -fx-background-radius: 7; "
                        + "-fx-border-color: #e53e3e; -fx-border-width: 1; -fx-border-radius: 7;");
            }
            case WARNING -> {
                conflictLabel.setText("⚠️  AVERTISSEMENT : " + result.getConcurrentAbsences()
                        + " autre(s) employé(s) absent(s) sur cette période.");
                conflictLabel.setStyle("-fx-background-color: #fffbeb; -fx-text-fill: #b7791f; "
                        + "-fx-font-weight: bold; -fx-padding: 8 14; -fx-background-radius: 7; "
                        + "-fx-border-color: #f6ad55; -fx-border-width: 1; -fx-border-radius: 7;");
            }
            case OK -> {
                conflictLabel.setText("✅  Aucun conflit détecté sur cette période.");
                conflictLabel.setStyle("-fx-background-color: #f0fff4; -fx-text-fill: #276749; "
                        + "-fx-font-weight: bold; -fx-padding: 8 14; -fx-background-radius: 7; "
                        + "-fx-border-color: #68d391; -fx-border-width: 1; -fx-border-radius: 7;");
            }
        }
        conflictLabel.setVisible(true);
        conflictLabel.setManaged(true);
    }

    // ─── Détails ─────────────────────────────────────────────────────────────────

    private void showRequestDetails(LeaveRequest r) {
        StringBuilder sb = new StringBuilder();
        sb.append("=== DEMANDE #").append(r.getId()).append(" ===\n\n");
        sb.append("Employé: ").append(r.getEmployeeName()).append("\n");
        sb.append("Type de congé: ").append(r.getLeaveType()).append("\n");
        sb.append("Période: ").append(r.getStartDate()).append(" au ").append(r.getEndDate()).append("\n");
        sb.append("Nombre de jours: ").append(r.getDaysCount()).append("\n");
        sb.append("Date de demande: ").append(r.getRequestDate()).append("\n");
        sb.append("Statut: ").append(r.getStatus().getDisplayName()).append("\n\n");
        if (r.getReason() != null && !r.getReason().isEmpty())
            sb.append("Raison:\n").append(r.getReason()).append("\n\n");
        if (r.getRhComment() != null && !r.getRhComment().isEmpty())
            sb.append("Commentaire RH:\n").append(r.getRhComment()).append("\n");
        detailsTextArea.setText(sb.toString());
    }

    private void showDetailedView() {
        LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
        if (sel == null) return;
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails complets");
        alert.setHeaderText("Demande #" + sel.getId());
        alert.setContentText(detailsTextArea.getText());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(500);
        applyDialogStyles(alert.getDialogPane());
        alert.showAndWait();
    }

    // ─── Approuver / Refuser / Supprimer ─────────────────────────────────────────

    private void approveSelectedRequest() {
        LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
        if (sel == null) { showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande."); return; }

        // Conflit pré-approbation
        ConflictResult conflict = leaveRequestService.detectConflicts(sel);
        if (!conflict.isOk()) {
            Alert ca = new Alert(conflict.isCritical() ? Alert.AlertType.WARNING : Alert.AlertType.CONFIRMATION);
            ca.setTitle("Conflit détecté avant approbation");
            ca.setHeaderText("Des conflits existent pour cette période !");
            ca.setContentText(conflict.getDetailedConflictSummary() + "\n\nVoulez-vous tout de même approuver ?");
            ca.setResizable(true); ca.getDialogPane().setPrefWidth(560);
            applyDialogStyles(ca.getDialogPane());
            var res = ca.showAndWait();
            if (res.isEmpty() || res.get() != ButtonType.OK) return;
        }

        TextInputDialog dlg = new TextInputDialog();
        dlg.setTitle("Approuver la demande");
        dlg.setHeaderText("Approuver la demande de " + sel.getEmployeeName());
        dlg.setContentText("Commentaire (optionnel):");
        applyDialogStyles(dlg.getDialogPane());

        dlg.showAndWait().ifPresent(comment -> {
            if (leaveRequestService.approveLeaveRequest(sel.getId(), comment)) {
                InAppNotificationService.getInstance().notifyEmployee(
                        sel.getEmployeeId(),
                        "✅ Votre demande de congé (" + sel.getLeaveType() + ") du " + sel.getStartDate()
                                + " au " + sel.getEndDate() + " a été APPROUVÉE"
                                + (comment != null && !comment.isBlank() ? " — " + comment : ""),
                        AppNotification.Type.LEAVE_APPROVED);
                showAlert(Alert.AlertType.INFORMATION, "Succès", "La demande a été approuvée avec succès!");
                refresh();
                if (onChangeCallback != null) onChangeCallback.run();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'approuver la demande.");
            }
        });
    }

    private void rejectSelectedRequest() {
        LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
        if (sel == null) { showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande."); return; }

        TextInputDialog dlg = new TextInputDialog();
        dlg.setTitle("Refuser la demande");
        dlg.setHeaderText("Refuser la demande de " + sel.getEmployeeName());
        dlg.setContentText("Raison du refus:");
        applyDialogStyles(dlg.getDialogPane());

        dlg.showAndWait().ifPresent(reason -> {
            if (reason.trim().isEmpty()) {
                showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez indiquer une raison pour le refus.");
                return;
            }
            if (leaveRequestService.rejectLeaveRequest(sel.getId(), reason)) {
                InAppNotificationService.getInstance().notifyEmployee(
                        sel.getEmployeeId(),
                        "❌ Votre demande de congé (" + sel.getLeaveType() + ") du " + sel.getStartDate()
                                + " au " + sel.getEndDate() + " a été REFUSÉE — Raison : " + reason,
                        AppNotification.Type.LEAVE_REJECTED);
                showAlert(Alert.AlertType.INFORMATION, "Succès", "La demande a été refusée.");
                refresh();
                if (onChangeCallback != null) onChangeCallback.run();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de refuser la demande.");
            }
        });
    }

    private void deleteSelectedRequest() {
        LeaveRequest sel = leaveRequestsTable.getSelectionModel().getSelectedItem();
        if (sel == null) { showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande."); return; }

        String msg = sel.getStatus() == LeaveStatus.ACCEPTE
                ? "Cette demande est ACCEPTÉE.\nLes " + sel.getDaysCount() + " jours seront remboursés au solde de "
                  + sel.getEmployeeName() + ".\n\nConfirmer la suppression ?"
                : "Supprimer la demande #" + sel.getId() + " de " + sel.getEmployeeName() + " ?";

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmer la suppression");
        confirm.setHeaderText("Suppression de la demande");
        confirm.setContentText(msg);
        applyDialogStyles(confirm.getDialogPane());

        confirm.showAndWait().ifPresent(resp -> {
            if (resp == ButtonType.OK) {
                if (leaveRequestService.rhDeleteLeaveRequest(sel.getId())) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès",
                            "La demande a été supprimée" +
                            (sel.getStatus() == LeaveStatus.ACCEPTE ? " et le solde a été restauré." : "."));
                    refresh();
                    if (onChangeCallback != null) onChangeCallback.run();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de supprimer la demande.");
                }
            }
        });
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
