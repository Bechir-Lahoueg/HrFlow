package org.example.ui.controller.Rh.Recrutement;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import models.Application;
import service.ApplicationService;
import org.example.ui.util.CSVExporter;
import java.io.IOException;
import java.sql.SQLException;
import java.util.Arrays;
import java.util.List;
import org.example.ui.controller.Rh.RHBaseController;

public class ApplicationsController extends RHBaseController {

    @FXML
    private TableView<Application> appTableView;
    @FXML
    private TableColumn<Application, String> colApplicantName, colEmail, colAppliedFor, colDepartment, colDateApplied,
            colStage;
    @FXML
    private TableColumn<Application, Void> colActions;

    @FXML
    private ComboBox<String> filterStage, filterJob;
    @FXML
    private TextField searchField;
    @FXML
    private Label statTotalApps, statUnderReview, statShortlisted, statRejected;
    @FXML
    private Label paginationInfo;

    private ApplicationService applicationService = new ApplicationService();
    private ObservableList<Application> masterData = FXCollections.observableArrayList();

    @FXML
    public void initialize() {
        setupTable();
        setupActionColumn();
        setupFilters();
        loadApplications();
    }

    private void setupTable() {
        colApplicantName.setCellValueFactory(new PropertyValueFactory<>("candidateName"));
        colEmail.setCellValueFactory(new PropertyValueFactory<>("emailAddress"));
        colAppliedFor.setCellValueFactory(new PropertyValueFactory<>("jobTitle"));
        colDepartment.setCellValueFactory(new PropertyValueFactory<>("department"));
        colDateApplied.setCellValueFactory(cellData -> new javafx.beans.property.SimpleStringProperty(
                cellData.getValue().getAppliedAt() == null ? ""
                        : cellData.getValue().getAppliedAt().toLocalDate().toString()));
        colStage.setCellValueFactory(new PropertyValueFactory<>("status"));
    }

    private void setupActionColumn() {
        colActions.setCellFactory(param -> new TableCell<>() {
            private final Button editBtn = new Button("✏️");
            private final Button deleteBtn = new Button("🗑️");
            private final HBox container = new HBox(8, editBtn, deleteBtn);
            {
                editBtn.getStyleClass().add("btn-icon-edit");
                deleteBtn.getStyleClass().add("btn-icon-delete");
                editBtn.setOnAction(event -> handleEdit(getTableView().getItems().get(getIndex())));
                deleteBtn.setOnAction(event -> handleDelete(getTableView().getItems().get(getIndex())));
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }

    private void setupFilters() {
        try {
            List<String> positions = applicationService.getPositions();
            if (!positions.contains("All Positions"))
                positions.add(0, "All Positions");
            filterJob.setItems(FXCollections.observableArrayList(positions));
            filterJob.setValue("All Positions");

            List<String> stages = applicationService.getDistinctStatuses();
            if (!stages.contains("All Stages"))
                stages.add(0, "All Stages");
            filterStage.setItems(FXCollections.observableArrayList(stages));
            filterStage.setValue("All Stages");
        } catch (SQLException ex) {
            System.err.println("Failed to load filters: " + ex.getMessage());
        }
    }

    private void loadApplications() {
        try {
            List<Application> applications = applicationService.getActiveApplications();
            masterData.setAll(applications);
            applyFilters();
            updateStats();
        } catch (SQLException e) {
            showErrorAlert("Error", "Failed to load applications: " + e.getMessage());
        }
    }

    private void updateStats() {
        if (statTotalApps != null)
            statTotalApps.setText(String.valueOf(masterData.size()));
        if (statUnderReview != null)
            statUnderReview.setText(
                    String.valueOf(masterData.stream().filter(a -> "SCREENING".equals(a.getStatus())).count()));
        if (statShortlisted != null)
            statShortlisted.setText(
                    String.valueOf(masterData.stream().filter(a -> "SHORTLISTED".equals(a.getStatus())).count()));
        if (statRejected != null)
            statRejected
                    .setText(String.valueOf(masterData.stream().filter(a -> "REJECTED".equals(a.getStatus())).count()));
    }

    private void updatePaginationInfo() {
        if (paginationInfo != null) {
            paginationInfo.setText(String.format("Showing %d applications", appTableView.getItems().size()));
        }
    }

    @FXML
    private void handleFilterChange() {
        applyFilters();
    }

    @FXML
    private void handleJobFilterChange() {
        applyFilters();
    }

    @FXML
    private void handleSearch() {
        applyFilters();
    }

    private void applyFilters() {
        String stage = filterStage.getValue();
        String job = filterJob.getValue();
        String search = searchField != null ? searchField.getText().toLowerCase().trim() : "";

        ObservableList<Application> filtered = masterData.filtered(app -> {
            boolean stageMatch = stage == null || stage.equals("All Stages") || stage.equalsIgnoreCase(app.getStatus());
            boolean jobMatch = job == null || job.equals("All Positions") || job.equalsIgnoreCase(app.getJobTitle());
            boolean searchMatch = search.isEmpty() ||
                    app.getCandidateName().toLowerCase().contains(search) ||
                    app.getEmailAddress().toLowerCase().contains(search);
            return stageMatch && jobMatch && searchMatch;
        });

        appTableView.setItems(filtered);
        updatePaginationInfo();
    }

    private void handleEdit(Application app) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/views/Rh-dashboard/Recrutement/ApplicationForm.fxml"));
            Parent form = loader.load();
            ApplicationFormController controller = loader.getController();
            controller.setMainController(mainController);
            controller.setEditingApplication(app);
            controller.setOnSaveCallback(this::loadApplications);

            if (mainController != null) {
                mainController.showModal("Edit Application", "Update candidate progress.", form, controller);
            }
        } catch (IOException e) {
            showErrorAlert("Error", "Could not load edit form: " + e.getMessage());
        }
    }

    private void handleDelete(Application app) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Delete Application");
        alert.setContentText("Delete application from " + app.getCandidateName() + "?");
        if (alert.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK) {
            try {
                applicationService.softDeleteApplication(app.getId());
                loadApplications();
            } catch (SQLException e) {
                showErrorAlert("Error", "Delete failed: " + e.getMessage());
            }
        }
    }

    @FXML
    private void handleDeleteSelected() {
        Application selected = appTableView.getSelectionModel().getSelectedItem();
        if (selected != null)
            handleDelete(selected);
    }

    @FXML
    private void handleExport() {
        CSVExporter.exportToCSV(
                appTableView.getItems(),
                Arrays.asList("Candidate Name", "Email", "Job Title", "Department", "Status", "Applied At"),
                Arrays.asList("candidateName", "emailAddress", "jobTitle", "department", "status", "appliedAt"),
                "applications_export.csv");
    }

    @FXML
    private void handleAddApplication() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/views/Rh-dashboard/Recrutement/ApplicationForm.fxml"));
            Parent form = loader.load();
            ApplicationFormController controller = loader.getController();
            controller.setMainController(mainController);
            controller.setOnSaveCallback(this::loadApplications);

            if (mainController != null) {
                mainController.showModal("Add New Application", "Log a new candidate application.", form, controller);
            }
        } catch (IOException e) {
            showErrorAlert("Error", "Could not load form: " + e.getMessage());
        }
    }
}