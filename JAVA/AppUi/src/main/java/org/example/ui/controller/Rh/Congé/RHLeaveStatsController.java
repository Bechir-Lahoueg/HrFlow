package org.example.ui.controller.Rh.Congé;

import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.chart.*;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.StackPane;
import javafx.scene.paint.Color;
import javafx.stage.FileChooser;
import org.example.model.LeaveRequest;
import org.example.model.User;
import org.example.service.LeaveExportService;
import org.example.service.LeaveRequestService;
import org.example.service.LeaveStatisticsService;

import java.io.File;
import java.io.IOException;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Map;

/**
 * Contrôleur de la vue Statistiques Avancées &amp; Export des congés.
 *
 * <ul>
 *   <li>Cartes KPI globales</li>
 *   <li>PieChart (répartition par type) + BarChart (soumissions mensuelles) + LineChart (jours approuvés)</li>
 *   <li>TableView classement employés</li>
 *   <li>Export Excel et PDF via {@link LeaveExportService}</li>
 * </ul>
 */
public class RHLeaveStatsController {

    // ─── KPI Labels ──────────────────────────────────────────────────────────────
    @FXML private Label kpiTotalLabel;
    @FXML private Label kpiRateLabel;
    @FXML private Label kpiDaysLabel;
    @FXML private Label kpiEmployeesLabel;

    // ─── Charts ──────────────────────────────────────────────────────────────────
    @FXML private PieChart                         typesPieChart;
    @FXML private BarChart<String, Number>         monthlyBarChart;
    @FXML private LineChart<String, Number>        approvedDaysLineChart;

    // ─── Employee table ──────────────────────────────────────────────────────────
    @FXML private TableView<EmployeeStatsRow>                employeeStatsTable;
    @FXML private TableColumn<EmployeeStatsRow, Integer>     empRankCol;
    @FXML private TableColumn<EmployeeStatsRow, String>      empNameCol;
    @FXML private TableColumn<EmployeeStatsRow, Long>        empTotalCol;
    @FXML private TableColumn<EmployeeStatsRow, Long>        empApprovedCol;
    @FXML private TableColumn<EmployeeStatsRow, Long>        empRejectedCol;
    @FXML private TableColumn<EmployeeStatsRow, Long>        empDaysCol;
    @FXML private TableColumn<EmployeeStatsRow, String>      empRateCol;
    @FXML private Label empTableCountLabel;

    // ─── Export ──────────────────────────────────────────────────────────────────
    @FXML private Button exportExcelButton;
    @FXML private Button exportPdfButton;
    @FXML private Label  exportStatusLabel;

    // ─── Navigation ──────────────────────────────────────────────────────────────
    @FXML private Button backButton;

    // ─── Services ────────────────────────────────────────────────────────────────
    private final LeaveStatisticsService statsService = new LeaveStatisticsService();
    private final LeaveRequestService    requestService = new LeaveRequestService();
    private final LeaveExportService     exportService = new LeaveExportService();

    // ─── State ───────────────────────────────────────────────────────────────────
    private int  rhId;
    private User currentRHUser;
    private List<LeaveRequest> allRequests;

    // ═════════════════════════════════════════════════════════════════════════════

    @FXML
    public void initialize() {
        setupEmployeeTable();
    }

    /**
     * Point d'entrée — appelé par {@code RHLeaveController} ou {@code RHDashboardController}.
     */
    public void initData(int rhId, User rhUser) {
        this.rhId = rhId;
        this.currentRHUser = rhUser;

        // Charger en arrière-plan pour ne pas bloquer l'UI
        Thread loader = new Thread(() -> {
            try {
                // Toutes les données son filtrées par rhId → seuls les employés de ce RH
                LeaveStatisticsService.GlobalStats stats = statsService.getGlobalStatsByRH(rhId);
                allRequests = requestService.getLeaveRequestsByRH(rhId);
                List<LeaveStatisticsService.EmployeeStat> empStats = statsService.allEmployeeStatsByRH(rhId);
                Map<String, Long>   countByType   = statsService.countByTypeByRH(rhId);
                Map<String, Long>   submMonthly   = statsService.submissionsPerMonthByRH(rhId);
                Map<String, Long>   apprMonthly   = statsService.approvedDaysPerMonthByRH(rhId);

                Platform.runLater(() -> {
                    populateKpis(stats);
                    populatePieChart(countByType);
                    populateBarChart(submMonthly);
                    populateLineChart(apprMonthly);
                    populateEmployeeTable(empStats);
                });
            } catch (Exception e) {
                Platform.runLater(() ->
                    showStatus("❌ Erreur au chargement des statistiques : " + e.getMessage(), false));
            }
        }, "stats-loader");
        loader.setDaemon(true);
        loader.start();
    }

    // ─── KPIs ────────────────────────────────────────────────────────────────────

