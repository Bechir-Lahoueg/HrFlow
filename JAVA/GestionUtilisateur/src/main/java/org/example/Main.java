package org.example;

import org.example.config.DatabaseConfig;
import org.example.controller.EmployeeController;
import org.example.controller.UserController;
import org.example.model.Employee;
import org.example.model.User;

import java.util.List;
import java.util.Scanner;

/**
 * Classe principale pour tester le module User (Vue)
 * Architecture MVC : affiche l'interface et délègue au Controller
 */
public class Main {
    private static final UserController userController = new UserController();
    private static final EmployeeController employeeController = new EmployeeController();
    private static User currentUser = null;
    private static Employee currentEmployee = null;
    private static final Scanner scanner = new Scanner(System.in);

    public static void main(String[] args) {
        System.out.println("═══════════════════════════════════════════════════");
        System.out.println("   MODULE GESTION UTILISATEURS - APPLICATION RH");
        System.out.println("═══════════════════════════════════════════════════\n");

        // Initialiser la base de données (créer la table si nécessaire)
        DatabaseConfig.initializeDatabase();

        // Initialiser l'admin par défaut au démarrage
        userController.initializeApplication();

        // Boucle principale
        while (true) {
            if (currentUser == null) {
                showLoginMenu();
            } else {
                showMainMenu();
            }
        }
    }

    private static void showLoginMenu() {
        System.out.println("\n╔══════════════════════════════════════════════════╗");
        System.out.println("║              AUTHENTIFICATION                    ║");
        System.out.println("╚══════════════════════════════════════════════════╝");
        System.out.println("1. Se connecter (Admin/RH)");
        System.out.println("2. Se connecter (Employé)");
        System.out.println("0. Quitter");
        System.out.print("\nVotre choix : ");

        String choice = scanner.nextLine();

        switch (choice) {
            case "1":
                login();
                break;
            case "2":
                employeeLogin();
                break;
            case "0":
                System.out.println("\n👋 Au revoir !");
                System.exit(0);
                break;
            default:
                System.out.println("❌ Choix invalide !");
        }
    }

    private static void login() {
        System.out.println("\n--- CONNEXION ADMIN/RH ---");
        System.out.print("Username : ");
        String username = scanner.nextLine();
        System.out.print("Password : ");
        String password = scanner.nextLine();

        currentUser = userController.handleLogin(username, password);

        if (currentUser != null) {
            System.out.println("\n✓✓✓ Bienvenue " + currentUser.getUsername() + " !");
            System.out.println("    Rôle : " + currentUser.getRole());
        } else {
            System.out.println("\n❌ Échec de la connexion. Veuillez réessayer.");
        }
    }

    private static void employeeLogin() {
        System.out.println("\n--- CONNEXION EMPLOYÉ ---");
        System.out.print("Email : ");
        String email = scanner.nextLine();
        System.out.print("Password : ");
        String password = scanner.nextLine();

        currentEmployee = employeeController.handleEmployeeLogin(email, password);

        if (currentEmployee != null) {
            System.out.println("\n✓✓✓ Bienvenue " + currentEmployee.getFullName() + " !");
            System.out.println("    Poste : " + currentEmployee.getJobTitle());
        } else {
            System.out.println("\n❌ Échec de la connexion. Veuillez réessayer.");
        }
    }

    private static void showMainMenu() {
        System.out.println("\n╔══════════════════════════════════════════════════╗");
        System.out.println("║              MENU PRINCIPAL                      ║");
        System.out.println("╚══════════════════════════════════════════════════╝");
        
        if (currentUser != null) {
            System.out.println("Connecté en tant que : " + currentUser.getUsername() + " (" + currentUser.getRole() + ")");
            System.out.println();

            if (currentUser.isAdmin()) {
                showAdminMenu();
            } else if (currentUser.isRH()) {
                showRHMenu();
            }

            System.out.println("9. Changer mon mot de passe");
            System.out.println("0. Se déconnecter");
            System.out.print("\nVotre choix : ");

            String choice = scanner.nextLine();
            handleMenuChoice(choice);
        } else if (currentEmployee != null) {
            System.out.println("Connecté en tant que : " + currentEmployee.getFullName() + " (" + currentEmployee.getJobTitle() + ")");
            System.out.println();
            showEmployeeMenu();

            System.out.println("9. Changer mon mot de passe");
            System.out.println("0. Se déconnecter");
            System.out.print("\nVotre choix : ");

            String choice = scanner.nextLine();
            handleEmployeeMenuChoice(choice);
        }
    }

