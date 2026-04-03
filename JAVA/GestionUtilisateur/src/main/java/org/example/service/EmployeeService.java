package org.example.service;

import org.example.config.DatabaseConfig;
import org.example.model.Employee;
import org.example.model.User;

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service métier pour la gestion des employés
 * Gère la création, modification, suppression et consultation des employés
 * Chaque employé est lié au RH qui l'a créé
 */
public class EmployeeService {

    public EmployeeService() {
    }

    /**
     * Authentifie un employé
     * @return L'employé si les credentials sont valides, null sinon
     */
    public Employee loginEmployee(String email, String password) {
        System.out.println("⏳ Tentative de connexion employé pour : " + email);

        if (email == null || email.trim().isEmpty() ||
            password == null || password.trim().isEmpty()) {
            System.err.println("✗ Email ou password vide");
            return null;
        }

        Employee employee = getEmployeeByEmail(email);

        if (employee == null) {
            System.err.println("✗ Employé non trouvé : " + email);
            return null;
        }

        String hashedPassword = hashPassword(password);

        if (employee.getPassword().equals(hashedPassword)) {
            System.out.println("✓ Connexion employé réussie : " + email);
            return employee;
        } else {
            System.err.println("✗ Mot de passe incorrect pour : " + email);
            return null;
        }
    }

    /**
     * Ajoute un nouvel employé (accessible aux RH)
     * L'employé est automatiquement lié au RH qui le crée
     */
    public boolean addEmployee(User currentUser, String firstName, String lastName, 
                               Integer age, String jobTitle, String email, String password) {
        
        if (!hasRHPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un RH peut ajouter des employés");
            return false;
        }

        // Validation des champs
        if (isNullOrEmpty(firstName) || isNullOrEmpty(lastName) || 
            isNullOrEmpty(jobTitle) || isNullOrEmpty(email) || 
            isNullOrEmpty(password) || age == null || age <= 0) {
            System.err.println("✗ Tous les champs sont obligatoires et valides");
            return false;
        }

        if (emailExists(email)) {
            System.err.println("✗ L'email existe déjà : " + email);
            return false;
        }

        Employee newEmployee = new Employee(
            firstName, 
            lastName, 
            age, 
            jobTitle, 
            email, 
            hashPassword(password), 
            currentUser.getId()  // Lie l'employé au RH
        );

        return createEmployee(newEmployee);
    }

    /**
     * Modifie un employé existant (accessible uniquement au RH qui l'a créé)
     */
    public boolean updateEmployee(User currentUser, int employeeId, String firstName, 
                                  String lastName, Integer age, String jobTitle, String newEmail) {
        
        if (!hasRHPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un RH peut modifier des employés");
            return false;
        }

        Employee employee = getEmployeeById(employeeId);

        if (employee == null) {
            System.err.println("✗ Employé non trouvé (ID: " + employeeId + ")");
            return false;
        }

        // Vérifier que c'est bien le RH qui a créé cet employé
        if (!employee.getRhId().equals(currentUser.getId())) {
            System.err.println("✗ Accès refusé : Vous ne pouvez modifier que vos propres employés");
            return false;
        }

        // Vérifier si le nouvel email existe déjà (sauf si c'est le même)
        if (!employee.getEmail().equals(newEmail) && emailExists(newEmail)) {
            System.err.println("✗ L'email existe déjà : " + newEmail);
            return false;
        }

        // Mise à jour des informations
        employee.setFirstName(firstName);
        employee.setLastName(lastName);
        employee.setAge(age);
        employee.setJobTitle(jobTitle);
        employee.setEmail(newEmail);

        return updateEmployeeInDB(employee);
    }

