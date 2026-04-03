package service;

import models.Request;
import org.junit.jupiter.api.*;

import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

class RequestServiceTest {

    private RequestService requestService;

    @BeforeEach
    void setUp() {
        requestService = new RequestService();
    }

    @Test
    void testAddRequest() {
        // 1️⃣ Préparer les données
        Request r = new Request(
                1,          // userId existant en DB
                1,          // requestTypeId existant
                "Test JUnit",
                "Description test",
                Request.Priority.medium
        );

        // 2️⃣ Exécuter
        boolean result = requestService.add(r);

        // 3️⃣ Vérifier
        assertTrue(result);
    }

    @Test
    void testGetByUserId() {

        // 1️⃣ Exécuter
        List<Request> list = requestService.getByUserId(1);

        // 2️⃣ Vérifier
        assertNotNull(list);
    }

    @Test
    void testGetById() {

        Request r = requestService.getById(1);

        if (r != null) {
            assertEquals(1, r.getId());
        }
    }

}