package controllers.RH;

import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ProgressBar;
import service.ApplicationService;
import service.JobOfferService;
import service.InterviewService;
import java.sql.SQLException;
import java.util.List;

public class HRDashboardController extends RHBaseController {
    
    @FXML
    private Label statActiveCandidates, statInterviewsToday, statShortlisted, statPendingOffers;
    @FXML
    private Label statHiringRate, statTimeToHire, statConversionRate, statActiveJobs;
    @FXML
    private Label pipelineApplied, pipelineScreening, pipelineInterview, pipelineOffer, pipelineHired;
    @FXML
    private ProgressBar progressApplied, progressScreening, progressInterview, progressOffer, progressHired;
    @FXML
    private Button btnViewApplications, btnViewInterviews, btnViewJobs, btnViewReports;
    
    private ApplicationService applicationService = new ApplicationService();
    private JobOfferService jobOfferService = new JobOfferService();
    private InterviewService interviewService = new InterviewService();
    
    @FXML
    public void initialize() {
        loadDashboardData();
    }
    
    private void loadDashboardData() {
        try {
            // Load application statistics
            List<models.Application> applications = applicationService.getActiveApplications();
            List<models.JobOffer> jobOffers = jobOfferService.getAllActiveOffers();
            List<models.Interview> interviews = interviewService.getActiveInterviews();
            
            long hiredCount = 0;
            
            int totalApps = applications.size();
            if (statActiveCandidates != null) {
                long activeCount = applications.stream()
                    .filter(app -> "APPLIED".equalsIgnoreCase(app.getStatus()) || 
                                   "UNDER_REVIEW".equalsIgnoreCase(app.getStatus()) ||
                                   "SHORTLISTED".equalsIgnoreCase(app.getStatus()))
                    .count();
                statActiveCandidates.setText(String.valueOf(activeCount));
            }

            if (statInterviewsToday != null) {
                statInterviewsToday.setText(String.valueOf(interviews.size()));
            }

            if (statPendingOffers != null) {
                long pendingCount = applications.stream()
                    .filter(app -> "OFFERED".equalsIgnoreCase(app.getStatus()))
                    .count();
                statPendingOffers.setText(String.valueOf(pendingCount));
            }
            
            if (statHiringRate != null) {
                hiredCount = applications.stream()
                    .filter(app -> "HIRED".equalsIgnoreCase(app.getStatus()))
                    .count();
                double hiringRate = totalApps > 0 ? (hiredCount * 100.0 / totalApps) : 0.0;
                statHiringRate.setText(String.format("%.1f%%", hiringRate));
            }
            
            if (statTimeToHire != null) {
                // Mock calculation - in real app this would be calculated from database
                statTimeToHire.setText("15 days");
            }
            
            if (statConversionRate != null) {
                long interviewedCount = applications.stream()
                    .filter(app -> "INTERVIEW".equalsIgnoreCase(app.getStatus()))
                    .count();
                double conversionRate = interviewedCount > 0 ? (hiredCount * 100.0 / interviewedCount) : 0.0;
                statConversionRate.setText(String.format("%.1f%%", conversionRate));
            }
            
            if (statActiveJobs != null) {
                statActiveJobs.setText(String.valueOf(jobOffers.size()));
            }

            // Pipeline: populate counts and progressbars
            if (pipelineApplied != null) {
                java.util.Map<String, Integer> funnel = applicationService.getPipelineFunnelData();
                int applied = funnel.getOrDefault("APPLIED", 0);
                int screening = funnel.getOrDefault("SCREENING", 0);
                int interview = funnel.getOrDefault("INTERVIEW", 0);
                int offer = funnel.getOrDefault("OFFER", 0);
                int hired = funnel.getOrDefault("HIRED", 0);
                int total = applied + screening + interview + offer + hired;

                pipelineApplied.setText(String.valueOf(applied));
                pipelineScreening.setText(String.valueOf(screening));
                pipelineInterview.setText(String.valueOf(interview));
                pipelineOffer.setText(String.valueOf(offer));
                pipelineHired.setText(String.valueOf(hired));

                if (progressApplied != null) progressApplied.setProgress(total == 0 ? 0 : (double) applied / total);
                if (progressScreening != null) progressScreening.setProgress(total == 0 ? 0 : (double) screening / total);
                if (progressInterview != null) progressInterview.setProgress(total == 0 ? 0 : (double) interview / total);
                if (progressOffer != null) progressOffer.setProgress(total == 0 ? 0 : (double) offer / total);
                if (progressHired != null) progressHired.setProgress(total == 0 ? 0 : (double) hired / total);
            }
            
            System.out.println("✅ HR Dashboard data loaded successfully");
            
        } catch (SQLException e) {
            System.err.println("❌ HR Dashboard Error: " + e.getMessage());
            showErrorAlert("Dashboard Error", "Failed to load dashboard data: " + e.getMessage());
        }
    }
    
    @FXML
    private void handleViewApplications() {
        if (mainController != null) {
            mainController.handleCandidates();
        }
    }
    
    @FXML
    private void handleViewInterviews() {
        if (mainController != null) {
            mainController.handleInterviews();
        }
    }
    
    @FXML
    private void handleViewJobs() {
        if (mainController != null) {
            mainController.handleOffers();
        }
    }
    
    @FXML
    private void handleViewReports() {
        showInfoAlert("Reports", "HR analytics reports coming soon!");
    }

    @FXML
    private void handleViewAllInterviews() {
        System.out.println("📋 Navigating to all interviews...");
        if (mainController != null) {
            mainController.handleInterviews();
        }
    }

    @FXML
    private void handleJoinInterview() {
        System.out.println("📹 Joining interview...");
        showInfoAlert("Interview", "Starting video conference...");
    }

    @FXML
    private void handleViewDetails() {
        System.out.println("📄 Viewing interview details...");
        showInfoAlert("Details", "Interview details view (coming soon)");
    }

    @FXML
    private void handleReschedule() {
        System.out.println("📅 Rescheduling interview...");
        showInfoAlert("Reschedule", "Interview reschedule (coming soon)");
    }

    @FXML
    private void handleViewAllCandidates() {
        System.out.println("👥 Navigating to all candidates...");
        if (mainController != null) {
            mainController.handleCandidates();
        }
    }

    @FXML
    private void handleViewProfile() {
        System.out.println("👤 Viewing candidate profile...");
        showInfoAlert("Profile", "Candidate profile view (coming soon)");
    }

    @FXML
    private void handleViewPipeline() {
        System.out.println("📊 Viewing hiring pipeline...");
        if (mainController != null) {
            mainController.handlePipeline();
        }
    }
    
}
