package org.example.ui.controller.Rh.Congé;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import org.example.model.LeaveRequest;
import org.example.model.LeaveRequest.LeaveStatus;
import org.example.model.User;

import java.io.IOException;

/**
 * Contrôleur orchestrateur pour la gestion des congés côté RH.
 * Délègue les demandes à {@link RHLeaveRequestsController}
 * et les soldes à {@link RHLeaveBalancesController}.
 */
public class RHLeaveController {

    // ─── Sous-contrôleurs (injectés via fx:include) ──────────────────────────────
    @FXML private VBox leaveRequests;
    @FXML private RHLeaveRequestsController leaveRequestsController;
    @FXML private VBox leaveBalances;
    @FXML private RHLeaveBalancesController leaveBalancesController;

    // ─── En-tête ─────────────────────────────────────────────────────────────────
    @FXML private Button statsButton;
    @FXML private Button refreshButton;

    // ─── Statistiques ────────────────────────────────────────────────────────────
    @FXML private Label totalRequestsLabel;
    @FXML private Label pendingRequestsLabel;
    @FXML private Label approvedRequestsLabel;
    @FXML private Label rejectedRequestsLabel;

    private int rhId;
    private User currentRHUser;

    @FXML
    public void initialize() {
        if (refreshButton != null) {
            refreshButton.setOnAction(e -> refreshAll());
        }
        if (statsButton != null) {
            statsButton.setOnAction(e -> handleShowStats());
        }
    }

    /**
     * Initialise le contrôleur avec l'ID du RH.
     * Appelé par le dashboard après le chargement du FXML.
     */
    public void initData(int rhId, User rhUser) {
        this.rhId = rhId;
        this.currentRHUser = rhUser;

        Runnable refreshStats = this::updateStatistics;

        leaveRequestsController.initData(rhId, rhUser, () -> {
            updateStatistics();
            leaveBalancesController.refresh();
        });
        leaveBalancesController.initData(rhId, refreshStats);

        updateStatistics();
    }

    // ─── Rafraîchissement global ─────────────────────────────────────────────────

    private void refreshAll() {
        leaveRequestsController.refresh();
        leaveBalancesController.refresh();
        updateStatistics();
    }

    // ─── Statistiques ────────────────────────────────────────────────────────────

    private void updateStatistics() {
        var list = leaveRequestsController.getAllRequests();
        int total    = list.size();
        long pending  = list.stream().filter(r -> r.getStatus() == LeaveStatus.ATTENTE).count();
        long approved = list.stream().filter(r -> r.getStatus() == LeaveStatus.ACCEPTE).count();
        long rejected = list.stream().filter(r -> r.getStatus() == LeaveStatus.REFUSE).count();

        if (totalRequestsLabel    != null) totalRequestsLabel.setText("Total: " + total);
        if (pendingRequestsLabel  != null) pendingRequestsLabel.setText("En attente: " + pending);
        if (approvedRequestsLabel != null) approvedRequestsLabel.setText("Approuvées: " + approved);
        if (rejectedRequestsLabel != null) rejectedRequestsLabel.setText("Refusées: " + rejected);
    }

    // ─── Navigation Statistiques Avancées ────────────────────────────────────────

    @FXML
    private void handleShowStats() {
        try {
            StackPane contentArea = (StackPane) refreshButton.getScene().lookup("#contentArea");
            if (contentArea == null) return;
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/views/Rh-dashboard/Congé/RHLeaveStatsView.fxml"));
            Parent view = loader.load();
            RHLeaveStatsController ctrl = loader.getController();
            ctrl.initData(rhId, currentRHUser);
            contentArea.getChildren().setAll(view);
        } catch (IOException e) {
            Alert alert = new Alert(Alert.AlertType.ERROR);
            alert.setTitle("Erreur Navigation");
            alert.setHeaderText(null);
            alert.setContentText("Impossible d'ouvrir la vue statistiques :\n" + e.getMessage());
            alert.showAndWait();
        }
    }
}
