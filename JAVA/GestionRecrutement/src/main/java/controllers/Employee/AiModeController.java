package controllers.Employee;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.scene.chart.*;
import javafx.collections.ObservableMap;
import javafx.beans.property.SimpleStringProperty;
import javafx.application.Platform;
import javafx.scene.chart.XYChart;
import javafx.collections.FXCollections;
import service.DataAnalysisAgent;
import javafx.scene.Node;

public class AiModeController {

    // ===== Table Section =====
    @FXML private Label tableStatusLabel;
    @FXML private TextArea tablePromptInput;
    @FXML private VBox tableEmptyState;
    @FXML private TableView<ObservableMap<String, Object>> resultsTableView;

    // ===== Chart Section =====
    @FXML private Label chartStatusLabel;
    @FXML private TextArea chartPromptInput;
    @FXML private ComboBox<String> chartTypeSelector;
    @FXML private VBox chartEmptyState;

    @FXML private BarChart<String, Number> resultsBarChart;
    @FXML private LineChart<String, Number> resultsLineChart;
    @FXML private PieChart resultsPieChart;
    @FXML private AreaChart<String, Number> resultsAreaChart;
    @FXML private CategoryAxis chartXAxis;
    

    private DataAnalysisAgent.ChartDataResult currentChartData;
    private DataAnalysisAgent aiService;

    // =========================
    // Initialization
    // =========================
    @FXML
    public void initialize() {
        aiService = new DataAnalysisAgent();

        chartXAxis.setTickLabelRotation(60);

        // Setup chart type selector
        chartTypeSelector.setItems(FXCollections.observableArrayList(
                "BAR", "PIE"
        ));
        chartTypeSelector.setValue("PIE");

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
                    tableStatusLabel.setText("✗ Error");
                    tableEmptyState.setVisible(true);
                    resultsTableView.setVisible(false);
                    resultsTableView.setManaged(false);
                    return;
                }

                resultsTableView.getColumns().clear();

                for (String columnName : results.getColumns()) {
                    TableColumn<ObservableMap<String, Object>, String> col =
                            new TableColumn<>(columnName);

                    col.setCellValueFactory(data ->
                            new SimpleStringProperty(
                                    String.valueOf(data.getValue().get(columnName))
                            )
                    );

                    resultsTableView.getColumns().add(col);
                }

                resultsTableView.setItems(results.getData());

                tableEmptyState.setVisible(false);
                resultsTableView.setVisible(true);
                resultsTableView.setManaged(true);

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

                chartTypeSelector.setValue(recommended);

                renderChart(recommended);

                chartEmptyState.setVisible(false);
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
            showChart(resultsPieChart);
        } else {
            populateBarChart(currentChartData);
            showChart(resultsBarChart);
        }
    }

    private void hideAllCharts() {
        resultsBarChart.setVisible(false);
        resultsBarChart.setManaged(false);

        resultsPieChart.setVisible(false);
        resultsPieChart.setManaged(false);

    }

    private void showChart(Node chart) {
        chart.setVisible(true);
        chart.setManaged(true);
    }

    private void populateBarChart(DataAnalysisAgent.ChartDataResult data) {
        resultsBarChart.getData().clear();

        XYChart.Series<String, Number> series = new XYChart.Series<>();

        data.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                series.getData().add(new XYChart.Data<>(category, (Number) value));
            }
        });

        resultsBarChart.getData().add(series);

        // Prevent label overlap
        chartXAxis.setTickLabelRotation(
                data.getChartData().size() > 8 ? 45 : 0
        );
    }


    private void populatePieChart(DataAnalysisAgent.ChartDataResult data) {
        resultsPieChart.getData().clear();

        data.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                resultsPieChart.getData().add(
                        new PieChart.Data(category, ((Number) value).doubleValue())
                );
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
    private void handleExportChart() {}

    @FXML
    private void handleRefreshChart() {}
}