    private static void showAdminMenu() {
        System.out.println("--- MENU ADMINISTRATEUR ---");
        System.out.println("1. Ajouter un RH");
        System.out.println("2. Modifier un RH");
        System.out.println("3. Supprimer un RH");
        System.out.println("4. Lister tous les RH");
        System.out.println("5. Lister tous les utilisateurs");
        System.out.println();
    }

    private static void showRHMenu() {
        System.out.println("--- MENU RH ---");
        System.out.println("1. Ajouter un employé");
        System.out.println("2. Modifier un employé");
        System.out.println("3. Supprimer un employé");
        System.out.println("4. Lister mes employés");
        System.out.println("5. Voir détails d'un employé");
        System.out.println();
    }

    private static void showEmployeeMenu() {
        System.out.println("--- MENU EMPLOYÉ ---");
        System.out.println("1. Voir mes informations");
        System.out.println();
    }

    private static void handleMenuChoice(String choice) {
        switch (choice) {
            case "1":
                if (currentUser.isAdmin()) addRH();
                else if (currentUser.isRH()) addEmployee();
                break;
            case "2":
                if (currentUser.isAdmin()) updateRH();
                else if (currentUser.isRH()) updateEmployee();
                break;
            case "3":
                if (currentUser.isAdmin()) deleteRH();
                else if (currentUser.isRH()) deleteEmployee();
                break;
            case "4":
                if (currentUser.isAdmin()) listRH();
                else if (currentUser.isRH()) listMyEmployees();
                break;
            case "5":
                if (currentUser.isAdmin()) listAllUsers();
                else if (currentUser.isRH()) viewEmployeeDetails();
                break;
            case "9":
                changePassword();
                break;
            case "0":
                logout();
                break;
            default:
                System.out.println("❌ Choix invalide !");
        }
    }

    private static void handleEmployeeMenuChoice(String choice) {
        switch (choice) {
            case "1":
                viewMyInfo();
                break;
            case "9":
                changeEmployeePassword();
                break;
            case "0":
                logoutEmployee();
                break;
            default:
                System.out.println("❌ Choix invalide !");
        }
    }

    private static void addRH() {
        System.out.println("\n--- AJOUTER UN RH ---");
        System.out.print("Username : ");
        String username = scanner.nextLine();
        System.out.print("Email (optionnel) : ");
        String email = scanner.nextLine();
        System.out.print("Password : ");
        String password = scanner.nextLine();

        if (userController.handleAddRH(currentUser, username, email.isEmpty() ? null : email, password)) {
            System.out.println("\n✓ RH ajouté avec succès !");
        } else {
            System.out.println("\n❌ Échec de l'ajout du RH");
        }
    }

