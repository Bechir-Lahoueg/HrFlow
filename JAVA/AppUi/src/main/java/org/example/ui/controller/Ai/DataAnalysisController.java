package org.example.ui.controller.Ai;

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
import service.ReportGenerator.ReportAgent;
import service.ReportGenerator.ReportResult;
import service.ReportGenerator.ReportBlock;
import javafx.scene.Node;
import javafx.scene.layout.HBox;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.Region;
import javafx.geometry.Insets;

/**
 * Unified Data Analysis Controller
 * Handles Table, Chart, and Report Generation in a single scrollable view
 */
public class DataAnalysisController {

    // ===== Table Section =====
    @FXML
    private Label tableStatusLabel;
    @FXML
    private TextArea tablePromptInput;
    @FXML
    private Button generateTableBtn;
    @FXML
    private VBox tableResultsSection;
    @FXML
    private TableView<ObservableMap<String, Object>> resultsTableView;
    @FXML
    private Label tableRowCount;
    @FXML
    private TableColumn<ObservableMap<String, Object>, String> tableColumn1;
    @FXML
    private TableColumn<ObservableMap<String, Object>, String> tableColumn2;
    @FXML
    private TableColumn<ObservableMap<String, Object>, String> tableColumn3;
    @FXML
    private TableColumn<ObservableMap<String, Object>, String> tableColumn4;

    // ===== Chart Section =====
    @FXML
    private Label chartStatusLabel;
    @FXML
    private TextArea chartPromptInput;
    @FXML
    private Button generateChartBtn;
    @FXML
    private ComboBox<String> chartTypeSelector;
    @FXML
    private VBox chartResultsSection;
    @FXML
    private StackPane chartContainer;
    @FXML
    private BarChart<String, Number> resultsBarChart;
    @FXML
    private LineChart<String, Number> resultsLineChart;
    @FXML
    private PieChart resultsPieChart;
    @FXML
    private AreaChart<String, Number> resultsAreaChart;
    @FXML
    private CategoryAxis chartXAxis, lineChartXAxis, areaChartXAxis;
    @FXML
    private NumberAxis chartYAxis, lineChartYAxis, areaChartYAxis;
    @FXML
    private Label chartDataPoints;

    // ===== Report Section =====
    @FXML
    private Label reportStatusLabel;
    @FXML
    private TextArea reportPromptInput;
    @FXML
    private Button generateReportBtn;
    @FXML
    private Button resetReportBtn;
    @FXML
    private VBox reportResultsSection;
    @FXML
    private ScrollPane reportScrollPane;
    @FXML
    private VBox reportContainer;
    @FXML
    private Label reportBlockCount;

    // ===== Services =====
    private DataAnalysisAgent dataAnalysisAgent;
    private ReportAgent reportAgent;

    // ===== Data =====
    private ChartDataResult currentChartData;

    // =========================
    // Initialization
    // =========================
    @FXML
    public void initialize() {
        dataAnalysisAgent = new DataAnalysisAgent();
        reportAgent = new ReportAgent();

        // Initialize table columns
        initializeTableColumns();

        // Set default chart type
        if (chartTypeSelector != null) {
            chartTypeSelector.setValue("BAR");
        }
    }

    private void initializeTableColumns() {
        if (tableColumn1 != null) {
            tableColumn1.setCellValueFactory(param -> {
                ObservableMap<String, Object> value = param.getValue();
                if (value != null) {
                    return new SimpleStringProperty(value.getOrDefault("Column1", "").toString());
                }
                return new SimpleStringProperty("");
            });
        }
        if (tableColumn2 != null) {
            tableColumn2.setCellValueFactory(param -> {
                ObservableMap<String, Object> value = param.getValue();
                if (value != null) {
                    return new SimpleStringProperty(value.getOrDefault("Column2", "").toString());
                }
                return new SimpleStringProperty("");
            });
        }
        if (tableColumn3 != null) {
            tableColumn3.setCellValueFactory(param -> {
                ObservableMap<String, Object> value = param.getValue();
                if (value != null) {
                    return new SimpleStringProperty(value.getOrDefault("Column3", "").toString());
                }
                return new SimpleStringProperty("");
            });
        }
        if (tableColumn4 != null) {
            tableColumn4.setCellValueFactory(param -> {
                ObservableMap<String, Object> value = param.getValue();
                if (value != null) {
                    return new SimpleStringProperty(value.getOrDefault("Column4", "").toString());
                }
                return new SimpleStringProperty("");
            });
        }
    }

