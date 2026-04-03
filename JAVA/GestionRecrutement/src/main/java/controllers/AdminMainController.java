package controllers;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import java.io.IOException;

public class AdminMainController {

    @FXML
    private Button btnBack, btnJobOffers, btnApplications, btnInterviews, btnAnalytics;
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
    private AdminBaseController currentFormController;

    @FXML
    public void initialize() {
        // Set default view
        handleJobOffers();
    }

    @FXML
    private void handleBack() {
        // Navigation history logic could go here
        System.out.println("Back button clicked");
    }

    @FXML
    public void handleJobOffers() {
        setActiveNav(btnJobOffers, "Job Offers");
        loadView("/fxml/Admin/JobOffersView.fxml");
    }

    @FXML
    public void handleApplications() {
        setActiveNav(btnApplications, "Applications");
        loadView("/fxml/Admin/ApplicationsView.fxml");
    }

    @FXML
    public void handleInterviews() {
        setActiveNav(btnInterviews, "Interviews");
        loadView("/fxml/Admin/InterviewsView.fxml");
    }

    @FXML
    public void handleAnalytics() {
        setActiveNav(btnAnalytics, "Analytics");
        loadView("/fxml/Admin/AnalyticsView.fxml");
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
            headerBreadcrumb.setText("Admin Portal > " + title);
        }
    }

    private void loadView(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            Parent view = loader.load();

            // Set main controller reference if the view controller supports it
            Object controller = loader.getController();
            if (controller instanceof AdminBaseController) {
                ((AdminBaseController) controller).setMainController(this);
                currentFormController = (AdminBaseController) controller;
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(view);

        } catch (IOException e) {
            showErrorAlert("Loading Error", "Failed to load view: " + e.getMessage());
        }
    }

    // Modal methods
    public void showModal(Parent content, String title, String subtitle, AdminBaseController controller) {
        modalTitle.setText(title);
        modalSubtitle.setText(subtitle);
        
        modalBody.getChildren().clear();
        modalBody.getChildren().add(content);
        
        currentFormController = controller;
        
        globalModalOverlay.setVisible(true);
        globalModalOverlay.setManaged(true);
    }

    @FXML
    public void handleCloseModal() {
        globalModalOverlay.setVisible(false);
        globalModalOverlay.setManaged(false);
        modalBody.getChildren().clear();
        if (currentFormController != null) {
            currentFormController.clearFields();
        }
    }

    @FXML
    public void handleModalSave() {
        if (currentFormController != null) {
            currentFormController.handleSave();
        }
    }

    // Utility methods
    protected void showErrorAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }

    protected void showInfoAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }

    protected void showSuccessAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
