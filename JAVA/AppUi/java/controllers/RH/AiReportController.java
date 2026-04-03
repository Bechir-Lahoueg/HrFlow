package controllers.RH;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.application.Platform;
import javafx.geometry.Insets;
import javafx.collections.FXCollections;
import javafx.beans.property.SimpleStringProperty;
import javafx.scene.chart.*;

import service.AiReportAgent;
import service.DataAnalysisAgent;

/**
 * Controller for AI Report Generator View (RH role)
 */
public class AiReportController {

    @FXML private TextArea reportPromptInput;
    @FXML private Button generateReportBtn;
    @FXML private Label reportStatusLabel;
    @FXML private ScrollPane reportScrollPane;
    @FXML private VBox reportContainer;
    @FXML private VBox reportEmptyState;

    private AiReportAgent reportAgent;
    private DataAnalysisAgent dataAgent;

    @FXML
    public void initialize() {
        reportAgent = new AiReportAgent();
        dataAgent = new DataAnalysisAgent();
        
        reportScrollPane.setContent(reportContainer);
        reportEmptyState.setVisible(true);
        reportContainer.setVisible(false);
    }

    /**
     * Handles report generation
     */
    @FXML
    private void handleGenerateReport() {
        String prompt = reportPromptInput.getText().trim();

        if (prompt.isEmpty()) {
            reportStatusLabel.setText("⚠️ Please enter a report description");
            return;
        }

        generateReportBtn.setDisable(true);
        reportStatusLabel.setText("⏳ Generating report...");

        reportAgent.generateReport(prompt, reportResult -> {
            Platform.runLater(() -> {
                if (reportResult.hasError()) {
                    reportStatusLabel.setText("❌ " + reportResult.getError());
                    generateReportBtn.setDisable(false);
                    return;
                }

                renderReport(reportResult);
                reportStatusLabel.setText("✅ Report generated successfully");
                generateReportBtn.setDisable(false);
            });
        });
    }

    /**
     * Renders the report by creating UI components for each block
     */
    private void renderReport(AiReportAgent.ReportResult report) {
        reportContainer.getChildren().clear();

        // Report title
        Label titleLabel = new Label(report.getTitle());
        titleLabel.setStyle("-fx-font-size: 22; -fx-font-weight: bold; -fx-text-fill: #1f2937; -fx-padding: 0 0 10 0;");
        reportContainer.getChildren().add(titleLabel);

        // Report summary
        Label summaryLabel = new Label(report.getSummary());
        summaryLabel.setStyle("-fx-font-size: 12; -fx-text-fill: #6b7280; -fx-wrap-text: true; -fx-padding: 0 0 20 0;");
        reportContainer.getChildren().add(summaryLabel);

        // Add separator
        Separator sep = new Separator();
        reportContainer.getChildren().add(sep);

        // Render each block
        for (AiReportAgent.ReportBlock block : report.getBlocks()) {
            renderReportBlock(block);
            reportContainer.getChildren().add(new Separator());
        }

        reportEmptyState.setVisible(false);
        reportContainer.setVisible(true);
        VBox.setVgrow(reportScrollPane, Priority.ALWAYS);
    }

    /**
     * Renders a single report block
     */
    private void renderReportBlock(AiReportAgent.ReportBlock block) {
        if ("text".equals(block.getType())) {
            renderTextBlock(block);
        } else if ("table".equals(block.getType())) {
            renderTableBlock(block);
        } else if ("chart".equals(block.getType())) {
            renderChartBlock(block);
        }
    }

    /**
     * Renders text block
     */
    private void renderTextBlock(AiReportAgent.ReportBlock block) {
        VBox textBox = new VBox(5);
        textBox.setStyle("-fx-padding: 15; -fx-background-color: #f3f4f6; -fx-background-radius: 6; -fx-border-color: #e5e7eb; -fx-border-width: 1; -fx-border-radius: 6;");

        Label textLabel = new Label(block.getContent());
        textLabel.setWrapText(true);
        textLabel.setStyle("-fx-font-size: 12; -fx-text-fill: #374151; -fx-line-spacing: 1.5;");

        textBox.getChildren().add(textLabel);
        reportContainer.getChildren().add(textBox);
    }

