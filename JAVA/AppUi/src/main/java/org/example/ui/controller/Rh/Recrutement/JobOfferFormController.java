package org.example.ui.controller.Rh.Recrutement;

import javafx.fxml.FXML;
import javafx.scene.control.*;
import models.JobOffer;
import service.JobOfferService;
import org.example.ui.controller.Rh.RHBaseController;
import java.sql.SQLException;
import java.time.LocalDateTime;

public class JobOfferFormController extends RHBaseController {

    @FXML
    private TextField fieldTitle, fieldLocation, fieldSalaryMin, fieldSalaryMax;
    @FXML
    private ComboBox<String> fieldDepartment, fieldType, fieldStatus;
    @FXML
    private TextArea fieldDescription, fieldRequirements;

    private JobOfferService jobOfferService = new JobOfferService();
    private JobOffer editingOffer;
    private Runnable onSaveCallback;

    public void setEditingOffer(JobOffer offer) {
        this.editingOffer = offer;
        if (offer != null) {
            fieldTitle.setText(offer.getTitle());
            fieldLocation.setText(offer.getLocation());
            fieldSalaryMin.setText(String.valueOf(offer.getSalaryMin()));
            fieldSalaryMax.setText(String.valueOf(offer.getSalaryMax()));
            fieldDepartment.setValue(offer.getDepartment());
            fieldType.setValue(offer.getEmploymentType());
            fieldStatus.setValue(offer.getStatus());
            fieldDescription.setText(offer.getDescription());
            // Requirements might need a separate field in model if not already there
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
            JobOffer offer = (editingOffer != null) ? editingOffer : new JobOffer();
            offer.setTitle(fieldTitle.getText());
            offer.setLocation(fieldLocation.getText());
            offer.setDepartment(fieldDepartment.getValue());
            offer.setEmploymentType(fieldType.getValue());
            offer.setSalaryMin(Double.parseDouble(fieldSalaryMin.getText()));
            offer.setSalaryMax(Double.parseDouble(fieldSalaryMax.getText()));
            offer.setStatus(fieldStatus.getValue());
            offer.setDescription(fieldDescription.getText());

            if (editingOffer == null) {
                offer.setCreatedAt(LocalDateTime.now());
                offer.setCreatedBy(1); // Default user ID for now
                jobOfferService.createOffer(offer);
                showSuccessAlert("Success", "Job offer created successfully.");
            } else {
                jobOfferService.updateJobOffer(offer);
                showSuccessAlert("Success", "Job offer updated successfully.");
            }

            if (onSaveCallback != null)
                onSaveCallback.run();
            if (mainController != null)
                mainController.handleCloseModal();

        } catch (SQLException e) {
            showErrorAlert("Database Error", "Could not save job offer: " + e.getMessage());
        } catch (NumberFormatException e) {
            showErrorAlert("Validation Error", "Please enter valid numbers for salary.");
        }
    }

    private boolean validateFields() {
        // Clear previous error
        mainController.setFormError("");

        if (fieldTitle.getText().trim().isEmpty()) {
            mainController.setFormError("Title is required.");
            return false;
        }
        if (fieldDepartment.getValue() == null) {
            mainController.setFormError("Please select a department.");
            return false;
        }
        if (fieldLocation.getText().trim().isEmpty()) {
            mainController.setFormError("Location is required.");
            return false;
        }
        if (fieldType.getValue() == null) {
            mainController.setFormError("Please select employment type.");
            return false;
        }
        if (fieldStatus.getValue() == null) {
            mainController.setFormError("Please select a status.");
            return false;
        }

        try {
            double min = Double.parseDouble(fieldSalaryMin.getText());
            double max = Double.parseDouble(fieldSalaryMax.getText());
            if (min < 0 || max < 0) {
                mainController.setFormError("Salary cannot be negative.");
                return false;
            }
            if (max < min) {
                mainController.setFormError("Max salary cannot be less than min salary.");
                return false;
            }
        } catch (NumberFormatException e) {
            mainController.setFormError("Please enter valid numeric values for salary.");
            return false;
        }

        if (fieldDescription.getText().trim().isEmpty()) {
            mainController.setFormError("Description is required.");
            return false;
        }

        return true;
    }

    @Override
    public void clearFields() {
        fieldTitle.clear();
        fieldLocation.clear();
        fieldSalaryMin.clear();
        fieldSalaryMax.clear();
        fieldDepartment.setValue(null);
        fieldType.setValue(null);
        fieldStatus.setValue(null);
        fieldDescription.clear();
        fieldRequirements.clear();
    }
}
