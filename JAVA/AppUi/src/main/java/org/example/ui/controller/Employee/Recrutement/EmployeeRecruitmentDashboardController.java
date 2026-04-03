package org.example.ui.controller.Employee.Recrutement;

import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.scene.control.ProgressBar;
import org.example.model.Employee;
import service.ApplicationService;
import service.JobOfferService;
import models.Application;
import models.JobOffer;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.time.temporal.ChronoUnit;
import java.util.List;
import java.util.ArrayList;

public class EmployeeRecruitmentDashboardController extends EmployeeBaseController {

    @FXML
    private Label statActiveApps, statInterviews, statNewJobs, statProfileViews;
    
    @FXML
    private VBox myApplicationsContainer, featuredOpportunitiesContainer;
    
    @FXML
    private Label welcomeTitle, welcomeSubtitle;
    
    @FXML
    private Label welcomeAvatarText;
    
    private ApplicationService applicationService;
    private JobOfferService jobOfferService;
    
    public EmployeeRecruitmentDashboardController() {
        this.applicationService = new ApplicationService();
        this.jobOfferService = new JobOfferService();
    }
    
    @Override
    public void setCurrentEmployee(Employee employee) {
        super.setCurrentEmployee(employee);
        // Refresh dashboard data when employee is set
        if (employee != null) {
            loadDashboardData();
        }
    }

    @FXML
    public void initialize() {
        System.out.println("✅ Employee Recruitment Dashboard initialized");
        loadDashboardData();
    }
    
    private void loadDashboardData() {
        try {
            // Load stats
            loadStats();
            
            // Load welcome message
            loadWelcomeMessage();
            
            // Load my applications
            loadMyApplications();
            
            // Load featured opportunities
            loadFeaturedOpportunities();
            
        } catch (Exception e) {
            System.err.println("❌ Error loading dashboard data: " + e.getMessage());
            e.printStackTrace();
        }
    }
    
    private void loadStats() {
        try {
            // Get employee's email to filter applications
            String employeeEmail = currentEmployee != null ? currentEmployee.getEmail() : "john.doe@example.com";
            
            // Load applications for this employee
            List<Application> applications = applicationService.getApplicationsByEmail(employeeEmail);
            
            // Calculate stats
            int activeApplications = applications.size();
            int upcomingInterviews = (int) applications.stream()
                .filter(app -> "INTERVIEW".equals(app.getStatus()))
                .count();
            
            // Load active job offers
            List<JobOffer> jobOffers = jobOfferService.getAllActiveOffers();
            int newJobs = jobOffers.size();
            
            // Update UI
            statActiveApps.setText(String.valueOf(activeApplications));
            statInterviews.setText(String.valueOf(upcomingInterviews));
            statNewJobs.setText(String.valueOf(newJobs));
            statProfileViews.setText("28"); // Placeholder - would need separate service
            
        } catch (Exception e) {
            System.err.println("❌ Error loading stats: " + e.getMessage());
            // Set default values on error
            statActiveApps.setText("0");
            statInterviews.setText("0");
            statNewJobs.setText("0");
            statProfileViews.setText("0");
        }
    }
    
    private void loadWelcomeMessage() {
        if (currentEmployee != null && welcomeTitle != null && welcomeSubtitle != null && welcomeAvatarText != null) {
            String firstName = currentEmployee.getFirstName();
            String lastName = currentEmployee.getLastName();
            String initials = String.valueOf(firstName != null ? firstName.charAt(0) : 'J') + 
                             String.valueOf(lastName != null ? lastName.charAt(0) : 'D');
            
            // Use actual employee name
            String displayName = firstName != null ? firstName : "User";
            welcomeTitle.setText("Welcome back, " + displayName + "! 👋");
            
            try {
                int activeApps = applicationService.getApplicationsByEmail(currentEmployee.getEmail()).size();
                welcomeSubtitle.setText("Ready to explore new opportunities? You have " + 
                    activeApps + " active application" + (activeApps != 1 ? "s" : "") + ".");
            } catch (Exception e) {
                welcomeSubtitle.setText("Ready to explore new opportunities?");
            }
            
            welcomeAvatarText.setText(initials.toUpperCase());
        } else {
            // Fallback when no employee is set
            if (welcomeTitle != null) welcomeTitle.setText("Welcome! 👋");
            if (welcomeSubtitle != null) welcomeSubtitle.setText("Ready to explore new opportunities?");
            if (welcomeAvatarText != null) welcomeAvatarText.setText("U");
        }
    }
    
