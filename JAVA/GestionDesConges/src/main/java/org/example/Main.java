package org.example;

import org.example.config.DatabaseConfig;
import org.example.model.LeaveRequest;
import org.example.model.LeaveRequest.LeaveStatus;
import org.example.service.LeaveRequestService;

import java.sql.Connection;
import java.time.LocalDate;
import java.util.List;

/**
 * Classe principale pour tester le module de gestion des congés
 * Architecture MVC : Test des services et de la connexion
 */
public class Main {
    public static void main(String[] args) {
        System.out.println("═══════════════════════════════════════════════════");
        System.out.println("   MODULE GESTION DES CONGÉS - TEST & VALIDATION");
        System.out.println("═══════════════════════════════════════════════════\n");

        // Test de connexion à la base de données
        testDatabaseConnection();

        // Test du service de gestion des congés
        testLeaveRequestService();

        System.out.println("\n═══════════════════════════════════════════════════");
        System.out.println("   TESTS TERMINÉS AVEC SUCCÈS");
        System.out.println("═══════════════════════════════════════════════════");
    }

    private static void testDatabaseConnection() {
        System.out.println("📡 Test 1: Connexion à la base de données");
        System.out.println("─────────────────────────────────────────────────────");
        
        try {
            Connection connection = DatabaseConfig.getConnection();
            if (connection != null && !connection.isClosed()) {
                System.out.println("✅ Connexion établie avec succès!");
                DatabaseConfig.closeConnection(connection);
            } else {
                System.out.println("❌ Échec de la connexion");
            }
        } catch (Exception e) {
            System.out.println("❌ Erreur: " + e.getMessage());
            e.printStackTrace();
        }
        System.out.println();
    }

    private static void testLeaveRequestService() {
        System.out.println("🔧 Test 2: Service de gestion des congés");
        System.out.println("─────────────────────────────────────────────────────");
        
        LeaveRequestService service = new LeaveRequestService();
        
        // IDs de test (à adapter selon votre base de données)
        int testEmployeeId = 1;
        String testEmployeeName = "Test Employee";
        
        try {
            // Test 2.1: Soumettre une demande de congé
            System.out.println("\n📝 Test 2.1: Soumettre une demande de congé");
            LocalDate startDate = LocalDate.now().plusDays(7);
            LocalDate endDate = LocalDate.now().plusDays(14);
            
            boolean submitted = service.submitLeaveRequest(
                testEmployeeId,
                testEmployeeName,
                startDate,
                endDate,
                "Congé annuel",
                "Test de fonctionnement du module"
            ).isSuccess();
            
            if (submitted) {
                System.out.println("✅ Demande soumise avec succès!");
            } else {
                System.out.println("❌ Échec de la soumission");
            }
            
            // Test 2.2: Récupérer les demandes de l'employé
            System.out.println("\n📋 Test 2.2: Récupérer les demandes de l'employé");
            List<LeaveRequest> employeeRequests = service.getEmployeeLeaveRequests(testEmployeeId);
            System.out.println("✅ Nombre de demandes trouvées: " + employeeRequests.size());
            
            if (!employeeRequests.isEmpty()) {
                System.out.println("\nDernière demande:");
                LeaveRequest lastRequest = employeeRequests.get(0);
                System.out.println("  - ID: " + lastRequest.getId());
                System.out.println("  - Période: " + lastRequest.getStartDate() + " au " + lastRequest.getEndDate());
                System.out.println("  - Type: " + lastRequest.getLeaveType());
                System.out.println("  - Statut: " + lastRequest.getStatus().getDisplayName());
                System.out.println("  - Jours: " + lastRequest.getDaysCount());
            }
            
            // Test 2.3: Récupérer toutes les demandes (pour RH)
            System.out.println("\n📊 Test 2.3: Récupérer toutes les demandes (vue RH)");
            List<LeaveRequest> allRequests = service.getAllLeaveRequests();
            System.out.println("✅ Nombre total de demandes: " + allRequests.size());
            
            // Test 2.4: Récupérer les demandes en attente
            System.out.println("\n⏳ Test 2.4: Récupérer les demandes en attente");
            List<LeaveRequest> pendingRequests = service.getPendingLeaveRequests();
            System.out.println("✅ Demandes en attente: " + pendingRequests.size());
            
            // Test 2.5: Compter les demandes en attente
            System.out.println("\n📈 Test 2.5: Compter les demandes en attente");
            int pendingCount = service.countPendingRequests();
            System.out.println("✅ Nombre: " + pendingCount);
            
            // Test 2.6: Calculer les jours de congé pour un employé
            System.out.println("\n📅 Test 2.6: Calculer les jours de congé");
            int totalDays = service.getTotalLeaveDaysForEmployee(testEmployeeId);
            int acceptedDays = service.getAcceptedLeaveDaysForEmployee(testEmployeeId);
            System.out.println("✅ Jours totaux demandés: " + totalDays);
            System.out.println("✅ Jours acceptés: " + acceptedDays);
            
            // Test 2.7: Vérifier les chevauchements de dates
            System.out.println("\n🔍 Test 2.7: Vérifier les chevauchements");
            boolean hasOverlap = service.hasDateOverlap(
                testEmployeeId,
                startDate,
                endDate
            );
            System.out.println("✅ Chevauchement détecté: " + (hasOverlap ? "Oui" : "Non"));
            
            // Test 2.8: Afficher les statistiques par statut
            System.out.println("\n📊 Test 2.8: Statistiques par statut");
            List<LeaveRequest> accepted = service.getAcceptedLeaveRequests();
            List<LeaveRequest> refused = service.getRefusedLeaveRequests();
            List<LeaveRequest> pending = service.getPendingLeaveRequests();
            
            System.out.println("  - Acceptées: " + accepted.size());
            System.out.println("  - Refusées: " + refused.size());
            System.out.println("  - En attente: " + pending.size());
            
        } catch (Exception e) {
            System.out.println("❌ Erreur lors des tests: " + e.getMessage());
            e.printStackTrace();
        }
    }
}