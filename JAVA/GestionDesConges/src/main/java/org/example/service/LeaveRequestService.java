package org.example.service;

import org.example.config.DatabaseConfig;
import org.example.model.LeaveRequest;
import org.example.model.LeaveRequest.LeaveStatus;
import org.example.model.LeaveSubmitResult;
import org.example.service.PublicHolidayService.HolidayEntry;

import java.sql.*;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;

/**
 * Service pour la gestion des demandes de congés
 * Intègre la logique métier et l'accès aux données
 */
public class LeaveRequestService {

    private final LeaveBalanceService      leaveBalanceService      = new LeaveBalanceService();
    private final PublicHolidayService     publicHolidayService     = new PublicHolidayService();
    private final ConflictDetectionService conflictDetectionService = new ConflictDetectionService();

    /** Pays utilisé pour les jours fériés (modifiable si besoin). */
    private String countryCode = PublicHolidayService.DEFAULT_COUNTRY;

    public LeaveRequestService() {
        initializeTable();
    }

    /**
     * Initialise la table leave_requests si elle n'existe pas
     */
    private void initializeTable() {
        String sql = """
                CREATE TABLE IF NOT EXISTS leave_requests (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    employee_name VARCHAR(255) NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    leave_type VARCHAR(100) NOT NULL,
                    reason TEXT,
                    status VARCHAR(20) NOT NULL DEFAULT 'ATTENTE',
                    request_date DATE NOT NULL,
                    rh_comment TEXT,
                    days_count INT NOT NULL,
                    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                """;

        try (Connection conn = DatabaseConfig.getConnection();
             Statement stmt = conn.createStatement()) {
            stmt.execute(sql);
            System.out.println("✓ Table leave_requests créée ou déjà existante");
        } catch (SQLException e) {
            System.err.println("Erreur lors de la création de la table: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Soumettre une nouvelle demande de congé.
     * Retourne un {@link LeaveSubmitResult} détaillé :
     * <ul>
     *   <li>Vérifie qu'aucun jour férié n'est inclus dans la période.</li>
     *   <li>Vérifie qu'il existe au moins un jour ouvrable.</li>
     *   <li>Calcule {@code days_count} en jours ouvrables (hors week-ends et fériés).</li>
     * </ul>
     */
    public LeaveSubmitResult submitLeaveRequest(int employeeId, String employeeName,
                                     LocalDate startDate, LocalDate endDate,
                                     String leaveType, String reason) {
        // ---- Validation des dates ----
        if (startDate.isBefore(LocalDate.now())) {
            return LeaveSubmitResult.validationError("La date de début ne peut pas être dans le passé.");
        }
        if (endDate.isBefore(startDate)) {
            return LeaveSubmitResult.validationError("La date de fin doit être après la date de début.");
        }

        int calendarDays = (int)(endDate.toEpochDay() - startDate.toEpochDay()) + 1;

        // ---- Vérification jours fériés ----
        List<HolidayEntry> holidays = publicHolidayService.findHolidaysInRange(startDate, endDate, countryCode);
        if (!holidays.isEmpty()) {
            int wd = publicHolidayService.countWorkingDays(startDate, endDate, countryCode);
            return LeaveSubmitResult.blockedByHoliday(holidays, wd, calendarDays);
        }

        // ---- Calcul jours ouvrables ----
        int workingDays = publicHolidayService.countWorkingDays(startDate, endDate, countryCode);
        if (workingDays == 0) {
            return LeaveSubmitResult.noWorkingDays(calendarDays);
        }

        // ---- Enregistrement en base ----
        LeaveRequest request = new LeaveRequest(employeeId, employeeName,
                                               startDate, endDate, leaveType, reason);
        request.setDaysCount(workingDays); // on stocke les jours ouvrables

        String sql = """
                INSERT INTO leave_requests
                (employee_id, employee_name, start_date, end_date, leave_type,
                 reason, status, request_date, days_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """;

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            pstmt.setInt(1, request.getEmployeeId());
            pstmt.setString(2, request.getEmployeeName());
            pstmt.setDate(3, Date.valueOf(request.getStartDate()));
            pstmt.setDate(4, Date.valueOf(request.getEndDate()));
            pstmt.setString(5, request.getLeaveType());
            pstmt.setString(6, request.getReason());
            pstmt.setString(7, request.getStatus().name());
            pstmt.setDate(8, Date.valueOf(request.getRequestDate()));
            pstmt.setInt(9, request.getDaysCount());

            int affected = pstmt.executeUpdate();
            if (affected > 0) {
                return LeaveSubmitResult.success(workingDays, calendarDays);
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors de la création de la demande: " + e.getMessage());
            e.printStackTrace();
        }
        return LeaveSubmitResult.dbError();
    }

    /**
     * Pré-calcule les informations d'une période sans rien enregistrer.
     * Utilisé pour l'affichage en temps réel dans le formulaire.
     */
    public LeaveSubmitResult previewRequest(LocalDate startDate, LocalDate endDate) {
        if (startDate == null || endDate == null || endDate.isBefore(startDate)) {
            return LeaveSubmitResult.validationError("Dates invalides.");
        }
        int calendarDays = (int)(endDate.toEpochDay() - startDate.toEpochDay()) + 1;
        List<HolidayEntry> holidays = publicHolidayService.findHolidaysInRange(startDate, endDate, countryCode);
        int workingDays = publicHolidayService.countWorkingDays(startDate, endDate, countryCode);
        if (!holidays.isEmpty()) {
            return LeaveSubmitResult.blockedByHoliday(holidays, workingDays, calendarDays);
        }
        if (workingDays == 0) {
            return LeaveSubmitResult.noWorkingDays(calendarDays);
        }
        return LeaveSubmitResult.success(workingDays, calendarDays);
    }

    /** Accès direct au service jours fériés (utilisé par l'UI). */
    public PublicHolidayService getPublicHolidayService() {
        return publicHolidayService;
    }

    /** Permet de changer le pays (ex : "MA", "DZ", "TN"...). */
    public void setCountryCode(String countryCode) {
        this.countryCode = countryCode;
    }
    public String getCountryCode() {
        return countryCode;
    }

    // ─── Détection de conflits ───────────────────────────────────────────────────

    /**
     * Analyse les conflits pour une demande existante.
     * Charge toutes les demandes depuis la DB et délègue au {@link ConflictDetectionService}.
     *
     * @param request Demande à analyser
     * @return {@link ConflictResult} avec le niveau de gravité
     */
    public ConflictResult detectConflicts(LeaveRequest request) {
        List<LeaveRequest> all = getAllLeaveRequests();
        return conflictDetectionService.detectConflicts(request, all);
    }

    /**
     * Analyse les conflits pour une période avant soumission.
     *
     * @param employeeId ID de l'employé demandeur
     * @param startDate  Début de la période
     * @param endDate    Fin de la période
     * @return {@link ConflictResult} avec le niveau de gravité
     */
    public ConflictResult detectConflictsForPeriod(int employeeId,
                                                    LocalDate startDate,
                                                    LocalDate endDate) {
        List<LeaveRequest> all = getAllLeaveRequests();
        return conflictDetectionService.detectConflictsForPeriod(employeeId, startDate, endDate, all);
    }

    /** Accès direct au service de détection de conflits (pour configuration). */
    public ConflictDetectionService getConflictDetectionService() {
        return conflictDetectionService;
    }

    /**
     * Récupérer toutes les demandes d'un employé
     */
    public List<LeaveRequest> getEmployeeLeaveRequests(int employeeId) {
        List<LeaveRequest> requests = new ArrayList<>();
        String sql = "SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY request_date DESC";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, employeeId);
            ResultSet rs = pstmt.executeQuery();

            while (rs.next()) {
                requests.add(mapResultSetToLeaveRequest(rs));
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors de la récupération des demandes: " + e.getMessage());
            e.printStackTrace();
        }
        return requests;
    }

    /**
     * Récupérer toutes les demandes (pour RH)
     */
    public List<LeaveRequest> getAllLeaveRequests() {
        List<LeaveRequest> requests = new ArrayList<>();
        String sql = "SELECT * FROM leave_requests ORDER BY request_date DESC";

        try (Connection conn = DatabaseConfig.getConnection();
             Statement stmt = conn.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {

            while (rs.next()) {
                requests.add(mapResultSetToLeaveRequest(rs));
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors de la récupération de toutes les demandes: " + e.getMessage());
            e.printStackTrace();
        }
        return requests;
    }

    /**
     * Récupérer les demandes des employés gérés par un RH (rh_id dans la table employees)
     */
    public List<LeaveRequest> getLeaveRequestsByRH(int rhId) {
        List<LeaveRequest> requests = new ArrayList<>();
        String sql = "SELECT lr.* FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE e.rh_id = ? ORDER BY lr.request_date DESC";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, rhId);
            ResultSet rs = pstmt.executeQuery();

            while (rs.next()) {
                requests.add(mapResultSetToLeaveRequest(rs));
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors de la récupération des demandes par RH: " + e.getMessage());
            e.printStackTrace();
        }
        return requests;
    }


    /**
     * Récupérer les demandes par statut
     */
    public List<LeaveRequest> getLeaveRequestsByStatus(LeaveStatus status) {
        List<LeaveRequest> requests = new ArrayList<>();
        String sql = "SELECT * FROM leave_requests WHERE status = ? ORDER BY request_date DESC";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, status.name());
            ResultSet rs = pstmt.executeQuery();

            while (rs.next()) {
                requests.add(mapResultSetToLeaveRequest(rs));
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors de la récupération par statut: " + e.getMessage());
            e.printStackTrace();
        }
        return requests;
    }

    /**
     * Récupérer les demandes en attente
     */
    public List<LeaveRequest> getPendingLeaveRequests() {
        return getLeaveRequestsByStatus(LeaveStatus.ATTENTE);
    }

    /**
     * Récupérer les demandes acceptées
     */
    public List<LeaveRequest> getAcceptedLeaveRequests() {
        return getLeaveRequestsByStatus(LeaveStatus.ACCEPTE);
    }

    /**
     * Récupérer les demandes refusées
     */
    public List<LeaveRequest> getRefusedLeaveRequests() {
        return getLeaveRequestsByStatus(LeaveStatus.REFUSE);
    }

    /**
     * Récupérer une demande par ID
     */
    public LeaveRequest getLeaveRequestById(int requestId) {
        String sql = "SELECT * FROM leave_requests WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, requestId);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return mapResultSetToLeaveRequest(rs);
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors de la récupération de la demande: " + e.getMessage());
            e.printStackTrace();
        }
        return null;
    }

    /**
     * Approuver une demande de congé (RH)
     */
    public boolean approveLeaveRequest(int requestId, String rhComment) {
        LeaveRequest request = getLeaveRequestById(requestId);
        if (request == null) {
            System.err.println("Demande non trouvée");
            return false;
        }

        if (request.getStatus() != LeaveStatus.ATTENTE) {
            System.err.println("Cette demande a déjà été traitée");
            return false;
        }

        boolean updated = updateLeaveRequestStatus(requestId, LeaveStatus.ACCEPTE, rhComment);
        if (updated) {
            // Déduire les jours du solde de l'employé
            leaveBalanceService.deductLeave(request.getEmployeeId(), request.getDaysCount());
        }
        return updated;
    }

    /**
     * Refuser une demande de congé (RH)
     */
    public boolean rejectLeaveRequest(int requestId, String rhComment) {
        LeaveRequest request = getLeaveRequestById(requestId);
        if (request == null) {
            System.err.println("Demande non trouvée");
            return false;
        }

        if (request.getStatus() != LeaveStatus.ATTENTE) {
            System.err.println("Cette demande a déjà été traitée");
            return false;
        }

        return updateLeaveRequestStatus(requestId, LeaveStatus.REFUSE, rhComment);
    }

    /**
     * Mettre à jour le statut d'une demande
     */
    private boolean updateLeaveRequestStatus(int requestId, LeaveStatus status, String rhComment) {
        String sql = "UPDATE leave_requests SET status = ?, rh_comment = ? WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, status.name());
            pstmt.setString(2, rhComment);
            pstmt.setInt(3, requestId);

            return pstmt.executeUpdate() > 0;
        } catch (SQLException e) {
            System.err.println("Erreur lors de la mise à jour du statut: " + e.getMessage());
            e.printStackTrace();
        }
        return false;
    }

