package org.example.ui.controller.Rh;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.stage.FileChooser;
import javafx.stage.Stage;
import javafx.scene.control.Alert;
import javafx.scene.control.Alert.AlertType;
import javafx.scene.control.ButtonType;
import javafx.scene.control.ProgressIndicator;
import javafx.concurrent.Task;
import javafx.application.Platform;

import org.example.ui.MainApp;
import service.ImportExport.CsvImportService;
import service.ImportExport.CsvExportService;

import java.io.File;
import java.util.Optional;

/**
 * Controller for CSV Import/Export functionality in RH dashboard
 */
public class RHCsvImportExportController {
    
    @FXML
    private Button importJobOffersBtn;
    
    @FXML
    private Button importApplicationsBtn;
    
    @FXML
    private Button importInterviewsBtn;
    
    @FXML
    private Button exportJobOffersBtn;
    
    @FXML
    private Button exportApplicationsBtn;
    
    @FXML
    private Button exportInterviewsBtn;
    
    @FXML
    private Button generateTemplatesBtn;
    
    @FXML
    private Button backBtn;
    
    @FXML
    private ProgressIndicator progressIndicator;
    
    private Stage currentStage;
    private CsvImportService importService;
    private CsvExportService exportService;
    private Runnable onJobOffersImportCallback;
    private Runnable onApplicationsImportCallback;
    private Runnable onInterviewsImportCallback;
    
    public RHCsvImportExportController() {
        this.importService = new CsvImportService();
        this.exportService = new CsvExportService();
    }
    
    @FXML
    public void initialize() {
        // Initialize button styles
        setupButtonStyles();
    }
    
    private void setupButtonStyles() {
        String buttonStyle = "-fx-background-color: #3498db; -fx-text-fill: white; " +
                         "-fx-background-radius: 8; -fx-padding: 10 20; " +
                         "-fx-font-size: 14px; -fx-cursor: hand; " +
                         "-fx-min-width: 200px; -fx-min-height: 40px;";
        
        importJobOffersBtn.setStyle(buttonStyle);
        importApplicationsBtn.setStyle(buttonStyle);
        importInterviewsBtn.setStyle(buttonStyle);
        exportJobOffersBtn.setStyle(buttonStyle);
        exportApplicationsBtn.setStyle(buttonStyle);
        exportInterviewsBtn.setStyle(buttonStyle);
        generateTemplatesBtn.setStyle(buttonStyle);
        
        String backStyle = "-fx-background-color: #6c757d; -fx-text-fill: white; " +
                        "-fx-background-radius: 8; -fx-padding: 10 20; " +
                        "-fx-font-size: 14px; -fx-cursor: hand; " +
                        "-fx-min-width: 200px; -fx-min-height: 40px;";
        backBtn.setStyle(backStyle);
    }
    
    @FXML
    private void importJobOffers() {
        FileChooser fileChooser = createFileChooser("Import Job Offers", "CSV Files", "*.csv");
        File selectedFile = fileChooser.showOpenDialog(currentStage);
        
        if (selectedFile != null) {
            showConfirmationDialog(
                "Import Job Offers",
                "Are you sure you want to import job offers from:\n" + selectedFile.getName() + "\n\n" +
                "This will add new job offers to the database.",
                () -> performImportJobOffers(selectedFile)
            );
        }
    }
    
    @FXML
    private void importApplications() {
        FileChooser fileChooser = createFileChooser("Import Applications", "CSV Files", "*.csv");
        File selectedFile = fileChooser.showOpenDialog(currentStage);
        
        if (selectedFile != null) {
            showConfirmationDialog(
                "Import Applications",
                "Are you sure you want to import applications from:\n" + selectedFile.getName() + "\n\n" +
                "This will add new candidate applications to the database.",
                () -> performImportApplications(selectedFile)
            );
        }
    }
    