    private static void updateRH() {
        System.out.println("\n--- MODIFIER UN RH ---");

        // D'abord lister les RH
        List<User> rhList = userController.handleListAllRH(currentUser);
        if (rhList.isEmpty()) {
            System.out.println("Aucun RH à modifier.");
            return;
        }

        System.out.println("\nListe des RH :");
        for (User rh : rhList) {
            System.out.println("  ID: " + rh.getId() + " - " + rh.getUsername());
        }

        System.out.print("\nID du RH à modifier : ");
        try {
            int id = Integer.parseInt(scanner.nextLine());
            System.out.print("Nouveau username : ");
            String username = scanner.nextLine();
            System.out.print("Nouveau email (laisser vide pour ne pas changer) : ");
            String email = scanner.nextLine();
            System.out.print("Nouveau password (laisser vide pour ne pas changer) : ");
            String password = scanner.nextLine();

            if (userController.handleUpdateRH(currentUser, id, username, email.isEmpty() ? null : email, password.isEmpty() ? null : password)) {
                System.out.println("\n✓ RH modifié avec succès !");
            } else {
                System.out.println("\n❌ Échec de la modification du RH");
            }
        } catch (NumberFormatException e) {
            System.out.println("❌ ID invalide");
        }
    }

    private static void deleteRH() {
        System.out.println("\n--- SUPPRIMER UN RH ---");

        // D'abord lister les RH
        List<User> rhList = userController.handleListAllRH(currentUser);
        if (rhList.isEmpty()) {
            System.out.println("Aucun RH à supprimer.");
            return;
        }

        System.out.println("\nListe des RH :");
        for (User rh : rhList) {
            System.out.println("  ID: " + rh.getId() + " - " + rh.getUsername());
        }

        System.out.print("\nID du RH à supprimer : ");
        try {
            int id = Integer.parseInt(scanner.nextLine());
            System.out.print("Êtes-vous sûr ? (oui/non) : ");
            String confirmation = scanner.nextLine();

            if (confirmation.equalsIgnoreCase("oui")) {
                if (userController.handleDeleteRH(currentUser, id)) {
                    System.out.println("\n✓ RH supprimé avec succès !");
                } else {
                    System.out.println("\n❌ Échec de la suppression du RH");
                }
            } else {
                System.out.println("Suppression annulée.");
            }
        } catch (NumberFormatException e) {
            System.out.println("❌ ID invalide");
        }
    }

    private static void listRH() {
        System.out.println("\n--- LISTE DES RH ---");
        List<User> rhList = userController.handleListAllRH(currentUser);

        if (rhList.isEmpty()) {
            System.out.println("Aucun RH trouvé.");
        } else {
            System.out.println("\nTotal : " + rhList.size() + " RH");
            System.out.println("─────────────────────────────────────────────");
            for (User rh : rhList) {
                System.out.println("ID: " + rh.getId() + " | Username: " + rh.getUsername() + " | Role: " + rh.getRole());
            }
            System.out.println("─────────────────────────────────────────────");
        }
    }

    private static void listAllUsers() {
        System.out.println("\n--- LISTE DE TOUS LES UTILISATEURS ---");
        List<User> allUsers = userController.handleListAllUsers(currentUser);

        if (allUsers.isEmpty()) {
            System.out.println("Aucun utilisateur trouvé.");
        } else {
            System.out.println("\nTotal : " + allUsers.size() + " utilisateur(s)");
            System.out.println("─────────────────────────────────────────────");
            for (User user : allUsers) {
                System.out.println("ID: " + user.getId() + " | Username: " + user.getUsername() + " | Role: " + user.getRole());
            }
            System.out.println("─────────────────────────────────────────────");
        }
    }

    private static void changePassword() {
        System.out.println("\n--- CHANGER MON MOT DE PASSE ---");
        System.out.print("Ancien mot de passe : ");
        String oldPassword = scanner.nextLine();
        System.out.print("Nouveau mot de passe : ");
        String newPassword = scanner.nextLine();
        System.out.print("Confirmer le nouveau mot de passe : ");
        String confirmPassword = scanner.nextLine();

        if (!newPassword.equals(confirmPassword)) {
            System.out.println("❌ Les mots de passe ne correspondent pas !");
            return;
        }

        if (userController.handleChangePassword(currentUser, oldPassword, newPassword)) {
            System.out.println("\n✓ Mot de passe changé avec succès !");
        } else {
            System.out.println("\n❌ Échec du changement de mot de passe");
        }
    }

