package org.example.model;

import java.time.LocalDate;
import java.time.Period;

/**
 * Représente le solde de congés d'un employé.
 * Chaque mois travaillé génère 1.8 jours de congé.
 */
public class LeaveBalance {

    private int id;
    private int employeeId;
    private String employeeName;

    /** Jours disponibles = accumulés - utilisés */
    private double availableDays;

    /** Total des jours accumulés depuis l'embauche */
    private double totalAccrued;

    /** Total des jours utilisés (congés approuvés) */
    private double totalUsed;

    /** Date de la dernière attributions mensuelle */
    private LocalDate lastAccrualDate;

    /** Date d'embauche pour calculer les mois travaillés */
    private LocalDate hireDate;

    public static final double MONTHLY_ACCRUAL_RATE = 1.8;

    // ===== Constructors =====

    public LeaveBalance() {}

    public LeaveBalance(int employeeId, String employeeName, LocalDate hireDate) {
        this.employeeId = employeeId;
        this.employeeName = employeeName;
        this.hireDate = hireDate;
        this.availableDays = 0.0;
        this.totalAccrued = 0.0;
        this.totalUsed = 0.0;
        this.lastAccrualDate = null;
    }

    // ===== Business methods =====

    /**
     * Calcule le nombre de mois complets travaillés depuis la dernière attribution.
     * Si lastAccrualDate est null, compte depuis la date d'embauche.
     */
    public int computePendingMonths() {
        if (hireDate == null) return 0;
        LocalDate from = (lastAccrualDate != null) ? lastAccrualDate.plusMonths(1).withDayOfMonth(1)
                                                   : hireDate;
        LocalDate now = LocalDate.now();
        if (from.isAfter(now)) return 0;
        Period period = Period.between(from.withDayOfMonth(1), now.withDayOfMonth(1));
        return period.getYears() * 12 + period.getMonths();
    }

    /**
     * Retourne les jours à ajouter lors de la prochaine attribution.
     */
    public double computePendingAccrual() {
        return computePendingMonths() * MONTHLY_ACCRUAL_RATE;
    }

    /**
     * Formate le solde disponible à 1 décimale.
     */
    public String getFormattedAvailableDays() {
        return String.format("%.1f", availableDays);
    }

    /**
     * Retourne le mois de la prochaine attribution.
     */
    public LocalDate getNextAccrualDate() {
        if (lastAccrualDate == null) return hireDate != null ? hireDate.plusMonths(1).withDayOfMonth(1) : null;
        return lastAccrualDate.plusMonths(1).withDayOfMonth(1);
    }

    // ===== Getters & Setters =====

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getEmployeeId() { return employeeId; }
    public void setEmployeeId(int employeeId) { this.employeeId = employeeId; }

    public String getEmployeeName() { return employeeName; }
    public void setEmployeeName(String employeeName) { this.employeeName = employeeName; }

    public double getAvailableDays() { return availableDays; }
    public void setAvailableDays(double availableDays) { this.availableDays = availableDays; }

    public double getTotalAccrued() { return totalAccrued; }
    public void setTotalAccrued(double totalAccrued) { this.totalAccrued = totalAccrued; }

    public double getTotalUsed() { return totalUsed; }
    public void setTotalUsed(double totalUsed) { this.totalUsed = totalUsed; }

    public LocalDate getLastAccrualDate() { return lastAccrualDate; }
    public void setLastAccrualDate(LocalDate lastAccrualDate) { this.lastAccrualDate = lastAccrualDate; }

    public LocalDate getHireDate() { return hireDate; }
    public void setHireDate(LocalDate hireDate) { this.hireDate = hireDate; }

    @Override
    public String toString() {
        return String.format("LeaveBalance{employee=%s, available=%.1f, accrued=%.1f, used=%.1f}",
                employeeName, availableDays, totalAccrued, totalUsed);
    }
}

