package org.example.service;

import org.example.model.LeaveRequest;

import java.time.LocalDate;
import java.util.Collections;
import java.util.List;

/**
 * Résultat d'une détection de conflits de congés.
 * Encapsule le nombre d'absences simultanées et la liste des demandes en conflit.
 */
public class ConflictResult {

    public enum Level {
        /** Aucun conflit détecté */
        OK,
        /** Avertissement : seuil approche (ex: 1 autre employé absent) */
        WARNING,
        /** Conflit critique : trop d'employés absents simultanément */
        CRITICAL
    }

    private final Level              level;
    private final int                concurrentAbsences;
    private final int                maxAllowed;
    private final List<LeaveRequest> conflictingRequests;
    private final LocalDate          peakStart;
    private final LocalDate          peakEnd;
    private final String             message;

    private ConflictResult(Level level, int concurrentAbsences, int maxAllowed,
                           List<LeaveRequest> conflictingRequests,
                           LocalDate peakStart, LocalDate peakEnd,
                           String message) {
        this.level               = level;
        this.concurrentAbsences  = concurrentAbsences;
        this.maxAllowed          = maxAllowed;
        this.conflictingRequests = Collections.unmodifiableList(conflictingRequests);
        this.peakStart           = peakStart;
        this.peakEnd             = peakEnd;
        this.message             = message;
    }

    // ─── Factory methods ────────────────────────────────────────────────────────

    /** Pas de conflit. */
    public static ConflictResult ok() {
        return new ConflictResult(Level.OK, 0, 0,
                Collections.emptyList(), null, null,
                "✅ Aucun conflit détecté sur cette période.");
    }

    /** Avertissement : un autre employé est déjà absent. */
    public static ConflictResult warning(int concurrent, int maxAllowed,
                                         List<LeaveRequest> conflicts,
                                         LocalDate peakStart, LocalDate peakEnd) {
        String msg = String.format(
                "⚠️  %d employé(s) déjà absent(s) sur cette période (max toléré : %d).\n" +
                "Pic d'absence : %s → %s",
                concurrent, maxAllowed, peakStart, peakEnd);
        return new ConflictResult(Level.WARNING, concurrent, maxAllowed,
                conflicts, peakStart, peakEnd, msg);
    }

    /** Conflit critique : seuil dépassé. */
    public static ConflictResult critical(int concurrent, int maxAllowed,
                                          List<LeaveRequest> conflicts,
                                          LocalDate peakStart, LocalDate peakEnd) {
        String msg = String.format(
                "🔴 CONFLIT CRITIQUE : %d employé(s) déjà absent(s) (max autorisé : %d).\n" +
                "Pic d'absence : %s → %s\n" +
                "Il est recommandé de refuser ou de reporter cette demande.",
                concurrent, maxAllowed, peakStart, peakEnd);
        return new ConflictResult(Level.CRITICAL, concurrent, maxAllowed,
                conflicts, peakStart, peakEnd, msg);
    }

    // ─── Getters ────────────────────────────────────────────────────────────────

    public Level              getLevel()               { return level; }
    public boolean            isOk()                   { return level == Level.OK; }
    public boolean            isWarning()              { return level == Level.WARNING; }
    public boolean            isCritical()             { return level == Level.CRITICAL; }
    public int                getConcurrentAbsences()  { return concurrentAbsences; }
    public int                getMaxAllowed()          { return maxAllowed; }
    public List<LeaveRequest> getConflictingRequests() { return conflictingRequests; }
    public LocalDate          getPeakStart()           { return peakStart; }
    public LocalDate          getPeakEnd()             { return peakEnd; }
    public String             getMessage()             { return message; }

    /**
     * Résumé lisible des demandes en conflit, pour affichage dans l'UI.
     */
    public String getDetailedConflictSummary() {
        if (conflictingRequests.isEmpty()) {
            return message;
        }
        StringBuilder sb = new StringBuilder(message).append("\n\nDemandes en chevauchement :\n");
        for (LeaveRequest r : conflictingRequests) {
            sb.append(String.format("  • #%d – %s (%s → %s, %s)\n",
                    r.getId(),
                    r.getEmployeeName(),
                    r.getStartDate(),
                    r.getEndDate(),
                    r.getStatus().getDisplayName()));
        }
        return sb.toString();
    }
}