    // =========================
    // Table Generation
    // =========================
    @FXML
    private void handleGenerateTable() {
        String prompt = tablePromptInput.getText().trim();
        if (prompt.isEmpty()) {
            showTableStatus("Please enter a query", false);
            return;
        }

        showTableStatus("⏳ Generating table...", false);
        generateTableBtn.setDisable(true);

        dataAnalysisAgent.generateTableData(prompt, result -> {
            Platform.runLater(() -> {
                if (result.hasError()) {
                    showTableStatus("✗ Error: " + result.getErrorMessage(), false);
                } else if (result.getData() == null || result.getData().isEmpty()) {
                    showTableStatus("⚠ No data found", false);
                } else {
                    displayTableResults(result);
                    showTableStatus("✅ Generated " + result.getData().size() + " rows", true);
                }
                generateTableBtn.setDisable(false);
            });
        });
    }

    private void displayTableResults(TableDataResult result) {
        // Check if data is empty
        if (result.getData() == null || result.getData().isEmpty()) {
            tableRowCount.setText("0 rows");
            return;
        }

        // Update table columns based on data
        updateTableColumns(result.getData().get(0));

        // Set data
        resultsTableView.setItems(FXCollections.observableArrayList(result.getData()));

        // Update row count
        tableRowCount.setText(result.getData().size() + " rows");

        // Show results section
        tableResultsSection.setVisible(true);
        tableResultsSection.setManaged(true);
    }

    private void updateTableColumns(ObservableMap<String, Object> firstRow) {
        String[] columnKeys = firstRow.keySet().toArray(new String[0]);

        TableColumn<ObservableMap<String, Object>, String>[] columns = new TableColumn[] {
                tableColumn1, tableColumn2, tableColumn3, tableColumn4
        };

        for (int i = columnKeys.length; i < columns.length; i++) {
            if (columns[i] != null) {
                columns[i].setVisible(false);
            }
        }
    }

    private void showTableStatus(String message, boolean success) {
        if (tableStatusLabel != null) {
            tableStatusLabel.setText(message);
            tableStatusLabel.getStyleClass().removeAll("error", "success");
            if (success) {
                tableStatusLabel.getStyleClass().add("success");
            } else if (message.contains("✗ Error")) {
                tableStatusLabel.getStyleClass().add("error");
            }
        }
    }

    // =========================
    // Chart Generation
    // =========================
    @FXML
    private void handleGenerateChart() {
        String prompt = chartPromptInput.getText().trim();
        if (prompt.isEmpty()) {
            showChartStatus("Please enter a query", false);
            return;
        }

        showChartStatus("⏳ Generating chart...", false);
        generateChartBtn.setDisable(true);

        dataAnalysisAgent.generateChartData(prompt, result -> {
            Platform.runLater(() -> {
                if (result.hasError()) {
                    showChartStatus("✗ Error: " + result.getErrorMessage(), false);
                } else if (result.getChartData() == null || result.getChartData().isEmpty()) {
                    showChartStatus("⚠ No data found", false);
                } else {
                    currentChartData = result;
                    displayChartResults(result);
                    showChartStatus("✅ Generated " + result.getChartData().size() + " data points", true);
                }
                generateChartBtn.setDisable(false);
            });
        });
    }

    private void displayChartResults(ChartDataResult data) {
        // Update chart type selector with recommended type
        if (chartTypeSelector != null && data.getRecommendedType() != null) {
            chartTypeSelector.setValue(data.getRecommendedType());
        }

        // Render the chart
        renderChart(chartTypeSelector.getValue());

        // Update data points count
        chartDataPoints.setText(data.getChartData().size() + " data points");

        // Show results section
        chartResultsSection.setVisible(true);
        chartResultsSection.setManaged(true);
    }

