package org.example.ui.util;

import javafx.scene.chart.*;
import service.DataVisualization.ChartDataResult;

/**
 * Utility class for creating charts with consistent styling and behavior
 * Eliminates code duplication across controllers
 */
public class ChartFactory {

    /**
     * Creates a chart based on the specified type
     * 
     * @param chartType The type of chart (PIE, BAR, LINE, AREA)
     * @param chartData The data to populate the chart with
     * @return The created Chart instance
     */
    public static Chart createChart(String chartType, ChartDataResult chartData) {
        if ("PIE".equalsIgnoreCase(chartType)) {
            return createPieChart(chartData);
        } else if ("BAR".equalsIgnoreCase(chartType)) {
            return createBarChart(chartData);
        } else if ("LINE".equalsIgnoreCase(chartType)) {
            return createLineChart(chartData);
        } else if ("AREA".equalsIgnoreCase(chartType)) {
            return createAreaChart(chartData);
        } else {
            return createBarChart(chartData); // Default to bar chart
        }
    }

    /**
     * Creates a PieChart with the provided data
     */
    public static PieChart createPieChart(ChartDataResult chartData) {
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

    /**
     * Creates a BarChart with the provided data
     */
    public static BarChart<String, Number> createBarChart(ChartDataResult chartData) {
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
                String label = entry.getKey();
                series.getData().add(new XYChart.Data<>(label, value));
            } catch (Exception e) {
                System.err.println("Error parsing bar chart value: " + e.getMessage());
            }
        }
        barChart.getData().add(series);
        return barChart;
    }

    /**
     * Creates a LineChart with the provided data
     */
    public static LineChart<String, Number> createLineChart(ChartDataResult chartData) {
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
                String label = entry.getKey();
                series.getData().add(new XYChart.Data<>(label, value));
            } catch (Exception e) {
                System.err.println("Error parsing line chart value: " + e.getMessage());
            }
        }
        lineChart.getData().add(series);
        return lineChart;
    }

    /**
     * Creates an AreaChart with the provided data
     */
    public static AreaChart<String, Number> createAreaChart(ChartDataResult chartData) {
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
                String label = entry.getKey();
                series.getData().add(new XYChart.Data<>(label, value));
            } catch (Exception e) {
                System.err.println("Error parsing area chart value: " + e.getMessage());
            }
        }
        areaChart.getData().add(series);
        return areaChart;
    }
}
