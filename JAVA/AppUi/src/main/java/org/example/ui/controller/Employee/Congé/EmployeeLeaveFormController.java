package org.example.ui.controller.Employee.Congé;

import javafx.collections.FXCollections;
import javafx.concurrent.Task;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.util.Callback;
import org.example.model.LeaveBalance;
import org.example.model.LeaveRequest;
import org.example.model.LeaveSubmitResult;
import org.example.service.LeaveBalanceService;
import org.example.service.LeaveRequestService;
import org.example.service.PublicHolidayService;
import org.example.ui.controller.Rh.Congé.notification.AppNotification;
import org.example.ui.controller.Rh.Congé.notification.InAppNotificationService;

import java.time.DayOfWeek;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.HashSet;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Contrôleur du formulaire de soumission de congé (côté Employé).
 * Gère : calendrier intelligent, calcul jours ouvrables, validation, soumission.
 */
public class EmployeeLeaveFormController {

    @FXML private DatePicker startDatePicker;
    @FXML private DatePicker endDatePicker;
    @FXML private ComboBox<String> leaveTypeComboBox;
    @FXML private TextArea reasonTextArea;
    @FXML private Label daysCountLabel;
    @FXML private Label calendarDaysLabel;
    @FXML private Label weekendDaysLabel;
    @FXML private Button submitButton;
    @FXML private Button clearButton;

    // Panneaux jours fériés
    @FXML private VBox holidayWarningBox;
    @FXML private VBox noWorkingDaysBox;
    @FXML private Label holidayListLabel;

    // Services
    private final LeaveRequestService leaveRequestService = new LeaveRequestService();
    private final LeaveBalanceService leaveBalanceService  = new LeaveBalanceService();
    private final PublicHolidayService publicHolidayService = new PublicHolidayService();
    private final ConcurrentHashMap<Integer, Set<LocalDate>> holidayYearCache = new ConcurrentHashMap<>();

    // State
    private int    currentEmployeeId;
    private String currentEmployeeName;
    private Runnable onSubmitSuccess;

    @FXML
    public void initialize() {
        setupLeaveTypes();
        setupDateListeners();
        setupInputValidation();
        submitButton.setOnAction(e -> submitLeaveRequest());
        clearButton.setOnAction(e -> clearForm());

        // Pré-charger les jours fériés
        Task<Void> preload = new Task<>() {
            @Override protected Void call() {
                int y = LocalDate.now().getYear();
                getHolidayDates(y);
                getHolidayDates(y + 1);
                return null;
            }
        };
        new Thread(preload, "holiday-preload").start();
    }

    /**
     * Initialise le contrôleur avec les données de l'employé connecté.
     *
     * @param onSubmitSuccess callback appelé après soumission réussie (pour rafraîchir historique/stats).
     */
    public void initData(int employeeId, String employeeName, Runnable onSubmitSuccess) {
        this.currentEmployeeId   = employeeId;
        this.currentEmployeeName = employeeName;
        this.onSubmitSuccess     = onSubmitSuccess;
    }

    // ─── Types de congé ──────────────────────────────────────────────────────────

    private void setupLeaveTypes() {
        leaveTypeComboBox.setItems(FXCollections.observableArrayList(
                "Congé annuel", "Congé maladie", "Congé sans solde",
                "Congé parental", "Congé exceptionnel", "RTT", "Autres"));
        leaveTypeComboBox.getSelectionModel().selectFirst();
    }

    // ─── Listeners dates ─────────────────────────────────────────────────────────

    private void setupDateListeners() {
        startDatePicker.valueProperty().addListener((obs, o, n) -> calculateDays());
        endDatePicker.valueProperty().addListener((obs, o, n) -> calculateDays());
    }

    // ─── Validation visuelle & calendrier intelligent ────────────────────────────

    private void setupInputValidation() {
        startDatePicker.setDayCellFactory(createSmartDayCellFactory(false));
        endDatePicker.setDayCellFactory(createSmartDayCellFactory(true));

        startDatePicker.valueProperty().addListener((obs, o, n) -> {
            endDatePicker.setDayCellFactory(createSmartDayCellFactory(true));
            validateDateField(startDatePicker, n);
        });
        endDatePicker.valueProperty().addListener((obs, o, n) -> validateDateField(endDatePicker, n));

        reasonTextArea.textProperty().addListener((obs, o, n) -> {
            if (n != null && n.length() > 500) {
                reasonTextArea.setText(o);
                showAlert(Alert.AlertType.WARNING, "Attention",
                        "La raison ne peut pas dépasser 500 caractères.");
            }
        });
    }

