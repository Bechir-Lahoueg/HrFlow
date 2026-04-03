package controllers.RH;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import models.Interview;
import service.InterviewService;
import utils.Mydb;
import java.io.IOException;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.List;

public class InterviewsController extends RHBaseController {

    @FXML
    private Label statScheduled, statToday, statCompleted, statOffersExtended;
    @FXML
    private ComboBox<String> filterStatus, filterType;
    @FXML
    private TableView<Interview> interviewTableView;
    @FXML
    private TableColumn<Interview, String> colCandidateName, colPosition, colType, colInterviewer, colDateTime,
            colDuration,
            colStatus;
    @FXML
    private HBox todayBanner;
    @FXML
    private Label paginationInfo;
    @FXML
    private Pagination tablePagination;

    // Form fields
    @FXML
    private ComboBox<String> fieldApplication, fieldType, fieldInterviewer, fieldResult;
    @FXML
    private TextField fieldLocation, fieldMeetingLink, fieldScore;
    @FXML
    private DatePicker fieldDate;
    @FXML
    private TextArea fieldNotes;

    private InterviewService interviewService = new InterviewService();
    private service.ApplicationService applicationService = new service.ApplicationService();
    private ObservableList<Interview> masterData = FXCollections.observableArrayList();
    private List<models.Application> activeApplications;
    private Interview editingInterview;

    @FXML
    public void initialize() {
        if (interviewTableView != null) {
            setupTable();
            refreshTable();
        }
        loadApplications();
        loadInterviewers();
        populateInterviewTypes();
    }

    private void loadInterviewers() {
        if (fieldInterviewer == null)
            return;
        try {
            List<String> interviewers = interviewService.getAllInterviewers();
            fieldInterviewer.setItems(FXCollections.observableArrayList(interviewers));
        } catch (SQLException e) {
            showErrorAlert("Database Error", "Failed to load interviewers: " + e.getMessage());
        }
    }

    private void loadApplications() {
        if (fieldApplication == null)
            return;
        try {
            activeApplications = applicationService.getActiveApplications();
            ObservableList<String> appLabels = FXCollections.observableArrayList();
            for (models.Application app : activeApplications) {
                appLabels.add("App :" + app.getJobTitle() + " - Candidate :" + app.getCandidateName());
            }
            fieldApplication.setItems(appLabels);
        } catch (SQLException e) {
            showErrorAlert("Database Error", "Failed to load applications for selection: " + e.getMessage());
        }
    }

    private void populateInterviewTypes() {
        if (fieldType != null) {
            fieldType.setItems(FXCollections.observableArrayList("HR", "TECHNICAL", "FINAL"));
        }
    }