    private void renderChart(String chartType) {
        // Hide all charts first
        hideAllCharts();

        if (currentChartData == null)
            return;

        switch (chartType.toUpperCase()) {
            case "BAR":
                populateBarChart(currentChartData);
                showChart(resultsBarChart);
                break;
            case "LINE":
                populateLineChart(currentChartData);
                showChart(resultsLineChart);
                break;
            case "PIE":
                populatePieChart(currentChartData);
                showChart(resultsPieChart);
                break;
            case "AREA":
                populateAreaChart(currentChartData);
                showChart(resultsAreaChart);
                break;
        }
    }

    private void hideAllCharts() {
        if (resultsBarChart != null) {
            resultsBarChart.setVisible(false);
            resultsBarChart.setManaged(false);
        }
        if (resultsLineChart != null) {
            resultsLineChart.setVisible(false);
            resultsLineChart.setManaged(false);
        }
        if (resultsPieChart != null) {
            resultsPieChart.setVisible(false);
            resultsPieChart.setManaged(false);
        }
        if (resultsAreaChart != null) {
            resultsAreaChart.setVisible(false);
            resultsAreaChart.setManaged(false);
        }
    }

    private void showChart(Node chart) {
        // Add animation class for fade-in effect
        chart.getStyleClass().remove("ai-fade-in");
        chart.getStyleClass().add("ai-chart");
        
        chart.setVisible(true);
        chart.setManaged(true);
        
        // Trigger fade-in animation after a brief delay
        javafx.application.Platform.runLater(() -> {
            chart.getStyleClass().add("visible");
            chart.getStyleClass().add("ai-fade-in");
        });
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

        // Fix axis labels - ensure they are visible and properly labeled
        if (chartXAxis != null) {
            chartXAxis.setLabel("Categories");
            chartXAxis.setTickLabelRotation(data.getChartData().size() > 8 ? 45 : 0);
            chartXAxis.setStyle("-fx-tick-label-fill: #64748b; -fx-font-size: 12px;");
        }
        if (chartYAxis != null) {
            chartYAxis.setLabel("Values");
            chartYAxis.setStyle("-fx-tick-label-fill: #64748b; -fx-font-size: 12px;");
        }
        
        // Apply animation to the chart
        resultsBarChart.getStyleClass().add("ai-chart");
    }