    private Set<LocalDate> getHolidayDates(int year) {
        return holidayYearCache.computeIfAbsent(year, y -> {
            Set<LocalDate> dates = new HashSet<>();
            for (PublicHolidayService.HolidayEntry e : publicHolidayService.getHolidays(y, PublicHolidayService.DEFAULT_COUNTRY))
                dates.add(e.date);
            return dates;
        });
    }

    private String getHolidayName(LocalDate date) {
        for (PublicHolidayService.HolidayEntry e : publicHolidayService.getHolidays(date.getYear(), PublicHolidayService.DEFAULT_COUNTRY))
            if (e.date.equals(date)) return e.localName != null ? e.localName : e.name;
        return "Jour férié";
    }

    private Callback<DatePicker, DateCell> createSmartDayCellFactory(boolean isEndPicker) {
        return picker -> new DateCell() {
            @Override
            public void updateItem(LocalDate date, boolean empty) {
                super.updateItem(date, empty);
                if (empty || date == null) { setStyle(""); setTooltip(null); return; }

                LocalDate today = LocalDate.now();
                DayOfWeek dow   = date.getDayOfWeek();
                boolean isPast      = date.isBefore(today);
                boolean isWeekend   = dow == DayOfWeek.SATURDAY || dow == DayOfWeek.SUNDAY;
                boolean isHoliday   = getHolidayDates(date.getYear()).contains(date);
                LocalDate startVal  = startDatePicker.getValue();
                boolean beforeStart = isEndPicker && startVal != null && date.isBefore(startVal);

                setDisable(isPast || isWeekend || isHoliday || beforeStart);

                if (isPast || beforeStart) {
                    setStyle("-fx-background-color: #fde8ea; -fx-text-fill: #a93046; -fx-opacity: 0.6;");
                    setTooltip(new Tooltip(beforeStart ? "⬅ Avant la date de début" : "⬅ Date passée"));
                } else if (isWeekend) {
                    String label = (dow == DayOfWeek.SATURDAY) ? "Samedi" : "Dimanche";
                    setStyle("-fx-background-color: #dce4f0; -fx-text-fill: #4a6090; -fx-font-weight: bold;");
                    setTooltip(new Tooltip("🚫 " + label + " — Week-end"));
                } else if (isHoliday) {
                    setStyle("-fx-background-color: #fff0d9; -fx-text-fill: #b36000; -fx-font-weight: bold;");
                    setTooltip(new Tooltip("🇹🇳 Jour férié : " + getHolidayName(date)));
                } else {
                    setStyle(""); setTooltip(null);
                }
            }
        };
    }

    private void validateDateField(DatePicker dp, LocalDate value) {
        if (value == null)
            dp.setStyle("-fx-border-color: #cbd5e0; -fx-border-width: 1.5; -fx-border-radius: 8;");
        else if (value.isBefore(LocalDate.now()))
            dp.setStyle("-fx-border-color: #f56565; -fx-border-width: 2; -fx-border-radius: 8;");
        else
            dp.setStyle("-fx-border-color: #48bb78; -fx-border-width: 2; -fx-border-radius: 8;");
    }

    // ─── Calcul des jours ────────────────────────────────────────────────────────

