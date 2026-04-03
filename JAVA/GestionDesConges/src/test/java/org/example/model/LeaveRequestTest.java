package org.example.model;

import org.junit.jupiter.api.Test;
import java.time.LocalDate;
import static org.junit.jupiter.api.Assertions.*;

class LeaveRequestTest {

    @Test
    void testCreerDemandeConge() {
        LeaveRequest demande = new LeaveRequest();
        
        assertNotNull(demande);
    }

    @Test
    void testCreerAvecConstructeur() {
        LocalDate debut = LocalDate.of(2026, 3, 1);
        LocalDate fin = LocalDate.of(2026, 3, 5);
        
        LeaveRequest demande = new LeaveRequest(1, "Jean Dupont", debut, fin, "Congé annuel", "Vacances");
        
        assertEquals(1, demande.getEmployeeId());
        assertEquals("Jean Dupont", demande.getEmployeeName());
        assertEquals(5, demande.getDaysCount());
    }

    @Test
    void testStatutParDefaut() {
        LeaveRequest demande = new LeaveRequest();
        
        assertEquals(LeaveRequest.LeaveStatus.ATTENTE, demande.getStatus());
    }

    @Test
    void testChangerStatut() {
        LeaveRequest demande = new LeaveRequest();
        
        demande.setStatus(LeaveRequest.LeaveStatus.ACCEPTE);
        
        assertEquals(LeaveRequest.LeaveStatus.ACCEPTE, demande.getStatus());
    }

    @Test
    void testCalculJours() {
        LocalDate debut = LocalDate.of(2026, 3, 1);
        LocalDate fin = LocalDate.of(2026, 3, 10);
        
        LeaveRequest demande = new LeaveRequest(1, "Test", debut, fin, "Congé", "Test");
        
        assertEquals(10, demande.getDaysCount());
    }
}
