package org.example.controller;

import org.example.model.LeaveRequest;
import org.example.model.LeaveRequest.LeaveStatus;
import org.example.service.LeaveRequestService;

import java.util.List;
import java.util.stream.Collectors;

/**
 * Contrôleur pour la gestion des demandes de congés côté RH
 */
public class RHLeaveController {
    
    private LeaveRequestService leaveRequestService;

    public RHLeaveController() {
        this.leaveRequestService = new LeaveRequestService();
    }

    /**
     * Récupérer toutes les demandes de congé
     */
    public List<LeaveRequest> getAllLeaveRequests() {
        return leaveRequestService.getAllLeaveRequests();
    }

    /**
     * Récupérer les demandes en attente
     */
    public List<LeaveRequest> getPendingLeaveRequests() {
        return leaveRequestService.getPendingLeaveRequests();
    }

    /**
     * Récupérer les demandes acceptées
     */
    public List<LeaveRequest> getAcceptedLeaveRequests() {
        return leaveRequestService.getAcceptedLeaveRequests();
    }

    /**
     * Récupérer les demandes refusées
     */
    public List<LeaveRequest> getRefusedLeaveRequests() {
        return leaveRequestService.getRefusedLeaveRequests();
    }

    /**
     * Filtrer les demandes par nom d'employé
     */
    public List<LeaveRequest> searchByEmployeeName(String name) {
        return leaveRequestService.getAllLeaveRequests()
            .stream()
            .filter(r -> r.getEmployeeName().toLowerCase().contains(name.toLowerCase()))
            .collect(Collectors.toList());
    }

    /**
     * Filtrer les demandes par type de congé
     */
    public List<LeaveRequest> searchByLeaveType(String type) {
        return leaveRequestService.getAllLeaveRequests()
            .stream()
            .filter(r -> r.getLeaveType().toLowerCase().contains(type.toLowerCase()))
            .collect(Collectors.toList());
    }

    /**
     * Approuver une demande de congé
     */
    public boolean approveLeaveRequest(int requestId, String rhComment) {
        LeaveRequest request = leaveRequestService.getLeaveRequestById(requestId);
        
        if (request == null) {
            System.err.println("Demande introuvable");
            return false;
        }

        if (request.getStatus() != LeaveStatus.ATTENTE) {
            System.err.println("Cette demande a déjà été traitée");
            return false;
        }

        return leaveRequestService.approveLeaveRequest(requestId, rhComment);
    }

    /**
     * Refuser une demande de congé
     */
    public boolean rejectLeaveRequest(int requestId, String rhComment) {
        LeaveRequest request = leaveRequestService.getLeaveRequestById(requestId);
        
        if (request == null) {
            System.err.println("Demande introuvable");
            return false;
        }

        if (request.getStatus() != LeaveStatus.ATTENTE) {
            System.err.println("Cette demande a déjà été traitée");
            return false;
        }

        if (rhComment == null || rhComment.trim().isEmpty()) {
            System.err.println("Un commentaire est requis pour refuser une demande");
            return false;
        }

        return leaveRequestService.rejectLeaveRequest(requestId, rhComment);
    }

    /**
     * Récupérer une demande par ID
     */
    public LeaveRequest getLeaveRequestById(int requestId) {
        return leaveRequestService.getLeaveRequestById(requestId);
    }

    /**
     * Supprimer une demande de congé (RH) — rembourse le solde si la demande était acceptée.
     */
    public boolean rhDeleteLeaveRequest(int requestId) {
        return leaveRequestService.rhDeleteLeaveRequest(requestId);
    }

    /**
     * Compter les demandes en attente
     */
    public int countPendingRequests() {
        return leaveRequestService.countPendingRequests();
    }

    /**
     * Obtenir les statistiques globales
     */
    public LeaveStatistics getStatistics() {
        List<LeaveRequest> all = getAllLeaveRequests();
        
        int total = all.size();
        int pending = (int) all.stream().filter(r -> r.getStatus() == LeaveStatus.ATTENTE).count();
        int accepted = (int) all.stream().filter(r -> r.getStatus() == LeaveStatus.ACCEPTE).count();
        int rejected = (int) all.stream().filter(r -> r.getStatus() == LeaveStatus.REFUSE).count();
        
        return new LeaveStatistics(total, pending, accepted, rejected);
    }

    /**
     * Classe interne pour les statistiques
     */
    public static class LeaveStatistics {
        private final int total;
        private final int pending;
        private final int accepted;
        private final int rejected;

        public LeaveStatistics(int total, int pending, int accepted, int rejected) {
            this.total = total;
            this.pending = pending;
            this.accepted = accepted;
            this.rejected = rejected;
        }

        public int getTotal() { return total; }
        public int getPending() { return pending; }
        public int getAccepted() { return accepted; }
        public int getRejected() { return rejected; }

        @Override
        public String toString() {
            return String.format("Total: %d | En attente: %d | Acceptées: %d | Refusées: %d",
                    total, pending, accepted, rejected);
        }
    }
}