    /**
     * Supprimer une demande de congé
     */
    public boolean deleteLeaveRequest(int requestId, int employeeId) {
        LeaveRequest request = getLeaveRequestById(requestId);
        if (request == null) {
            System.err.println("Demande non trouvée");
            return false;
        }

        // Vérifier que c'est bien l'employé propriétaire
        if (request.getEmployeeId() != employeeId) {
            System.err.println("Vous n'êtes pas autorisé à supprimer cette demande");
            return false;
        }

        // On ne peut supprimer que les demandes en attente
        if (request.getStatus() != LeaveStatus.ATTENTE) {
            System.err.println("Impossible de supprimer une demande déjà traitée");
            return false;
        }

        String sql = "DELETE FROM leave_requests WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, requestId);
            boolean deleted = pstmt.executeUpdate() > 0;
            // Pas de remboursement nécessaire : les demandes en attente n'ont pas encore été déduites
            return deleted;
        } catch (SQLException e) {
            System.err.println("Erreur lors de la suppression: " + e.getMessage());
            e.printStackTrace();
        }
        return false;
    }

    /**
     * Supprimer une demande de congé par le RH (fonctionne pour ATTENTE et ACCEPTE).
     * Si la demande est acceptée, les jours sont remboursés au solde de l'employé.
     */
    public boolean rhDeleteLeaveRequest(int requestId) {
        LeaveRequest request = getLeaveRequestById(requestId);
        if (request == null) {
            System.err.println("Demande non trouvée");
            return false;
        }

        // Rembourser les jours si le congé était déjà accepté
        if (request.getStatus() == LeaveStatus.ACCEPTE) {
            boolean refunded = leaveBalanceService.refundLeave(request.getEmployeeId(), request.getDaysCount());
            if (!refunded) {
                System.err.println("Avertissement : impossible de rembourser le solde pour l'employé " + request.getEmployeeId());
            }
        }

        String sql = "DELETE FROM leave_requests WHERE id = ?";
        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setInt(1, requestId);
            return pstmt.executeUpdate() > 0;
        } catch (SQLException e) {
            System.err.println("Erreur lors de la suppression RH: " + e.getMessage());
            e.printStackTrace();
        }
        return false;
    }

    /**
     * Compter les demandes en attente
     */
    public int countPendingRequests() {
        String sql = "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'ATTENTE'";

        try (Connection conn = DatabaseConfig.getConnection();
             Statement stmt = conn.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {
            if (rs.next()) {
                return rs.getInt("count");
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors du comptage: " + e.getMessage());
            e.printStackTrace();
        }
        return 0;
    }

    /**
     * Obtenir le nombre total de jours de congé pour un employé
     */
    public int getTotalLeaveDaysForEmployee(int employeeId) {
        List<LeaveRequest> requests = getEmployeeLeaveRequests(employeeId);
        return requests.stream()
                .filter(r -> r.getStatus() == LeaveStatus.ACCEPTE || r.getStatus() == LeaveStatus.ATTENTE)
                .mapToInt(LeaveRequest::getDaysCount)
                .sum();
    }

    /**
     * Obtenir le nombre de jours de congé acceptés pour un employé
     */
    public int getAcceptedLeaveDaysForEmployee(int employeeId) {
        List<LeaveRequest> requests = getEmployeeLeaveRequests(employeeId);
        return requests.stream()
                .filter(r -> r.getStatus() == LeaveStatus.ACCEPTE)
                .mapToInt(LeaveRequest::getDaysCount)
                .sum();
    }

    /**
     * Vérifier s'il y a un chevauchement de dates pour un employé
     */
    public boolean hasDateOverlap(int employeeId, LocalDate startDate, LocalDate endDate) {
        List<LeaveRequest> requests = getEmployeeLeaveRequests(employeeId);
        
        return requests.stream()
                .filter(r -> r.getStatus() == LeaveStatus.ACCEPTE || r.getStatus() == LeaveStatus.ATTENTE)
                .anyMatch(r -> datesOverlap(r.getStartDate(), r.getEndDate(), startDate, endDate));
    }

    /**
     * Vérifie si deux périodes de dates se chevauchent
     */
    private boolean datesOverlap(LocalDate start1, LocalDate end1, LocalDate start2, LocalDate end2) {
        return !start1.isAfter(end2) && !start2.isAfter(end1);
    }

    /**
     * Mapper ResultSet vers LeaveRequest
     */
    private LeaveRequest mapResultSetToLeaveRequest(ResultSet rs) throws SQLException {
        LeaveRequest request = new LeaveRequest();
        request.setId(rs.getInt("id"));
        request.setEmployeeId(rs.getInt("employee_id"));
        request.setEmployeeName(rs.getString("employee_name"));
        request.setStartDate(rs.getDate("start_date").toLocalDate());
        request.setEndDate(rs.getDate("end_date").toLocalDate());
        request.setLeaveType(rs.getString("leave_type"));
        request.setReason(rs.getString("reason"));
        request.setStatus(LeaveStatus.valueOf(rs.getString("status")));
        request.setRequestDate(rs.getDate("request_date").toLocalDate());
        request.setRhComment(rs.getString("rh_comment"));
        request.setDaysCount(rs.getInt("days_count"));
        return request;
    }
}