    private void loadMyApplications() {
        if (myApplicationsContainer == null) return;
        
        try {
            String employeeEmail = currentEmployee != null ? currentEmployee.getEmail() : "john.doe@example.com";
            System.out.println("🔍 Loading applications for employee: " + employeeEmail);
            
            List<Application> applications = applicationService.getApplicationsByEmail(employeeEmail);
            System.out.println("📊 Found " + applications.size() + " applications");
            
            // Clear existing content
            myApplicationsContainer.getChildren().clear();
            
            if (applications.isEmpty()) {
                // Show no applications message
                Label placeholder = new Label("No applications found. Start exploring opportunities!");
                placeholder.setStyle("-fx-text-fill: #64748b; -fx-font-size: 13px; -fx-padding: 20 24 20 24;");
                myApplicationsContainer.getChildren().add(placeholder);
                return;
            }
            
            // Show up to 3 most recent applications
            int displayCount = Math.min(3, applications.size());
            for (int i = 0; i < displayCount; i++) {
                Application app = applications.get(i);
                System.out.println("📋 Adding application: " + app.getJobTitle() + " (Status: " + app.getStatus() + ")");
                HBox applicationItem = createApplicationItem(app);
                myApplicationsContainer.getChildren().add(applicationItem);
            }
            
        } catch (Exception e) {
            System.err.println("❌ Error loading applications: " + e.getMessage());
            e.printStackTrace();
            // Show placeholder
            Label placeholder = new Label("Unable to load applications. Please try again.");
            placeholder.setStyle("-fx-text-fill: #dc2626; -fx-font-size: 13px; -fx-padding: 20 24 20 24;");
            myApplicationsContainer.getChildren().add(placeholder);
        }
    }
    
    private void loadFeaturedOpportunities() {
        if (featuredOpportunitiesContainer == null) return;
        
        try {
            List<JobOffer> jobOffers = jobOfferService.getAllActiveOffers();
            
            // Clear existing content
            featuredOpportunitiesContainer.getChildren().clear();
            
            // Show up to 3 featured opportunities
            int displayCount = Math.min(3, jobOffers.size());
            for (int i = 0; i < displayCount; i++) {
                JobOffer job = jobOffers.get(i);
                HBox jobItem = createJobOfferItem(job);
                featuredOpportunitiesContainer.getChildren().add(jobItem);
            }
            
        } catch (Exception e) {
            System.err.println("❌ Error loading job opportunities: " + e.getMessage());
            // Show placeholder
            Label placeholder = new Label("No opportunities available");
            placeholder.setStyle("-fx-text-fill: #64748b; -fx-font-size: 13px; -fx-padding: 20 24 20 24;");
            featuredOpportunitiesContainer.getChildren().add(placeholder);
        }
    }
    
    private HBox createApplicationItem(Application application) {
        HBox item = new HBox();
        item.setSpacing(12);
        item.getStyleClass().add("application-status-item");
        
        // Main content
        VBox content = new VBox();
        content.setSpacing(6);
        HBox.setHgrow(content, javafx.scene.layout.Priority.ALWAYS);
        
        // Job title
        Label jobTitleLabel = new Label(application.getJobTitle());
        jobTitleLabel.getStyleClass().add("application-job-title");
        
        // Metadata
        String metadata = (application.getDepartment() != null ? application.getDepartment() : "Unknown") + 
                        " • Applied " + formatTimeAgo(application.getAppliedAt());
        Label metadataLabel = new Label(metadata);
        metadataLabel.getStyleClass().add("application-job-meta");
        
        // Status and progress
        HBox statusBox = new HBox();
        statusBox.setSpacing(8);
        
        Label statusBadge = new Label(formatStatus(application.getStatus()));
        statusBadge.getStyleClass().add("badge");
        statusBadge.getStyleClass().add(getStatusBadgeClass(application.getStatus()));
        
        Label dot = new Label("•");
        dot.getStyleClass().add("timeline-dot");
        
        Label progressText = new Label(getProgressText(application.getStatus()));
        progressText.getStyleClass().add("application-progress-text");
        
        statusBox.getChildren().addAll(statusBadge, dot, progressText);
        
        content.getChildren().addAll(jobTitleLabel, metadataLabel, statusBox);
        
        // Progress section
        VBox progressSection = new VBox();
        progressSection.setSpacing(6);
        progressSection.setAlignment(javafx.geometry.Pos.CENTER_RIGHT);
        
        ProgressBar progressBar = new ProgressBar();
        progressBar.setProgress(calculateProgress(application.getStatus()));
        progressBar.getStyleClass().add("application-progress-bar");
        progressBar.setPrefWidth(80);
        
        Label progressLabel = new Label((int)(calculateProgress(application.getStatus()) * 100) + "%");
        progressLabel.getStyleClass().add("application-progress-label");
        
        progressSection.getChildren().addAll(progressBar, progressLabel);
        
        item.getChildren().addAll(content, progressSection);
        
        return item;
    }
    
