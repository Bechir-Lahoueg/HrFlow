package controllers.RH;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import java.io.IOException;

public class RHMainController {

    @FXML
    private Button btnBack, btnDashboard, btnCandidates, btnInterviews, btnPipeline, btnOffers, btnAIMode, btnReport, btnCalendar, btnReports, btnTemplates;
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
    private RHBaseController currentFormController;

    @FXML
    public void initialize() {
        // Set default view
        handleDashboard();
    }

    @FXML
    private void handleBack() {
        // Navigation history logic could go here
        System.out.println("Back button clicked");
    }

    @FXML
    public void handleDashboard() {
        System.out.println("\n📊 Navigating to Dashboard...");
        setActiveNav(btnDashboard, "Dashboard");
        loadView("/fxml/RH/HRDashboardView.fxml");
    }

    @FXML
    public void handleCandidates() {
        System.out.println("\n👥 Navigating to Candidates...");
        setActiveNav(btnCandidates, "Candidates");
        loadView("/fxml/RH/HRCandidatesView.fxml");
    }

    @FXML
    public void handleInterviews() {
        System.out.println("\n📅 Navigating to Interviews...");
        setActiveNav(btnInterviews, "Interviews");
        String fxmlPath = "/fxml/RH/HRInterviewsView.fxml";
        var resource = getClass().getResource(fxmlPath);
        if (resource == null) {
            System.out.println("⚠️  HRInterviewsView not found, using Admin version");
            fxmlPath = "/fxml/Admin/InterviewsView.fxml";
        }
        loadView(fxmlPath);
    }

    @FXML
    public void handlePipeline() {
        setActiveNav(btnPipeline, "Pipeline");
        showInfoAlert("Pipeline", "Pipeline management coming soon!");
    }

    @FXML
    public void handleOffers() {
        System.out.println("\n💼 Navigating to Job Offers...");
        setActiveNav(btnOffers, "Job Offers");
        loadView("/fxml/RH/HROffersView.fxml");
    }

    @FXML
    public void handleCalendar() {
        setActiveNav(btnCalendar, "Calendar");
        showInfoAlert("Calendar", "Interview calendar coming soon!");
    }

    @FXML
    public void handleReports() {
        setActiveNav(btnReports, "Reports");
        showInfoAlert("Reports", "HR analytics reports coming soon!");
    }

    @FXML
    public void handleTemplates() {
        setActiveNav(btnTemplates, "Templates");
        showInfoAlert("Templates", "Email templates coming soon!");
    }

    @FXML
    public void handleAiMode() {
        System.out.println("\n🤖 Navigating to AI Mode...");
        setActiveNav(btnAIMode, "AI Mode");
        loadView("/fxml/RH/AIModelView.fxml");
    }

    @FXML
    public void handleReport() {
        System.out.println("\n📊 Navigating to Report Generator...");
        setActiveNav(btnReport, "Report");
        loadView("/fxml/RH/ReportGeneratorView.fxml");
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
            headerBreadcrumb.setText("HR Portal > " + title);
        }
    }

    private void loadView(String fxmlPath) {
        try {
            System.out.println("🔍 Attempting to load view: " + fxmlPath);
            
            // Try to get resource
            var resource = getClass().getResource(fxmlPath);
            if (resource == null) {
                System.err.println("❌ Resource not found: " + fxmlPath);
                System.err.println("   Class loader: " + getClass().getClassLoader());
                System.err.println("   Current classpath resources available");
                showErrorAlert("Resource Not Found", "FXML file not found: " + fxmlPath);
                return;
            }
            
            System.out.println("✅ Resource found: " + resource);
            FXMLLoader loader = new FXMLLoader(resource);
            System.out.println("📦 Loading FXML...");
            Parent view = loader.load();
            System.out.println("✅ FXML loaded successfully");

            // Set main controller reference if the view controller supports it
            Object controller = loader.getController();
            System.out.println("🎮 Controller class: " + (controller != null ? controller.getClass().getName() : "null"));
            
            if (controller instanceof RHBaseController) {
                ((RHBaseController) controller).setMainController(this);
                currentFormController = (RHBaseController) controller;
                System.out.println("✅ RHBaseController reference set");
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

    // Modal methods
    public void showModal(Parent content, String title, String subtitle, RHBaseController controller) {
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
