package org.example.ui.controller.Ai;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import javafx.scene.layout.Priority;
import javafx.application.Platform;
import javafx.geometry.Insets;
import javafx.collections.FXCollections;
import javafx.beans.property.SimpleStringProperty;
import javafx.scene.chart.*;

import service.ReportGenerator.ReportAgent;
import service.ReportGenerator.ReportResult;
import service.ReportGenerator.ReportBlock;
import service.DataVisualization.DataAnalysisAgent;
import service.DataVisualization.ChartDataResult;
import service.DataVisualization.TableDataResult; 

/**
 * Controller for AI Report Generator View (RH role)
 */
public class ReportGeneratorController {

    @FXML private TextArea reportPromptInput;
    @FXML private Button generateReportBtn;
    @FXML private Label reportStatusLabel;
    @FXML private VBox reportContainer;
    @FXML private VBox reportEmptyState;

    private ReportAgent reportAgent;
    private DataAnalysisAgent dataAgent;

    @FXML
    public void initialize() {
        reportAgent = new ReportAgent();
        dataAgent = new DataAnalysisAgent();
        
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
            System.out.println(" [DEBUG] UI: Callback received with result: " + (reportResult != null ? "not null" : "null"));
            System.out.println(" [DEBUG] UI: Result success: " + (reportResult != null ? reportResult.isSuccess() : "N/A"));
            if (reportResult != null && reportResult.isSuccess()) {
                System.out.println(" [DEBUG] UI: Title: " + reportResult.getTitle());
                System.out.println(" [DEBUG] UI: Blocks count: " + (reportResult.getBlocks() != null ? reportResult.getBlocks().size() : 0));
            }
            
            Platform.runLater(() -> {
                System.out.println(" [DEBUG] UI: Platform.runLater() executing");
                if (!reportResult.isSuccess()) {
                    reportStatusLabel.setText("❌ " + reportResult.getError());
                    generateReportBtn.setDisable(false);
                    return;
                }

                System.out.println(" [DEBUG] UI: About to call renderReport()");
                renderReport(reportResult);
                System.out.println(" [DEBUG] UI: renderReport() completed");
                reportStatusLabel.setText("✅ Report generated successfully");
                generateReportBtn.setDisable(false);
            });
        });
    }

    /**
     * Renders report by creating UI components for each block
     */
    private void renderReport(ReportResult report) {
        System.out.println(" [DEBUG] renderReport() called with report: " + (report != null ? "not null" : "null"));
        if (report != null) {
            System.out.println(" [DEBUG] renderReport() - Title: " + report.getTitle());
            System.out.println(" [DEBUG] renderReport() - Blocks: " + (report.getBlocks() != null ? report.getBlocks().size() : 0) + " blocks");
        }
        
        reportContainer.getChildren().clear();
        System.out.println(" [DEBUG] renderReport() - Container cleared");

        // Report title
        Label titleLabel = new Label(report.getTitle());
        titleLabel.setStyle("-fx-font-size: 24; -fx-font-weight: 700; -fx-text-fill: #1e293b; -fx-font-family: 'Inter', 'Segoe UI', sans-serif;");
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

        if (report.getBlocks() == null || report.getBlocks().isEmpty()) {
            if (report.getContent() != null && !report.getContent().trim().isEmpty()) {
                ReportBlock textBlock = new ReportBlock();
                textBlock.setType("text");
                textBlock.setContent(report.getContent());
                renderTextBlock(textBlock);
            }

            reportEmptyState.setVisible(false);
            reportContainer.setVisible(true);
            return;
        }

        // Render each block with section dividers
        for (int i = 0; i < report.getBlocks().size(); i++) {
            ReportBlock block = report.getBlocks().get(i);
            
            // Add section divider before each block (except first)
            if (i > 0) {
                Region divider = new Region();
                divider.setPrefHeight(16);
                reportContainer.getChildren().add(divider);
            }
            
            renderReportBlock(block, i);
        }

        reportEmptyState.setVisible(false);
        reportContainer.setVisible(true);
    }

    /**
     * Renders a single report block
     */
    private void renderReportBlock(ReportBlock block, int index) {
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
    private void renderTextBlock(ReportBlock block) {
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

        if (block.getContent() != null) {
            String[] lines = block.getContent().split("\\r?\\n");
            for (String line : lines) {
                if (line == null) continue;
                String trimmed = line.trim();
                if (trimmed.isEmpty()) {
                    Region spacer = new Region();
                    spacer.setPrefHeight(6);
                    textBox.getChildren().add(spacer);
                    continue;
                }

                java.util.regex.Matcher heading = java.util.regex.Pattern.compile("^\\*\\*(.+)\\*\\*$").matcher(trimmed);
                if (heading.matches()) {
                    Label headingLabel = new Label(heading.group(1).trim());
                    headingLabel.setWrapText(true);
                    headingLabel.setStyle("-fx-font-size: 14; -fx-font-weight: 700; -fx-text-fill: #0f172a; -fx-font-family: 'Segoe UI', 'Helvetica Neue', Arial;");
                    textBox.getChildren().add(headingLabel);
                } else {
                    Label textLabel = new Label(line);
                    textLabel.setWrapText(true);
                    textLabel.setStyle("-fx-font-size: 12; -fx-text-fill: #334155; -fx-line-spacing: 1.8; -fx-font-family: 'Segoe UI', 'Helvetica Neue', Arial;");
                    textBox.getChildren().add(textLabel);
                }
            }
        }
        reportContainer.getChildren().add(textBox);
    }

    /**
     * Renders table block
     */
    private void renderTableBlock(ReportBlock block) {
        // Validate block has required fields
        if (block.getSql() == null || block.getSql().isEmpty()) {
            System.err.println("Skipping table block: SQL is empty or null");
            return;
        }

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

        // Use data from ReportBlock instead of re-executing query
        try {
            if (!block.isQuerySuccess()) {
                System.err.println("Skipping table block due to query error: " + block.getQueryError());
                Label errorLabel = new Label("Query execution failed: " + block.getQueryError());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11; -fx-padding: 8 0;");
                tableBox.getChildren().add(errorLabel);
                reportContainer.getChildren().add(tableBox);
                return;
            }

            // Create TableDataResult from ReportBlock data
            TableDataResult tableData = new TableDataResult();
            if (block.getColumns() != null) {
                tableData.getColumns().addAll(block.getColumns());
            }
            if (block.getData() != null) {
                for (java.util.Map<String, Object> row : block.getData()) {
                    javafx.collections.ObservableMap<String, Object> observableRow = javafx.collections.FXCollections.observableHashMap();
                    observableRow.putAll(row);
                    tableData.getData().add(observableRow);
                }
            }

            if (tableData.getData() == null || tableData.getData().isEmpty()) {
                System.err.println("Skipping table block: no data returned");
                Label errorLabel = new Label("No data returned from query");
                errorLabel.setStyle("-fx-text-fill: #f59e0b; -fx-font-size: 11; -fx-padding: 8 0;");
                tableBox.getChildren().add(errorLabel);
                reportContainer.getChildren().add(tableBox);
                return;
            }

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

            if (block.getContent() != null && !block.getContent().trim().isEmpty()) {
                Label desc = new Label(block.getContent());
                desc.setWrapText(true);
                desc.setStyle("-fx-font-size: 12; -fx-text-fill: #475569; -fx-padding: 8 0 0 0;");
                tableBox.getChildren().add(desc);
            }
            reportContainer.getChildren().add(tableBox);
        } catch (Exception e) {
            System.err.println("Skipping table block due to error: " + e.getMessage());
            Label errorLabel = new Label("Unable to display table: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11; -fx-padding: 8 0;");
            tableBox.getChildren().add(errorLabel);
            reportContainer.getChildren().add(tableBox);
        }
    }

    /**
     * Renders chart block
     */
    private void renderChartBlock(ReportBlock block) {
        // Validate block has required fields
        if (block.getSql() == null || block.getSql().isEmpty()) {
            System.err.println("Skipping chart block: SQL is empty or null");
            return;
        }
        
        if (block.getChartType() == null || block.getChartType().isEmpty()) {
            System.err.println("Skipping chart block: chartType is empty or null");
            return;
        }

        VBox chartBox = new VBox(10);
        chartBox.setStyle("-fx-padding: 16; -fx-background-color: #ffffff; -fx-border-color: #e2e8f0; -fx-border-width: 1; -fx-border-radius: 6; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.05), 2, 0, 0, 1);");

        // Header with icon and description
        HBox headerBox = new HBox(8);
        headerBox.setStyle("-fx-alignment: CENTER_LEFT;");
        Label iconLabel = new Label("📈");
        iconLabel.setStyle("-fx-font-size: 12;");
        Label descLabel = new Label(block.getDescription() != null ? block.getDescription() : "Data Visualization");
        descLabel.setStyle("-fx-font-size: 13; -fx-font-weight: bold; -fx-text-fill: #0f172a; -fx-font-family: 'Segoe UI', 'Helvetica Neue', Arial;");
        headerBox.getChildren().addAll(iconLabel, descLabel);
        chartBox.getChildren().add(headerBox);

        try {
            if (!block.isQuerySuccess()) {
                System.err.println("Skipping chart block due to query error: " + block.getQueryError());
                Label errorLabel = new Label("Query execution failed: " + block.getQueryError());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11; -fx-padding: 8 0;");
                chartBox.getChildren().add(errorLabel);
                reportContainer.getChildren().add(chartBox);
                return;
            }

            // Create TableDataResult from ReportBlock data
            TableDataResult tableResult = new TableDataResult();
            if (block.getColumns() != null) {
                tableResult.getColumns().addAll(block.getColumns());
            }
            if (block.getData() != null) {
                for (java.util.Map<String, Object> row : block.getData()) {
                    javafx.collections.ObservableMap<String, Object> observableRow = javafx.collections.FXCollections.observableHashMap();
                    observableRow.putAll(row);
                    tableResult.getData().add(observableRow);
                }
            }
            
            ChartDataResult chartData = dataAgent.convertToChartData(tableResult);

            if (chartData != null && chartData.hasError()) {
                Label errorLabel = new Label("Query error: " + chartData.getErrorMessage());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11; -fx-padding: 8 0;");
                chartBox.getChildren().add(errorLabel);
                reportContainer.getChildren().add(chartBox);
                return;
            }

            if (chartData == null || chartData.getChartData() == null || chartData.getChartData().isEmpty()) {
                System.err.println("Skipping chart block: no data returned from query");
                Label errorLabel = new Label("No data returned from query");
                errorLabel.setStyle("-fx-text-fill: #f59e0b; -fx-font-size: 11; -fx-padding: 8 0;");
                chartBox.getChildren().add(errorLabel);
                reportContainer.getChildren().add(chartBox);
                return;
            }

            Chart chart = createChart(block.getChartType(), chartData);
            chart.setPrefHeight(320);
            chart.setStyle("-fx-padding: 8;");
            chartBox.getChildren().add(chart);

            if (block.getContent() != null && !block.getContent().trim().isEmpty()) {
                Label desc = new Label(block.getContent());
                desc.setWrapText(true);
                desc.setStyle("-fx-font-size: 12; -fx-text-fill: #475569; -fx-padding: 8 0 0 0;");
                chartBox.getChildren().add(desc);
            }
            reportContainer.getChildren().add(chartBox);
        } catch (Exception e) {
            System.err.println("Skipping chart block due to error: " + e.getMessage());
            Label errorLabel = new Label("Unable to display chart: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11; -fx-padding: 8 0;");
            chartBox.getChildren().add(errorLabel);
            reportContainer.getChildren().add(chartBox);
        }
    }

    /**
     * Creates PIE chart
     */
    private Chart createPieChart(ChartDataResult chartData) {
        PieChart pieChart = new PieChart();
        pieChart.setTitle("Data Distribution");
        
        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                String label = entry.getKey(); // Use actual data key as label
                PieChart.Data data = new PieChart.Data(label, value);
                pieChart.getData().add(data);
            } catch (Exception e) {
                System.err.println("Error parsing pie chart value: " + e.getMessage());
            }
        }
        return pieChart;
    }

    /**
     * Creates a chart based on type
     */
    private Chart createChart(String chartType, ChartDataResult chartData) {
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
            chart = createBarChart(chartData);
        }
        return chart;
    }


    /**
     * Creates BAR chart
     */

    // i want to set charts types  to Object Object for more flexibility 
    private Chart createBarChart(ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("Categories"); // More descriptive label
        xAxis.setTickLabelRotation(0); // Ensure labels are horizontal
        xAxis.setTickLabelGap(5); // Add gap between labels
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Values"); // More descriptive label
        
        BarChart<String, Number> barChart = new BarChart<>(xAxis, yAxis);
        barChart.setTitle("Data Analysis");
        barChart.setLegendVisible(false); // Hide legend for cleaner look
        
        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Data Series"); // Add series name for legend
        
        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                String label = entry.getKey(); // Use actual data key as label
                series.getData().add(new XYChart.Data<>(label, value));
                System.out.println("Adding chart data: " + label + " = " + value);
            } catch (Exception e) {
                System.err.println("Error parsing bar chart value: " + e.getMessage());
            }
        }
        barChart.getData().add(series);
        return barChart;
    }

    /**
     * Creates LINE chart
     */
    private Chart createLineChart(ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("Categories"); // More descriptive label
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Values"); // More descriptive label
        LineChart<String, Number> lineChart = new LineChart<>(xAxis, yAxis);
        lineChart.setTitle("Trend Analysis");

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Trend Data"); // Add series name for legend
        
        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                String label = entry.getKey(); // Use actual data key as label
                series.getData().add(new XYChart.Data<>(label, value));
            } catch (Exception e) {
                System.err.println("Error parsing line chart value: " + e.getMessage());
            }
        }
        lineChart.getData().add(series);
        return lineChart;
    }

    /**
     * Creates AREA chart
     */
    private Chart createAreaChart(ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("Categories"); // More descriptive label
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Values"); // More descriptive label
        AreaChart<String, Number> areaChart = new AreaChart<>(xAxis, yAxis);
        areaChart.setTitle("Cumulative Analysis");

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Cumulative Data"); // Add series name for legend
        
        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                String label = entry.getKey(); // Use actual data key as label
                series.getData().add(new XYChart.Data<>(label, value));
            } catch (Exception e) {
                System.err.println("Error parsing area chart value: " + e.getMessage());
            }
        }
        areaChart.getData().add(series);
        return areaChart;
    }
}
