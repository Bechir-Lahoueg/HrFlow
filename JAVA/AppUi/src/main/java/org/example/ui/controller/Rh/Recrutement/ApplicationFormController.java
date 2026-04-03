package org.example.ui.controller.Rh.Recrutement;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import models.Application;
import models.JobOffer;
import service.ApplicationService;
import service.JobOfferService;
import org.example.ui.controller.Rh.RHBaseController;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.List;

public class ApplicationFormController extends RHBaseController {

    @FXML
    private TextField fieldCandidate, fieldEmail, fieldResumeUrl;
    @FXML
    private ComboBox<String> fieldJob, fieldDepartment, fieldStage, fieldExperience;
    @FXML
    private DatePicker fieldDateApplied;
    @FXML
    private TextArea fieldCoverLetter;

    private ApplicationService applicationService = new ApplicationService();
    private JobOfferService jobOfferService = new JobOfferService();
    private Application editingApplication;
    private Runnable onSaveCallback;

    @FXML
    public void initialize() {
        try {
            // Populate jobs from service - ensure we have real job offers
            List<String> jobs = applicationService.getPositions();
            if (jobs.isEmpty()) {
                // Create a default job offer if none exist
                createDefaultJobOffer();
                jobs = applicationService.getPositions(); // Refresh after creating
            }
            fieldJob.setItems(FXCollections.observableArrayList(jobs));

            // Stages and departments are static in FXML but we can refresh them if needed
        } catch (SQLException e) {
            System.err.println("Failed to load jobs: " + e.getMessage());
        }
    }

    private void createDefaultJobOffer() {
        try {
            // Create a default job offer that applications can reference
            String defaultTitle = "General Position";
            String defaultDept = "Engineering";
            
            // Check if this job already exists
            if (!jobOfferExists(defaultTitle)) {
                // Create the job offer using JobOfferService
                JobOffer defaultJob = new JobOffer(
                    defaultTitle,
                    "General position for applications",
                    defaultDept,
                    "Remote",
                    "Full-Time",
                    5000,
                    8000,
                    "OPEN",
                    LocalDateTime.now(),
                    1
                );
                
                jobOfferService.createOffer(defaultJob);
                System.out.println("✅ Created default job offer '" + defaultTitle + "' for applications to reference");
            }
        } catch (Exception e) {
            System.err.println("Failed to create default job offer: " + e.getMessage());
        }
    }

    private boolean jobOfferExists(String title) {
        try {
            List<String> jobs = applicationService.getPositions();
            return jobs.contains(title);
        } catch (SQLException e) {
            return false;
        }
    }

    public void setEditingApplication(Application app) {
        this.editingApplication = app;
        if (app != null) {
            fieldCandidate.setText(app.getCandidateName());
            fieldEmail.setText(app.getEmailAddress());
            fieldJob.setValue(app.getJobTitle());
            fieldDepartment.setValue(app.getDepartment());
            fieldStage.setValue(app.getStatus());
            if (app.getAppliedAt() != null) {
                fieldDateApplied.setValue(app.getAppliedAt().toLocalDate());
            }
            fieldExperience.setValue(app.getExperienceLevel());
            fieldResumeUrl.setText(app.getCvPath() != null ? app.getCvPath() : "");
            fieldCoverLetter.setText(app.getNotes());
        }
    }

    public void setOnSaveCallback(Runnable callback) {
        this.onSaveCallback = callback;
    }

