package org.example.model;

import org.example.service.PublicHolidayService.HolidayEntry;

import java.util.Collections;
import java.util.List;

/**
 * Résultat détaillé d'une tentative de soumission de demande de congé.
 *
 * <ul>
 *   <li>{@link Status#SUCCESS}               — demande enregistrée.</li>
 *   <li>{@link Status#BLOCKED_BY_HOLIDAY}    — la période contient un ou plusieurs jours fériés.</li>
 *   <li>{@link Status#NO_WORKING_DAYS}       — la période ne contient aucun jour ouvrable.</li>
 *   <li>{@link Status#VALIDATION_ERROR}      — données invalides (dates passées, chevauchement…).</li>
 *   <li>{@link Status#DB_ERROR}              — erreur base de données.</li>
 * </ul>
 */
public class LeaveSubmitResult {

    public enum Status {
        SUCCESS,
        BLOCKED_BY_HOLIDAY,
        NO_WORKING_DAYS,
        VALIDATION_ERROR,
        DB_ERROR
    }

    private final Status status;
    private final String message;
    private final int workingDays;
    private final int calendarDays;
    private final List<HolidayEntry> holidaysFound;

    // ---- constructors -------------------------------------------------------

    private LeaveSubmitResult(Status status, String message,
                               int workingDays, int calendarDays,
                               List<HolidayEntry> holidaysFound) {
        this.status        = status;
        this.message       = message;
        this.workingDays   = workingDays;
        this.calendarDays  = calendarDays;
        this.holidaysFound = holidaysFound != null ? holidaysFound : Collections.emptyList();
    }

    // ---- factory methods ----------------------------------------------------

    public static LeaveSubmitResult success(int workingDays, int calendarDays) {
        return new LeaveSubmitResult(Status.SUCCESS,
                "Demande soumise avec succès ! (" + workingDays + " jour(s) ouvrable(s))",
                workingDays, calendarDays, null);
    }

    public static LeaveSubmitResult blockedByHoliday(List<HolidayEntry> holidays,
                                                      int workingDays, int calendarDays) {
        StringBuilder msg = new StringBuilder("❌ Demande refusée — jours fériés détectés :\n");
        for (HolidayEntry h : holidays) msg.append("  • ").append(h).append("\n");
        return new LeaveSubmitResult(Status.BLOCKED_BY_HOLIDAY, msg.toString().trim(),
                workingDays, calendarDays, holidays);
    }

    public static LeaveSubmitResult noWorkingDays(int calendarDays) {
        return new LeaveSubmitResult(Status.NO_WORKING_DAYS,
                "❌ Aucun jour ouvrable dans la période sélectionnée (week-ends / jours fériés uniquement).",
                0, calendarDays, null);
    }

    public static LeaveSubmitResult validationError(String reason) {
        return new LeaveSubmitResult(Status.VALIDATION_ERROR, reason, 0, 0, null);
    }

    public static LeaveSubmitResult dbError() {
        return new LeaveSubmitResult(Status.DB_ERROR,
                "❌ Erreur lors de l'enregistrement. Veuillez réessayer.", 0, 0, null);
    }

    // ---- getters ------------------------------------------------------------

    public boolean isSuccess()               { return status == Status.SUCCESS; }
    public boolean isBlockedByHoliday()      { return status == Status.BLOCKED_BY_HOLIDAY; }
    public Status  getStatus()               { return status; }
    public String  getMessage()              { return message; }
    public int     getWorkingDays()          { return workingDays; }
    public int     getCalendarDays()         { return calendarDays; }
    public List<HolidayEntry> getHolidaysFound() { return holidaysFound; }

    @Override
    public String toString() {
        return "LeaveSubmitResult{status=" + status + ", workingDays=" + workingDays + "}";
    }
}