    /**
     * Supprime un employé (accessible uniquement au RH qui l'a créé)
     */
    public boolean deleteEmployee(User currentUser, int employeeId) {
        if (!hasRHPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un RH peut supprimer des employés");
            return false;
        }

        Employee employee = getEmployeeById(employeeId);

        if (employee == null) {
            System.err.println("✗ Employé non trouvé (ID: " + employeeId + ")");
            return false;
        }

        // Vérifier que c'est bien le RH qui a créé cet employé
        if (!employee.getRhId().equals(currentUser.getId())) {
            System.err.println("✗ Accès refusé : Vous ne pouvez supprimer que vos propres employés");
            return false;
        }

        return deleteEmployeeFromDB(employeeId);
    }

    /**
     * Liste tous les employés d'un RH spécifique
     */
    public List<Employee> listMyEmployees(User currentUser) {
        if (!hasRHPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un RH peut lister ses employés");
            return List.of();
        }

        List<Employee> employees = getEmployeesByRhId(currentUser.getId());
        System.out.println("✓ " + employees.size() + " employé(s) trouvé(s)");
        return employees;
    }

    /**
     * Liste tous les employés (pour admin uniquement)
     */
    public List<Employee> listAllEmployees(User currentUser) {
        if (currentUser == null || !currentUser.isAdmin()) {
            System.err.println("✗ Accès refusé : Seul un administrateur peut lister tous les employés");
            return List.of();
        }

        List<Employee> employees = getAllEmployees();
        System.out.println("✓ " + employees.size() + " employé(s) au total");
        return employees;
    }

    /**
     * Récupère les détails d'un employé spécifique
     */
    public Employee getEmployeeDetails(User currentUser, int employeeId) {
        if (!hasRHPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé");
            return null;
        }

        Employee employee = getEmployeeById(employeeId);

        if (employee == null) {
            System.err.println("✗ Employé non trouvé");
            return null;
        }

        // Vérifier que c'est bien son employé
        if (!employee.getRhId().equals(currentUser.getId())) {
            System.err.println("✗ Accès refusé : Cet employé ne vous appartient pas");
            return null;
        }

        return employee;
    }

    /**
     * Change le mot de passe d'un employé
     */
    public boolean changeEmployeePassword(Employee employee, String oldPassword, String newPassword) {
        if (employee == null) {
            System.err.println("✗ Employé non valide");
            return false;
        }

        // Vérifier l'ancien mot de passe
        String hashedOldPassword = hashPassword(oldPassword);
        if (!employee.getPassword().equals(hashedOldPassword)) {
            System.err.println("✗ Ancien mot de passe incorrect");
            return false;
        }

        // Mettre à jour avec le nouveau mot de passe
        employee.setPassword(hashPassword(newPassword));

        if (updateEmployeeInDB(employee)) {
            System.out.println("✓ Mot de passe changé avec succès");
            return true;
        }

        return false;
    }

    // ============================================
    // MÉTHODES PRIVÉES D'ACCÈS AUX DONNÉES
    // ============================================

