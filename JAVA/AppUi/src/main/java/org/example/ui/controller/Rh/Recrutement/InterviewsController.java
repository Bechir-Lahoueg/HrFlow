package org.example.ui.controller.Rh.Recrutement;

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
import org.example.ui.util.CSVExporter;
import java.io.IOException;
import java.sql.SQLException;
import java.util.Arrays;
import java.util.List;
import org.example.ui.controller.Rh.RHBaseController;

public class InterviewsController extends RHBaseController {

    @FXML
    private TableView<Interview> interviewTableView;
    @FXML
    private TableColumn<Interview, String> colCandidateName, colInterviewer, colPosition, colDateTime, colType,
            colStatus;
    @FXML
    private TableColumn<Interview, Void> colActions;

    @FXML
    private Label statScheduled, statToday, statCompleted, statOffersExtended;
    @FXML
    private ComboBox<String> filterStatus, filterType;
    @FXML
    private TextField searchField;
    @FXML
    private Label paginationInfo;

    private InterviewService interviewService = new InterviewService();
    private ObservableList<Interview> masterData = FXCollections.observableArrayList();

    @FXML
    public void initialize() {
        if (interviewTableView != null) {
            setupTable();
            setupActionColumn();
            refreshTable();
        }
        setupFilters();
    }

    private void setupFilters() {
        try {
            List<String> types = interviewService.getDistinctInterviewTypes();
            if (!types.contains("All Types"))
                types.add(0, "All Types");
            if (filterType != null) {
                filterType.setItems(FXCollections.observableArrayList(types));
                filterType.setValue("All Types");
            }

            List<String> results = interviewService.getDistinctResults();
            if (!results.contains("All Statuses"))
                results.add(0, "All Statuses");
            if (filterStatus != null) {
                filterStatus.setItems(FXCollections.observableArrayList(results));
                filterStatus.setValue("All Statuses");
            }
        } catch (SQLException e) {
            System.err.println("Failed to load filters: " + e.getMessage());
        }
    }

    private void setupTable() {
        colCandidateName.setCellValueFactory(new PropertyValueFactory<>("candidateName"));
        colInterviewer.setCellValueFactory(new PropertyValueFactory<>("interviewerName"));
        colPosition.setCellValueFactory(new PropertyValueFactory<>("jobTitle"));
        colDateTime.setCellValueFactory(cellData -> new javafx.beans.property.SimpleStringProperty(
                cellData.getValue().getInterviewDate() != null ? cellData.getValue().getInterviewDate().toString()
                        : ""));
        colType.setCellValueFactory(new PropertyValueFactory<>("type"));
        colStatus.setCellValueFactory(new PropertyValueFactory<>("result"));
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

    @FXML
    public void refreshTable() {
        try {
            List<Interview> interviews = interviewService.getActiveInterviews();
            masterData.setAll(interviews);
            applyFilters();
            updateStats();
        } catch (SQLException e) {
            interviewTableView.setPlaceholder(new Label("Failed to load interviews: " + e.getMessage()));
        }
    }

    private void updateStats() {
        if (statScheduled != null)
            statScheduled.setText(String.valueOf(masterData.size()));
        long completed = masterData.stream().filter(i -> "PASS".equals(i.getResult()) || "FAIL".equals(i.getResult()))
                .count();
        if (statCompleted != null)
            statCompleted.setText(String.valueOf(completed));
    }

    @FXML
    private void handleTypeFilterChange() {
        applyFilters();
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
        String type = filterType != null ? filterType.getValue() : null;
        String status = filterStatus != null ? filterStatus.getValue() : null;
        String search = searchField != null ? searchField.getText().toLowerCase().trim() : "";

        ObservableList<Interview> filtered = masterData.filtered(i -> {
            boolean typeMatch = type == null || type.equals("All Types") || type.equalsIgnoreCase(i.getType());
            boolean statusMatch = status == null || status.equals("All Statuses")
                    || status.equalsIgnoreCase(i.getResult());
            boolean searchMatch = search.isEmpty() || i.getCandidateName().toLowerCase().contains(search)
                    || i.getInterviewerName().toLowerCase().contains(search);
            return typeMatch && statusMatch && searchMatch;
        });
        interviewTableView.setItems(filtered);
    }

    @FXML
    private void handleExport() {
        CSVExporter.exportToCSV(
                interviewTableView.getItems(),
                Arrays.asList("Candidate", "Interviewer", "Position", "Date/Time", "Type", "Status"),
                Arrays.asList("candidateName", "interviewerName", "jobTitle", "interviewDate", "type", "result"),
                "interviews_export.csv");
    }

    private void handleEdit(Interview interview) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/views/Rh-dashboard/Recrutement/InterviewForm.fxml"));
            Parent form = loader.load();
            InterviewFormController controller = loader.getController();
            controller.setMainController(mainController);
            controller.setEditingInterview(interview);
            controller.setOnSaveCallback(this::refreshTable);

            if (mainController != null) {
                mainController.showModal("Edit Interview", "Update schedule or results.", form, controller);
            }
        } catch (IOException e) {
            showErrorAlert("Error", "Could not load edit form: " + e.getMessage());
        }
    }

    private void handleDelete(Interview interview) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Delete Interview");
        alert.setContentText("Delete interview with " + interview.getCandidateName() + "?");
        if (alert.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK) {
            try {
                interviewService.softDeleteInterview(interview.getId());
                refreshTable();
            } catch (SQLException e) {
                showErrorAlert("Error", "Delete failed: " + e.getMessage());
            }
        }
    }

    @FXML
    private void handleDeleteSelected() {
        Interview selected = interviewTableView.getSelectionModel().getSelectedItem();
        if (selected != null)
            handleDelete(selected);
    }

    @FXML
    private void handleScheduleInterview() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/fxml/views/Rh-dashboard/Recrutement/InterviewForm.fxml"));
            Parent form = loader.load();
            InterviewFormController controller = loader.getController();
            controller.setMainController(mainController);
            controller.setOnSaveCallback(this::refreshTable);

            if (mainController != null) {
                mainController.showModal("Schedule Interview", "Set date, time, and interviewer.", form, controller);
            }
        } catch (IOException e) {
            showErrorAlert("Error", "Could not load form: " + e.getMessage());
        }
    }

    @FXML
    private void handleViewToday() {
    }

    @FXML
    private void handleDismissBanner() {
    }
}
