package org.example.ui.controller.Rh.Congé;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import org.example.model.LeaveBalance;
import org.example.service.LeaveBalanceService;

/**
 * Contrôleur de la table des soldes de congés (côté RH).
 * Gère : chargement des soldes, attribution mensuelle.
 */
public class RHLeaveBalancesController {

    @FXML private TableView<LeaveBalance> balanceTable;
    @FXML private TableColumn<LeaveBalance, String>  balEmpColumn;
    @FXML private TableColumn<LeaveBalance, Double>  balAvailableColumn;
    @FXML private TableColumn<LeaveBalance, Double>  balAccruedColumn;
    @FXML private TableColumn<LeaveBalance, Double>  balUsedColumn;
    @FXML private TableColumn<LeaveBalance, String>  balLastAccrualCol;
    @FXML private Button accrueAllButton;

    private final LeaveBalanceService leaveBalanceService = new LeaveBalanceService();
    private final ObservableList<LeaveBalance> balanceList = FXCollections.observableArrayList();

    private int rhId;
    private Runnable onChangeCallback;

    @FXML
    public void initialize() {
        setupTable();
        if (accrueAllButton != null) {
            accrueAllButton.setOnAction(e -> {
                int months = leaveBalanceService.accrueForAllEmployees();
                refresh();
                showAlert(Alert.AlertType.INFORMATION, "✅ Soldes mis à jour",
                        "Attribution effectuée pour tous les employés.\n\n"
                        + months + " mois-employé attribué(s) (+1.8 j/mois).");
                if (onChangeCallback != null) onChangeCallback.run();
            });
        }
    }

    /**
     * @param rhId             ID du RH connecté (pour filtrer ses employés).
     * @param onChangeCallback appelé après attribution (pour rafraîchir les stats).
     */
    public void initData(int rhId, Runnable onChangeCallback) {
        this.rhId = rhId;
        this.onChangeCallback = onChangeCallback;
        refresh();
    }

    /** Recharge les soldes depuis la base. */
    public void refresh() {
        if (balanceTable == null) return;
        try {
            balanceList.clear();
            if (rhId > 0) {
                balanceList.addAll(leaveBalanceService.getBalancesByRH(rhId));
            } else {
                balanceList.addAll(leaveBalanceService.getAllBalances());
            }
            balanceTable.setItems(balanceList);
        } catch (Exception e) {
            System.err.println("Erreur chargement balances: " + e.getMessage());
        }
    }

    // ─── Configuration table ─────────────────────────────────────────────────────

    private void setupTable() {
        if (balEmpColumn != null) {
            balEmpColumn.setCellValueFactory(new javafx.scene.control.cell.PropertyValueFactory<>("employeeName"));
        }
        if (balAvailableColumn != null) {
            balAvailableColumn.setCellValueFactory(d ->
                    new javafx.beans.property.SimpleObjectProperty<>(d.getValue().getAvailableDays()));
            balAvailableColumn.setCellFactory(col -> new TableCell<>() {
                @Override protected void updateItem(Double v, boolean empty) {
                    super.updateItem(v, empty);
                    if (empty || v == null) { setText(null); setStyle(""); }
                    else {
                        setText(String.format("%.1f j", v));
                        setStyle(v < 0
                                ? "-fx-text-fill: #e53e3e; -fx-font-weight: bold; -fx-alignment: CENTER;"
                                : "-fx-text-fill: #11998e; -fx-font-weight: bold; -fx-alignment: CENTER;");
                    }
                }
            });
        }
        if (balAccruedColumn != null) {
            balAccruedColumn.setCellValueFactory(d ->
                    new javafx.beans.property.SimpleObjectProperty<>(d.getValue().getTotalAccrued()));
            balAccruedColumn.setCellFactory(col -> new TableCell<>() {
                @Override protected void updateItem(Double v, boolean empty) {
                    super.updateItem(v, empty);
                    setText(empty || v == null ? null : String.format("%.1f j", v));
                    setStyle("-fx-alignment: CENTER;");
                }
            });
        }
        if (balUsedColumn != null) {
            balUsedColumn.setCellValueFactory(d ->
                    new javafx.beans.property.SimpleObjectProperty<>(d.getValue().getTotalUsed()));
            balUsedColumn.setCellFactory(col -> new TableCell<>() {
                @Override protected void updateItem(Double v, boolean empty) {
                    super.updateItem(v, empty);
                    setText(empty || v == null ? null : String.format("%.1f j", v));
                    setStyle("-fx-alignment: CENTER;");
                }
            });
        }
        if (balLastAccrualCol != null) {
            balLastAccrualCol.setCellValueFactory(d -> {
                java.time.LocalDate date = d.getValue().getLastAccrualDate();
                return new javafx.beans.property.SimpleStringProperty(date != null ? date.toString() : "--");
            });
        }
        balanceTable.setItems(balanceList);
    }

    // ─── Utilitaires ─────────────────────────────────────────────────────────────

    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        try {
            String css = getClass().getResource("/css/style.css").toExternalForm();
            alert.getDialogPane().getStylesheets().add(css);
        } catch (Exception ignored) {}
        alert.showAndWait();
    }
}
