package controllers.Employee;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import models.JobOffer;
import service.JobOfferService;
import java.io.IOException;
import java.sql.SQLException;
import java.util.List;

public class EmployeeBrowseJobsController extends EmployeeBaseController {

    @FXML
    private TableView<JobOffer> jobsTableView;
    @FXML
    private TableColumn<JobOffer, String> colTitle, colDepartment, colLocation, colType, colStatus;
    @FXML
    private TableColumn<JobOffer, String> colSalary;
    @FXML
    private TableColumn<JobOffer, Void> colActions;

    @FXML
    private ComboBox<String> filterDepartment, filterLocation, filterType;
    @FXML
    private TextField searchField;
    @FXML
    private Label paginationInfo;

    private JobOfferService jobOfferService = new JobOfferService();
    private ObservableList<JobOffer> masterData = FXCollections.observableArrayList();

    @FXML
    public void initialize() {
        System.out.println("🔍 Initializing Browse Jobs view...");
        try {
            System.out.println("   - Setting up table...");
            setupTable();
            System.out.println("   ✅ Table setup complete");
            
            System.out.println("   - Setting up filters...");
            setupFilters();
            System.out.println("   ✅ Filters setup complete");
            System.out.println("   - Loading jobs...");
            loadJobs();
            System.out.println("   ✅ Jobs loaded");
            System.out.println("✅ Browse Jobs initialization complete");
        } catch (Exception e) {
            System.err.println("❌ Error during Browse Jobs initialization: " + e.getMessage());
            e.printStackTrace();
        }
    }
    
private void setupFilters() {
    try {
        // 1. Handle Departments
        if (filterDepartment != null) {
            List<String> departments = jobOfferService.getDepartments();
            filterDepartment.getItems().clear();
            filterDepartment.getItems().add("All Departments");
            filterDepartment.getItems().addAll(departments);
            filterDepartment.setValue("All Departments");   
        }

        // 2. Handle Locations
        if (filterLocation != null) {
            List<String> locations = jobOfferService.getLocations();
            filterLocation.getItems().clear();
            filterLocation.getItems().add("All Locations");
            filterLocation.getItems().addAll(locations);
            filterLocation.setValue("All Locations");
        }

        // 3. Handle Employment Types
        if (filterType != null) {
            List<String> types = jobOfferService.getEmploymentTypes();
            filterType.getItems().clear();
            filterType.getItems().add("All Types");
            filterType.getItems().addAll(types);
            filterType.setValue("All Types");
        }

    } catch (SQLException e) {
        System.err.println("Error loading filters from database: " + e.getMessage());
        showErrorAlert("Database Error", "Failed to load filter options: " + e.getMessage());
        
    }
}

    private void setupTable() {     
        colTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colDepartment.setCellValueFactory(new PropertyValueFactory<>("department"));
        colLocation.setCellValueFactory(new PropertyValueFactory<>("location"));
        colType.setCellValueFactory(new PropertyValueFactory<>("employmentType"));
        colSalary.setCellValueFactory(cellData -> {
            JobOffer jo = cellData.getValue();
            return new javafx.beans.property.SimpleStringProperty(
                    String.format("$%.0f - $%.0f", jo.getSalaryMin(), jo.getSalaryMax()));
        });
        colStatus.setCellValueFactory(new PropertyValueFactory<>("status"));

        setupActionsColumn();
    }

