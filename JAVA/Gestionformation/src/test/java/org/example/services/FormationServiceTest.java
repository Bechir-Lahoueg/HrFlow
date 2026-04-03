package org.example.services;

import org.example.models.Formation;
import org.junit.jupiter.api.*;
import static org.junit.jupiter.api.Assertions.*;
import java.util.List;

@TestMethodOrder(MethodOrderer.OrderAnnotation.class) // Permet de définir l'ordre d'exécution [cite: 77, 79]
public class FormationServiceTest {
    static FormationService service;

    @BeforeAll
    static void setup() {
        service = new FormationService(); // Initialisation du service avant tous les tests [cite: 78]
    }

    @Test
    @Order(1)
    void testAddFormation() {
        Formation f = new Formation(0, "Java Unit Test", "Apprendre JUnit 5", "Technique", 10, "Esprit", "Maitriser les tests", 1);
        service.addFormation(f);

        List<Formation> formations = service.getAllFormations();
        // Vérifie que la liste n'est pas vide [cite: 71, 83]
        assertFalse(formations.isEmpty(), "La liste ne devrait pas être vide après l'ajout");
        // Vérifie si le titre ajouté est bien présent [cite: 70, 84]
        assertTrue(formations.stream().anyMatch(form -> form.getTitre().equals("Java Unit Test")));
    }

    @Test
    @Order(2)
    void testUpdateFormation() {
        List<Formation> liste = service.getAllFormations();
        // Récupération de l'élément à modifier
        Formation f = liste.get(liste.size() - 1);
        f.setTitre("Java Unit Test - Modifié");

        service.updateFormation(f);

        List<Formation> formationsModifiees = service.getAllFormations();
        // Vérifie que la modification a bien été appliquée en base [cite: 93]
        assertTrue(formationsModifiees.stream().anyMatch(form -> form.getTitre().equals("Java Unit Test - Modifié")));
    }

    @Test
    @Order(3)
    void testDeleteFormation() {
        List<Formation> liste = service.getAllFormations();
        int idASupprimer = liste.get(liste.size() - 1).getIdFormation();

        service.deleteFormation(idASupprimer);

        List<Formation> listeApres = service.getAllFormations();
        // Vérifie que l'unité de code a bien supprimé l'entrée [cite: 71, 102]
        assertFalse(listeApres.stream().anyMatch(form -> form.getIdFormation() == idASupprimer));
    }

    @AfterEach
    void cleanUp() {
        // Un bon test ne laisse aucune trace en base de données [cite: 106, 107]
        // Ici, testDeleteFormation s'en occupe déjà, mais c'est une bonne pratique [cite: 105]
    }
}