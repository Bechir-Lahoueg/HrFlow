package controllers.Employee;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.StackPane;
import java.io.IOException;

public class EmployeeMainController {

    @FXML
    private Button btnBack, btnDashboard, btnMyProfile, btnBrowseJobs, btnMyApplications;
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
    @FXML private Button btnAIMode ;

    private Button activeNavButton;
    private EmployeeBaseController currentFormController;

    @FXML
    public void initialize() {
        System.out.println("🔍 EmployeeMainController initialized - Loading Employee Dashboard");
        handleDashboard();
    }

    @FXML
    private void handleBack() {
        // Navigation history logic could go here
        System.out.println("Back button clicked");
    }

    @FXML
    public void handleDashboard() {
        setActiveNav(btnDashboard, "Dashboard");
        loadView("/fxml/Employee/EmployeeDashboardView.fxml");
    }


    @FXML
    public void handleBrowseJobs() {
        System.out.println("📂 handleBrowseJobs called");
        setActiveNav(btnBrowseJobs, "Browse Jobs");
        loadView("/fxml/Employee/EmployeeBrowseJobsView.fxml");
    }

    @FXML
    public void handleMyApplications() {
        System.out.println("📂 handleMyApplications called");
        setActiveNav(btnMyApplications, "My Applications");
        loadView("/fxml/Employee/EmployeeMyApplicationsView.fxml");
    }

        @FXML
    public void handleAiMode() {
        setActiveNav(btnAIMode, "AI Mode");
        loadView("/fxml/Employee/AIModelView.fxml");
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
            headerBreadcrumb.setText("Employee Portal > " + title);
        }
    }

    private void loadView(String fxmlPath) {
        try {
            System.out.println("🔄 Loading FXML: " + fxmlPath);
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            
            if (loader.getLocation() == null) {
                System.err.println("❌ FXML resource not found: " + fxmlPath);
                throw new IOException("FXML resource not found: " + fxmlPath);
            }
            
            System.out.println("✅ Resource found at: " + loader.getLocation());
            Parent view = loader.load();
            System.out.println("✅ FXML loaded successfully");

            // Set main controller reference if the view controller supports it
            Object controller = loader.getController();
            System.out.println("🔍 Controller type: " + (controller != null ? controller.getClass().getName() : "null"));
            
            if (controller instanceof EmployeeBaseController) {
                ((EmployeeBaseController) controller).setMainController(this);
                currentFormController = (EmployeeBaseController) controller;
                System.out.println("✅ Main controller reference set");
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(view);
            System.out.println("✅ View added to content area");

        } catch (IOException e) {
            System.err.println("❌ Failed to load view: " + fxmlPath);
            e.printStackTrace();
            showErrorAlert("Loading Error", "Failed to load view: " + fxmlPath + "\n" + e.getMessage());
        } catch (Exception e) {
            System.err.println("❌ Unexpected error loading view: " + fxmlPath);
            e.printStackTrace();
            showErrorAlert("Unexpected Error", "Error loading view: " + e.getMessage());
        }
    }

    // Modal methods
    public void showModal(Parent content, String title, String subtitle, EmployeeBaseController controller) {
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