    @FXML
    private void importInterviews() {
        FileChooser fileChooser = createFileChooser("Import Interviews", "CSV Files", "*.csv");
        File selectedFile = fileChooser.showOpenDialog(currentStage);
        
        if (selectedFile != null) {
            showConfirmationDialog(
                "Import Interviews",
                "Are you sure you want to import interviews from:\n" + selectedFile.getName() + "\n\n" +
                "This will add new interview schedules to the database.",
                () -> performImportInterviews(selectedFile)
            );
        }
    }
    
    @FXML
    private void exportJobOffers() {
        FileChooser fileChooser = createFileChooser("Export Job Offers", "CSV Files", "*.csv");
        File selectedFile = fileChooser.showSaveDialog(currentStage);
        
        if (selectedFile != null) {
            showConfirmationDialog(
                "Export Job Offers",
                "Are you sure you want to export job offers to:\n" + selectedFile.getName() + "\n\n" +
                "This will export all current job offers from the database.",
                () -> performExportJobOffers(selectedFile)
            );
        }
    }
    
    @FXML
    private void exportApplications() {
        FileChooser fileChooser = createFileChooser("Export Applications", "CSV Files", "*.csv");
        File selectedFile = fileChooser.showSaveDialog(currentStage);
        
        if (selectedFile != null) {
            showConfirmationDialog(
                "Export Applications",
                "Are you sure you want to export applications to:\n" + selectedFile.getName() + "\n\n" +
                "This will export all candidate applications from the database.",
                () -> performExportApplications(selectedFile)
            );
        }
    }
    
    @FXML
    private void exportInterviews() {
        FileChooser fileChooser = createFileChooser("Export Interviews", "CSV Files", "*.csv");
        File selectedFile = fileChooser.showSaveDialog(currentStage);
        
        if (selectedFile != null) {
            showConfirmationDialog(
                "Export Interviews",
                "Are you sure you want to export interviews to:\n" + selectedFile.getName() + "\n\n" +
                "This will export all interview data from the database.",
                () -> performExportInterviews(selectedFile)
            );
        }
    }
    
    @FXML
    private void generateTemplates() {
        Alert templateChoice = new Alert(AlertType.CONFIRMATION);
        templateChoice.setTitle("Generate Templates");
        templateChoice.setHeaderText("Choose which templates to generate:");
        
        ButtonType jobOfferBtn = new ButtonType("Job Offer Template");
        ButtonType applicationBtn = new ButtonType("Application Template");
        ButtonType interviewBtn = new ButtonType("Interview Template");
        ButtonType cancelBtn = new ButtonType("Cancel", ButtonBar.ButtonData.CANCEL_CLOSE);
        
        templateChoice.getButtonTypes().setAll(jobOfferBtn, applicationBtn, interviewBtn, cancelBtn);
        
        Optional<ButtonType> result = templateChoice.showAndWait();
        
        result.ifPresent(buttonType -> {
            if (buttonType == jobOfferBtn) {
                FileChooser fileChooser = createFileChooser("Save Job Offer Template", "CSV Files", "*.csv");
                File selectedFile = fileChooser.showSaveDialog(currentStage);
                if (selectedFile != null) {
                    exportService.generateJobOfferTemplate(selectedFile.getAbsolutePath());
                    showSuccessDialog("Template generated successfully: " + selectedFile.getName());
                }
            } else if (buttonType == applicationBtn) {
                FileChooser fileChooser = createFileChooser("Save Application Template", "CSV Files", "*.csv");
                File selectedFile = fileChooser.showSaveDialog(currentStage);
                if (selectedFile != null) {
                    exportService.generateApplicationTemplate(selectedFile.getAbsolutePath());
                    showSuccessDialog("Template generated successfully: " + selectedFile.getName());
                }
            } else if (buttonType == interviewBtn) {
                FileChooser fileChooser = createFileChooser("Save Interview Template", "CSV Files", "*.csv");
                File selectedFile = fileChooser.showSaveDialog(currentStage);
                if (selectedFile != null) {
                    exportService.generateInterviewTemplate(selectedFile.getAbsolutePath());
                    showSuccessDialog("Template generated successfully: " + selectedFile.getName());
                }
            }
        });
    }
    
