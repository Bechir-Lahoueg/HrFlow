package controllers;

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
import java.time.LocalDateTime;
import java.util.List;

public class JobOffersController extends BaseController {

    @FXML
    private TableView<JobOffer> jobTableView;
    @FXML
    private TableColumn<JobOffer, String> colTitle, colDepartment, colLocation, colType, colStatus;
    @FXML
    private TableColumn<JobOffer, String> colSalary;
    @FXML
    private TableColumn<JobOffer, Void> colActions;

    @FXML
    private Label statTotalJobs, statActiveJobs, statDraftJobs, statApplications;
    @FXML
    private ComboBox<String> filterStatus;
    @FXML
    private Label paginationInfo;
    @FXML
    private Pagination tablePagination;

    // Form fields (loaded in modal)
    @FXML
    private TextField fieldTitle, fieldLocation, fieldSalaryMin, fieldSalaryMax;
    @FXML
    private ComboBox<String> fieldDepartment, fieldType, fieldStatus;
    @FXML
    private TextArea fieldDescription;

    private JobOfferService jobOfferService = new JobOfferService();
    private ObservableList<JobOffer> masterData = FXCollections.observableArrayList();
    private int editingJobId = 0; // 0 means create mode

    @FXML
    public void initialize() {
        if (jobTableView != null) {
            setupTable();
            refreshTable();
        }
        loadFilterOptions();
    }

    private void loadFilterOptions() {
        if (filterStatus == null)
            return;
        try {
            ObservableList<String> statuses = FXCollections.observableArrayList("All Statuses", "DRAFT", "OPEN", "CLOSED");
            filterStatus.setItems(statuses);
            filterStatus.setValue("All Statuses");
        } catch (Exception e) {
            showErrorAlert("Error", "Failed to load filter options: " + e.getMessage());
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
            private final Button editBtn = new Button("Edit");
            private final Button deleteBtn = new Button("Delete");
            private final HBox pane = new HBox(4, editBtn, deleteBtn);

            {
                editBtn.getStyleClass().addAll("action-btn", "action-btn-edit");
                deleteBtn.getStyleClass().addAll("action-btn", "action-btn-delete");

                editBtn.setOnAction(event -> {
                    JobOffer jo = getTableView().getItems().get(getIndex());
                    handleEditJob(jo);
                });

                deleteBtn.setOnAction(event -> {
                    JobOffer jo = getTableView().getItems().get(getIndex());
                    handleDelete(jo);
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

    private void handleEditJob(JobOffer jo) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Admin/JobOfferForm.fxml"));
            Parent form = loader.load();

            JobOffersController formController = loader.getController();
            formController.setMainController(this.mainController);
            formController.setEditingJob(jo);

            mainController.showModal(form, "Edit Job Offer", "Update details for " + jo.getTitle(), formController);
        } catch (IOException e) {
            showErrorAlert("System Error", "Failed to load job offer form: " + e.getMessage());
        }
    }

    public void setEditingJob(JobOffer jo) {
        this.editingJobId = jo.getId();
        // Only set form fields if they exist (i.e., when in modal form mode)
        if (fieldTitle == null)
            return;
            
        fieldTitle.setText(jo.getTitle());
        fieldLocation.setText(jo.getLocation());
        fieldSalaryMin.setText(String.valueOf((int) jo.getSalaryMin()));
        fieldSalaryMax.setText(String.valueOf((int) jo.getSalaryMax()));
        fieldDescription.setText(jo.getDescription());
        fieldDepartment.setValue(jo.getDepartment());
        fieldType.setValue(jo.getEmploymentType());
        fieldStatus.setValue(jo.getStatus());
    }

    private void handleDelete(JobOffer jo) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Delete Job Offer");
        alert.setHeaderText("Are you sure you want to delete " + jo.getTitle() + "?");
        alert.setContentText("This action will move the job offer to archive.");

        if (alert.showAndWait().get() == ButtonType.OK) {
            try {
                jobOfferService.softDeleteJobOffer(jo.getId());
                refreshTable();
            } catch (SQLException e) {
                showErrorAlert("Database error", "Failed to delete job offer: " + e.getMessage());
            }
        }
    }

    @FXML
    public void refreshTable() {
        // Only refresh table if in main view mode (jobTableView not null)
        if (jobTableView == null)
            return;
            
        try {
            List<JobOffer> offers = jobOfferService.getAllActiveOffers();
            masterData.setAll(offers);
            jobTableView.setItems(masterData);
            updateStats();
        } catch (SQLException e) {
            jobTableView.setPlaceholder(new Label("Failed to load data: " + e.getMessage()));
            showErrorAlert("Database error", "Failed to load job offers: " + e.getMessage());
        }
    }

    private void updateStats() {
        if (statTotalJobs == null || statActiveJobs == null)
            return;
        statTotalJobs.setText(String.valueOf(masterData.size()));
        long active = masterData.stream().filter(o -> "OPEN".equals(o.getStatus())).count();
        statActiveJobs.setText(String.valueOf(active));
        statDraftJobs.setText(String.valueOf(masterData.stream().filter(o -> "DRAFT".equals(o.getStatus())).count()));
    }

    @FXML
    private void handleFilterChange() {
        String selected = filterStatus.getValue();
        if (selected == null || selected.equals("All Statuses")) {
            jobTableView.setItems(masterData);
        } else {
            jobTableView.setItems(masterData.filtered(o -> o.getStatus().equalsIgnoreCase(selected)));
        }
        // Force refresh of the table to ensure action buttons are properly rendered
        jobTableView.refresh();
    }

    @FXML
    private void handleExport() {
        System.out.println("Exporting data...");
    }

    @FXML
    private void handleAddJob() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Admin/JobOfferForm.fxml"));
            Parent form = loader.load();

            JobOffersController formController = loader.getController();
            formController.setMainController(this.mainController);
            formController.editingJobId = 0; // Explicitly set to create mode
            formController.clearFields(); // Clear form fields for add mode

            mainController.showModal(form, "Add New Job Offer", "Provide the details for the new vacancy.",
                    formController);
        } catch (IOException e) {
            showErrorAlert("System error", "Failed to load job offer form: " + e.getMessage());
        }
    }