    private HBox createJobOfferItem(JobOffer jobOffer) {
        HBox item = new HBox();
        item.setSpacing(12);
        item.getStyleClass().add("application-status-item");
        
        VBox content = new VBox();
        content.setSpacing(4);
        HBox.setHgrow(content, javafx.scene.layout.Priority.ALWAYS);
        
        // Job title with emoji
        String jobTitle = getJobEmoji(jobOffer.getDepartment()) + " " + jobOffer.getTitle();
        Label titleLabel = new Label(jobTitle);
        titleLabel.getStyleClass().add("application-job-title");
        
        // Job details
        String details = (jobOffer.getDepartment() != null ? jobOffer.getDepartment() : "Unknown") + 
                        " • " + (jobOffer.getLocation() != null ? jobOffer.getLocation() : "Remote") + 
                        " • $" + (int)jobOffer.getSalaryMin() + "k-$" + (int)jobOffer.getSalaryMax() + "k";
        Label detailsLabel = new Label(details);
        detailsLabel.getStyleClass().add("application-job-meta");
        
        // Additional info
        String info = "Posted " + formatTimeAgo(jobOffer.getCreatedAt()) + " • Open";
        Label infoLabel = new Label(info);
        infoLabel.getStyleClass().add("application-progress-text");
        
        content.getChildren().addAll(titleLabel, detailsLabel, infoLabel);
        
        item.getChildren().add(content);
        
        return item;
    }
    
    // Helper methods
    private String formatTimeAgo(LocalDateTime dateTime) {
        if (dateTime == null) return "recently";
        
        LocalDateTime now = LocalDateTime.now();
        long days = ChronoUnit.DAYS.between(dateTime, now);
        
        if (days == 0) return "today";
        if (days == 1) return "yesterday";
        if (days < 7) return days + " days ago";
        if (days < 30) return (days / 7) + " week" + ((days / 7) != 1 ? "s" : "") + " ago";
        return (days / 30) + " month" + ((days / 30) != 1 ? "s" : "") + " ago";
    }
    
    private String formatStatus(String status) {
        if (status == null) return "Unknown";
        
        switch (status.toUpperCase()) {
            case "APPLIED": return "Applied";
            case "SCREENING": return "Under Review";
            case "SHORTLISTED": return "Shortlisted";
            case "INTERVIEW": return "Interview";
            case "OFFERED": return "Offered";
            case "REJECTED": return "Rejected";
            case "HIRED": return "Hired";
            default: return status;
        }
    }
    
    private String getStatusBadgeClass(String status) {
        if (status == null) return "badge-new";
        
        switch (status.toUpperCase()) {
            case "APPLIED": return "badge-new";
            case "SCREENING": return "badge-new";
            case "SHORTLISTED": return "badge-shortlisted";
            case "INTERVIEW": return "badge-interview";
            case "OFFERED": return "badge-offer";
            case "REJECTED": return "badge-rejected";
            case "HIRED": return "badge-offer";
            default: return "badge-new";
        }
    }
    
    private double calculateProgress(String status) {
        if (status == null) return 0.0;
        
        switch (status.toUpperCase()) {
            case "APPLIED": return 0.1;
            case "SCREENING": return 0.33;
            case "SHORTLISTED": return 0.5;
            case "INTERVIEW": return 0.66;
            case "OFFERED": return 0.85;
            case "HIRED": return 1.0;
            case "REJECTED": return 0.0;
            default: return 0.0;
        }
    }
    
    private String getProgressText(String status) {
        if (status == null) return "Status unknown";
        
        switch (status.toUpperCase()) {
            case "APPLIED": return "Application received";
            case "SCREENING": return "Reviewed by HR";
            case "SHORTLISTED": return "Awaiting next round";
            case "INTERVIEW": return "Interview scheduled";
            case "OFFERED": return "Offer extended";
            case "REJECTED": return "Not selected";
            case "HIRED": return "Welcome aboard!";
            default: return "In progress";
        }
    }
    
    private String getJobEmoji(String department) {
        if (department == null) return "💼";
        
        String dept = department.toLowerCase();
        if (dept.contains("engineer") || dept.contains("develop") || dept.contains("tech")) return "💻";
        if (dept.contains("design") || dept.contains("creative") || dept.contains("ux")) return "🎨";
        if (dept.contains("data") || dept.contains("analytic") || dept.contains("science")) return "📊";
        if (dept.contains("market") || dept.contains("sales") || dept.contains("business")) return "📈";
        if (dept.contains("hr") || dept.contains("human") || dept.contains("people")) return "👥";
        if (dept.contains("finance") || dept.contains("account") || dept.contains("money")) return "💰";
        if (dept.contains("operation") || dept.contains("admin") || dept.contains("support")) return "⚙️";
        
        return "💼";
    }

    @FXML
    private void handleCompleteProfile() {
        // Logic to show profile or settings
        if (mainController != null) {
            // mainController.loadView("/fxml/views/Employee-dashboard/EmployeeSettingsView.fxml");
            mainController.showInfoAlert("Coming Soon", "Profile completion feature is coming soon!");
        }
    }

    @FXML
    private void handleViewAllApplications() {
        if (mainController != null) {
            mainController.handleMyApplications();
        }
    }

    @FXML
    private void handleBrowseJobs() {
        if (mainController != null) {
            mainController.handleBrowseJobs();
        }
    }

    @Override
    public void handleSave() {
        // Not used in dashboard
    }

    @Override
    public void clearFields() {
        // Not used in dashboard - but we can implement a refresh instead
        loadDashboardData();
    }
    
    public void refreshDashboard() {
        loadDashboardData();
    }
}
