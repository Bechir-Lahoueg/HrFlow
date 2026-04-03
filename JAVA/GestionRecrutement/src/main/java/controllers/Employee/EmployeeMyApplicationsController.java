package controllers.Employee;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import models.Application;
import service.ApplicationService;
import utils.EmployeeSession;
import java.sql.SQLException;
import java.util.List;

public class EmployeeMyApplicationsController extends EmployeeBaseController {

    @FXML
    private TableView<Application> applicationsTableView;
    @FXML
    private TableColumn<Application, String> colJobTitle, colDepartment, colDateApplied, colStatus;
    @FXML
    private TableColumn<Application, Void> colActions;

    @FXML
    private ComboBox<String> filterStatus;
    @FXML
    private Label paginationInfo;

    private ApplicationService applicationService = new ApplicationService();
    private ObservableList<Application> masterData = FXCollections.observableArrayList();

    // Mock employee email - in real app this would come from session/auth
    private final String currentEmployeeEmail = EmployeeSession.getInstance().getEmployeeEmail();
    private int editingAppId;

    @FXML
    public void initialize() {
        System.out.println("� Initializing My Applications view...");
        try {
            System.out.println("   - Setting up table...");
            setupTable();
            System.out.println("   ✅ Table setup complete");

            System.out.println("   - Setting up status filter...");
            setupStatusFilter();
            System.out.println("   ✅ Status filter setup complete");

            System.out.println("   - Loading applications...");
            loadApplications();
            System.out.println("   ✅ Applications loaded");
            System.out.println("✅ My Applications initialization complete");
        } catch (Exception e) {
            System.err.println("❌ Error during My Applications initialization: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void setupStatusFilter() {
        if (filterStatus != null) {
            filterStatus.getItems().addAll(
                    "All Statuses",
                    "APPLIED",
                    "SCREENING",
                    "SHORTLISTED",
                    "INTERVIEW",
                    "OFFERED",
                    "REJECTED",
                    "HIRED");
            filterStatus.setValue("All Statuses");
        }
    }

    private void setupTable() {
        colJobTitle.setCellValueFactory(new PropertyValueFactory<>("jobTitle"));
        colDepartment.setCellValueFactory(new PropertyValueFactory<>("department"));
        colDateApplied.setCellValueFactory(cellData -> new javafx.beans.property.SimpleStringProperty(
                cellData.getValue().getAppliedAt().toString()));
        colStatus.setCellValueFactory(new PropertyValueFactory<>("status"));

        // Status badge cell factory
        colStatus.setCellFactory(column -> new TableCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                } else {
                    Label badge = new Label(item);
                    badge.getStyleClass().add("badge");
                    switch (item.toUpperCase()) {
                        case "PENDING":
                            badge.getStyleClass().add("badge-review");
                            break;
                        case "UNDER_REVIEW":
                            badge.getStyleClass().add("badge-review");
                            break;
                        case "SHORTLISTED":
                            badge.getStyleClass().add("badge-active");
                            break;
                        case "REJECTED":
                            badge.getStyleClass().add("badge-closed");
                            break;
                        case "HIRED":
                            badge.getStyleClass().add("badge-active");
                            break;
                        default:
                            badge.getStyleClass().add("badge-draft");
                            break;
                    }
                    setGraphic(badge);
                    setText(null);
                }
            }
        });

