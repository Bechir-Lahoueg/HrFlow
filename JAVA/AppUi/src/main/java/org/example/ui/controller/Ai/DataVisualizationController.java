package org.example.ui.controller.Ai;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.scene.layout.Region;
import javafx.scene.chart.*;
import javafx.collections.ObservableMap;
import javafx.beans.property.SimpleStringProperty;
import javafx.application.Platform;
import javafx.scene.chart.XYChart;
import javafx.collections.FXCollections;
import service.DataVisualization.DataAnalysisAgent;
import service.DataVisualization.TableDataResult;
import service.DataVisualization.ChartDataResult;
import javafx.scene.Node;
import javafx.scene.control.TableCell;
import javafx.util.Callback;
import java.util.Map; 


public class DataVisualizationController {

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

    private ChartDataResult currentChartData;
    private DataAnalysisAgent dataAnalysisAgent;

    // =========================
    // Initialization
    // =========================
    @FXML
    public void initialize() {
        dataAnalysisAgent = new DataAnalysisAgent();

        chartXAxis.setTickLabelRotation(60);

        // Setup chart type selector
        chartTypeSelector.setItems(FXCollections.observableArrayList(
                "BAR", "PIE", "LINE", "AREA"
        ));
        chartTypeSelector.setValue("PIE");

        // Ensure table view maintains CSS styling
        resultsTableView.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY);