    private void calculateDays() {
        LocalDate start = startDatePicker.getValue();
        LocalDate end   = endDatePicker.getValue();

        if (start == null || end == null || end.isBefore(start)) {
            daysCountLabel.setText("--");
            daysCountLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #667eea; -fx-font-weight: bold;");
            if (calendarDaysLabel != null) { calendarDaysLabel.setText("--"); calendarDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #4a5568; -fx-font-weight: bold;"); }
            if (weekendDaysLabel  != null) { weekendDaysLabel.setText("--");  weekendDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #718096; -fx-font-weight: bold;"); }
            hideHolidayPanels();
            return;
        }

        daysCountLabel.setText("⏳");
        daysCountLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #a0aec0; -fx-font-weight: bold;");
        if (calendarDaysLabel != null) { calendarDaysLabel.setText("⏳"); calendarDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #a0aec0; -fx-font-weight: bold;"); }
        if (weekendDaysLabel  != null) { weekendDaysLabel.setText("⏳");  weekendDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #a0aec0; -fx-font-weight: bold;"); }
        submitButton.setDisable(true);
        hideHolidayPanels();

        Task<LeaveSubmitResult> task = new Task<>() {
            @Override protected LeaveSubmitResult call() {
                return leaveRequestService.previewRequest(start, end);
            }
        };
        task.setOnSucceeded(e -> updateCalcDisplay(task.getValue()));
        task.setOnFailed(e -> {
            daysCountLabel.setText("erreur");
            daysCountLabel.setStyle("-fx-font-size: 14px; -fx-text-fill: #f56565; -fx-font-weight: bold;");
            submitButton.setDisable(false);
        });
        Thread t = new Thread(task); t.setDaemon(true); t.start();
    }

    private void updateCalcDisplay(LeaveSubmitResult result) {
        int wd  = result.getWorkingDays();
        int cal = result.getCalendarDays();
        int excl = cal - wd;

        if (calendarDaysLabel != null) {
            calendarDaysLabel.setText(cal + " j.");
            calendarDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #4a5568; -fx-font-weight: bold;");
        }
        if (weekendDaysLabel != null) {
            weekendDaysLabel.setText(excl + " j.");
            weekendDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #718096; -fx-font-weight: bold;");
        }

        if (result.isBlockedByHoliday()) {
            daysCountLabel.setText(wd + " j.");
            daysCountLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #c53030; -fx-font-weight: bold;");
            showHolidayWarning(result);
            submitButton.setDisable(true);
        } else if (result.getStatus() == LeaveSubmitResult.Status.NO_WORKING_DAYS) {
            daysCountLabel.setText("0 j.");
            daysCountLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #c05621; -fx-font-weight: bold;");
            showNoWorkingDaysPanel();
            submitButton.setDisable(true);
        } else {
            daysCountLabel.setText(wd + " j.");
            daysCountLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #667eea; -fx-font-weight: bold;");
            hideHolidayPanels();
            submitButton.setDisable(false);
        }
    }

    private void showHolidayWarning(LeaveSubmitResult result) {
        if (holidayWarningBox == null) return;
        StringBuilder sb = new StringBuilder();
        DateTimeFormatter fmt = DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy", java.util.Locale.FRENCH);
        for (var h : result.getHolidaysFound())
            sb.append("• ").append(h.date.format(fmt)).append(" — ").append(h.localName).append("\n");
        if (holidayListLabel != null) holidayListLabel.setText(sb.toString().trim());
        holidayWarningBox.setManaged(true);  holidayWarningBox.setVisible(true);
        if (noWorkingDaysBox != null) { noWorkingDaysBox.setManaged(false); noWorkingDaysBox.setVisible(false); }
    }

    private void showNoWorkingDaysPanel() {
        if (noWorkingDaysBox == null) return;
        noWorkingDaysBox.setManaged(true);  noWorkingDaysBox.setVisible(true);
        if (holidayWarningBox != null) { holidayWarningBox.setManaged(false); holidayWarningBox.setVisible(false); }
    }

    private void hideHolidayPanels() {
        if (holidayWarningBox != null) { holidayWarningBox.setManaged(false); holidayWarningBox.setVisible(false); }
        if (noWorkingDaysBox  != null) { noWorkingDaysBox.setManaged(false);  noWorkingDaysBox.setVisible(false); }
    }

    // ─── Soumission ──────────────────────────────────────────────────────────────

    @FXML
    private void submitLeaveRequest() {
        if (!validateForm()) return;

        LocalDate startDate = startDatePicker.getValue();
        LocalDate endDate   = endDatePicker.getValue();
        String leaveType    = leaveTypeComboBox.getValue();
        String reason       = reasonTextArea.getText().trim();

        // Avertir si solde insuffisant
        int requestedDays = leaveRequestService.previewRequest(startDate, endDate).getWorkingDays();
        LeaveBalance balance = leaveBalanceService.getBalance(currentEmployeeId);
        if (balance != null && requestedDays > 0 && balance.getAvailableDays() < requestedDays) {
            Alert warn = new Alert(Alert.AlertType.WARNING);
            warn.setTitle("⚠️ Solde Insuffisant");
            warn.setHeaderText("Solde disponible : " + balance.getFormattedAvailableDays() + " jour(s)");
            warn.setContentText(
                    "Votre demande nécessite " + requestedDays + " jour(s) ouvrable(s) mais votre solde est de "
                    + balance.getFormattedAvailableDays() + " jour(s).\n\n"
                    + "Vous pouvez tout de même soumettre la demande ; le RH décidera de l'approbation.");
            applyDialogStyles(warn.getDialogPane());
            warn.showAndWait();
        }

        // Chevauchement
        if (leaveRequestService.hasDateOverlap(currentEmployeeId, startDate, endDate)) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Chevauchement Détecté",
                    "Vous avez déjà une demande de congé approuvée ou en attente sur cette période.\n\n"
                    + "Veuillez choisir d'autres dates.");
            return;
        }

        // Confirmation
        Alert confirmation = new Alert(Alert.AlertType.CONFIRMATION);
        confirmation.setTitle("Confirmation de Demande");
        confirmation.setHeaderText("Confirmer la soumission ?");
        LeaveSubmitResult preview = leaveRequestService.previewRequest(startDate, endDate);
        confirmation.setContentText(String.format(
                "Type: %s\nPériode: %s au %s\nJours ouvrables: %d\nJours calendaires: %d\n\nVoulez-vous soumettre cette demande ?",
                leaveType, startDate, endDate, preview.getWorkingDays(), preview.getCalendarDays()));
        applyDialogStyles(confirmation.getDialogPane());

        confirmation.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                LeaveSubmitResult result = leaveRequestService.submitLeaveRequest(
                        currentEmployeeId, currentEmployeeName, startDate, endDate, leaveType, reason);

                if (result.isSuccess()) {
                    showAlert(Alert.AlertType.INFORMATION, "✅ Succès",
                            "Votre demande de congé a été soumise avec succès!\n\n"
                            + result.getMessage() + "\n\n"
                            + "Elle sera examinée par le service RH dans les plus brefs délais.");
                    InAppNotificationService.getInstance().notifyAllRH(
                            currentEmployeeName + " a soumis une demande de congé ("
                            + leaveType + ") du " + startDate + " au " + endDate
                            + " — " + result.getWorkingDays() + " jour(s) ouvrable(s)",
                            AppNotification.Type.LEAVE_SUBMITTED);
                    clearForm();
                    if (onSubmitSuccess != null) onSubmitSuccess.run();
                } else {
                    showAlert(Alert.AlertType.ERROR, "❌ Demande refusée", result.getMessage());
                    updateCalcDisplay(result);
                }
            }
        });
    }

    // ─── Validation ──────────────────────────────────────────────────────────────

    private boolean validateForm() {
        if (startDatePicker.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Validation", "Veuillez sélectionner une date de début.");
            startDatePicker.setStyle("-fx-border-color: #f56565; -fx-border-width: 2; -fx-border-radius: 8;");
            return false;
        }
        if (endDatePicker.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Validation", "Veuillez sélectionner une date de fin.");
            endDatePicker.setStyle("-fx-border-color: #f56565; -fx-border-width: 2; -fx-border-radius: 8;");
            return false;
        }
        if (startDatePicker.getValue().isBefore(LocalDate.now())) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Validation", "La date de début ne peut pas être dans le passé.");
            startDatePicker.setStyle("-fx-border-color: #f56565; -fx-border-width: 2; -fx-border-radius: 8;");
            return false;
        }
        if (endDatePicker.getValue().isBefore(startDatePicker.getValue())) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Validation", "La date de fin doit être après ou égale à la date de début.");
            endDatePicker.setStyle("-fx-border-color: #f56565; -fx-border-width: 2; -fx-border-radius: 8;");
            return false;
        }
        long daysBetween = java.time.temporal.ChronoUnit.DAYS.between(startDatePicker.getValue(), endDatePicker.getValue()) + 1;
        if (daysBetween > 90) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Validation", "La période de congé ne peut pas dépasser 90 jours consécutifs.");
            return false;
        }
        if (leaveTypeComboBox.getValue() == null || leaveTypeComboBox.getValue().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Validation", "Veuillez sélectionner un type de congé.");
            return false;
        }
        String reason = reasonTextArea.getText();
        if (reason != null && reason.trim().length() > 500) {
            showAlert(Alert.AlertType.WARNING, "⚠️ Validation", "La raison ne peut pas dépasser 500 caractères.");
            return false;
        }
        return true;
    }

    // ─── Clear ───────────────────────────────────────────────────────────────────

    private void clearForm() {
        startDatePicker.setValue(null);
        endDatePicker.setValue(null);
        leaveTypeComboBox.getSelectionModel().selectFirst();
        reasonTextArea.clear();
        daysCountLabel.setText("--");
        daysCountLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #667eea; -fx-font-weight: bold;");
        if (calendarDaysLabel != null) { calendarDaysLabel.setText("--"); calendarDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #4a5568; -fx-font-weight: bold;"); }
        if (weekendDaysLabel  != null) { weekendDaysLabel.setText("--");  weekendDaysLabel.setStyle("-fx-font-size: 24px; -fx-text-fill: #718096; -fx-font-weight: bold;"); }
        hideHolidayPanels();
        submitButton.setDisable(false);
        startDatePicker.setStyle("-fx-border-color: #cbd5e0; -fx-border-width: 1.5; -fx-border-radius: 8;");
        endDatePicker.setStyle("-fx-border-color: #cbd5e0; -fx-border-width: 1.5; -fx-border-radius: 8;");
        leaveTypeComboBox.setStyle("-fx-border-color: #cbd5e0; -fx-border-width: 1.5; -fx-border-radius: 8;");
    }

    // ─── Utilitaires ─────────────────────────────────────────────────────────────

    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        applyDialogStyles(alert.getDialogPane());
        alert.showAndWait();
    }

    private void applyDialogStyles(DialogPane dialogPane) {
        try {
            String css = getClass().getResource("/css/style.css").toExternalForm();
            dialogPane.getStylesheets().add(css);
        } catch (Exception ignored) {}
    }
}
