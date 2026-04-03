package org.example.service;

import org.junit.jupiter.api.Test;
import java.time.LocalDate;
import static org.junit.jupiter.api.Assertions.*;

class LeaveRequestServiceTest {

    @Test
    void testDateDebutDansLePasse() {
        LeaveRequestService service = new LeaveRequestService();
        LocalDate hier = LocalDate.now().minusDays(1);
        LocalDate demain = LocalDate.now().plusDays(1);
        
        boolean resultat = service.submitLeaveRequest(1, "Test", hier, demain, "Congé", "Test").isSuccess();
        
        assertFalse(resultat);
    }

    @Test
    void testDateFinAvantDateDebut() {
        LeaveRequestService service = new LeaveRequestService();
        LocalDate debut = LocalDate.now().plusDays(10);
        LocalDate fin = LocalDate.now().plusDays(5);
        
        boolean resultat = service.submitLeaveRequest(1, "Test", debut, fin, "Congé", "Test").isSuccess();
        
        assertFalse(resultat);
    }

    @Test
    void testServiceCree() {
        LeaveRequestService service = new LeaveRequestService();
        
        assertNotNull(service);
    }
}
