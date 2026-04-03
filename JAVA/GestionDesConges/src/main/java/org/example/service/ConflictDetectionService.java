package org.example.service;

import org.example.model.LeaveRequest;
import org.example.model.LeaveRequest.LeaveStatus;

import java.time.LocalDate;
import java.util.*;
import java.util.stream.Collectors;

/**
 * Service de détection de conflits de congés.
 *
 * <p>Logique :
 * <ul>
 *   <li>Pour une demande donnée, on identifie toutes les autres demandes
 *       (ACCEPTE ou ATTENTE) dont la période se chevauche.</li>
 *   <li>On recherche le jour de pic : la date où le maximum d'employés
 *       sont absents simultanément.</li>
 *   <li>On retourne un {@link ConflictResult} avec le niveau de gravité
 *       (OK / WARNING / CRITICAL) selon les seuils configurés.</li>
 * </ul>
 *
 * <p>Seuils par défaut :
 * <ul>
 *   <li>{@code warningThreshold = 1} → avertissement dès 1 autre absence simultanée</li>
 *   <li>{@code criticalThreshold = 2} → critique à partir de 2 absences simultanées</li>
 * </ul>
 */
public class ConflictDetectionService {

    /** Nombre d'autres absences simultanées déclenchant un avertissement. */
    private int warningThreshold  = 1;
    /** Nombre d'autres absences simultanées déclenchant un conflit critique. */
    private int criticalThreshold = 2;

    public ConflictDetectionService() {}

    public ConflictDetectionService(int warningThreshold, int criticalThreshold) {
        this.warningThreshold  = warningThreshold;
        this.criticalThreshold = criticalThreshold;
    }

    // ─── API publique ────────────────────────────────────────────────────────────

    /**
     * Analyse les conflits pour la demande {@code target} au sein de la liste
     * {@code allRequests}.
     *
     * @param target      Demande à analyser
     * @param allRequests Toutes les demandes connues (provenant de la DB ou du service)
     * @return {@link ConflictResult} décrivant le niveau de conflit
     */
    public ConflictResult detectConflicts(LeaveRequest target, List<LeaveRequest> allRequests) {
        if (target == null || target.getStartDate() == null || target.getEndDate() == null) {
            return ConflictResult.ok();
        }

        // Demandes qui chevauchent la période cible, sans compter l'employé lui-même
        // et seulement celles ACCEPTE ou ATTENTE
        List<LeaveRequest> overlapping = allRequests.stream()
                .filter(r -> r.getId() != target.getId())
                .filter(r -> r.getEmployeeId() != target.getEmployeeId())
                .filter(r -> r.getStatus() == LeaveStatus.ACCEPTE
                          || r.getStatus() == LeaveStatus.ATTENTE)
                .filter(r -> datesOverlap(r.getStartDate(), r.getEndDate(),
                                          target.getStartDate(), target.getEndDate()))
                .collect(Collectors.toList());

        if (overlapping.isEmpty()) {
            return ConflictResult.ok();
        }

        // Calcul du pic : jour où le plus d'employés sont absents
        int[] peak = findPeakDay(overlapping, target.getStartDate(), target.getEndDate());
        int peakCount    = peak[0];
        int peakDayIndex = peak[1];

        LocalDate peakDate  = target.getStartDate().plusDays(peakDayIndex);
        LocalDate peakStart = peakDate;
        LocalDate peakEnd   = peakDate;

        // Étendre la plage du pic
        for (LeaveRequest r : overlapping) {
            if (!r.getStartDate().isAfter(peakDate) && !r.getEndDate().isBefore(peakDate)) {
                if (r.getStartDate().isAfter(peakStart)) peakStart = r.getStartDate();
                if (r.getEndDate().isBefore(peakEnd))    peakEnd   = r.getEndDate();
            }
        }
        // S'assurer que peakStart <= peakEnd
        if (peakStart.isAfter(peakEnd)) {
            peakStart = peakDate;
            peakEnd   = peakDate;
        }

        // Déterminer le niveau
        if (peakCount >= criticalThreshold) {
            return ConflictResult.critical(peakCount, criticalThreshold - 1,
                    overlapping, peakStart, peakEnd);
        } else if (peakCount >= warningThreshold) {
            return ConflictResult.warning(peakCount, criticalThreshold - 1,
                    overlapping, peakStart, peakEnd);
        }

        return ConflictResult.ok();
    }

    /**
     * Variante sans demande existante : analyse les conflits pour une période
     * et un employé donnés (utile avant soumission).
     */
    public ConflictResult detectConflictsForPeriod(int employeeId,
                                                    LocalDate startDate, LocalDate endDate,
                                                    List<LeaveRequest> allRequests) {
        LeaveRequest dummy = new LeaveRequest();
        dummy.setId(-1);
        dummy.setEmployeeId(employeeId);
        dummy.setStartDate(startDate);
        dummy.setEndDate(endDate);
        dummy.setStatus(LeaveStatus.ATTENTE);
        return detectConflicts(dummy, allRequests);
    }

    // ─── Logique interne ─────────────────────────────────────────────────────────

    /**
     * Pour chaque jour de la fenêtre [start, end], compte le nombre de
     * demandes chevauchant ce jour et retourne le max.
     *
     * @return int[2] = { picMaxAbsences, indexDuJourDansFenetre }
     */
    private int[] findPeakDay(List<LeaveRequest> overlapping,
                               LocalDate windowStart, LocalDate windowEnd) {
        int maxCount   = 0;
        int maxDayIdx  = 0;
        long totalDays = windowEnd.toEpochDay() - windowStart.toEpochDay() + 1;

        for (int i = 0; i < totalDays; i++) {
            LocalDate day = windowStart.plusDays(i);
            int count = (int) overlapping.stream()
                    .filter(r -> !r.getStartDate().isAfter(day) && !r.getEndDate().isBefore(day))
                    .count();
            if (count > maxCount) {
                maxCount  = count;
                maxDayIdx = i;
            }
        }
        return new int[]{ maxCount, maxDayIdx };
    }

    private boolean datesOverlap(LocalDate s1, LocalDate e1,
                                  LocalDate s2, LocalDate e2) {
        return !s1.isAfter(e2) && !s2.isAfter(e1);
    }

    // ─── Accesseurs ─────────────────────────────────────────────────────────────

    public int  getWarningThreshold()              { return warningThreshold; }
    public void setWarningThreshold(int t)         { this.warningThreshold  = t; }
    public int  getCriticalThreshold()             { return criticalThreshold; }
    public void setCriticalThreshold(int t)        { this.criticalThreshold = t; }
}
