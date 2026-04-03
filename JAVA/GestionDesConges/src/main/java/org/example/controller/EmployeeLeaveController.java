package org.example.controller;

import org.example.model.LeaveRequest;
import org.example.model.LeaveRequest.LeaveStatus;
import org.example.service.LeaveRequestService;

import java.time.LocalDate;
import java.util.List;

/**
 * Contrôleur pour la gestion des demandes de congés côté employé
 */
public class EmployeeLeaveController {
    
    private LeaveRequestService leaveRequestService;

    public EmployeeLeaveController() {
        this.leaveRequestService = new LeaveRequestService();
    }

    /**
     * Soumettre une nouvelle demande de congé
     */
    public boolean submitLeaveRequest(int employeeId, String employeeName, 
                                     LocalDate startDate, LocalDate endDate,
                                     String leaveType, String reason) {
        
        // Validation basique
        if (startDate == null || endDate == null) {
            System.err.println("Les dates sont obligatoires");
            return false;
        }
        
        if (startDate.isBefore(LocalDate.now())) {
            System.err.println("La date de début ne peut pas être dans le passé");
            return false;
        }

        if (endDate.isBefore(startDate)) {
            System.err.println("La date de fin doit être après la date de début");
            return false;
        }

        // Vérifier les chevauchements
        if (leaveRequestService.hasDateOverlap(employeeId, startDate, endDate)) {
            System.err.println("Vous avez déjà une demande sur cette période");
            return false;
        }

        // Soumettre la demande
        return leaveRequestService.submitLeaveRequest(
            employeeId, employeeName, startDate, endDate, leaveType, reason).isSuccess();
    }

    /**
     * Récupérer toutes les demandes d'un employé
     */
    public List<LeaveRequest> getEmployeeLeaveRequests(int employeeId) {
        return leaveRequestService.getEmployeeLeaveRequests(employeeId);
    }

    /**
     * Supprimer une demande de congé (seulement si en attente)
     */
    public boolean deleteLeaveRequest(int requestId, int employeeId) {
        return leaveRequestService.deleteLeaveRequest(requestId, employeeId);
    }

    /**
     * Récupérer une demande par ID
     */
    public LeaveRequest getLeaveRequestById(int requestId) {
        return leaveRequestService.getLeaveRequestById(requestId);
    }

    /**
     * Obtenir les statistiques pour un employé
     */
    public int getTotalAcceptedDays(int employeeId) {
        return leaveRequestService.getAcceptedLeaveDaysForEmployee(employeeId);
    }
    
    /**
     * Obtenir le nombre de demandes en attente pour un employé
     */
    public int getPendingRequestsCount(int employeeId) {
        return (int) leaveRequestService.getEmployeeLeaveRequests(employeeId)
            .stream()
            .filter(r -> r.getStatus() == LeaveStatus.ATTENTE)
            .count();
    }
    
    /**
     * Obtenir le nombre de demandes acceptées pour un employé
     */
    public int getAcceptedRequestsCount(int employeeId) {
        return (int) leaveRequestService.getEmployeeLeaveRequests(employeeId)
            .stream()
            .filter(r -> r.getStatus() == LeaveStatus.ACCEPTE)
            .count();
    }
}
