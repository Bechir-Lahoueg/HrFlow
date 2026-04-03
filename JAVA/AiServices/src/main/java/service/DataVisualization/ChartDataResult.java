package service.DataVisualization;

import javafx.collections.FXCollections;
import javafx.collections.ObservableMap;

/**
 * Chart Data Result - Contains chart query results
 */
public class ChartDataResult {
    private String recommendedType;
    private ObservableMap<String, Object> chartData;
    private String errorMessage;

    public ChartDataResult() {
        this.recommendedType = "BAR";
        this.chartData = FXCollections.observableHashMap();
        this.errorMessage = null;
    }

    public ChartDataResult(String recommendedType) {
        this.recommendedType = recommendedType;
        this.chartData = FXCollections.observableHashMap();
        this.errorMessage = null;
    }

    public String getRecommendedType() {
        return recommendedType;
    }

    public ObservableMap<String, Object> getChartData() {
        return chartData;
    }

    public void setRecommendedType(String recommendedType) {
        this.recommendedType = recommendedType;
    }

    public void setChartData(ObservableMap<String, Object> chartData) {
        this.chartData = chartData;
    }

    public String getErrorMessage() {
        return errorMessage;
    }

    public void setErrorMessage(String errorMessage) {
        this.errorMessage = errorMessage;
    }

    public boolean hasError() {
        return errorMessage != null && !errorMessage.isEmpty();
    }
}
