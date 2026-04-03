package org.example.ui.controller.Rh.Recrutement;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import java.io.IOException;
import org.example.ui.controller.Rh.RHBaseController;
import org.example.ui.controller.Rh.ShellController;

/**
 * MainController for Recrutement Module
 * Handles horizontal navigation for HR recruitment features
 */
public class MainController implements ShellController {

    @FXML
    private Button btnJobOffers, btnApplications, btnInterviews, btnAnalytics;
    @FXML
    private Label badgeJobs, badgeApplications, badgeInterviews;
    @FXML
    private Label headerTitle, headerBreadcrumb;
    @FXML
    private TextField searchField;
    @FXML
    private StackPane contentArea;

    // Modal Overlays
    @FXML
    private StackPane globalModalOverlay;
    @FXML
    private Label modalTitle, modalSubtitle, formErrorLabel;
    @FXML
    private StackPane modalBody;
    @FXML
    private Button btnModalSave;

    private Button activeNavButton;

    @FXML
    public void initialize() {
        // Set default view
        handleJobOffers();
    }

    @FXML
    public void handleJobOffers() {
        System.out.println("\n📋 Navigating to Job Offers...");
        setActiveNav(btnJobOffers, "Job Offers");
        loadView("/fxml/views/Rh-dashboard/Recrutement/JobOffersView.fxml");
    }

    @FXML
    public void handleApplications() {
        System.out.println("\n📄 Navigating to Applications...");
        setActiveNav(btnApplications, "Applications");
        loadView("/fxml/views/Rh-dashboard/Recrutement/ApplicationsView.fxml");
    }

    @FXML
    public void handleInterviews() {
        System.out.println("\n🎙 Navigating to Interviews...");
        setActiveNav(btnInterviews, "Interviews");
        loadView("/fxml/views/Rh-dashboard/Recrutement/InterviewsView.fxml");
    }

    @FXML
    public void handleAnalytics() {
        System.out.println("\n📊 Navigating to Data Analysis...");
        setActiveNav(btnAnalytics, "Data Analysis");
        loadView("/fxml/views/Rh-dashboard/Recrutement/DataAnalysisView.fxml");
    }

    @FXML
    public void handleRefresh() {
        System.out.println("\n🔄 Refreshing current view...");
        // Reload the current view to refresh data
        if (activeNavButton != null) {
            // Trigger the appropriate handler based on active button
            if (activeNavButton == btnJobOffers) {
                handleJobOffers();
            } else if (activeNavButton == btnApplications) {
                handleApplications();
            } else if (activeNavButton == btnInterviews) {
                handleInterviews();
            } else if (activeNavButton == btnAnalytics) {
                handleAnalytics();
            }
        }
    }

    private void setActiveNav(Button button, String title) {
        // Reset previous active button
        if (activeNavButton != null) {
            activeNavButton.getStyleClass().remove("nav-button-active");
        }

        // Set new active button
        activeNavButton = button;
        button.getStyleClass().add("nav-button-active");

        // Update header
        if (headerTitle != null) {
            headerTitle.setText(title);
        }
        if (headerBreadcrumb != null) {
            headerBreadcrumb.setText("Recruitment > " + title);
        }
    }

    private void loadView(String fxmlPath) {
        try {
            System.out.println("🔍 Attempting to load view: " + fxmlPath);

            // Try to get resource
            var resource = getClass().getResource(fxmlPath);
            if (resource == null) {
                System.err.println("❌ Resource not found: " + fxmlPath);
                showErrorAlert("Resource Not Found", "FXML file not found: " + fxmlPath);
                return;
            }

            System.out.println("✅ Resource found: " + resource);
            FXMLLoader loader = new FXMLLoader(resource);
            System.out.println("📦 Loading FXML...");
            Parent view = loader.load();
            System.out.println("✅ FXML loaded successfully");

            // Inject main controller into sub-controllers
            Object controller = loader.getController();
            if (controller instanceof RHBaseController) {
                ((RHBaseController) controller).setMainController(this);
                System.out.println("🔗 Injected ShellController into " + controller.getClass().getSimpleName());
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(view);
            System.out.println("✅ View loaded and displayed successfully\n");

        } catch (IOException e) {
            System.err.println("❌ IOException: " + e.getMessage());
            e.printStackTrace();
            showErrorAlert("Loading Error", "Failed to load view: " + e.getMessage());
        } catch (Exception e) {
            System.err.println("❌ Unexpected error: " + e.getClass().getName() + " - " + e.getMessage());
            e.printStackTrace();
            showErrorAlert("Error", "Unexpected error loading view: " + e.getMessage());
        }
    }

    private RHBaseController currentFormController;

    @FXML
    public void handleModalSave() {
        if (currentFormController != null) {
            currentFormController.handleSave();
        }
    }

    // Modal methods
    @FXML
    public void handleCloseModal() {
        globalModalOverlay.setVisible(false);
        globalModalOverlay.setManaged(false);
        modalBody.getChildren().clear();
        if (formErrorLabel != null) {
            formErrorLabel.setText("");
        }
        if (currentFormController != null) {
            currentFormController.clearFields();
        }
        currentFormController = null;
    }

    // Utility methods
    public void showErrorAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }

    public void showInfoAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }

    public void showSuccessAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    @Override
    public void showModal(String title, String subtitle, Parent content) {
        showModal(title, subtitle, content, null);
    }

    @Override
    public void showModal(String title, String subtitle, Parent content, RHBaseController formController) {
        if (modalTitle != null) {
            modalTitle.setText(title);
        }
        if (modalSubtitle != null) {
            modalSubtitle.setText(subtitle);
        }
        if (formErrorLabel != null) {
            formErrorLabel.setText("");
        }
        if (modalBody != null) {
            modalBody.getChildren().clear();
            modalBody.getChildren().add(content);
        }
        currentFormController = formController;
        if (globalModalOverlay != null) {
            globalModalOverlay.setVisible(true);
            globalModalOverlay.setManaged(true);
        }
    }

    @Override
    public void hideModal() {
        if (globalModalOverlay != null) {
            globalModalOverlay.setVisible(false);
            globalModalOverlay.setManaged(false);
        }
        if (formErrorLabel != null) {
            formErrorLabel.setText("");
        }
        modalBody.getChildren().clear();
        currentFormController = null;
    }

    @Override
    public void setFormError(String error) {
        System.out.println(" [DEBUG] MainController.setFormError() called with: '" + error + "'");
        System.out.println(" [DEBUG] formErrorLabel: " + (formErrorLabel != null ? "exists" : "null"));
        if (formErrorLabel != null) {
            formErrorLabel.setText(error != null ? error : "");
            System.out.println(" [DEBUG] formErrorLabel text set to: '" + formErrorLabel.getText() + "'");
        }
    }

    @Override
    public void clearFormError() {
        setFormError(null);
    }
}