    @Override
    public void handleSave() {
        if (!validateFields())
            return;

        try {
            Application app = (editingApplication != null) ? editingApplication : new Application();
            app.setCandidateName(fieldCandidate.getText());
            app.setEmailAddress(fieldEmail.getText());
            app.setJobTitle(fieldJob.getValue());
            app.setDepartment(fieldDepartment.getValue());
            app.setStatus(fieldStage.getValue());
            app.setExperienceLevel(fieldExperience.getValue());
            app.setNotes(fieldCoverLetter.getText());
            app.setCvPath(fieldResumeUrl.getText());

            if (editingApplication == null) {
                app.setAppliedAt(fieldDateApplied.getValue() != null ? fieldDateApplied.getValue().atStartOfDay()
                        : LocalDateTime.now());
                // Set a default employee ID (RH user creating the application)
                app.setEmployeeId(1); // Default employee ID - could be made dynamic
                // Set job offer ID based on selected job title
                app.setJobOfferId(getJobOfferIdByTitle(fieldJob.getValue()));
                applicationService.addApplication(app);
                showSuccessAlert("Success", "Application created successfully.");
            } else {
                applicationService.updateApplication(app);
                showSuccessAlert("Success", "Application updated successfully.");
            }

            if (onSaveCallback != null)
                onSaveCallback.run();
            if (mainController != null)
                mainController.handleCloseModal();

        } catch (SQLException e) {
            showErrorAlert("Database Error", "Could not save application: " + e.getMessage());
        }
    }

    private boolean validateFields() {
        System.out.println(" [DEBUG] ApplicationFormController - validateFields() called");
        System.out.println(" [DEBUG] mainController: " + (mainController != null ? mainController.getClass().getSimpleName() : "null"));
        
        // Clear previous error
        mainController.setFormError("");

        if (fieldCandidate.getText().trim().isEmpty()) {
            System.out.println(" [DEBUG] Validation failed: Candidate name is empty");
            mainController.setFormError("Candidate name is required.");
            return false;
        }

        String email = fieldEmail.getText().trim();
        if (email.isEmpty()) {
            System.out.println(" [DEBUG] Validation failed: Email is empty");
            mainController.setFormError("Email address is required.");
            return false;
        }
        if (!email.matches("^[A-Za-z0-9+_.-]+@(.+)$")) {
            System.out.println(" [DEBUG] Validation failed: Invalid email format: " + email);
            mainController.setFormError("Please enter a valid email address.");
            return false;
        }

        if (fieldJob.getValue() == null) {
            System.out.println(" [DEBUG] Validation failed: Job not selected");
            mainController.setFormError("Please select a job position.");
            return false;
        }
        if (fieldDepartment.getValue() == null) {
            System.out.println(" [DEBUG] Validation failed: Department not selected");
            mainController.setFormError("Please select a department.");
            return false;
        }
        if (fieldStage.getValue() == null) {
            System.out.println(" [DEBUG] Validation failed: Stage not selected");
            mainController.setFormError("Please select an application stage.");
            return false;
        }
        if (fieldExperience.getValue() == null) {
            mainController.setFormError("Please select experience level.");
            return false;
        }

        return true;
    }

    private int getJobOfferIdByTitle(String jobTitle) {
        try {
            // Get all job positions and find the matching one
            List<String> jobs = applicationService.getPositions();
            
            if (jobTitle == null || jobTitle.trim().isEmpty()) {
                // Return first available job ID if no specific title selected
                return jobs.isEmpty() ? 1 : 1; // Default fallback
            }
            
            // Find the exact match or return first available
            for (int i = 0; i < jobs.size(); i++) {
                if (jobTitle.equals(jobs.get(i))) {
                    return i + 1; // Job IDs are 1-based in database
                }
            }
            
            // If no exact match, return first available job
            return jobs.isEmpty() ? 1 : 1;
        } catch (SQLException e) {
            System.err.println("Error getting job offer ID: " + e.getMessage());
            return 1; // Fallback to default
        }
    }

    @Override
    public void clearFields() {
        fieldCandidate.clear();
        fieldEmail.clear();
        fieldJob.setValue(null);
        fieldDepartment.setValue(null);
        fieldStage.setValue(null);
        fieldDateApplied.setValue(null);
        fieldExperience.setValue(null);
        fieldResumeUrl.clear();
        fieldCoverLetter.clear();
    }
}