    private void setupTable() {
        colCandidateName.setCellValueFactory(new PropertyValueFactory<>("candidateName"));
        colPosition.setCellValueFactory(new PropertyValueFactory<>("jobTitle"));
        colType.setCellValueFactory(new PropertyValueFactory<>("type"));
        colInterviewer.setCellValueFactory(new PropertyValueFactory<>("interviewerName"));

        java.time.format.DateTimeFormatter dtf = java.time.format.DateTimeFormatter.ofPattern("MMM dd, HH:mm");
        colDateTime.setCellValueFactory(cellData -> {
            java.time.LocalDateTime dt = cellData.getValue().getInterviewDate();
            if (dt == null)
                return new javafx.beans.property.SimpleStringProperty("N/A");
            return new javafx.beans.property.SimpleStringProperty(dtf.format(dt));
        });

        colStatus.setCellFactory(column -> new TableCell<Interview, String>() {
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
                        case "PASS":
                            badge.getStyleClass().add("badge-success");
                            break;
                        case "FAIL":
                            badge.getStyleClass().add("badge-danger");
                            break;
                        case "PENDING":
                            badge.getStyleClass().add("badge-warning");
                            break;
                        default:
                            badge.getStyleClass().add("badge-secondary");
                            break;
                    }
                    setGraphic(badge);
                    setText(null);
                }
            }
        });
        colStatus.setCellValueFactory(new PropertyValueFactory<>("result"));

    }

    @FXML
    public void refreshTable() {
        try {
            List<Interview> interviews = interviewService.getActiveInterviews();
            if (interviews.isEmpty()) {
                if (Mydb.getInstance().getConnection() == null) {
                    interviewTableView.setPlaceholder(new Label("⚠️ Database Connection Unavailable. Reconnecting..."));
                } else {
                    interviewTableView.setPlaceholder(new Label("No interviews found."));
                }
            }
            masterData.setAll(interviews);
            if (interviewTableView != null) {
                interviewTableView.setItems(masterData);
                interviewTableView.refresh(); // Force table refresh to display all data
            }
            // Reset filters to show all data after refresh
            if (filterStatus != null) {
                filterStatus.setValue("All Statuses");
            }
            if (filterType != null) {
                filterType.setValue("All Types");
            }
            updateStats();
        } catch (SQLException e) {
            if (interviewTableView != null) {
                interviewTableView.setPlaceholder(new Label("Failed to load: " + e.getMessage()));
            }
            showErrorAlert("Database Error", "Failed to load interviews: " + e.getMessage());
        }
    }

    private void updateStats() {
        if (statScheduled == null)
            return;
        statScheduled.setText(String.valueOf(masterData.stream().filter(i -> "PENDING".equals(i.getResult())).count()));

        long completed = masterData.stream().filter(i -> !"PENDING".equals(i.getResult())).count();
        statCompleted.setText(String.valueOf(completed));

        // Count interviews today
        java.time.LocalDate today = java.time.LocalDate.now();
        long todayCount = masterData.stream().filter(i -> {
            if (i.getInterviewDate() == null)
                return false;
            java.time.LocalDate idate = i.getInterviewDate().toLocalDate();
            return idate.equals(today);
        }).count();
        statToday.setText(String.valueOf(todayCount));

        if (todayCount > 0 && todayBanner != null) {
            todayBanner.setVisible(true);
            todayBanner.setManaged(true);
            // Update banner text if needed
        }
    }

    @FXML
    private void handleFilterChange() {
        String selectedStatus = filterStatus.getValue();
        String selectedType = filterType.getValue();

        if (selectedStatus != null && selectedStatus.equals("All Statuses") &&
                selectedType != null && selectedType.equals("All Types")) {
            // No filters applied - show all data from masterData
            if (interviewTableView != null) {
                interviewTableView.setItems(masterData);
            }
        } else {
            // Apply filters
            ObservableList<Interview> filtered = masterData.filtered(interview -> {
                boolean statusMatch = selectedStatus == null || selectedStatus.equals("All Statuses") ||
                        (interview.getResult() != null && interview.getResult().equals(selectedStatus));
                boolean typeMatch = selectedType == null || selectedType.equals("All Types") ||
                        (interview.getType() != null && interview.getType().equals(selectedType));
                return statusMatch && typeMatch;
            });

            if (interviewTableView != null) {
                interviewTableView.setItems(filtered);
            }
        }
    }

    @FXML
    private void handleTypeFilterChange() {
        handleFilterChange(); // Apply filter with both status and type
    }

    @FXML
    private void handleDelete() {
        Interview selected = interviewTableView.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showErrorAlert("Selection Error", "Please select an interview to delete.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirm Deletion");
        confirm.setHeaderText("Delete Interview");
        confirm.setContentText(
                "Are you sure you want to delete the interview for " + selected.getCandidateName() + "?");

        if (confirm.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK) {
            try {
                if (interviewService.deleteInterview(selected.getId())) {
                    showSuccessAlert("Deleted", "Interview deleted successfully.");
                    refreshTable();
                } else {
                    showErrorAlert("Deletion Failed", "Could not delete the interview.");
                }
            } catch (SQLException e) {
                showErrorAlert("Database Error", "Failed to delete interview: " + e.getMessage());
            }
        }
    }

    @FXML
    private void handleExport() {
        try {
            List<Interview> interviewsToExport = interviewTableView == null ? masterData
                    : interviewTableView.getItems();
            if (interviewsToExport.isEmpty()) {
                showInfoAlert("No Data", "There are no interviews to export.");
                return;
            }
            // Basic export simulation
            showSuccessAlert("Export Complete", "Interviews exported to CSV format");
        } catch (Exception e) {
            showErrorAlert("Export Error", "Failed to export interviews: " + e.getMessage());
        }
    }

    @FXML
    public void handleCloseModal() {
        mainController.handleCloseModal();
    }

    @FXML
    private void handleViewToday() {
    }

    @FXML
    private void handleDismissBanner() {
        if (todayBanner != null) {
            todayBanner.setVisible(false);
            todayBanner.setManaged(false);
        }
    }

}
