package controllers.Employee;

import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import service.ApplicationService;
import service.JobOfferService;
import utils.EmployeeSession;
import java.sql.SQLException;
import java.util.List;

public class EmployeeDashboardController extends EmployeeBaseController {

    @FXML
    private Label statActiveApps, statInterviews, statNewJobs, statProfileViews;

    private ApplicationService applicationService = new ApplicationService();
    private JobOfferService jobOfferService = new JobOfferService();

    @FXML
    public void initialize() {
        System.out.println("📊 Loading Employee Dashboard...");
        try {
            System.out.println("   - Fetching dashboard data...");
            loadDashboardData();
            System.out.println("✅ Dashboard initialization complete");
        } catch (Exception e) {
            System.err.println("❌ Error during Dashboard initialization: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void loadDashboardData() {
        try {
            // Load real statistics from database using employee email
            String email = EmployeeSession.getInstance().getEmployeeEmail();
            List<models.Application> applications = applicationService.getApplicationsByEmail(email);
            List<models.JobOffer> availableJobs = jobOfferService.getAllActiveOffers();

            // Active Applications (APPLIED or SCREENING)
            if (statActiveApps != null) {
                long activeCount = applications.stream()
                        .filter(app -> "APPLIED".equalsIgnoreCase(app.getStatus()) ||
                                "SCREENING".equalsIgnoreCase(app.getStatus()))
                        .count();
                statActiveApps.setText(String.valueOf(activeCount));
                System.out.println("✅ Active Applications: " + activeCount);
            }

            // Interviews (SHORTLISTED or INTERVIEW)
            if (statInterviews != null) {
                long interviewCount = applications.stream()
                        .filter(app -> "SHORTLISTED".equalsIgnoreCase(app.getStatus()) ||
                                "INTERVIEW".equalsIgnoreCase(app.getStatus()))
                        .count();
                statInterviews.setText(String.valueOf(interviewCount));
                System.out.println("✅ Interview Count: " + interviewCount);
            }

            // New Job Opportunities
            if (statNewJobs != null) {
                int newJobCount = availableJobs.size();
                statNewJobs.setText(String.valueOf(newJobCount));
                System.out.println("✅ New Opportunities: " + newJobCount);
            }

            // Profile Views - mock data
            if (statProfileViews != null) {
                statProfileViews.setText("28"); // Mock profile views
                System.out.println("✅ Profile Views: 28");
            }

            System.out.println("✅ Dashboard data loaded successfully");

        } catch (SQLException e) {
            System.err.println("❌ Dashboard Error: " + e.getMessage());
            showErrorAlert("Dashboard Error", "Failed to load dashboard data: " + e.getMessage());
        }
    }

    private int getCurrentEmployeeId() {
        // TODO: In production, this should come from session/authentication context
        // Using EmployeeSession utility for employee-specific data
        return EmployeeSession.getInstance().getEmployeeId();
    }

    public int getEmployeeId() {
        return EmployeeSession.getInstance().getEmployeeId();
    }

    @FXML
    private void handleCompleteProfile() {
        showInfoAlert("Profile", "Complete your profile to improve application success rate!");
    }

    @FXML
    private void handleViewAllApplications() {
        System.out.println("📋 Navigating to view all applications...");
        if (mainController != null) {
            mainController.handleMyApplications();
        }
    }

    @FXML
    private void handleBrowseJobs() {
        System.out.println("🔍 Navigating to browse jobs...");
        if (mainController != null) {
            mainController.handleBrowseJobs();
        }
    }

    @FXML
    private void handleViewJob() {
        System.out.println("👁️ Viewing job details...");
        // This will be called from dashboard job cards
        // The actual job data would come from the JobOffer context
    }

    @FXML
    private void handleApplyJob() {
        System.out.println("📝 Applying for job...");
        // This will be called from dashboard job cards
        // The actual application would be handled by EmployeeApplicationFormController
    }

    @FXML
    private void handleViewApplications() {
        if (mainController != null) {
            mainController.handleMyApplications();
        }
    }

    @Override
    public void handleSave() {
        // Dashboard doesn't have save functionality
    }

    @Override
    public void clearFields() {
        // Dashboard doesn't have fields to clear
    }
}
