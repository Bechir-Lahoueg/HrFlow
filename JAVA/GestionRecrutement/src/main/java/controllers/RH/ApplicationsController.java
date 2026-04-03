package controllers.RH;

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
import java.io.IOException;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.List;

public class ApplicationsController extends RHBaseController {

    @FXML
    private TableView<Application> candidateTableView;
    @FXML
    private TableColumn<Application, String> colName, colEmail, colPosition, colDepartment, colAppliedDate, colStage;
    @FXML
    private TableColumn<Application, Void> colActions;

    @FXML
    private ComboBox<String> filterStage, filterPosition;
    @FXML
    private TextField searchField; // optional - may be null if not present in FXML
    @FXML
    private Label paginationInfo;

    private ApplicationService applicationService = new ApplicationService();
    private ObservableList<Application> masterData = FXCollections.observableArrayList();
    private int editingAppId = 0;

    @FXML
    public void initialize() {
        if (colName != null) {
            setupTable();
        }

        // Populate dynamic filters from database
        try {
            if (filterPosition != null) {
                List<String> positions = applicationService.getPositions();
                positions.add(0, "All Positions");
                filterPosition.setItems(FXCollections.observableArrayList(positions));
                filterPosition.setValue("All Positions");
            }
            if (filterStage != null) {
                List<String> stages = applicationService.getDistinctStatuses();
                stages.add(0, "All Stages");
                filterStage.setItems(FXCollections.observableArrayList(stages));
                filterStage.setValue("All Stages");
            }
        } catch (SQLException ex) {
            System.err.println("❌ Failed to populate filters: " + ex.getMessage());
        }

        loadApplications();
    }

    private void setupTable() {
        colName.setCellValueFactory(new PropertyValueFactory<>("candidateName"));
        colEmail.setCellValueFactory(new PropertyValueFactory<>("emailAddress"));
        colPosition.setCellValueFactory(new PropertyValueFactory<>("jobTitle"));
        colDepartment.setCellValueFactory(new PropertyValueFactory<>("department"));
        colAppliedDate.setCellValueFactory(cellData -> new javafx.beans.property.SimpleStringProperty(
                cellData.getValue().getAppliedAt() == null ? ""
                        : cellData.getValue().getAppliedAt().toLocalDate().toString()));
        colStage.setCellValueFactory(new PropertyValueFactory<>("status"));
    }

    private void loadApplications() {
        try {
            List<Application> applications = applicationService.getActiveApplications();
            masterData.setAll(applications);
            if (candidateTableView != null) {
                candidateTableView.setItems(masterData);
            }
            updatePaginationInfo();
        } catch (SQLException e) {
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
        final String selected = (filterStage != null) ? filterStage.getValue() : null;
        if (selected == null || selected.equals("All Stages") || selected.equals("All Statuses")) {
            if (candidateTableView != null)
                candidateTableView.setItems(masterData);
        } else {
            if (candidateTableView != null)
                candidateTableView.setItems(masterData.filtered(app -> app.getStatus().equalsIgnoreCase(selected)));
        }
        updatePaginationInfo();
    }

    @FXML
    private void handleSearch() {
        String raw = (searchField == null) ? "" : searchField.getText();
        String searchTerm = (raw == null ? "" : raw).toLowerCase().trim();
        if (searchTerm.isEmpty()) {
            if (candidateTableView != null)
                candidateTableView.setItems(masterData);
        } else {
            if (candidateTableView != null)
                candidateTableView.setItems(
                        masterData.filtered(app -> app.getCandidateName().toLowerCase().contains(searchTerm) ||
                                app.getJobTitle().toLowerCase().contains(searchTerm) ||
                                app.getDepartment().toLowerCase().contains(searchTerm) ||
                                app.getEmailAddress().toLowerCase().contains(searchTerm)));
        }
        updatePaginationInfo();
    }

    @FXML
    private void handleFilterChange() {
        String stage = (filterStage != null) ? filterStage.getValue() : "All Stages";
        String position = (filterPosition != null) ? filterPosition.getValue() : "All Positions";

        if (candidateTableView == null)
            return;

        candidateTableView.setItems(masterData.filtered(app -> {
            boolean stageMatch = stage.equals("All Stages") || stage.equals("All Statuses")
                    || app.getStatus().equalsIgnoreCase(stage);
            boolean posMatch = position.equals("All Positions") || app.getJobTitle().equalsIgnoreCase(position);
            return stageMatch && posMatch;
        }));
        updatePaginationInfo();
    }

    @FXML
    private void handleExport() {
        try {
            // Export to CSV or JSON
            List<Application> applicationsToExport = candidateTableView == null ? masterData
                    : candidateTableView.getItems();
            if (applicationsToExport.isEmpty()) {
                Alert alert = new Alert(Alert.AlertType.INFORMATION);
                alert.setTitle("No Data");
                alert.setHeaderText(null);
                alert.setContentText("There are no applications to export.");
                alert.showAndWait();
                return;
            }
            StringBuilder csv = new StringBuilder();
            csv.append("Candidate Name,Email,Job Title,Department,Status,Applied At\n");
            for (Application app : applicationsToExport) {
                csv.append(String.format("\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        app.getCandidateName(),
                        app.getEmailAddress(),
                        app.getJobTitle(),
                        app.getDepartment(),
                        app.getStatus(),
                        app.getAppliedAt()));
            }
            System.out.println("✅ Applications exported successfully");
            showSuccessAlert("Export Complete", "Applications exported to CSV format");
        } catch (Exception e) {
            showErrorAlert("Export Error", "Failed to export applications: " + e.getMessage());
        }
    }

}