    @FXML
    private void goBack() {
        // Close the current window/stage
        if (currentStage != null) {
            currentStage.close();
        } else {
            // Fallback: try to get the current window from any button
            Stage stage = (Stage) backBtn.getScene().getWindow();
            stage.close();
        }
    }
    
    // Background task methods
    private void performImportJobOffers(File file) {
        showProgress(true);
        Task<CsvImportService.ImportResult> task = new Task<CsvImportService.ImportResult>() {
            @Override
            protected CsvImportService.ImportResult call() throws Exception {
                return importService.importJobOffers(file.getAbsolutePath());
            }
        };
        
        task.setOnSucceeded(e -> {
            showProgress(false);
            CsvImportService.ImportResult result = task.getValue();
            showImportResultDialog("Job Offers Import", result);
            // Trigger refresh callback if available
            if (onJobOffersImportCallback != null) {
                onJobOffersImportCallback.run();
            }
        });
        
        task.setOnFailed(e -> {
            showProgress(false);
            showErrorDialog("Import failed: " + task.getException().getMessage());
        });
        
        new Thread(task).start();
    }
    
    private void performImportApplications(File file) {
        showProgress(true);
        Task<CsvImportService.ImportResult> task = new Task<CsvImportService.ImportResult>() {
            @Override
            protected CsvImportService.ImportResult call() throws Exception {
                return importService.importApplications(file.getAbsolutePath());
            }
        };
        
        task.setOnSucceeded(e -> {
            showProgress(false);
            CsvImportService.ImportResult result = task.getValue();
            showImportResultDialog("Applications Import", result);
        });
        
        task.setOnFailed(e -> {
            showProgress(false);
            showErrorDialog("Import failed: " + task.getException().getMessage());
        });
        
        new Thread(task).start();
    }
    
    private void performImportInterviews(File file) {
        showProgress(true);
        Task<CsvImportService.ImportResult> task = new Task<CsvImportService.ImportResult>() {
            @Override
            protected CsvImportService.ImportResult call() throws Exception {
                return importService.importInterviews(file.getAbsolutePath());
            }
        };
        
        task.setOnSucceeded(e -> {
            showProgress(false);
            CsvImportService.ImportResult result = task.getValue();
            showImportResultDialog("Interviews Import", result);
        });
        
        task.setOnFailed(e -> {
            showProgress(false);
            showErrorDialog("Import failed: " + task.getException().getMessage());
        });
        
        new Thread(task).start();
    }
    
    private void performExportJobOffers(File file) {
        showProgress(true);
        Task<CsvExportService.ExportResult> task = new Task<CsvExportService.ExportResult>() {
            @Override
            protected CsvExportService.ExportResult call() throws Exception {
                return exportService.exportJobOffers(file.getAbsolutePath(), false);
            }
        };
        
        task.setOnSucceeded(e -> {
            showProgress(false);
            CsvExportService.ExportResult result = task.getValue();
            showExportResultDialog("Job Offers Export", result);
        });
        
        task.setOnFailed(e -> {
            showProgress(false);
            showErrorDialog("Export failed: " + task.getException().getMessage());
        });
        
        new Thread(task).start();
    }
    
    private void performExportApplications(File file) {
        showProgress(true);
        Task<CsvExportService.ExportResult> task = new Task<CsvExportService.ExportResult>() {
            @Override
            protected CsvExportService.ExportResult call() throws Exception {
                return exportService.exportApplications(file.getAbsolutePath(), false);
            }
        };
        
        task.setOnSucceeded(e -> {
            showProgress(false);
            CsvExportService.ExportResult result = task.getValue();
            showExportResultDialog("Applications Export", result);
        });
        
        task.setOnFailed(e -> {
            showProgress(false);
            showErrorDialog("Export failed: " + task.getException().getMessage());
        });
        
        new Thread(task).start();
    }
    
