package org.example.model;

import java.time.LocalDate;

public class LeaveRequest {
    private int id;
    private int employeeId;
    private String employeeName;
    private LocalDate startDate;
    private LocalDate endDate;
    private String leaveType; // Congé annuel, maladie, etc.
    private String reason;
    private LeaveStatus status;
    private LocalDate requestDate;
    private String rhComment;
    private int daysCount;

    public enum LeaveStatus {
        ATTENTE("En attente"),
        ACCEPTE("Accepté"),
        REFUSE("Refusé");

        private final String displayName;

        LeaveStatus(String displayName) {
            this.displayName = displayName;
        }

        public String getDisplayName() {
            return displayName;
        }
    }

    // Constructeurs
    public LeaveRequest() {
        this.status = LeaveStatus.ATTENTE;
        this.requestDate = LocalDate.now();
    }

    public LeaveRequest(int employeeId, String employeeName, LocalDate startDate, 
                       LocalDate endDate, String leaveType, String reason) {
        this.employeeId = employeeId;
        this.employeeName = employeeName;
        this.startDate = startDate;
        this.endDate = endDate;
        this.leaveType = leaveType;
        this.reason = reason;
        this.status = LeaveStatus.ATTENTE;
        this.requestDate = LocalDate.now();
        this.daysCount = calculateDaysCount();
    }

    // Méthode pour calculer le nombre de jours
    private int calculateDaysCount() {
        if (startDate != null && endDate != null) {
            return (int) (endDate.toEpochDay() - startDate.toEpochDay()) + 1;
        }
        return 0;
    }

    // Getters et Setters
    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public int getEmployeeId() {
        return employeeId;
    }

    public void setEmployeeId(int employeeId) {
        this.employeeId = employeeId;
    }

    public String getEmployeeName() {
        return employeeName;
    }

    public void setEmployeeName(String employeeName) {
        this.employeeName = employeeName;
    }

    public LocalDate getStartDate() {
        return startDate;
    }

    public void setStartDate(LocalDate startDate) {
        this.startDate = startDate;
        this.daysCount = calculateDaysCount();
    }

    public LocalDate getEndDate() {
        return endDate;
    }

    public void setEndDate(LocalDate endDate) {
        this.endDate = endDate;
        this.daysCount = calculateDaysCount();
    }

    public String getLeaveType() {
        return leaveType;
    }

    public void setLeaveType(String leaveType) {
        this.leaveType = leaveType;
    }

    public String getReason() {
        return reason;
    }

    public void setReason(String reason) {
        this.reason = reason;
    }

    public LeaveStatus getStatus() {
        return status;
    }

    public void setStatus(LeaveStatus status) {
        this.status = status;
    }

    public LocalDate getRequestDate() {
        return requestDate;
    }

    public void setRequestDate(LocalDate requestDate) {
        this.requestDate = requestDate;
    }

    public String getRhComment() {
        return rhComment;
    }

    public void setRhComment(String rhComment) {
        this.rhComment = rhComment;
    }

    public int getDaysCount() {
        return daysCount;
    }

    public void setDaysCount(int daysCount) {
        this.daysCount = daysCount;
    }

    @Override
    public String toString() {
        return "LeaveRequest{" +
                "id=" + id +
                ", employeeName='" + employeeName + '\'' +
                ", startDate=" + startDate +
                ", endDate=" + endDate +
                ", leaveType='" + leaveType + '\'' +
                ", status=" + status +
                ", daysCount=" + daysCount +
                '}';
    }
}