    private static void logout() {
        System.out.println("\n👋 Déconnexion de " + currentUser.getUsername());
        currentUser = null;
    }

    private static void logoutEmployee() {
        System.out.println("\n👋 Déconnexion de " + currentEmployee.getFullName());
        currentEmployee = null;
    }

    // ============================================
    // MÉTHODES POUR GESTION DES EMPLOYÉS (RH)
    // ============================================

    private static void addEmployee() {
        System.out.println("\n--- AJOUTER UN EMPLOYÉ ---");
        System.out.print("Prénom : ");
        String firstName = scanner.nextLine();
        System.out.print("Nom : ");
        String lastName = scanner.nextLine();
        System.out.print("Âge : ");
        try {
            int age = Integer.parseInt(scanner.nextLine());
            System.out.print("Poste : ");
            String jobTitle = scanner.nextLine();
            System.out.print("Email : ");
            String email = scanner.nextLine();
            System.out.print("Mot de passe : ");
            String password = scanner.nextLine();

            if (employeeController.handleAddEmployee(currentUser, firstName, lastName, age, jobTitle, email, password)) {
                System.out.println("\n✓ Employé ajouté avec succès !");
            } else {
                System.out.println("\n❌ Échec de l'ajout de l'employé");
            }
        } catch (NumberFormatException e) {
            System.out.println("❌ Âge invalide");
        }
    }

    private static void updateEmployee() {
        System.out.println("\n--- MODIFIER UN EMPLOYÉ ---");

        // D'abord lister les employés
        List<Employee> employeeList = employeeController.handleListMyEmployees(currentUser);
        if (employeeList.isEmpty()) {
            System.out.println("Aucun employé à modifier.");
            return;
        }

        System.out.println("\nListe de vos employés :");
        for (Employee emp : employeeList) {
            System.out.println("  ID: " + emp.getId() + " - " + emp.getFullName() + " (" + emp.getJobTitle() + ")");
        }

        System.out.print("\nID de l'employé à modifier : ");
        try {
            int id = Integer.parseInt(scanner.nextLine());
            System.out.print("Nouveau prénom : ");
            String firstName = scanner.nextLine();
            System.out.print("Nouveau nom : ");
            String lastName = scanner.nextLine();
            System.out.print("Nouvel âge : ");
            int age = Integer.parseInt(scanner.nextLine());
            System.out.print("Nouveau poste : ");
            String jobTitle = scanner.nextLine();
            System.out.print("Nouvel email : ");
            String email = scanner.nextLine();

            if (employeeController.handleUpdateEmployee(currentUser, id, firstName, lastName, age, jobTitle, email)) {
                System.out.println("\n✓ Employé modifié avec succès !");
            } else {
                System.out.println("\n❌ Échec de la modification de l'employé");
            }
        } catch (NumberFormatException e) {
            System.out.println("❌ Valeur invalide");
        }
    }

    private static void deleteEmployee() {
        System.out.println("\n--- SUPPRIMER UN EMPLOYÉ ---");

        // D'abord lister les employés
        List<Employee> employeeList = employeeController.handleListMyEmployees(currentUser);
        if (employeeList.isEmpty()) {
            System.out.println("Aucun employé à supprimer.");
            return;
        }

        System.out.println("\nListe de vos employés :");
        for (Employee emp : employeeList) {
            System.out.println("  ID: " + emp.getId() + " - " + emp.getFullName() + " (" + emp.getJobTitle() + ")");
        }

        System.out.print("\nID de l'employé à supprimer : ");
        try {
            int id = Integer.parseInt(scanner.nextLine());
            System.out.print("Êtes-vous sûr ? (oui/non) : ");
            String confirmation = scanner.nextLine();

            if (confirmation.equalsIgnoreCase("oui")) {
                if (employeeController.handleDeleteEmployee(currentUser, id)) {
                    System.out.println("\n✓ Employé supprimé avec succès !");
                } else {
                    System.out.println("\n❌ Échec de la suppression de l'employé");
                }
            } else {
                System.out.println("Suppression annulée.");
            }
        } catch (NumberFormatException e) {
            System.out.println("❌ ID invalide");
        }
    }