    private void populateKpis(LeaveStatisticsService.GlobalStats g) {
        kpiTotalLabel.setText(String.valueOf(g.total()));
        kpiRateLabel.setText(String.format("%.0f%%", g.approvalRatePct()));
        kpiDaysLabel.setText(String.valueOf(g.totalApprovedDays()));
        kpiEmployeesLabel.setText(String.valueOf(g.uniqueEmployees()));
    }

    // ─── Charts ──────────────────────────────────────────────────────────────────

    private void populatePieChart(Map<String, Long> countByType) {
        ObservableList<PieChart.Data> data = FXCollections.observableArrayList();
        countByType.forEach((type, count) ->
            data.add(new PieChart.Data(type + " (" + count + ")", count)));
        typesPieChart.setData(data);
        typesPieChart.setTitle("");
    }

    private void populateBarChart(Map<String, Long> submissionsPerMonth) {
        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Demandes soumises");
        submissionsPerMonth.forEach((month, count) ->
            series.getData().add(new XYChart.Data<>(month, count)));
        monthlyBarChart.getData().clear();
        monthlyBarChart.getData().add(series);
    }

    private void populateLineChart(Map<String, Long> approvedDaysPerMonth) {
        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Jours approuvés");
        approvedDaysPerMonth.forEach((month, days) ->
            series.getData().add(new XYChart.Data<>(month, days)));
        approvedDaysLineChart.getData().clear();
        approvedDaysLineChart.getData().add(series);
    }

    // ─── Employee table ──────────────────────────────────────────────────────────

