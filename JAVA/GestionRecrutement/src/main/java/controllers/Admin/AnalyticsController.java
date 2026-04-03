package controllers;

import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.scene.chart.BarChart;
import javafx.scene.chart.CategoryAxis;
import javafx.scene.chart.LineChart;
import javafx.scene.chart.NumberAxis;
import javafx.scene.chart.XYChart;
import service.ApplicationService;
import service.InterviewService;
import java.sql.SQLException;
import java.util.Map;

public class AnalyticsController extends BaseController {

    @FXML
    private Label hiringVelocityLabel;
    @FXML
    private Label passRateLabel;
    @FXML
    private BarChart<String, Number> funnelChart;
    @FXML
    private LineChart<String, Number> trendsChart;

    private final ApplicationService applicationService = new ApplicationService();
    private final InterviewService interviewService = new InterviewService();

    @FXML
    public void initialize() {
        loadAnalyticsData();
    }

    private void loadAnalyticsData() {
        try {
            // Load Metrics
            double velocity = applicationService.getHiringVelocity();
            hiringVelocityLabel.setText(String.format("%.1f days", velocity));

            double passRate = interviewService.getInterviewPassRate();
            passRateLabel.setText(String.format("%.1f%%", passRate));

            // Load Funnel Data
            Map<String, Integer> funnelData = applicationService.getPipelineFunnelData();
            XYChart.Series<String, Number> funnelSeries = new XYChart.Series<>();
            funnelSeries.setName("Applications by Status");
            for (Map.Entry<String, Integer> entry : funnelData.entrySet()) {
                funnelSeries.getData().add(new XYChart.Data<>(entry.getKey(), entry.getValue()));
            }
            funnelChart.getData().clear();
            funnelChart.getData().add(funnelSeries);

            // Load Trends Data
            Map<String, Integer> trendsData = applicationService.getApplicationTrends();
            XYChart.Series<String, Number> trendsSeries = new XYChart.Series<>();
            trendsSeries.setName("Daily Applications");
            for (Map.Entry<String, Integer> entry : trendsData.entrySet()) {
                trendsSeries.getData().add(new XYChart.Data<>(entry.getKey(), entry.getValue()));
            }
            trendsChart.getData().clear();
            trendsChart.getData().add(trendsSeries);

        } catch (SQLException e) {
            e.printStackTrace();
            System.err.println("Failed to load analytics data: " + e.getMessage());
        }
    }
}