    private void performExportInterviews(File file) {
        showProgress(true);
        Task<CsvExportService.ExportResult> task = new Task<CsvExportService.ExportResult>() {
            @Override
            protected CsvExportService.ExportResult call() throws Exception {
                return exportService.exportInterviews(file.getAbsolutePath(), false);
            }
        };
        
        task.setOnSucceeded(e -> {
            showProgress(false);
            CsvExportService.ExportResult result = task.getValue();
            showExportResultDialog("Interviews Export", result);
        });
        
        task.setOnFailed(e -> {
            showProgress(false);
            showErrorDialog("Export failed: " + task.getException().getMessage());
        });
        
        new Thread(task).start();
    }
    
    // Helper methods
    private FileChooser createFileChooser(String title, String description, String extension) {
        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle(title);
        FileChooser.ExtensionFilter extFilter = new FileChooser.ExtensionFilter(description, extension);
        fileChooser.getExtensionFilters().add(extFilter);
        fileChooser.setInitialDirectory(new File(System.getProperty("user.home")));
        return fileChooser;
    }
    
    private void showConfirmationDialog(String title, String message, Runnable onConfirm) {
        Alert alert = new Alert(AlertType.CONFIRMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.getButtonTypes().setAll(ButtonType.YES, ButtonType.NO);
        
        Optional<ButtonType> result = alert.showAndWait();
        if (result.isPresent() && result.get() == ButtonType.YES) {
            onConfirm.run();
        }
    }
    
    private void showImportResultDialog(String title, CsvImportService.ImportResult result) {
        Alert alert = new Alert(AlertType.INFORMATION);
        alert.setTitle(title + " Result");
        alert.setHeaderText(null);
        
        String content = "Successfully imported: " + result.getSuccessCount() + " records\n\n";
        
        if (result.hasErrors()) {
            content += "Errors encountered: " + result.getErrors().size() + "\n";
            content += "First few errors:\n";
            for (int i = 0; i < Math.min(3, result.getErrors().size()); i++) {
                content += "• " + result.getErrors().get(i) + "\n";
            }
            if (result.getErrors().size() > 3) {
                content += "... and " + (result.getErrors().size() - 3) + " more errors";
            }
        } else {
            content += "Import completed successfully with no errors!";
        }
        
        alert.setContentText(content);
        alert.showAndWait();
    }
    
    private void showExportResultDialog(String title, CsvExportService.ExportResult result) {
        Alert alert = new Alert(AlertType.INFORMATION);
        alert.setTitle(title + " Result");
        alert.setHeaderText(null);
        
        String content;
        if (result.isSuccess()) {
            content = "Successfully exported: " + result.getRecordCount() + " records\n\n";
            content += "Export completed successfully!";
        } else {
            content = "Export failed!\n\n";
            content += "Error: " + result.getError();
        }
        
        alert.setContentText(content);
        alert.showAndWait();
    }
    
    private void showSuccessDialog(String message) {
        Alert alert = new Alert(AlertType.INFORMATION);
        alert.setTitle("Success");
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
    
    private void showErrorDialog(String message) {
        Alert alert = new Alert(AlertType.ERROR);
        alert.setTitle("Error");
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
    
    private void showProgress(boolean show) {
        Platform.runLater(() -> {
            progressIndicator.setVisible(show);
            importJobOffersBtn.setDisable(show);
            importApplicationsBtn.setDisable(show);
            importInterviewsBtn.setDisable(show);
            exportJobOffersBtn.setDisable(show);
            exportApplicationsBtn.setDisable(show);
            exportInterviewsBtn.setDisable(show);
            generateTemplatesBtn.setDisable(show);
            backBtn.setDisable(show);
        });
    }
    
    public void setCurrentStage(Stage stage) {
        this.currentStage = stage;
    }
    
    public void setOnJobOffersImportCallback(Runnable callback) {
        this.onJobOffersImportCallback = callback;
    }
    
    public void setOnApplicationsImportCallback(Runnable callback) {
        this.onApplicationsImportCallback = callback;
    }
    
    public void setOnInterviewsImportCallback(Runnable callback) {
        this.onInterviewsImportCallback = callback;
    }
}