    @Override
    public void handleSave() {
        // Only save if form fields exist (i.e., when in modal form mode)
        if (fieldTitle == null)
            return;
            
        try {
            validateInputs();

            double salaryMinVal = parseSalary(fieldSalaryMin.getText());
            double salaryMaxVal = parseSalary(fieldSalaryMax.getText());

            if (editingJobId == 0) {
                // CREATE
                JobOffer newOffer = new JobOffer(
                        fieldTitle.getText(),
                        fieldDescription.getText(),
                        fieldDepartment.getValue(),
                        fieldLocation.getText(),
                        fieldType.getValue(),
                        salaryMinVal,
                        salaryMaxVal,
                        fieldStatus.getValue().toUpperCase(),
                    LocalDateTime.now(),
                        1 // Mock Admin ID
                );
                jobOfferService.createOffer(newOffer);
            } else {
                // UPDATE
                JobOffer existingOffer = new JobOffer(
                        editingJobId,
                        fieldTitle.getText(),
                        fieldDescription.getText(),
                        fieldDepartment.getValue(),
                        fieldLocation.getText(),
                        fieldType.getValue(),
                        salaryMinVal,
                        salaryMaxVal,
                        fieldStatus.getValue().toUpperCase(),
                    LocalDateTime.now(), // CreatedAt not updated
                        1 // CreatedBy not updated
                );
                jobOfferService.updateJobOffer(existingOffer);
            }

            mainController.handleCloseModal();
            refreshTable(); // Update table and stats
            mainController.handleJobOffers();

        } catch (IllegalArgumentException e) {
            showErrorAlert("Validation Error", e.getMessage());
        } catch (SQLException e) {
            showErrorAlert("Database Error", "Failed to save job offer: " + e.getMessage());
        }
    }

    private void validateInputs() {
        // Only validate if form fields exist (i.e., when in modal form mode)
        if (fieldTitle == null)
            return;
            
        if (fieldTitle.getText() == null || fieldTitle.getText().isEmpty())
            throw new IllegalArgumentException("Title is required");
        if (fieldDepartment.getValue() == null)
            throw new IllegalArgumentException("Department is required");
        if (fieldStatus.getValue() == null)
            throw new IllegalArgumentException("Status is required");
    }

    private double parseSalary(String text) {
        if (text == null || text.trim().isEmpty())
            return 0;
        try {
            return Double.parseDouble(text.replaceAll("[^0-9.]", ""));
        } catch (NumberFormatException e) {
            return 0;
        }
    }

    @Override
    public void clearFields() {
        editingJobId = 0;
        if (fieldTitle != null)
            fieldTitle.clear();
        if (fieldLocation != null)
            fieldLocation.clear();
        if (fieldSalaryMin != null)
            fieldSalaryMin.clear();
        if (fieldSalaryMax != null)
            fieldSalaryMax.clear();
        if (fieldDescription != null)
            fieldDescription.clear();
        if (fieldDepartment != null)
            fieldDepartment.setValue(null);
        if (fieldType != null)
            fieldType.setValue(null);
        if (fieldStatus != null)
            fieldStatus.setValue(null);
    }

    @FXML
    public void handleCloseModal() {
        mainController.handleCloseModal();
    }

    private void showErrorAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