    /**
     * Crée un nouvel employé dans la base de données
     */
    private boolean createEmployee(Employee employee) {
        String sql = "INSERT INTO employees (first_name, last_name, age, job_title, email, password, rh_id) " +
                     "VALUES (?, ?, ?, ?, ?, ?, ?)";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            pstmt.setString(1, employee.getFirstName());
            pstmt.setString(2, employee.getLastName());
            pstmt.setInt(3, employee.getAge());
            pstmt.setString(4, employee.getJobTitle());
            pstmt.setString(5, employee.getEmail());
            pstmt.setString(6, employee.getPassword());
            pstmt.setInt(7, employee.getRhId());

            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                try (ResultSet generatedKeys = pstmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) {
                        employee.setId(generatedKeys.getInt(1));
                    }
                }
                System.out.println("✓ Employé créé : " + employee.getFullName());
                return true;
            }
            return false;

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la création de l'employé : " + e.getMessage());
            return false;
        }
    }

    /**
     * Récupère un employé par son email
     */
    private Employee getEmployeeByEmail(String email) {
        String sql = "SELECT * FROM employees WHERE email = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, email);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return mapResultSetToEmployee(rs);
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération de l'employé : " + e.getMessage());
        }
        return null;
    }

    /**
     * Récupère un employé par son ID
     */
    private Employee getEmployeeById(int id) {
        String sql = "SELECT * FROM employees WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, id);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return mapResultSetToEmployee(rs);
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération de l'employé : " + e.getMessage());
        }
        return null;
    }

    /**
     * Récupère tous les employés d'un RH spécifique
     */
    private List<Employee> getEmployeesByRhId(int rhId) {
        String sql = "SELECT * FROM employees WHERE rh_id = ? ORDER BY last_name, first_name";
        List<Employee> employees = new ArrayList<>();

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, rhId);
            ResultSet rs = pstmt.executeQuery();

            while (rs.next()) {
                employees.add(mapResultSetToEmployee(rs));
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération des employés : " + e.getMessage());
        }
        return employees;
    }

    /**
     * Récupère tous les employés (pour admin)
     */
    private List<Employee> getAllEmployees() {
        String sql = "SELECT * FROM employees ORDER BY last_name, first_name";
        List<Employee> employees = new ArrayList<>();

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            ResultSet rs = pstmt.executeQuery();

            while (rs.next()) {
                employees.add(mapResultSetToEmployee(rs));
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération de tous les employés : " + e.getMessage());
        }
        return employees;
    }

    /**
     * Met à jour un employé existant
     */
    private boolean updateEmployeeInDB(Employee employee) {
        String sql = "UPDATE employees SET first_name = ?, last_name = ?, age = ?, " +
                     "job_title = ?, email = ?, password = ? WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, employee.getFirstName());
            pstmt.setString(2, employee.getLastName());
            pstmt.setInt(3, employee.getAge());
            pstmt.setString(4, employee.getJobTitle());
            pstmt.setString(5, employee.getEmail());
            pstmt.setString(6, employee.getPassword());
            pstmt.setInt(7, employee.getId());

            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                System.out.println("✓ Employé mis à jour : " + employee.getFullName());
                return true;
            }
            return false;

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la mise à jour de l'employé : " + e.getMessage());
            return false;
        }
    }

    /**
     * Supprime un employé par son ID
     */
    private boolean deleteEmployeeFromDB(int id) {
        String sql = "DELETE FROM employees WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, id);
            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                System.out.println("✓ Employé supprimé (ID: " + id + ")");
                return true;
            }
            return false;

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la suppression de l'employé : " + e.getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un email existe déjà
     */
    private boolean emailExists(String email) {
        String sql = "SELECT COUNT(*) FROM employees WHERE email = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, email);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return rs.getInt(1) > 0;
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la vérification de l'email : " + e.getMessage());
        }
        return false;
    }

    /**
     * Mappe un ResultSet vers un objet Employee
     */
    private Employee mapResultSetToEmployee(ResultSet rs) throws SQLException {
        return new Employee(
            rs.getInt("id"),
            rs.getString("first_name"),
            rs.getString("last_name"),
            rs.getInt("age"),
            rs.getString("job_title"),
            rs.getString("email"),
            rs.getString("password"),
            rs.getInt("rh_id")
        );
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Vérifie si l'utilisateur a les privilèges RH
     */
    private boolean hasRHPrivileges(User user) {
        return user != null && user.isRH();
    }

    /**
     * Vérifie si une chaîne est null ou vide
     */
    private boolean isNullOrEmpty(String str) {
        return str == null || str.trim().isEmpty();
    }

    /**
     * Hash le mot de passe avec SHA-256
     */
    private String hashPassword(String password) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            byte[] hash = digest.digest(password.getBytes());

            StringBuilder hexString = new StringBuilder();
            for (byte b : hash) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) hexString.append('0');
                hexString.append(hex);
            }
            return hexString.toString();
        } catch (NoSuchAlgorithmException e) {
            throw new RuntimeException("Erreur lors du hashage du mot de passe", e);
        }
    }
}
