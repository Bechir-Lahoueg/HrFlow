package org.example.ui.controller.Rh.Recrutement;

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
import org.example.ui.util.CSVExporter;
import java.io.IOException;
import java.sql.SQLException;
import java.util.Arrays;
import java.util.List;
import org.example.ui.controller.Rh.RHBaseController;

public class JobOffersController extends RHBaseController {

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
    private TextField searchField;
    @FXML
    private Label paginationInfo;

    private JobOfferService jobOfferService = new JobOfferService();
    private ObservableList<JobOffer> masterData = FXCollections.observableArrayList();

    @FXML
    public void initialize() {
        if (jobTableView != null) {
            setupTable();
            refreshTable();
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

        setupActionColumn();
    }

    private void setupActionColumn() {
        colActions.setCellFactory(param -> new TableCell<>() {
            private final Button editBtn = new Button("✏️");
            private final Button deleteBtn = new Button("🗑️");
            private final HBox container = new HBox(8, editBtn, deleteBtn);
            {
                editBtn.getStyleClass().add("btn-icon-edit");
                deleteBtn.getStyleClass().add("btn-icon-delete");
                editBtn.setOnAction(event -> handleEditJob(getTableView().getItems().get(getIndex())));
                deleteBtn.setOnAction(event -> handleDeleteJob(getTableView().getItems().get(getIndex())));
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }

    @FXML
    public void refreshTable() {
        try {
            List<JobOffer> offers = jobOfferService.getAllActiveOffers();
            masterData.setAll(offers);
            applyFilters();
            updateStats();
        } catch (SQLException e) {
            jobTableView.setPlaceholder(new Label("Failed to load data: " + e.getMessage()));
        }
    }

    private void updateStats() {
        if (statTotalJobs != null)
            statTotalJobs.setText(String.valueOf(masterData.size()));
        if (statActiveJobs != null)
            statActiveJobs
                    .setText(String.valueOf(masterData.stream().filter(o -> "OPEN".equals(o.getStatus())).count()));
        if (statDraftJobs != null)
            statDraftJobs
                    .setText(String.valueOf(masterData.stream().filter(o -> "DRAFT".equals(o.getStatus())).count()));
    }

    @FXML
    private void handleFilterChange() {
        applyFilters();
    }

    @FXML
    private void handleSearch() {
        applyFilters();
    }

    private void applyFilters() {
        String status = filterStatus.getValue();
        String search = searchField != null ? searchField.getText().toLowerCase().trim() : "";

        ObservableList<JobOffer> filtered = masterData.filtered(o -> {
            boolean statusMatch = status == null || status.equals("All Statuses")
                    || status.equalsIgnoreCase(o.getStatus());
            boolean searchMatch = search.isEmpty() || o.getTitle().toLowerCase().contains(search);
            return statusMatch && searchMatch;
        });
        jobTableView.setItems(filtered);
    }

    @FXML
    private void handleAddJob() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/views/Rh-dashboard/Recrutement/JobOfferForm.fxml"));
            Parent form = loader.load();
            JobOfferFormController controller = loader.getController();
            controller.setMainController(mainController);
            controller.setOnSaveCallback(this::refreshTable);

            if (mainController != null) {
                mainController.showModal("Add New Job Offer", "Fill in the details below.", form, controller);
            }
        } catch (IOException e) {
            showErrorAlert("Error", "Could not load form: " + e.getMessage());
        }
    }

    private void handleEditJob(JobOffer offer) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/views/Rh-dashboard/Recrutement/JobOfferForm.fxml"));
            Parent form = loader.load();
            JobOfferFormController controller = loader.getController();
            controller.setMainController(mainController);
            controller.setEditingOffer(offer);
            controller.setOnSaveCallback(this::refreshTable);

            if (mainController != null) {
                mainController.showModal("Edit Job Offer", "Update post details below.", form, controller);
            }
        } catch (IOException e) {
            showErrorAlert("Error", "Could not load edit form: " + e.getMessage());
        }
    }

    private void handleDeleteJob(JobOffer offer) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Confirm Deletion");
        alert.setContentText("Delete job offer '" + offer.getTitle() + "'?");
        if (alert.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK) {
            try {
                jobOfferService.softDeleteJobOffer(offer.getId());
                refreshTable();
            } catch (SQLException e) {
                showErrorAlert("Error", "Delete failed: " + e.getMessage());
            }
        }
    }

    @FXML
    private void handleExport() {
        CSVExporter.exportToCSV(
                jobTableView.getItems(),
                Arrays.asList("Title", "Department", "Location", "Type", "Status", "Salary Min", "Salary Max"),
                Arrays.asList("title", "department", "location", "employmentType", "status", "salaryMin", "salaryMax"),
                "job_offers_export.csv");
    }
}