    private void setupActionsColumn() {
        colActions.setCellFactory(column -> new TableCell<>() {
            private final Button viewBtn = new Button("👁️");
            private final Button applyBtn = new Button("📝");
            private final HBox pane = new HBox(4, viewBtn, applyBtn);

            {
                viewBtn.getStyleClass().addAll("action-btn", "action-btn-view");
                applyBtn.getStyleClass().addAll("action-btn", "action-btn-edit");

                viewBtn.setOnAction(event -> {
                    JobOffer job = getTableView().getItems().get(getIndex());
                    handleViewJob(job);
                });

                applyBtn.setOnAction(event -> {
                    JobOffer job = getTableView().getItems().get(getIndex());
                    handleApplyForJob(job);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                } else {
                    setGraphic(pane);
                }
            }
        });
    }

    private void loadJobs() {
        try {
            System.out.println("📋 Loading job offers...");
            List<JobOffer> jobs = jobOfferService.getAllActiveOffers();
            masterData.setAll(jobs);
            jobsTableView.setItems(masterData);
            updatePaginationInfo();
            System.out.println("✅ Loaded " + jobs.size() + " job offers");
        } catch (SQLException e) {
            System.err.println("❌ Failed to load jobs: " + e.getMessage());
            showErrorAlert("Database Error", "Failed to load jobs: " + e.getMessage());
        }
    }

    private void updatePaginationInfo() {
        if (paginationInfo != null) {
            int total = masterData.size();
            paginationInfo.setText(String.format("Showing %d job openings", total));
        }
    }

    @FXML
    private void handleDepartmentFilter() {
        System.out.println("🔍 Department filter changed: " + filterDepartment.getValue());
        applyFilters();
    }

    @FXML
    private void handleLocationFilter() {
        System.out.println("🔍 Location filter changed: " + filterLocation.getValue());
        applyFilters();
    }

    @FXML
    private void handleTypeFilter() {
        System.out.println("🔍 Type filter changed: " + filterType.getValue());
        applyFilters();
    }

    @FXML
    private void handleSearch() {
        System.out.println("🔍 Search: " + searchField.getText());
        applyFilters();
    }

    private void applyFilters() {
        String department = filterDepartment.getValue();
        String location = filterLocation.getValue();
        String type = filterType.getValue();
        String searchText = searchField.getText();

        ObservableList<JobOffer> filteredList = FXCollections.observableArrayList();

        for (JobOffer job : masterData) {
            boolean matches = true;

            if (department != null && !department.equals("All Departments") && 
                !job.getDepartment().equalsIgnoreCase(department)) {
                matches = false;
            }

            if (location != null && !location.equals("All Locations") && 
                !job.getLocation().equalsIgnoreCase(location)) {
                matches = false;
            }

            if (type != null && !type.equals("All Types") && 
                !job.getEmploymentType().equalsIgnoreCase(type)) {
                matches = false;
            }

            if (searchText != null && !searchText.trim().isEmpty()) {
                String searchLower = searchText.toLowerCase();
                if (!job.getTitle().toLowerCase().contains(searchLower) &&
                    !job.getDepartment().toLowerCase().contains(searchLower) &&
                    !job.getLocation().toLowerCase().contains(searchLower) &&
                    !job.getDescription().toLowerCase().contains(searchLower)) {
                    matches = false;
                }
            }

            if (matches) {
                filteredList.add(job);
            }
        }

        jobsTableView.setItems(filteredList);
        updatePaginationInfo();
        System.out.println("✅ Filters applied - " + filteredList.size() + " jobs found");
    }

    private void handleViewJob(JobOffer job) {
        System.out.println("👁️ Viewing job details: " + job.getTitle());
        // Show job details in a modal or detailed view
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Job Details");
        alert.setHeaderText(job.getTitle());
        alert.setContentText(String.format(
            "Department: %s\nLocation: %s\nEmployment Type: %s\nSalary Range: $%.0f - $%.0f\nStatus: %s\n\nDescription:\n%s",
            job.getDepartment(),
            job.getLocation(),
            job.getEmploymentType(),
            job.getSalaryMin(),
            job.getSalaryMax(),
            job.getStatus(),
            job.getDescription()
        ));
        alert.showAndWait();
    }

    private void handleApplyForJob(JobOffer job) {
        System.out.println("📝 Opening application form for: " + job.getTitle());
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Employee/EmployeeApplicationForm.fxml"));
            Parent form = loader.load();

            EmployeeApplicationFormController formController = loader.getController();
            formController.setMainController(mainController);
            formController.setJobOffer(job);

            mainController.showModal(form, "Apply for Position", 
                "Submit your application for " + job.getTitle(), formController);

        } catch (IOException e) {
            System.err.println("❌ Failed to load application form: " + e.getMessage());
            showErrorAlert("System Error", "Failed to load application form: " + e.getMessage());
        }
    }

    @Override
    public void handleSave() {
        // This view doesn't have direct save functionality
        // Save is handled in the application form
    }

    @Override
    public void clearFields() {
        searchField.clear();
        filterDepartment.setValue("All Departments");
        filterLocation.setValue("All Locations");
        filterType.setValue("All Types");
        loadJobs(); // Reset to show all jobs
    }
}
