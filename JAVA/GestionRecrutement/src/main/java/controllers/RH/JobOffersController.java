package controllers.RH;

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
    private Label paginationInfo;
    @FXML
    private Pagination tablePagination;

    // Form fields (loaded in modal)
    @FXML
    private TextField fieldTitle, fieldLocation, fieldSalaryMin, fieldSalaryMax;
    @FXML
    private ComboBox<String> fieldDepartment, fieldType, fieldStatus;
    @FXML
    private TextArea fieldDescription, fieldRequirements;

    private JobOfferService jobOfferService = new JobOfferService();
    private ObservableList<JobOffer> masterData = FXCollections.observableArrayList();
    private int editingJobId = 0; // 0 means create mode

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
    }


    @FXML
    public void refreshTable() {
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
        if (statTotalJobs == null)
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
    public void handleCloseModal() {
        mainController.handleCloseModal();
    }


}
