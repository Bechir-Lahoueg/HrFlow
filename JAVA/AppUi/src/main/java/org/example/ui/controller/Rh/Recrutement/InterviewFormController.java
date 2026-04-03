package org.example.ui.controller.Rh.Recrutement;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import models.Interview;
import models.Application;
import service.InterviewService;
import service.ApplicationService;
import org.example.ui.controller.Rh.RHBaseController;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.List;

public class InterviewFormController extends RHBaseController {

    @FXML
    private ComboBox<String> fieldApplication, fieldType, fieldInterviewer, fieldResult;
    @FXML
    private DatePicker fieldDate;
    @FXML
    private TextField fieldLocation, fieldMeetingLink, fieldScore;
    @FXML
    private TextArea fieldNotes;

    private InterviewService interviewService = new InterviewService();
    private Interview editingInterview;
    private Runnable onSaveCallback;

    @FXML
    public void initialize() {
        try {
            fieldType.setItems(FXCollections.observableArrayList("HR", "TECHNICAL", "FINAL"));
            fieldInterviewer.setItems(FXCollections.observableArrayList(interviewService.getAllInterviewers()));

            ApplicationService appService = new ApplicationService();
            List<Application> apps = appService.getActiveApplications();
            ObservableList<String> appStrings = FXCollections.observableArrayList();
            for (Application a : apps) {
                appStrings.add(a.getCandidateName() + " (ID: " + a.getId() + ") - " + a.getJobTitle());
            }
            fieldApplication.setItems(appStrings);
        } catch (SQLException e) {
            System.err.println("Failed to load interview form data: " + e.getMessage());
        }
    }

    public void setEditingInterview(Interview interview) {
        this.editingInterview = interview;
        if (interview != null) {
            fieldApplication.setValue(interview.getCandidateName() + " (ID: " + interview.getApplicationId() + ") - "
                    + interview.getJobTitle());
            fieldDate
                    .setValue(interview.getInterviewDate() != null ? interview.getInterviewDate().toLocalDate() : null);
            fieldType.setValue(interview.getType());
            fieldInterviewer.setValue(interview.getInterviewerName() + " (ID: " + interview.getInterviewerId() + ")");
            fieldLocation.setText(interview.getLocation());
            fieldMeetingLink.setText(interview.getMeetingLink() != null ? interview.getMeetingLink() : "");
            fieldScore.setText(String.valueOf(interview.getScore()));
            fieldResult.setValue(interview.getResult());
            fieldNotes.setText(interview.getFeedback() != null ? interview.getFeedback() : "");
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
            Interview interview = (editingInterview != null) ? editingInterview : new Interview();

            // Extract IDs from ComboBox values
            String appValue = fieldApplication.getValue();
            int appId = Integer.parseInt(appValue.split("\\(ID: ")[1].split("\\)")[0]);
            interview.setApplicationId(appId);

            String intValue = fieldInterviewer.getValue();
            int intId = Integer.parseInt(intValue.split("\\(ID: ")[1].split("\\)")[0]);
            interview.setInterviewerId(intId);

            interview.setType(fieldType.getValue());
            interview.setInterviewDate(
                    fieldDate.getValue() != null ? fieldDate.getValue().atStartOfDay() : LocalDateTime.now());
            interview.setResult(fieldResult.getValue());
            interview.setLocation(fieldLocation.getText());
            interview.setMeetingLink(fieldMeetingLink.getText());
            interview.setFeedback(fieldNotes.getText());
            try {
                interview.setScore(Integer.parseInt(fieldScore.getText()));
            } catch (NumberFormatException e) {
                interview.setScore(0);
            }

            if (editingInterview == null) {
                interviewService.scheduleInterview(interview);
                showSuccessAlert("Success", "Interview scheduled successfully.");
            } else {
                interviewService.updateInterview(interview);
                showSuccessAlert("Success", "Interview updated successfully.");
            }

            if (onSaveCallback != null)
                onSaveCallback.run();
            if (mainController != null)
                mainController.handleCloseModal();

        } catch (SQLException e) {
            showErrorAlert("Database Error", "Could not save interview: " + e.getMessage());
        } catch (Exception e) {
            showErrorAlert("Error", "Unexpected error: " + e.getMessage());
        }
    }

    private boolean validateFields() {
        System.out.println(" [DEBUG] InterviewFormController - validateFields() called");
        System.out.println(" [DEBUG] mainController: " + (mainController != null ? mainController.getClass().getSimpleName() : "null"));
        
        // Clear previous error
        mainController.setFormError("");

        if (fieldApplication.getValue() == null) {
            mainController.setFormError("Please select an application.");
            return false;
        }
        if (fieldDate.getValue() == null) {
            mainController.setFormError("Please select an interview date.");
            return false;
        }
        if (fieldType.getValue() == null) {
            mainController.setFormError("Please select interview type.");
            return false;
        }
        if (fieldInterviewer.getValue() == null) {
            mainController.setFormError("Please select an interviewer.");
            return false;
        }

        if (fieldScore.getText().trim().isEmpty()) {
            mainController.setFormError("Score is required.");
            return false;
        }
        try {
            int score = Integer.parseInt(fieldScore.getText());
            if (score < 0 || score > 100) {
                mainController.setFormError("Score must be between 0 and 100.");
                return false;
            }
        } catch (NumberFormatException e) {
            mainController.setFormError("Please enter a valid numeric score.");
            return false;
        }

        return true;
    }

    @Override
    public void clearFields() {
        fieldApplication.setValue(null);
        fieldDate.setValue(null);
        fieldType.setValue(null);
        fieldInterviewer.setValue(null);
        fieldLocation.clear();
        fieldMeetingLink.clear();
        fieldScore.clear();
        fieldResult.setValue(null);
        fieldNotes.clear();
    }
}