    private void setupEmployeeTable() {
        empRankCol.setCellValueFactory(new PropertyValueFactory<>("rank"));
        empNameCol.setCellValueFactory(new PropertyValueFactory<>("name"));
        empTotalCol.setCellValueFactory(new PropertyValueFactory<>("totalRequests"));
        empApprovedCol.setCellValueFactory(new PropertyValueFactory<>("approvedCount"));
        empRejectedCol.setCellValueFactory(new PropertyValueFactory<>("rejectedCount"));
        empDaysCol.setCellValueFactory(new PropertyValueFactory<>("approvedDays"));
        empRateCol.setCellValueFactory(new PropertyValueFactory<>("approvalRate"));

        // Colonne rang avec médailles
        empRankCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(Integer rank, boolean empty) {
                super.updateItem(rank, empty);
                if (empty || rank == null) { setText(null); setStyle(""); return; }
                switch (rank) {
                    case 1 -> { setText("🥇 1"); setStyle("-fx-font-weight: bold; -fx-text-fill: #b45309;"); }
                    case 2 -> { setText("🥈 2"); setStyle("-fx-font-weight: bold; -fx-text-fill: #6b7280;"); }
                    case 3 -> { setText("🥉 3"); setStyle("-fx-font-weight: bold; -fx-text-fill: #92400e;"); }
                    default -> { setText(String.valueOf(rank)); setStyle(""); }
                }
            }
        });

        // Colonne jours avec couleur selon valeur
        empDaysCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(Long days, boolean empty) {
                super.updateItem(days, empty);
                if (empty || days == null) { setText(null); setStyle(""); return; }
                setText(days + " j");
                if (days >= 20) setStyle("-fx-text-fill: #dc2626; -fx-font-weight: bold;");
                else if (days >= 10) setStyle("-fx-text-fill: #d97706; -fx-font-weight: bold;");
                else setStyle("-fx-text-fill: #059669; -fx-font-weight: bold;");
            }
        });
    }

    private void populateEmployeeTable(List<LeaveStatisticsService.EmployeeStat> empStats) {
        ObservableList<EmployeeStatsRow> rows = FXCollections.observableArrayList();
        int rank = 0;
        for (LeaveStatisticsService.EmployeeStat e : empStats) {
            rank++;
            long decided = e.approvedCount() + e.rejectedCount();
            String rate = decided == 0 ? "—" :
                String.format("%.0f%%", (double) e.approvedCount() / decided * 100);
            rows.add(new EmployeeStatsRow(rank, e.name(),
                e.totalRequests(), e.approvedCount(), e.rejectedCount(), e.approvedDays(), rate));
        }
        employeeStatsTable.setItems(rows);
        empTableCountLabel.setText(rows.size() + " employé(s)");
    }

    // ─── Export Excel ─────────────────────────────────────────────────────────────

    @FXML
    private void handleExportExcel() {
        FileChooser fc = new FileChooser();
        fc.setTitle("Enregistrer le rapport Excel");
        fc.setInitialFileName("rapport_conges_" +
            LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyyMMdd_HHmm")) + ".xlsx");
        fc.getExtensionFilters().add(
            new FileChooser.ExtensionFilter("Excel (.xlsx)", "*.xlsx"));

        File file = fc.showSaveDialog(exportExcelButton.getScene().getWindow());
        if (file == null) return;

        setExportButtonsDisabled(true);
        showStatus("⏳ Génération du fichier Excel en cours...", true);

        final List<LeaveRequest> requests = allRequests != null ? allRequests : List.of();
        Thread t = new Thread(() -> {
            try {
                exportService.exportToExcel(requests, statsService, file);
                Platform.runLater(() -> {
                    showStatus("✅ Excel exporté avec succès : " + file.getName(), true);
                    setExportButtonsDisabled(false);
                });
            } catch (Exception e) {
                Platform.runLater(() -> {
                    showStatus("❌ Erreur export Excel : " + e.getMessage(), false);
                    setExportButtonsDisabled(false);
                });
            }
        }, "export-excel");
        t.setDaemon(true);
        t.start();
    }

    // ─── Export PDF ──────────────────────────────────────────────────────────────

    @FXML
    private void handleExportPdf() {
        FileChooser fc = new FileChooser();
        fc.setTitle("Enregistrer le rapport PDF");
        fc.setInitialFileName("rapport_conges_" +
            LocalDateTime.now().format(DateTimeFormatter.ofPattern("yyyyMMdd_HHmm")) + ".pdf");
        fc.getExtensionFilters().add(
            new FileChooser.ExtensionFilter("PDF (.pdf)", "*.pdf"));

        File file = fc.showSaveDialog(exportPdfButton.getScene().getWindow());
        if (file == null) return;

        setExportButtonsDisabled(true);
        showStatus("⏳ Génération du PDF en cours...", true);

        final List<LeaveRequest> requests = allRequests != null ? allRequests : List.of();
        Thread t = new Thread(() -> {
            try {
                exportService.exportToPdf(requests, statsService, file);
                Platform.runLater(() -> {
                    showStatus("✅ PDF exporté avec succès : " + file.getName(), true);
                    setExportButtonsDisabled(false);
                });
            } catch (Exception e) {
                Platform.runLater(() -> {
                    showStatus("❌ Erreur export PDF : " + e.getMessage(), false);
                    setExportButtonsDisabled(false);
                });
            }
        }, "export-pdf");
        t.setDaemon(true);
        t.start();
    }

    // ─── Navigation : retour ─────────────────────────────────────────────────────

    @FXML
    private void handleBack() {
        try {
            StackPane contentArea = (StackPane) backButton.getScene().lookup("#contentArea");
            if (contentArea == null) return;
            FXMLLoader loader = new FXMLLoader(
                getClass().getResource("/fxml/views/Rh-dashboard/Congé/RHLeaveContentView.fxml"));
            Parent view = loader.load();
            RHLeaveController ctrl = loader.getController();
            ctrl.initData(rhId, currentRHUser);
            contentArea.getChildren().setAll(view);
        } catch (IOException e) {
            showStatus("❌ Impossible de revenir en arrière : " + e.getMessage(), false);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private void showStatus(String message, boolean success) {
        exportStatusLabel.setText(message);
        exportStatusLabel.setStyle(
            "-fx-font-size: 13px; -fx-font-weight: bold; -fx-padding: 10 16; -fx-background-radius: 8;" +
            (success
                ? "-fx-background-color: #d1fae5; -fx-text-fill: #065f46;"
                : "-fx-background-color: #fee2e2; -fx-text-fill: #991b1b;"));
        exportStatusLabel.setVisible(true);
        exportStatusLabel.setManaged(true);
    }

    private void setExportButtonsDisabled(boolean disabled) {
        exportExcelButton.setDisable(disabled);
        exportPdfButton.setDisable(disabled);
    }

    // ═════════════════════════════════════════════════════════════════════════════
    //  Inner view-model bean
    // ═════════════════════════════════════════════════════════════════════════════

    public static class EmployeeStatsRow {
        private final int    rank;
        private final String name;
        private final long   totalRequests;
        private final long   approvedCount;
        private final long   rejectedCount;
        private final long   approvedDays;
        private final String approvalRate;

        public EmployeeStatsRow(int rank, String name,
                                long totalRequests, long approvedCount,
                                long rejectedCount, long approvedDays, String approvalRate) {
            this.rank          = rank;
            this.name          = name;
            this.totalRequests = totalRequests;
            this.approvedCount = approvedCount;
            this.rejectedCount = rejectedCount;
            this.approvedDays  = approvedDays;
            this.approvalRate  = approvalRate;
        }

        public int    getRank()          { return rank; }
        public String getName()          { return name; }
        public long   getTotalRequests() { return totalRequests; }
        public long   getApprovedCount() { return approvedCount; }
        public long   getRejectedCount() { return rejectedCount; }
        public long   getApprovedDays()  { return approvedDays; }
        public String getApprovalRate()  { return approvalRate; }
    }
}
