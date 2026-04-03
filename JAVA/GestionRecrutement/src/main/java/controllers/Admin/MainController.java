package controllers;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import java.io.IOException;

public class MainController {

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

    @FXML
    private Button btnAIMode ; 

    private Button activeNavButton;
    private BaseController currentFormController;

    @FXML
    public void initialize() {
        // Set default view
        handleJobOffers();
    }

    @FXML
    private void handleBack() {
        // Navigation history logic could go here
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
    public void handleAiMode() {
        setActiveNav(btnAIMode, "AI Mode");
        loadView("/fxml/Admin/AIModelView.fxml");
    }

    @FXML
    public void handleAnalytics() {
        setActiveNav(btnAnalytics, "Analytics");
        loadView("/fxml/Admin/AnalyticsView.fxml");
    }

    @FXML
    public void handleCloseModal() {
        globalModalOverlay.setVisible(false);
        globalModalOverlay.setManaged(false);
        modalBody.getChildren().clear();
    }

    @FXML
    private void handleModalSave() {
        if (currentFormController != null) {
            currentFormController.handleSave();
        } else {
            handleCloseModal();
        }
    }

    /**
     * Shows a modal with the given content.
     */
    public void showModal(Parent content, String title, String subtitle, BaseController controller) {
        modalTitle.setText(title);
        modalSubtitle.setText(subtitle);
        modalBody.getChildren().setAll(content);
        this.currentFormController = controller;
        globalModalOverlay.setManaged(true);
        globalModalOverlay.setVisible(true);
    }

    private void setActiveNav(Button button, String title) {
        if (activeNavButton != null) {
            activeNavButton.getStyleClass().removeAll("nav-button-active");
        }
        // Also ensure the target button doesn't have it already to avoid duplicates
        button.getStyleClass().removeAll("nav-button-active");
        button.getStyleClass().add("nav-button-active");
        activeNavButton = button;

        headerTitle.setText(title);
        headerBreadcrumb.setText("Dashboard  /  " + title);
    }

    private void loadView(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            Parent view = loader.load();

            // Pass MainController to the sub-controller if needed
            Object controller = loader.getController();
            if (controller instanceof BaseController) {
                ((BaseController) controller).setMainController(this);
            }

            contentArea.getChildren().setAll(view);
        } catch (IOException e) {
            e.printStackTrace();
            contentArea.getChildren().setAll(new Label("Error loading view: " + e.getMessage()));
        }
    }
}
