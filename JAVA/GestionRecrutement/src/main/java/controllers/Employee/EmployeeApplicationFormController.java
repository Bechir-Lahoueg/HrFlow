package controllers.Employee;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import models.Application;
import models.JobOffer;
import service.ApplicationService;
import utils.EmployeeSession;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.List;

public class EmployeeApplicationFormController extends EmployeeBaseController {

    @FXML
    private Label jobTitleLabel, departmentLabel, locationLabel, typeLabel;
    @FXML
    private TextField fieldEmail;
    @FXML
    private TextArea fieldCoverLetter;
    @FXML
    private ComboBox<String> fieldExperience, fieldSource;
    @FXML
    private Button btnSubmit, btnCancel;
    @FXML
    private Label formErrorLabel;

    private ApplicationService applicationService = new ApplicationService();
    private JobOffer currentJobOffer;

    // Use EmployeeSession for current employee data
    private EmployeeSession employeeSession = EmployeeSession.getInstance();
    private final String currentEmployeeName = employeeSession.getEmployeeName();
    private final String currentEmployeeEmail = employeeSession.getEmployeeEmail();
    private final int currentEmployeeId = employeeSession.getEmployeeId();

    @FXML
    public void initialize() {
        setupForm();
    }

    private void setupForm() {
        // Populate experience levels
        if (fieldExperience != null) {
            fieldExperience.getItems().addAll(
                    "Entry Level (0-2 yrs)",
                    "Mid Level (2-5 yrs)",
                    "Senior Level (5-10 yrs)",
                    "Lead/Principal (10+ yrs)");
            fieldExperience.setValue("Mid Level (2-5 yrs)");
        }

        // Populate application sources
        if (fieldSource != null) {
            fieldSource.getItems().addAll(
                    "Company Website",
                    "LinkedIn",
                    "Indeed",
                    "Glassdoor",
                    "Employee Referral",
                    "Job Fair",
                    "Other");
            fieldSource.setValue("Company Website");
        }

        // Pre-fill employee information
        if (fieldEmail != null) {
            String email = currentEmployeeEmail;
            if (email == null || email.trim().isEmpty()) {
                // Fallback to a default email if session is not properly initialized
                email = "employee@company.com";
                System.err.println("⚠️ Employee email not found in session, using fallback: " + email);
            }
            fieldEmail.setText(email);
            fieldEmail.setDisable(true); // Employee email is typically not editable
            System.out.println("✅ Email field pre-filled with: " + email);
        }
    }

    public void setJobOffer(JobOffer jobOffer) {
        this.currentJobOffer = jobOffer;
        updateJobInfo();
    }

    private void updateJobInfo() {
        if (currentJobOffer != null) {
            if (jobTitleLabel != null) {
                jobTitleLabel.setText(currentJobOffer.getTitle());
            }
            if (departmentLabel != null) {
                departmentLabel.setText(currentJobOffer.getDepartment());
            }
            if (locationLabel != null) {
                locationLabel.setText(currentJobOffer.getLocation());
            }
            if (typeLabel != null) {
                typeLabel.setText(currentJobOffer.getEmploymentType());
            }
        }
    }

    @FXML
    private void handleSubmit() {
        if (validateInputs()) {
            try {
                submitApplication();
            } catch (SQLException e) {
                showErrorAlert("Database Error", "Failed to submit application: " + e.getMessage());
            }
        }
    }

    @FXML
    private void handleCancel() {
        if (mainController != null) {
            mainController.handleCloseModal();
        }
    }

    private boolean validateInputs() {
        if (formErrorLabel != null) {
            formErrorLabel.setText("");
        }
        
        // Enhanced email validation
        if (fieldEmail == null) {
            setFormError("Email field is not available");
            return false;
        }
        
        String email = fieldEmail.getText().trim();
        if (email.isEmpty()) {
            setFormError("Email address is required");
            return false;
        }
        
        // Basic email format validation
        if (!email.matches("^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$")) {
            setFormError("Please enter a valid email address");
            return false;
        }
        
        if (fieldCoverLetter == null || fieldCoverLetter.getText().trim().isEmpty()) {
            setFormError("Cover letter is required");
            return false;
        }
        if (fieldExperience == null || fieldExperience.getValue() == null) {
            setFormError("Experience level is required");
            return false;
        }
        return true;
    }

    private void setFormError(String message) {
        if (formErrorLabel != null) {
            formErrorLabel.setText(message);
        }
    }

    private void submitApplication() throws SQLException {
        if (currentJobOffer == null) {
            setFormError("No job selected");
            System.err.println("❌ No job offer selected");
            return;
        }

        System.out.println("📝 Submitting application for job: " + currentJobOffer.getTitle());

        // Check if employee has already applied for this job
        if (hasAlreadyApplied()) {
            setFormError("You have already applied for this position");
            return;
        }

        Application application = new Application(
                currentEmployeeName,
                currentJobOffer.getId(),
                "employee_profile_" + currentEmployeeId + ".pdf",
                "cover_letter_" + currentEmployeeId + ".txt",
                "APPLIED",
                fieldCoverLetter.getText(),
                LocalDateTime.now(),
                currentJobOffer.getDepartment(),
                fieldExperience.getValue(),
                currentEmployeeEmail,
                currentEmployeeId);

        applicationService.addApplication(application);

        System.out.println("✅ Application submitted successfully!");
        showSuccessAlert("Success", "Your application has been submitted successfully!");

        if (mainController != null) {
            mainController.handleCloseModal();
            // Refresh the applications list
            mainController.handleMyApplications();
        }
    }

    private boolean hasAlreadyApplied() throws SQLException {
        // Check if the current employee has already applied for this job
        List<Application> employeeApplications = applicationService.getApplicationsByEmail(currentEmployeeEmail);

        for (Application app : employeeApplications) {
            if (app.getJobOfferId() == currentJobOffer.getId()) {
                System.out.println("⚠️ Employee has already applied for this job");
                return true;
            }
        }
        System.out.println("✅ No previous application found for this job");
        return false;
    }

    @Override
    public void handleSave() {
        handleSubmit();
    }

    @Override
    public void clearFields() {
        if (fieldEmail != null) {
            fieldEmail.clear();
        }
        if (fieldCoverLetter != null) {
            fieldCoverLetter.clear();
        }
        if (fieldExperience != null) {
            fieldExperience.setValue(null);
        }
        if (fieldSource != null) {
            fieldSource.setValue(null);
        }
        if (formErrorLabel != null) {
            formErrorLabel.setText("");
        }
    }
}