        hideAllCharts();
    }

    // =========================
    // Table Generation
    // =========================
    @FXML
    private void handleGenerateTable() {
        String prompt = tablePromptInput.getText();

        // Set loading status
        tableStatusLabel.setText("⏳ Generating...");
        tableStatusLabel.getStyleClass().remove("error");

        dataAnalysisAgent.generateTableData(prompt, results -> {
            Platform.runLater(() -> {

                if (results.hasError()) {
                    tableStatusLabel.setText("✗ Error: " + results.getErrorMessage());
                    tableStatusLabel.getStyleClass().add("error");
                    tableEmptyState.setVisible(true);
                    resultsTableView.setVisible(false);
                    resultsTableView.setManaged(false);
                    return;
                }

                resultsTableView.getColumns().clear();

                // Calculate equal width for columns
                double columnWidth = 200;
                if (!results.getColumns().isEmpty()) {
                    columnWidth = (resultsTableView.getPrefWidth() > 0 ? 
                        resultsTableView.getPrefWidth() : 600) / results.getColumns().size();
                }

                for (String columnName : results.getColumns()) {
                    TableColumn<ObservableMap<String, Object>, String> col =
                            new TableColumn<>(columnName);

                    col.setCellValueFactory(data ->
                            new SimpleStringProperty(
                                    String.valueOf(data.getValue().get(columnName))
                            )
                    );
                    
                    // Set column width to fill space evenly
                    col.setPrefWidth(columnWidth);
                    col.setMinWidth(100);

                    resultsTableView.getColumns().add(col);
                }

                resultsTableView.setItems(results.getData());

                tableEmptyState.setVisible(false);
                resultsTableView.setVisible(true);
                resultsTableView.setManaged(true);

                tableStatusLabel.setText("✅ Generated " + results.getData().size() + " rows");
                tableStatusLabel.getStyleClass().remove("error");
            });
        });
    }

    // =========================
    // Chart Generation
    // =========================
    @FXML
    private void handleGenerateChart() {
        String prompt = chartPromptInput.getText();

        // Set loading status
        chartStatusLabel.setText("⏳ Generating...");
        chartStatusLabel.getStyleClass().remove("error");

        dataAnalysisAgent.generateChartData(prompt, chartData -> {
            Platform.runLater(() -> {

                if (chartData.hasError()) {
                    chartStatusLabel.setText("✗ Error: " + chartData.getErrorMessage());
                    chartStatusLabel.getStyleClass().add("error");
                    chartEmptyState.setVisible(true);
                    hideAllCharts();
                    return;
                }

                currentChartData = chartData;

                // Apply AI recommended type safely
                String recommended = chartData.getRecommendedType();
                if (recommended == null || recommended.isEmpty()) {
                    recommended = "BAR";
                }

                chartTypeSelector.setValue(recommended);

                renderChart(recommended);

                chartEmptyState.setVisible(false);
                chartStatusLabel.setText("✅ Generated " + chartData.getChartData().size() + " data points");
                chartStatusLabel.getStyleClass().remove("error");
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
            System.err.println("Chart rendering failed: No data available");
            chartEmptyState.setVisible(true);
            hideAllCharts();
            return;
        }

        if (chartType == null || chartType.isEmpty()) {
            chartType = "BAR";
        }

        hideAllCharts();

        System.out.println("Rendering chart type: " + chartType + " with " + currentChartData.getChartData().size() + " data points");

        switch (chartType.toUpperCase()) {
            case "PIE":
                populatePieChart(currentChartData);
                showChart(resultsPieChart);
                break;
            case "LINE":
                populateLineChart(currentChartData);
                showChart(resultsLineChart);
                break;
            case "AREA":
                populateAreaChart(currentChartData);
                showChart(resultsAreaChart);
                break;
            case "BAR":
            default:
                populateBarChart(currentChartData);
                showChart(resultsBarChart);
                break;
        }
    }

    private void hideAllCharts() {
        resultsBarChart.setVisible(false);
        resultsBarChart.setManaged(false);

        resultsPieChart.setVisible(false);
        resultsPieChart.setManaged(false);

        resultsLineChart.setVisible(false);
        resultsLineChart.setManaged(false);

        resultsAreaChart.setVisible(false);
        resultsAreaChart.setManaged(false);
    }

    private void showChart(Node chart) {
        chart.setVisible(true);
        chart.setManaged(true);
    }

    private void populateBarChart(ChartDataResult data) {
        resultsBarChart.getData().clear();

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Count");

        int dataPointCount = 0;
        for (Map.Entry<String, Object> entry : data.getChartData().entrySet()) {
            if (entry.getValue() instanceof Number) {
                series.getData().add(new XYChart.Data<>(entry.getKey(), (Number) entry.getValue()));
                dataPointCount++;
            }
        }

        System.out.println("Bar chart populated with " + dataPointCount + " data points");
        resultsBarChart.getData().add(series);

        // Prevent label overlap
        chartXAxis.setTickLabelRotation(dataPointCount > 8 ? 45 : 0);
    }

    private void populateLineChart(ChartDataResult data) {
        resultsLineChart.getData().clear();

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Value");

        int dataPointCount = 0;
        for (Map.Entry<String, Object> entry : data.getChartData().entrySet()) {
            if (entry.getValue() instanceof Number) {
                series.getData().add(new XYChart.Data<>(entry.getKey(), (Number) entry.getValue()));
                dataPointCount++;
            }
        }

        System.out.println("Line chart populated with " + dataPointCount + " data points");
        resultsLineChart.getData().add(series);
    }

    private void populateAreaChart(ChartDataResult data) {
        resultsAreaChart.getData().clear();

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Value");

        int dataPointCount = 0;
        for (Map.Entry<String, Object> entry : data.getChartData().entrySet()) {
            if (entry.getValue() instanceof Number) {
                series.getData().add(new XYChart.Data<>(entry.getKey(), (Number) entry.getValue()));
                dataPointCount++;
            }
        }

        System.out.println("Area chart populated with " + dataPointCount + " data points");
        resultsAreaChart.getData().add(series);
    }

    private void populatePieChart(ChartDataResult data) {
        resultsPieChart.getData().clear();

        int dataPointCount = 0;
        for (Map.Entry<String, Object> entry : data.getChartData().entrySet()) {
            if (entry.getValue() instanceof Number) {
                double value = ((Number) entry.getValue()).doubleValue();
                resultsPieChart.getData().add(
                        new PieChart.Data(entry.getKey(), value)
                );
                dataPointCount++;
            }
        }

        System.out.println("Pie chart populated with " + dataPointCount + " data points");
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