    private void populateLineChart(ChartDataResult data) {
        if (resultsLineChart == null)
            return;

        resultsLineChart.getData().clear();
        XYChart.Series<String, Number> series = new XYChart.Series<>();

        data.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                series.getData().add(new XYChart.Data<>(category, (Number) value));
            }
        });

        resultsLineChart.getData().add(series);

        // Fix axis labels - ensure they are visible and properly labeled
        if (lineChartXAxis != null) {
            lineChartXAxis.setLabel("Categories");
            lineChartXAxis.setTickLabelRotation(data.getChartData().size() > 8 ? 45 : 0);
            lineChartXAxis.setStyle("-fx-tick-label-fill: #64748b; -fx-font-size: 12px;");
        }
        if (lineChartYAxis != null) {
            lineChartYAxis.setLabel("Values");
            lineChartYAxis.setStyle("-fx-tick-label-fill: #64748b; -fx-font-size: 12px;");
        }
        
        // Apply animation to the chart
        resultsLineChart.getStyleClass().add("ai-chart");
    }

    private void populatePieChart(ChartDataResult data) {
        if (resultsPieChart == null)
            return;

        resultsPieChart.getData().clear();

        data.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                javafx.scene.chart.PieChart.Data slice = new javafx.scene.chart.PieChart.Data(
                        category, ((Number) value).doubleValue());
                resultsPieChart.getData().add(slice);
            }
        });
        
        // Apply animation to the chart
        resultsPieChart.getStyleClass().add("ai-chart");
    }

    private void populateAreaChart(ChartDataResult data) {
        if (resultsAreaChart == null)
            return;

        resultsAreaChart.getData().clear();
        XYChart.Series<String, Number> series = new XYChart.Series<>();

        data.getChartData().forEach((category, value) -> {
            if (value instanceof Number) {
                series.getData().add(new XYChart.Data<>(category, (Number) value));
            }
        });

        resultsAreaChart.getData().add(series);

        // Fix axis labels - ensure they are visible and properly labeled
        if (areaChartXAxis != null) {
            areaChartXAxis.setLabel("Categories");
            areaChartXAxis.setTickLabelRotation(data.getChartData().size() > 8 ? 45 : 0);
            areaChartXAxis.setStyle("-fx-tick-label-fill: #64748b; -fx-font-size: 12px;");
        }
        if (areaChartYAxis != null) {
            areaChartYAxis.setLabel("Values");
            areaChartYAxis.setStyle("-fx-tick-label-fill: #64748b; -fx-font-size: 12px;");
        }
        
        // Apply animation to the chart
        resultsAreaChart.getStyleClass().add("ai-chart");
    }

    private void showChartStatus(String message, boolean success) {
        if (chartStatusLabel != null) {
            chartStatusLabel.setText(message);
            chartStatusLabel.getStyleClass().removeAll("error", "success");
            if (success) {
                chartStatusLabel.getStyleClass().add("success");
            } else if (message.contains("✗ Error")) {
                chartStatusLabel.getStyleClass().add("error");
            }
        }
    }

    // =========================
    // Report Generation
    // =========================
    @FXML
    private void handleGenerateReport() {
        String prompt = reportPromptInput.getText().trim();
        if (prompt.isEmpty()) {
            showReportStatus("Please enter a report description", false);
            return;
        }

        showReportStatus("⏳ Generating comprehensive report...", false);
        generateReportBtn.setDisable(true);

        // Use callback-based method
        reportAgent.generateReport(prompt, result -> {
            Platform.runLater(() -> {
                if (!result.isSuccess()) {
                    showReportStatus("✗ Error: " + result.getError(), false);
                } else if (result.getBlocks() == null || result.getBlocks().isEmpty()) {
                    showReportStatus("⚠ No report content generated", false);
                } else {
                    displayReportResults(result);
                    showReportStatus("✅ Generated report with " + result.getBlocks().size() + " sections", true);
                }
                generateReportBtn.setDisable(false);
            });
        });
    }

    @FXML
    private void handleResetReport() {
        reportPromptInput.clear();
        reportContainer.getChildren().clear();
        reportResultsSection.setVisible(false);
        reportResultsSection.setManaged(false);
        showReportStatus("", false);
    }

    private void displayReportResults(ReportResult result) {
        // Clear previous content
        reportContainer.getChildren().clear();

        // Generate report blocks
        for (ReportBlock block : result.getBlocks()) {
            VBox blockContent = createReportBlock(block);
            reportContainer.getChildren().add(blockContent);
        }

        // Update block count
        reportBlockCount.setText(result.getBlocks().size() + " sections");

        // Show results section
        reportResultsSection.setVisible(true);
        reportResultsSection.setManaged(true);
    }

    private VBox createReportBlock(ReportBlock block) {
        VBox blockContainer = new VBox(12);
        blockContainer.getStyleClass().add("ai-report-block");
        blockContainer.getStyleClass().add("ai-slide-in");

        // Block header
        HBox headerBox = new HBox(8);
        headerBox.setStyle("-fx-alignment: CENTER_LEFT;");

        Label iconLabel = new Label();
        Label descLabel = new Label(block.getDescription() != null ? block.getDescription() : "Report Section");
        descLabel.getStyleClass().add("ai-report-block-header");

        // Set icon based on block type
        switch (block.getType().toUpperCase()) {
            case "TABLE":
                iconLabel.setText("📊");
                break;
            case "CHART":
                iconLabel.setText("📈");
                break;
            default:
                iconLabel.setText("📄");
        }
        iconLabel.setStyle("-fx-font-size: 16; -fx-font-family: 'Segoe UI Emoji', 'Apple Color Emoji', 'Noto Color Emoji', sans-serif;");

        headerBox.getChildren().addAll(iconLabel, descLabel);
        blockContainer.getChildren().add(headerBox);

        // Add block content based on type
        try {
            switch (block.getType().toUpperCase()) {
                case "TABLE":
                    addTableBlock(blockContainer, block);
                    break;
                case "CHART":
                    addChartBlock(blockContainer, block);
                    break;
                default:
                    addTextBlock(blockContainer, block);
            }
        } catch (Exception e) {
            System.err.println("Error creating report block: " + e.getMessage());
            // Add error message as fallback
            Label errorLabel = new Label("Unable to display this section: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
            blockContainer.getChildren().add(errorLabel);
        }

        return blockContainer;
    }

    private void addTableBlock(VBox container, ReportBlock block) {
        try {
            service.DataVisualization.TableDataResult tableData;
            try {
                tableData = dataAnalysisAgent.executeQuery(block.getSql());
            } catch (Exception e) {
                System.err.println("Skipping table block due to query error: " + e.getMessage());
                Label errorLabel = new Label("Unable to execute table query: " + e.getMessage());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
                container.getChildren().add(errorLabel);
                return;
            }

            if (tableData.hasError()) {
                System.err.println("Skipping table block due to query error: " + tableData.getErrorMessage());
                Label errorLabel = new Label("Query error: " + tableData.getErrorMessage());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
                container.getChildren().add(errorLabel);
                return;
            }

            if (tableData.getData() == null || tableData.getData().isEmpty()) {
                System.err.println("Skipping table block: no data returned");
                Label errorLabel = new Label("No data returned from query");
                errorLabel.setStyle("-fx-text-fill: #f59e0b; -fx-font-size: 11;");
                container.getChildren().add(errorLabel);
                return;
            }

            TableView<javafx.collections.ObservableMap<String, Object>> tableView = new TableView<>(
                    tableData.getData());
            tableView.getStyleClass().add("ai-results-table");
            tableView.setColumnResizePolicy(TableView.CONSTRAINED_RESIZE_POLICY);

            // Create columns dynamically
            if (tableData.getData() != null && !tableData.getData().isEmpty()) {
                ObservableMap<String, Object> firstRow = tableData.getData().get(0);
                int columnCount = 0;
                for (String columnName : firstRow.keySet()) {
                    if (columnCount < 6) { // Limit to 6 columns for readability
                        TableColumn<ObservableMap<String, Object>, String> column = new TableColumn<>(columnName);
                        column.setCellValueFactory(param -> {
                            ObservableMap<String, Object> value = param.getValue();
                            if (value != null) {
                                return new SimpleStringProperty(value.getOrDefault(columnName, "").toString());
                            }
                            return new SimpleStringProperty("");
                        });
                        tableView.getColumns().add(column);
                        columnCount++;
                    }
                }
            }

            container.getChildren().add(tableView);

            if (block.getContent() != null && !block.getContent().trim().isEmpty()) {
                Label desc = new Label(block.getContent());
                desc.setWrapText(true);
                desc.getStyleClass().add("ai-report-block-content");
                container.getChildren().add(desc);
            }
        } catch (Exception e) {
            System.err.println("Error creating table block: " + e.getMessage());
            Label errorLabel = new Label("Unable to display table: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
            container.getChildren().add(errorLabel);
        }
    }

    private void addChartBlock(VBox container, ReportBlock block) {
        try {
            if (!block.isQuerySuccess()) {
                System.err.println("Skipping chart block due to query error: " + block.getQueryError());
                Label errorLabel = new Label("Unable to execute chart query: " + block.getQueryError());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
                container.getChildren().add(errorLabel);
                return;
            }

            // Create TableDataResult from ReportBlock data
            service.DataVisualization.TableDataResult tableResult = new service.DataVisualization.TableDataResult();
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
            
            service.DataVisualization.ChartDataResult chartData = dataAnalysisAgent.convertToChartData(tableResult);

            if (chartData != null && chartData.hasError()) {
                Label errorLabel = new Label("Query error: " + chartData.getErrorMessage());
                errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
                container.getChildren().add(errorLabel);
                return;
            }

            if (chartData == null || chartData.getChartData() == null || chartData.getChartData().isEmpty()) {
                System.err.println("Skipping chart block: no data returned from query");
                Label errorLabel = new Label("No data returned from query");
                errorLabel.setStyle("-fx-text-fill: #f59e0b; -fx-font-size: 11;");
                container.getChildren().add(errorLabel);
                return;
            }

            Chart chart = createChart(block.getChartType(), chartData);
            chart.setPrefHeight(320);
            chart.setStyle("-fx-padding: 8;");
            container.getChildren().add(chart);

            if (block.getContent() != null && !block.getContent().trim().isEmpty()) {
                Label desc = new Label(block.getContent());
                desc.setWrapText(true);
                desc.setStyle("-fx-font-size: 12; -fx-text-fill: #475569; -fx-padding: 8 0 0 0;");
                container.getChildren().add(desc);
            }
        } catch (Exception e) {
            System.err.println("Error creating chart block: " + e.getMessage());
            Label errorLabel = new Label("Unable to display chart: " + e.getMessage());
            errorLabel.setStyle("-fx-text-fill: #ef4444; -fx-font-size: 11;");
            container.getChildren().add(errorLabel);
        }
    }

    private void addTextBlock(VBox container, ReportBlock block) {
        if (block.getContent() != null && !block.getContent().trim().isEmpty()) {
            VBox textBox = new VBox(6);
            textBox.setPadding(new Insets(8, 0, 0, 0));

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
                    headingLabel.setStyle("-fx-font-size: 14; -fx-font-weight: 700; -fx-text-fill: #0f172a;");
                    textBox.getChildren().add(headingLabel);
                } else {
                    Label textLabel = new Label(line);
                    textLabel.setWrapText(true);
                    textLabel.setStyle("-fx-font-size: 12; -fx-text-fill: #475569;");
                    textBox.getChildren().add(textLabel);
                }
            }

            container.getChildren().add(textBox);
        }
    }

    private Chart createChart(String chartType, service.DataVisualization.ChartDataResult chartData) {
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
            chart = createBarChart(chartData); // Default
        }
        return chart;
    }

    private Chart createPieChart(service.DataVisualization.ChartDataResult chartData) {
        PieChart pieChart = new PieChart();
        pieChart.setTitle("Data Distribution");

        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                String label = entry.getKey();
                PieChart.Data data = new PieChart.Data(label, value);
                pieChart.getData().add(data);
            } catch (Exception e) {
                System.err.println("Error parsing pie chart value: " + e.getMessage());
            }
        }
        return pieChart;
    }

    private Chart createBarChart(service.DataVisualization.ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("Categories");
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Values");

        BarChart<String, Number> barChart = new BarChart<>(xAxis, yAxis);
        barChart.setTitle("Data Analysis");

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Data Series");

        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                series.getData().add(new XYChart.Data<>(entry.getKey(), value));
            } catch (Exception e) {
                System.err.println("Error parsing bar chart value: " + e.getMessage());
            }
        }
        barChart.getData().add(series);
        return barChart;
    }

    private Chart createLineChart(service.DataVisualization.ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("Categories");
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Values");
        LineChart<String, Number> lineChart = new LineChart<>(xAxis, yAxis);
        lineChart.setTitle("Trend Analysis");

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Trend Data");

        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                series.getData().add(new XYChart.Data<>(entry.getKey(), value));
            } catch (Exception e) {
                System.err.println("Error parsing line chart value: " + e.getMessage());
            }
        }
        lineChart.getData().add(series);
        return lineChart;
    }

    private Chart createAreaChart(service.DataVisualization.ChartDataResult chartData) {
        CategoryAxis xAxis = new CategoryAxis();
        xAxis.setLabel("Categories");
        NumberAxis yAxis = new NumberAxis();
        yAxis.setLabel("Values");
        AreaChart<String, Number> areaChart = new AreaChart<>(xAxis, yAxis);
        areaChart.setTitle("Cumulative Analysis");

        XYChart.Series<String, Number> series = new XYChart.Series<>();
        series.setName("Cumulative Data");

        for (var entry : chartData.getChartData().entrySet()) {
            try {
                double value = Double.parseDouble(entry.getValue().toString());
                series.getData().add(new XYChart.Data<>(entry.getKey(), value));
            } catch (Exception e) {
                System.err.println("Error parsing area chart value: " + e.getMessage());
            }
        }
        areaChart.getData().add(series);
        return areaChart;
    }

    private void showReportStatus(String message, boolean success) {
        if (reportStatusLabel != null) {
            reportStatusLabel.setText(message);
            reportStatusLabel.getStyleClass().removeAll("error", "success");
            if (success) {
                reportStatusLabel.getStyleClass().add("success");
            } else if (message.contains("✗ Error")) {
                reportStatusLabel.getStyleClass().add("error");
            }
        }
    }
}