    /**
     * Renders table block
     */
    private void renderTableBlock(AiReportAgent.ReportBlock block) {
        VBox tableBox = new VBox(8);
        tableBox.setStyle("-fx-padding: 15; -fx-background-color: white; -fx-border-color: #d1d5db; -fx-border-width: 1; -fx-border-radius: 6;");

        // Description
        Label descLabel = new Label(block.getDescription());
        descLabel.setStyle("-fx-font-size: 12; -fx-font-weight: bold; -fx-text-fill: #1f2937;");

        // Execute query and display table
        try {
            DataAnalysisAgent.TableDataResult tableData = reportAgent.executeTableQuery(block.getSql());

            if (tableData.hasError()) {
                Label errorLabel = new Label("❌ " + tableData.getErrorMessage());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
                tableBox.getChildren().addAll(descLabel, errorLabel);
            } else {
                TableView<javafx.collections.ObservableMap<String, Object>> tableView = 
                    new TableView<>(tableData.getData());

                for (String columnName : tableData.getColumns()) {
                    TableColumn<javafx.collections.ObservableMap<String, Object>, String> col = 
                        new TableColumn<>(columnName);
                    col.setCellValueFactory(data -> 
                        new SimpleStringProperty(String.valueOf(data.getValue().get(columnName)))
                    );
                    col.setPrefWidth(150);
                    tableView.getColumns().add(col);
                }

                tableView.setPrefHeight(250);
                tableView.setStyle("-fx-font-size: 11;");
                tableBox.getChildren().addAll(descLabel, tableView);
            }
        } catch (Exception e) {
            Label errorLabel = new Label("❌ Query execution failed: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
            tableBox.getChildren().addAll(descLabel, errorLabel);
        }

        reportContainer.getChildren().add(tableBox);
    }

    /**
     * Renders chart block
     */
    private void renderChartBlock(AiReportAgent.ReportBlock block) {
        VBox chartBox = new VBox(8);
        chartBox.setStyle("-fx-padding: 15; -fx-background-color: white; -fx-border-color: #d1d5db; -fx-border-width: 1; -fx-border-radius: 6;");

        // Description
        Label descLabel = new Label(block.getDescription());
        descLabel.setStyle("-fx-font-size: 12; -fx-font-weight: bold; -fx-text-fill: #1f2937;");

        try {
            DataAnalysisAgent.ChartDataResult chartData = reportAgent.executeChartQuery(block.getSql());

            if (chartData.getChartData().isEmpty()) {
                Label emptyLabel = new Label("No data available for chart");
                emptyLabel.setStyle("-fx-text-fill: #9ca3af; -fx-font-size: 11;");
                chartBox.getChildren().addAll(descLabel, emptyLabel);
            } else {
                Chart chart = createChart(block.getChartType(), chartData);
                chart.setPrefHeight(300);
                chartBox.getChildren().addAll(descLabel, chart);
            }
        } catch (Exception e) {
            Label errorLabel = new Label("❌ Chart generation failed: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
            chartBox.getChildren().addAll(descLabel, errorLabel);
        }

        reportContainer.getChildren().add(chartBox);
    }

    /**
     * Creates a chart based on type
     */
    private Chart createChart(String chartType, DataAnalysisAgent.ChartDataResult chartData) {
        Chart chart = null;

        if ("PIE".equalsIgnoreCase(chartType)) {
            chart = createPieChart(chartData);
        } else if ("BAR".equalsIgnoreCase(chartType)) {
            chart = createBarChart(chartData);
        } else if ("LINE".equalsIgnoreCase(chartType)) {
            chart = createLineChart(chartData);
        } else if ("AREA".equalsIgnoreCase(chartType)) {
            chart = createAreaChart(chartData);
        } else {
            chart = createBarChart(chartData); // default
        }

        return chart;
    }

    /**
     * Creates a pie chart
     */
    private PieChart createPieChart(DataAnalysisAgent.ChartDataResult chartData) {
        PieChart pieChart = new PieChart();
        chartData.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                pieChart.getData().add(
                    new PieChart.Data(category, ((Number) value).doubleValue())
                );
            }
        });
        return pieChart;
    }

    /**
     * Creates a bar chart
     */
    private BarChart<String, Number> createBarChart(DataAnalysisAgent.ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        NumberAxis yAxis = new NumberAxis();
        BarChart<String, Number> barChart = new BarChart<>(xAxis, yAxis);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        chartData.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                series.getData().add(
                    new XYChart.Data<>(category, (Number) value)
                );
            }
        });

        barChart.getData().add(series);
        return barChart;
    }

    /**
     * Creates a line chart
     */
    private LineChart<String, Number> createLineChart(DataAnalysisAgent.ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        NumberAxis yAxis = new NumberAxis();
        LineChart<String, Number> lineChart = new LineChart<>(xAxis, yAxis);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        chartData.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                series.getData().add(
                    new XYChart.Data<>(category, (Number) value)
                );
            }
        });

        lineChart.getData().add(series);
        return lineChart;
    }

    /**
     * Creates an area chart
     */
    private AreaChart<String, Number> createAreaChart(DataAnalysisAgent.ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        NumberAxis yAxis = new NumberAxis();
        AreaChart<String, Number> areaChart = new AreaChart<>(xAxis, yAxis);

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        chartData.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                series.getData().add(
                    new XYChart.Data<>(category, (Number) value)
                );
            }
        });

        areaChart.getData().add(series);
        return areaChart;
    }
}
