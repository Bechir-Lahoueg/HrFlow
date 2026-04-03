package org.example.ui.controller.Employee.Recrutement;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.scene.chart.*;
import javafx.collections.ObservableMap;
import javafx.beans.property.SimpleStringProperty;
import javafx.application.Platform;
import javafx.scene.chart.XYChart;
import javafx.collections.FXCollections;
import service.DataVisualization.DataAnalysisAgent;
import service.DataVisualization.ChartDataResult;
import service.DataVisualization.TableDataResult;
import javafx.scene.Node;

public class AiModeController extends EmployeeBaseController {

    // ===== Table Section =====
    @FXML
    private Label tableStatusLabel;
    @FXML
    private TextArea tablePromptInput;
    @FXML
    private VBox tableEmptyState;
    @FXML
    private TableView<ObservableMap<String, Object>> resultsTableView;

    // ===== Chart Section =====
    @FXML
    private Label chartStatusLabel;
    @FXML
    private TextArea chartPromptInput;
    @FXML
    private ComboBox<String> chartTypeSelector;
    @FXML
    private VBox chartEmptyState;

    @FXML
    private BarChart<String, Number> resultsBarChart;
    @FXML
    private LineChart<String, Number> resultsLineChart;
    @FXML
    private PieChart resultsPieChart;
    @FXML
    private AreaChart<String, Number> resultsAreaChart;
    @FXML
    private CategoryAxis chartXAxis;

    private ChartDataResult currentChartData;
    private DataAnalysisAgent aiService;

    // =========================
    // Initialization
    // =========================
    @FXML
    public void initialize() {
        aiService = new DataAnalysisAgent();

        if (chartXAxis != null) {
            chartXAxis.setTickLabelRotation(60);
        }

        // Setup chart type selector
        if (chartTypeSelector != null) {
            chartTypeSelector.setItems(FXCollections.observableArrayList(
                    "BAR", "PIE"));
            chartTypeSelector.setValue("PIE");
        }

        hideAllCharts();
    }

    // =========================
    // Table Generation
    // =========================
    @FXML
    private void handleGenerateTable() {
        String prompt = tablePromptInput.getText();

        aiService.generateTableData(prompt, results -> {
            Platform.runLater(() -> {

                if (results.hasError()) {
                    if (tableStatusLabel != null)
                        tableStatusLabel.setText("✗ Error");
                    if (tableEmptyState != null)
                        tableEmptyState.setVisible(true);
                    if (resultsTableView != null) {
                        resultsTableView.setVisible(false);
                        resultsTableView.setManaged(false);
                    }
                    return;
                }

                if (resultsTableView != null) {
                    resultsTableView.getColumns().clear();

                    for (String columnName : results.getColumns()) {
                        TableColumn<ObservableMap<String, Object>, String> col = new TableColumn<>(columnName);

                        col.setCellValueFactory(data -> new SimpleStringProperty(
                                String.valueOf(data.getValue().get(columnName))));

                        resultsTableView.getColumns().add(col);
                    }

                    resultsTableView.setItems(results.getData());

                    if (tableEmptyState != null)
                        tableEmptyState.setVisible(false);
                    resultsTableView.setVisible(true);
                    resultsTableView.setManaged(true);
                }

                if (tableStatusLabel != null)
                    tableStatusLabel.setText("✓ Generated");
            });
        });
    }

    // =========================
    // Chart Generation
    // =========================
    @FXML
    private void handleGenerateChart() {
        String prompt = chartPromptInput.getText();

        aiService.generateChartData(prompt, chartData -> {
            Platform.runLater(() -> {

                currentChartData = chartData;

                // Apply AI recommended type safely
                String recommended = chartData.getRecommendedType();
                if (recommended == null || recommended.isEmpty()) {
                    recommended = "BAR";
                }

                if (chartTypeSelector != null)
                    chartTypeSelector.setValue(recommended);

                renderChart(recommended);

                if (chartEmptyState != null)
                    chartEmptyState.setVisible(false);
                if (chartStatusLabel != null)
                    chartStatusLabel.setText("✓ Generated");
            });
        });
    }

    // =========================
    // Chart Rendering
    // =========================
    private void renderChart(String chartType) {

        if (currentChartData == null ||
                currentChartData.getChartData() == null ||
                currentChartData.getChartData().isEmpty()) {
            return;
        }

        if (chartType == null) {
            chartType = "BAR";
        }

        hideAllCharts();

        if (chartType.equals("PIE")) {
            populatePieChart(currentChartData);
            if (resultsPieChart != null)
                showChart(resultsPieChart);
        } else {
            populateBarChart(currentChartData);
            if (resultsBarChart != null)
                showChart(resultsBarChart);
        }
    }

    private void hideAllCharts() {
        if (resultsBarChart != null) {
            resultsBarChart.setVisible(false);
            resultsBarChart.setManaged(false);
        }

        if (resultsPieChart != null) {
            resultsPieChart.setVisible(false);
            resultsPieChart.setManaged(false);
        }
    }

    private void showChart(Node chart) {
        chart.setVisible(true);
        chart.setManaged(true);
    }

    private void populateBarChart(ChartDataResult data) {
        if (resultsBarChart == null)
            return;
        resultsBarChart.getData().clear();

        XYChart.Series<String, Number> series = new XYChart.Series<>();

        data.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                series.getData().add(new XYChart.Data<>(category, (Number) value));
            }
        });

        resultsBarChart.getData().add(series);

        // Prevent label overlap
        if (chartXAxis != null) {
            chartXAxis.setTickLabelRotation(
                    data.getChartData().size() > 8 ? 45 : 0);
        }
    }

    private void populatePieChart(ChartDataResult data) {
        if (resultsPieChart == null)
            return;
        resultsPieChart.getData().clear();

        data.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                resultsPieChart.getData().add(
                        new PieChart.Data(category, ((Number) value).doubleValue()));
            }
        });
    }

    // =========================
    // User Actions
    // =========================
    @FXML
    private void handleChartTypeChange() {
        renderChart(chartTypeSelector.getValue());
    }

    @FXML
    private void handleExportChart() {
    }

    @FXML
    private void handleRefreshChart() {
    }

    @Override
    public void handleSave() {
    }

    @Override
    public void clearFields() {
        if (tablePromptInput != null)
            tablePromptInput.clear();
        if (chartPromptInput != null)
            chartPromptInput.clear();
    }
}
