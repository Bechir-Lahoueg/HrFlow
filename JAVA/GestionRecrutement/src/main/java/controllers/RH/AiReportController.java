package controllers.RH;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import javafx.scene.layout.Priority;
import javafx.application.Platform;
import javafx.geometry.Insets;
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
        titleLabel.setStyle("-fx-font-size: 26; -fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-padding: 0 0 12 0; -fx-font-family: 'Segoe UI', 'Helvetica Neue', Arial;");
        reportContainer.getChildren().add(titleLabel);

        // Report summary - styled as highlighted insight
        VBox summaryBox = new VBox(8);
        summaryBox.setStyle("-fx-border-color: #2563eb; -fx-border-width: 0 0 0 4; -fx-padding: 14 16 14 16; -fx-background-color: #dbeafe; -fx-background-radius: 4; -fx-border-radius: 4;");
        Label summaryLabel = new Label(report.getSummary());
        summaryLabel.setWrapText(true);
        summaryLabel.setStyle("-fx-font-size: 13; -fx-text-fill: #0f172a; -fx-line-spacing: 1.6; -fx-font-family: 'Segoe UI', 'Helvetica Neue', Arial;");
        summaryBox.getChildren().add(summaryLabel);
        reportContainer.getChildren().add(summaryBox);

        // Add some vertical space
        Region spacer1 = new Region();
        spacer1.setPrefHeight(8);
        reportContainer.getChildren().add(spacer1);

        // Render each block with section dividers
        for (int i = 0; i < report.getBlocks().size(); i++) {
            AiReportAgent.ReportBlock block = report.getBlocks().get(i);
            
            // Add section divider before each block (except the first)
            if (i > 0) {
                Region divider = new Region();
                divider.setPrefHeight(16);
                reportContainer.getChildren().add(divider);
            }
            
            renderReportBlock(block);
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
        VBox textBox = new VBox(6);
        textBox.setStyle("-fx-padding: 16; -fx-background-color: #ffffff; -fx-border-color: #e2e8f0; -fx-border-width: 1; -fx-border-radius: 6; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.05), 2, 0, 0, 1);");
        
        // Add icon indicator for text blocks
        HBox titleBox = new HBox(8);
        titleBox.setStyle("-fx-alignment: CENTER_LEFT;");
        Label iconLabel = new Label("📝");
        iconLabel.setStyle("-fx-font-size: 12;");
        Label typeLabel = new Label("Insight");
        typeLabel.setStyle("-fx-font-size: 10; -fx-text-fill: #64748b; -fx-font-weight: bold;");
        titleBox.getChildren().addAll(iconLabel, typeLabel);
        textBox.getChildren().add(titleBox);

        Label textLabel = new Label(block.getContent());
        textLabel.setWrapText(true);
        textLabel.setStyle("-fx-font-size: 12; -fx-text-fill: #334155; -fx-line-spacing: 1.8; -fx-font-family: 'Segoe UI', 'Helvetica Neue', Arial;");

        textBox.getChildren().add(textLabel);
        reportContainer.getChildren().add(textBox);
    }

    /**
     * Renders table block
     */
    private void renderTableBlock(AiReportAgent.ReportBlock block) {
        VBox tableBox = new VBox(10);
        tableBox.setStyle("-fx-padding: 16; -fx-background-color: #ffffff; -fx-border-color: #e2e8f0; -fx-border-width: 1; -fx-border-radius: 6; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.05), 2, 0, 0, 1);");

        // Header with icon and description
        HBox headerBox = new HBox(8);
        headerBox.setStyle("-fx-alignment: CENTER_LEFT;");
        Label iconLabel = new Label("📊");
        iconLabel.setStyle("-fx-font-size: 12;");
        Label descLabel = new Label(block.getDescription() != null ? block.getDescription() : "Data Table");
        descLabel.setStyle("-fx-font-size: 13; -fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-family: 'Segoe UI', 'Helvetica Neue', Arial;");
        headerBox.getChildren().addAll(iconLabel, descLabel);
        tableBox.getChildren().add(headerBox);

        // Execute query and display table
        try {
            DataAnalysisAgent.TableDataResult tableData = reportAgent.executeTableQuery(block.getSql());

            if (tableData.hasError()) {
                Label errorLabel = new Label("⚠️ " + tableData.getErrorMessage());
                errorLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 11;");
                tableBox.getChildren().add(errorLabel);
            } else {
                TableView<javafx.collections.ObservableMap<String, Object>> tableView = 
                    new TableView<>(tableData.getData());
                tableView.setStyle("-fx-font-size: 11; -fx-padding: 0;");

                for (String columnName : tableData.getColumns()) {
                    TableColumn<javafx.collections.ObservableMap<String, Object>, String> col = 
                        new TableColumn<>(columnName);
                    col.setCellValueFactory(data -> 
                        new SimpleStringProperty(String.valueOf(data.getValue().get(columnName)))
                    );
                    col.setPrefWidth(150);
                    tableView.getColumns().add(col);
                }

                tableView.setPrefHeight(Math.min(250, tableData.getData().size() * 25 + 30));
                tableBox.getChildren().add(tableView);
            }
        } catch (Exception e) {
            Label errorLabel = new Label("❌ Query execution failed: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 11;");
            tableBox.getChildren().add(errorLabel);
        }

        reportContainer.getChildren().add(tableBox);
    }

    /**
     * Renders chart block
     */
    private void renderChartBlock(AiReportAgent.ReportBlock block) {
        // Validate block has required fields
        if (block.getSql() == null || block.getSql().isEmpty()) {
            System.err.println("Skipping chart block: SQL is empty or null");
            return;
        }
        
        if (block.getChartType() == null || block.getChartType().isEmpty()) {
            System.err.println("Skipping chart block: chartType is empty or null");
            return;
        }

        VBox chartBox = new VBox(8);
        chartBox.setStyle("-fx-padding: 15; -fx-background-color: white; -fx-border-color: #d1d5db; -fx-border-width: 1; -fx-border-radius: 6;");

        // Description
        Label descLabel = new Label(block.getDescription() != null ? block.getDescription() : "Chart");
        descLabel.setStyle("-fx-font-size: 12; -fx-font-weight: bold; -fx-text-fill: #1f2937;");

        try {
            DataAnalysisAgent.ChartDataResult chartData = reportAgent.executeChartQuery(block.getSql());

            if (chartData == null || chartData.getChartData() == null || chartData.getChartData().isEmpty()) {
                System.err.println("Skipping chart block: no data returned from query");
                return;
            }

            Chart chart = createChart(block.getChartType(), chartData);
            chart.setPrefHeight(300);
            chartBox.getChildren().addAll(descLabel, chart);
            reportContainer.getChildren().add(chartBox);
        } catch (Exception e) {
            System.err.println("Skipping chart block due to error: " + e.getMessage());
            // Don't add the chart block if there's an error - skip it silently
        }
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
        xAxis.setLabel("Category");
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Value");
        BarChart<String, Number> barChart = new BarChart<>(xAxis, yAxis);
        barChart.setTitle("Data Summary");

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
        xAxis.setLabel("Category");
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Value");
        LineChart<String, Number> lineChart = new LineChart<>(xAxis, yAxis);
        lineChart.setTitle("Trend Analysis");

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
        xAxis.setLabel("Category");
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Value");
        AreaChart<String, Number> areaChart = new AreaChart<>(xAxis, yAxis);
        areaChart.setTitle("Cumulative Trend");

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
