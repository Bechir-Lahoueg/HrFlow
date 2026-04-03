# Module GestionDesConges

## Architecture

Ce module suit une architecture MVC simple et claire :

```
src/main/java/org/example/
├── config/          # Configuration de la base de données
│   └── DatabaseConfig.java
├── controller/      # Contrôleurs métier
│   ├── EmployeeLeaveController.java  # Gestion des congés côté employé
│   └── RHLeaveController.java        # Gestion des congés côté RH
├── model/           # Modèles de données
│   └── LeaveRequest.java
├── service/         # Services métier avec accès aux données
│   └── LeaveRequestService.java
└── Main.java        # Point d'entrée de l'application
```

## Fonctionnalités

### Pour les Employés (`EmployeeLeaveController`)
- Soumettre une demande de congé
- Consulter l'historique de ses demandes
- Supprimer une demande en attente
- Voir les statistiques (jours acceptés, demandes en attente, etc.)

### Pour les RH (`RHLeaveController`)
- Consulter toutes les demandes de congé
- Approuver ou refuser des demandes
- Filtrer par statut (en attente, acceptées, refusées)
- Rechercher par nom d'employé ou type de congé
- Voir les statistiques globales

## Base de Données

Table `leave_requests` :
- `id` : Identifiant unique
- `employee_id` : ID de l'employé
- `employee_name` : Nom de l'employé
- `start_date` : Date de début
- `end_date` : Date de fin
- `leave_type` : Type de congé
- `reason` : Motif de la demande
- `status` : Statut (ATTENTE, ACCEPTE, REFUSE)
- `request_date` : Date de la demande
- `rh_comment` : Commentaire du RH
- `days_count` : Nombre de jours

## Types de Congés Supportés
- Congé annuel
- Congé maladie
- Congé sans solde
- Congé parental
- Congé exceptionnel
- RTT
- Autres

## Compilation et Tests

```bash
# Compiler le module
mvn clean compile

# Lancer les tests
mvn test

# Créer le JAR
mvn clean package
```

## Comment le Module Fonctionne

### Flux de Travail - Employé

1. **L'employé se connecte** via l'interface AppUi
2. **Accède à la page congés** dans son dashboard
3. **Remplit le formulaire** :
   - Sélectionne les dates de début et fin
   - Choisit le type de congé
   - Ajoute une raison (optionnel)
4. **Soumet la demande** → Controller → Service → Base de données
5. **La demande est créée** avec le statut `ATTENTE`
6. **L'employé peut consulter** l'historique de ses demandes dans le tableau

### Flux de Travail - RH

1. **Le RH se connecte** via l'interface AppUi
2. **Accède à la page congés** dans le dashboard RH
3. **Voit toutes les demandes** en attente dans le tableau
4. **Sélectionne une demande** pour voir les détails
5. **Prend une décision** :
   - **Approuver** : Statut passe à `ACCEPTE` + commentaire optionnel
   - **Refuser** : Statut passe à `REFUSE` + commentaire obligatoire
6. **La décision est sauvegardée** et l'employé peut voir la réponse

### Architecture des Données

```
Base de données MySQL
        ↓
LeaveRequestService (logique métier + SQL)
        ↓
Controllers (EmployeeLeaveController, RHLeaveController)
        ↓
Interface JavaFX (AppUi)
```

## Intégration avec AppUi

### Dépendance Maven

AppUi déclare GestionDesConges comme dépendance dans son `pom.xml` :

```xml
<dependency>
    <groupId>org.example</groupId>
    <artifactId>GestionDesConges</artifactId>
    <version>1.0-SNAPSHOT</version>
</dependency>
```

### Utilisation dans AppUi

AppUi utilise ce module via des contrôleurs JavaFX qui appellent nos controllers :

**Côté Employé** (`AppUi/src/main/java/org/example/ui/controller/Employee/`):
```java
// EmployeeLeaveController (JavaFX) utilise :
import org.example.service.LeaveRequestService;
import org.example.model.LeaveRequest;

// Pour soumettre une demande
leaveRequestService.submitLeaveRequest(
    employeeId, employeeName, startDate, endDate, leaveType, reason
);

// Pour récupérer l'historique
List<LeaveRequest> requests = leaveRequestService.getEmployeeLeaveRequests(employeeId);
```

**Côté RH** (`AppUi/src/main/java/org/example/ui/controller/Rh/`):
```java
// RHLeaveController (JavaFX) utilise :
import org.example.service.LeaveRequestService;

// Pour voir toutes les demandes
List<LeaveRequest> all = leaveRequestService.getAllLeaveRequests();

// Pour approuver
leaveRequestService.approveLeaveRequest(requestId, comment);

// Pour refuser
leaveRequestService.rejectLeaveRequest(requestId, reason);
```

### Fichiers FXML Associés

- `AppUi/src/main/resources/fxml/views/Employee-dashboard/EmployeeLeaveView.fxml`
  - Formulaire de soumission de demande
  - Tableau d'historique des demandes
  - Statistiques (jours acceptés, demandes en attente)

- `AppUi/src/main/resources/fxml/views/Rh-dashboard/RHLeaveView.fxml`
  - Tableau de toutes les demandes
  - Filtres par statut
  - Boutons d'approbation/refus
  - Zone de détails de la demande

### Lancement de l'Application

```bash
cd AppUi
mvn clean install
cmd /c run.bat
```

L'application charge automatiquement tous les modules (GestionUtilisateur, GestionDesConges, etc.) grâce aux dépendances Maven.

## Notes Techniques

- Java 17
- MySQL 8.0.33
- JUnit 5 pour les tests
- Architecture simple sans framework complexe
- Service intégré avec accès direct à la base de données (pas de repository séparé)
- Intégration transparente avec JavaFX via AppUi