    private static void listMyEmployees() {
        System.out.println("\n--- LISTE DE MES EMPLOYÉS ---");
        List<Employee> employeeList = employeeController.handleListMyEmployees(currentUser);

        if (employeeList.isEmpty()) {
            System.out.println("Aucun employé trouvé.");
        } else {
            System.out.println("\nTotal : " + employeeList.size() + " employé(s)");
            System.out.println("─────────────────────────────────────────────────────────────────────");
            for (Employee emp : employeeList) {
                System.out.println("ID: " + emp.getId() + " | " + emp.getFullName() + " | Âge: " + emp.getAge() +
                        " | Poste: " + emp.getJobTitle() + " | Email: " + emp.getEmail());
            }
            System.out.println("─────────────────────────────────────────────────────────────────────");
        }
    }

    private static void viewEmployeeDetails() {
        System.out.println("\n--- DÉTAILS D'UN EMPLOYÉ ---");

        // D'abord lister les employés
        List<Employee> employeeList = employeeController.handleListMyEmployees(currentUser);
        if (employeeList.isEmpty()) {
            System.out.println("Aucun employé trouvé.");
            return;
        }

        System.out.println("\nListe de vos employés :");
        for (Employee emp : employeeList) {
            System.out.println("  ID: " + emp.getId() + " - " + emp.getFullName());
        }

        System.out.print("\nID de l'employé : ");
        try {
            int id = Integer.parseInt(scanner.nextLine());
            Employee employee = employeeController.handleGetEmployeeDetails(currentUser, id);

            if (employee != null) {
                System.out.println("\n╔═══════════════════════════════════════╗");
                System.out.println("║      INFORMATIONS DE L'EMPLOYÉ        ║");
                System.out.println("╚═══════════════════════════════════════╝");
                System.out.println("ID          : " + employee.getId());
                System.out.println("Nom complet : " + employee.getFullName());
                System.out.println("Âge         : " + employee.getAge() + " ans");
                System.out.println("Poste       : " + employee.getJobTitle());
                System.out.println("Email       : " + employee.getEmail());
                System.out.println("─────────────────────────────────────────");
            }
        } catch (NumberFormatException e) {
            System.out.println("❌ ID invalide");
        }
    }

    // ============================================
    // MÉTHODES POUR ESPACE EMPLOYÉ
    // ============================================

    private static void viewMyInfo() {
        System.out.println("\n╔═══════════════════════════════════════╗");
        System.out.println("║         MES INFORMATIONS              ║");
        System.out.println("╚═══════════════════════════════════════╝");
        System.out.println("Nom complet : " + currentEmployee.getFullName());
        System.out.println("Âge         : " + currentEmployee.getAge() + " ans");
        System.out.println("Poste       : " + currentEmployee.getJobTitle());
        System.out.println("Email       : " + currentEmployee.getEmail());
        System.out.println("─────────────────────────────────────────");
    }

    private static void changeEmployeePassword() {
        System.out.println("\n--- CHANGER MON MOT DE PASSE ---");
        System.out.print("Ancien mot de passe : ");
        String oldPassword = scanner.nextLine();
        System.out.print("Nouveau mot de passe : ");
        String newPassword = scanner.nextLine();
        System.out.print("Confirmer le nouveau mot de passe : ");
        String confirmPassword = scanner.nextLine();

        if (!newPassword.equals(confirmPassword)) {
            System.out.println("❌ Les mots de passe ne correspondent pas !");
            return;
        }

        if (employeeController.handleChangeEmployeePassword(currentEmployee, oldPassword, newPassword)) {
            System.out.println("\n✓ Mot de passe changé avec succès !");
        } else {
            System.out.println("\n❌ Échec du changement de mot de passe");
        }
    }
}