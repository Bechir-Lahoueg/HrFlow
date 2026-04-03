package controllers;

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

public class InterviewsController extends BaseController {

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
    private TableColumn<Interview, Void> colActions;
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
        loadFilterOptions();
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
                appLabels.add("App #" + app.getId() + " - " + app.getJobTitle() + " (" + app.getCandidateName() + ")");
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

    private void loadFilterOptions() {
        if (filterStatus == null && filterType == null)
            return;
        try {
            if (filterStatus != null) {
                List<String> statuses = interviewService.getDistinctResults();
                statuses.add(0, "All Statuses");
                filterStatus.setItems(FXCollections.observableArrayList(statuses));
                filterStatus.setValue("All Statuses");
            }
            if (filterType != null) {
                List<String> types = interviewService.getDistinctInterviewTypes();
                types.add(0, "All Types");
                filterType.setItems(FXCollections.observableArrayList(types));
                filterType.setValue("All Types");
            }
        } catch (SQLException e) {
            showErrorAlert("Database Error", "Failed to load filter options: " + e.getMessage());
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

        // Duration column - not available in model, show as empty
        colDuration.setCellValueFactory(cellData -> new javafx.beans.property.SimpleStringProperty("-"));

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

        colActions.setCellValueFactory(features -> new javafx.beans.property.ReadOnlyObjectWrapper<>(null));
        setupActionsColumn();
    }

    private void setupActionsColumn() {
        colActions.setCellFactory(column -> new TableCell<Interview, Void>() {
            private final Button editBtn = new Button("✏️");
            private final Button deleteBtn = new Button("🗑️");
            private final HBox container = new HBox(4, editBtn, deleteBtn);

            {
                editBtn.getStyleClass().addAll("action-btn", "action-btn-edit");
                deleteBtn.getStyleClass().addAll("action-btn", "action-btn-delete");

                editBtn.setOnAction(event -> {
                    Interview interview = getTableView().getItems().get(getIndex());
                    handleEdit(interview);
                });

                deleteBtn.setOnAction(event -> {
                    Interview interview = getTableView().getItems().get(getIndex());
                    confirmAndProcessDeletion(interview);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                } else {
                    setGraphic(container);
                }
            }
        });
    }

    @FXML
    public void handleDelete() {
        Interview selected = interviewTableView.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showErrorAlert("No Selection", "Please select an interview to delete.");
            return;
        }
        confirmAndProcessDeletion(selected);
    }

    private void handleEdit(Interview interview) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Admin/InterviewForm.fxml"));
            Parent form = loader.load();

            InterviewsController formController = loader.getController();
            formController.setMainController(this.mainController);
            formController.setInterviewToEdit(interview);

            mainController.showModal(form, "Edit Interview", "Update interview details and record feedback.",
                    formController);
        } catch (IOException e) {
            showErrorAlert("System Error", "Failed to load interview form: " + e.getMessage());
        }
    }

    public void setInterviewToEdit(Interview interview) {
        this.editingInterview = interview;
        // Only set form fields if they exist (i.e., when in modal form mode)
        if (fieldApplication == null)
            return;
            
        if (interview != null) {
            // Populate fields
            if (activeApplications != null) {
                for (models.Application app : activeApplications) {
                    if (app.getId() == interview.getApplicationId()) {
                        String label = "App #" + app.getId() + " - " + app.getJobTitle() + " (" + app.getCandidateName() + ")";
                        fieldApplication.setValue(label);
                        break;
                    }
                }
            }
            if (interview.getInterviewDate() != null) {
                fieldDate.setValue(interview.getInterviewDate().toLocalDate());
            }
            fieldType.setValue(interview.getType());

            // Match interviewer
            for (String label : fieldInterviewer.getItems()) {
                if (label.contains("(ID: " + interview.getInterviewerId() + ")")) {
                    fieldInterviewer.setValue(label);
                    break;
                }
            }

            fieldLocation.setText(interview.getLocation());
            fieldMeetingLink.setText(interview.getMeetingLink());
            fieldNotes.setText(interview.getFeedback());
            fieldScore.setText(String.valueOf(interview.getScore()));
            fieldResult.setValue(interview.getResult());

            // In Edit mode, we don't allow changing the application to maintain data
            // integrity
            fieldApplication.setDisable(true);
        }
    }

    private void confirmAndProcessDeletion(Interview interview) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Cancel Interview");
        alert.setHeaderText("Cancel interview for App #" + interview.getApplicationId() + "?");
        alert.setContentText("This will move the interview to history as cancelled.");

        if (alert.showAndWait().get() == ButtonType.OK) {
            try {
                interviewService.softDeleteInterview(interview.getId());
                refreshTable();
            } catch (SQLException e) {
                showErrorAlert("Database Error", "Failed to delete interview: " + e.getMessage());
            }
        }
    }

    @FXML
    public void refreshTable() {
        // Only refresh table if in main view mode (interviewTableView not null)
        if (interviewTableView == null)
            return;
            
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
            // Reset filters to show all data after refresh
            if (filterStatus != null) {
                filterStatus.setValue("All Statuses");
            }
            if (filterType != null) {
                filterType.setValue("All Types");
            }
            interviewTableView.setItems(masterData);
            interviewTableView.refresh(); // Force table refresh to display all data
            updateStats();
        } catch (SQLException e) {
            interviewTableView.setPlaceholder(new Label("Failed to load: " + e.getMessage()));
            showErrorAlert("Database Error", "Failed to load interviews: " + e.getMessage());
        }
    }

    private void updateStats() {
        if (statScheduled == null || statCompleted == null)
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
    private void handleExport() {
    }

    @FXML
    private void handleScheduleInterview() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Admin/InterviewForm.fxml"));
            Parent form = loader.load();

            InterviewsController formController = loader.getController();
            formController.setMainController(this.mainController);
            formController.editingInterview = null; // Reset for add mode
            formController.clearFields(); // Clear form fields for add mode

            mainController.showModal(form, "Schedule Interview", "Set up a new round of interview for a candidate.",
                    formController);
        } catch (IOException e) {
            showErrorAlert("System Error", "Failed to load interview form: " + e.getMessage());
        }
    }

    @Override
    public void handleSave() {
        // Only save if form fields exist (i.e., when in modal form mode)
        if (fieldApplication == null)
            return;
            
        try {
            validateInputs();

            int appId = 1; // Fallback
            String selectedAppLabel = fieldApplication.getValue();
            if (activeApplications != null && selectedAppLabel != null) {
                for (models.Application app : activeApplications) {
                    if (selectedAppLabel.startsWith("App #" + app.getId() + " ")) {
                        appId = app.getId();
                        break;
                    }
                }
            }

            int interviewerId = 1; // Fallback
            String selectedInterviewer = fieldInterviewer.getValue();
            if (selectedInterviewer != null && selectedInterviewer.contains("(ID: ")) {
                try {
                    String idStr = selectedInterviewer.substring(selectedInterviewer.lastIndexOf("(ID: ") + 5,
                            selectedInterviewer.lastIndexOf(")"));
                    interviewerId = Integer.parseInt(idStr);
                } catch (Exception e) {
                    System.err.println("Failed to parse interviewer ID from: " + selectedInterviewer);
                }
            }

            int score = 0;
            try {
                if (fieldScore.getText() != null && !fieldScore.getText().trim().isEmpty()) {
                    score = Integer.parseInt(fieldScore.getText().trim());
                }
            } catch (NumberFormatException e) {
                throw new IllegalArgumentException("Score must be a number between 0 and 100");
            }

            if (editingInterview != null) {
                // Update mode
                editingInterview.setInterviewerId(interviewerId);
                editingInterview.setInterviewDate(fieldDate.getValue().atStartOfDay());
                editingInterview.setType(fieldType.getValue());
                editingInterview.setLocation(fieldLocation.getText());
                editingInterview.setMeetingLink(fieldMeetingLink.getText());
                editingInterview.setFeedback(fieldNotes.getText());
                editingInterview.setScore(score);
                editingInterview.setResult(fieldResult.getValue() != null ? fieldResult.getValue() : "PENDING");

                interviewService.updateInterview(editingInterview);
            } else {
                // Create mode
                Interview interview = new Interview(
                        appId,
                        interviewerId,
                        fieldDate.getValue() != null ? fieldDate.getValue().atStartOfDay() : LocalDateTime.now(),
                        fieldType.getValue() != null ? fieldType.getValue() : "TECHNICAL",
                        fieldLocation.getText(),
                        fieldMeetingLink.getText(),
                        fieldNotes.getText(),
                        score,
                        fieldResult.getValue() != null ? fieldResult.getValue() : "PENDING");
                interviewService.scheduleInterview(interview);
            }

            mainController.handleCloseModal();
            refreshTable(); // Update table and stats
            mainController.handleInterviews();
        } catch (IllegalArgumentException e) {
            showErrorAlert("Validation Error", e.getMessage());
        } catch (SQLException e) {
            showErrorAlert("Database Error", "Failed to save interview: " + e.getMessage());
        }
    }

    private void validateInputs() {
        // Only validate if form fields exist (i.e., when in modal form mode)
        if (fieldApplication == null)
            return;
            
        if (fieldApplication.getValue() == null)
            throw new IllegalArgumentException("Application selection is required");
        if (fieldDate.getValue() == null)
            throw new IllegalArgumentException("Interview date is required");
        if (fieldType.getValue() == null)
            throw new IllegalArgumentException("Interview type is required");
        if (fieldInterviewer.getValue() == null)
            throw new IllegalArgumentException("Interviewer selection is required");

        // Score validation
        String scoreStr = fieldScore.getText();
        if (scoreStr != null && !scoreStr.trim().isEmpty()) {
            try {
                int score = Integer.parseInt(scoreStr.trim());
                if (score < 0 || score > 100)
                    throw new IllegalArgumentException("Score must be between 0 and 100");
            } catch (NumberFormatException e) {
                throw new IllegalArgumentException("Score must be a valid number");
            }
        }
    }

    @Override
    public void clearFields() {
        if (fieldLocation != null)
            fieldLocation.clear();
        if (fieldMeetingLink != null)
            fieldMeetingLink.clear();
        if (fieldNotes != null)
            fieldNotes.clear();
        if (fieldScore != null)
            fieldScore.clear();
        if (fieldApplication != null)
            fieldApplication.setValue(null);
        if (fieldType != null)
            fieldType.setValue(null);
        if (fieldInterviewer != null)
            fieldInterviewer.setValue(null);
        if (fieldResult != null)
            fieldResult.setValue(null);
        if (fieldDate != null)
            fieldDate.setValue(null);

        editingInterview = null;
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

    private void showErrorAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