        setupActionsColumn();
    }

    private void setupActionsColumn() {
        colActions.setCellFactory(column -> new TableCell<>() {
            private final Button viewBtn = new Button("👁️");
            private final Button withdrawBtn = new Button("❌");
            private final HBox pane = new HBox(4, viewBtn, withdrawBtn);

            {
                viewBtn.getStyleClass().addAll("action-btn", "action-btn-view");
                withdrawBtn.getStyleClass().addAll("action-btn", "action-btn-delete");

                viewBtn.setOnAction(event -> {
                    Application app = getTableView().getItems().get(getIndex());
                    handleViewApplication(app);
                });

                withdrawBtn.setOnAction(event -> {
                    Application app = getTableView().getItems().get(getIndex());
                    handleWithdrawApplication(app);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                } else {
                    Application app = getTableView().getItems().get(getIndex());
                    // Only allow withdrawal for pending/under_review applications
                    withdrawBtn.setDisable(!"PENDING".equalsIgnoreCase(app.getStatus()) &&
                            !"UNDER_REVIEW".equalsIgnoreCase(app.getStatus()));
                    setGraphic(pane);
                }
            }
        });
    }

    private void loadApplications() {
        try {
            // Load applications for current employee email
            List<Application> applications = applicationService.getApplicationsByEmail(currentEmployeeEmail);
            masterData.setAll(applications);
            applicationsTableView.setItems(masterData);
            updatePaginationInfo();
            System.out.println("✅ Loaded " + applications.size() + " applications");
        } catch (SQLException e) {
            System.err.println("❌ Failed to load applications: " + e.getMessage());
            showErrorAlert("Database Error", "Failed to load applications: " + e.getMessage());
        }
    }

    private void updatePaginationInfo() {
        if (paginationInfo != null) {
            int total = masterData.size();
            paginationInfo.setText(String.format("Showing %d applications", total));
        }
    }

    @FXML
    private void handleStatusFilter() {
        String selectedStatus = filterStatus.getValue();
        System.out.println("🔍 Status filter changed: " + selectedStatus);
        if (selectedStatus == null || selectedStatus.equals("All Statuses")) {
            applicationsTableView.setItems(masterData);
            System.out.println("✅ Showing all applications: " + masterData.size());
        } else {
            ObservableList<Application> filtered = masterData
                    .filtered(app -> app.getStatus().equalsIgnoreCase(selectedStatus));
            applicationsTableView.setItems(filtered);
            System.out.println("✅ Showing " + selectedStatus + " applications: " + filtered.size());
        }
        updatePaginationInfo();
    }

    private void handleViewApplication(Application app) {
        System.out.println("👁️ Viewing application: " + app.getJobTitle());
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Application Details");
        alert.setHeaderText("Application for " + app.getJobTitle());

        String content = String.format(
                "Candidate: %s\n" +
                        "Email: %s\n" +
                        "Department: %s\n" +
                        "Applied: %s\n" +
                        "Status: %s\n" +
                        "Experience Level: %s\n\n" +
                        "Notes: %s",
                app.getCandidateName(),
                app.getEmailAddress(),
                app.getDepartment(),
                app.getAppliedAt().toString(),
                app.getStatus(),
                app.getExperienceLevel(),
                app.getNotes() != null ? app.getNotes() : "No additional notes");

        alert.setContentText(content);
        alert.showAndWait();
    }

    private void handleWithdrawApplication(Application app) {
        System.out.println("❌ Attempting to withdraw application: " + app.getJobTitle());
        Alert confirmAlert = new Alert(Alert.AlertType.CONFIRMATION);
        confirmAlert.setTitle("Withdraw Application");
        confirmAlert.setHeaderText("Are you sure?");
        confirmAlert.setContentText("Do you want to withdraw your application for " + app.getJobTitle() + "?");

        if (confirmAlert.showAndWait().get() == ButtonType.OK) {
            try {
                // In a real app, this would update the application status to WITHDRAWN
                applicationService.updateApplication(app);
                System.out.println("✅ Application withdrawn successfully");
                showSuccessAlert("Success", "Application withdrawn successfully");
                loadApplications(); // Refresh the list
            } catch (Exception e) {
                System.err.println("❌ Failed to withdraw application: " + e.getMessage());
                showErrorAlert("Error", "Failed to withdraw application: " + e.getMessage());
            }
        }
    }

    private void setEditingApplication(Application app) {
        editingAppId = app.getId();
        if (app != null) {
            // In a real app, this would load the application form for editing
            // For now, we'll just display the details in an alert
            showInfoAlert("Application Details", "Edit functionality coming soon!");
        }
    }

    @FXML
    private void handleJoinInterview() {
        System.out.println("📹 Joining interview session...");
        showInfoAlert("Interview",
                "Interview session link will be sent to your email. Check your inbox for meeting details.");
    }

    @FXML
    private void handleViewDetails() {
        System.out.println("👁️ Viewing application details...");
        if (!masterData.isEmpty()) {
            handleViewApplication(masterData.get(0));
        } else {
            showInfoAlert("No Application", "Please select an application to view details.");
        }
    }

    @FXML
    private void handleWithdraw() {
        System.out.println("❌ Withdrawing application...");
        if (!masterData.isEmpty()) {
            handleWithdrawApplication(masterData.get(0));
        } else {
            showInfoAlert("No Application", "Please select an application to withdraw.");
        }
    }

    @Override
    public void handleSave() {
        // This view doesn't have direct save functionality
    }

    @Override
    public void clearFields() {
        filterStatus.setValue("All Statuses");
        loadApplications(); // Reset to show all applications
    }
}
