package org.example.service;

import org.example.config.DatabaseConfig;
import org.example.model.User;
import org.example.model.User.Role;

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service métier pour la gestion des utilisateurs
 * Intègre la logique métier et l'accès aux données
 */
public class UserService {

    public UserService() {
    }

    /**
     * Initialise le compte ADMIN par défaut si aucun n'existe
     * À appeler au démarrage de l'application
     */
    public void initializeDefaultAdmin() {
        System.out.println("⏳ Vérification de l'existence d'un compte ADMIN...");

        int adminCount = countUsersByRole(Role.ADMIN);

        if (adminCount == 0) {
            System.out.println("ℹ Aucun ADMIN trouvé. Création du compte par défaut...");

            // Créer l'admin par défaut
            User defaultAdmin = new User("Admin", "admin@gmail.com", hashPassword("admin123"), Role.ADMIN);

            if (createUser(defaultAdmin)) {
                System.out.println("✓✓✓ Compte ADMIN créé avec succès !");
                System.out.println("    Username: Admin");
                System.out.println("    Email: admin@gmail.com");
                System.out.println("    Password: admin123");
                System.out.println("    ⚠ IMPORTANT : Changez ce mot de passe dès que possible !");
            } else {
                System.err.println("✗ Échec de la création du compte ADMIN par défaut");
            }
        } else {
            System.out.println("✓ Un compte ADMIN existe déjà (" + adminCount + " admin(s) trouvé(s))");
        }
    }

    /**
     * Authentifie un utilisateur avec email ou username
     * @param identifier Username ou Email de l'utilisateur
     * @param password Mot de passe
     * @return L'utilisateur si les credentials sont valides, null sinon
     */
    public User login(String identifier, String password) {
        System.out.println("⏳ Tentative de connexion pour : " + identifier);

        if (identifier == null || identifier.trim().isEmpty() ||
            password == null || password.trim().isEmpty()) {
            System.err.println("✗ Identifiant ou password vide");
            return null;
        }

        // Déterminer si c'est un email ou un username
        User user = null;
        if (identifier.contains("@")) {
            // C'est un email
            user = getUserByEmail(identifier);
        } else {
            // C'est un username
            user = getUserByUsername(identifier);
        }

        if (user == null) {
            System.err.println("✗ Utilisateur non trouvé : " + identifier);
            return null;
        }

        String hashedPassword = hashPassword(password);

        if (user.getPassword().equals(hashedPassword)) {
            System.out.println("✓ Connexion réussie : " + identifier + " (Role: " + user.getRole() + ")");
            return user;
        } else {
            System.err.println("✗ Mot de passe incorrect pour : " + identifier);
            return null;
        }
    }

    /**
     * Ajoute un nouvel utilisateur RH (accessible uniquement aux ADMIN)
     */
    public boolean addRH(User currentUser, String username, String email, String password) {
        if (!hasAdminPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un ADMIN peut ajouter des RH");
            return false;
        }

        if (username == null || username.trim().isEmpty() ||
            password == null || password.trim().isEmpty()) {
            System.err.println("✗ Username ou password vide");
            return false;
        }

        if (usernameExists(username)) {
            System.err.println("✗ Le username existe déjà : " + username);
            return false;
        }

        User newRH = new User(username, email, hashPassword(password), Role.RH);
        return createUser(newRH);
    }

    /**
     * Modifie un utilisateur RH (accessible uniquement aux ADMIN)
     */
    public boolean updateRH(User currentUser, int rhId, String newUsername, String newEmail, String newPassword) {
        if (!hasAdminPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un ADMIN peut modifier des RH");
            return false;
        }

        User rh = getUserById(rhId);

        if (rh == null) {
            System.err.println("✗ Utilisateur RH non trouvé (ID: " + rhId + ")");
            return false;
        }

        if (rh.getRole() != Role.RH) {
            System.err.println("✗ L'utilisateur n'est pas un RH (ID: " + rhId + ")");
            return false;
        }

        // Vérifier si le nouveau username existe déjà (sauf si c'est le même)
        if (!rh.getUsername().equals(newUsername) && usernameExists(newUsername)) {
            System.err.println("✗ Le username existe déjà : " + newUsername);
            return false;
        }

        rh.setUsername(newUsername);
        
        // Mettre à jour l'email s'il est fourni
        if (newEmail != null && !newEmail.trim().isEmpty()) {
            rh.setEmail(newEmail);
        }

        // Ne mettre à jour le mot de passe que s'il est fourni
        if (newPassword != null && !newPassword.trim().isEmpty()) {
            rh.setPassword(hashPassword(newPassword));
        }

        return updateUser(rh);
    }

    /**
     * Supprime un utilisateur RH (accessible uniquement aux ADMIN)
     */
    public boolean deleteRH(User currentUser, int rhId) {
        if (!hasAdminPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un ADMIN peut supprimer des RH");
            return false;
        }

        User rh = getUserById(rhId);

        if (rh == null) {
            System.err.println("✗ Utilisateur RH non trouvé (ID: " + rhId + ")");
            return false;
        }

        if (rh.getRole() != Role.RH) {
            System.err.println("✗ L'utilisateur n'est pas un RH (ID: " + rhId + ")");
            return false;
        }

        return deleteUser(rhId);
    }

    /**
     * Liste tous les utilisateurs RH (accessible uniquement aux ADMIN)
     */
    public List<User> listAllRH(User currentUser) {
        if (!hasAdminPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un ADMIN peut lister les RH");
            return List.of();
        }

        List<User> rhList = getAllUsersByRole(Role.RH);
        System.out.println("✓ " + rhList.size() + " RH trouvé(s)");
        return rhList;
    }

    /**
     * Change le mot de passe d'un utilisateur
     */
    public boolean changePassword(User user, String oldPassword, String newPassword) {
        if (user == null) {
            System.err.println("✗ Utilisateur non valide");
            return false;
        }

        // Vérifier l'ancien mot de passe
        String hashedOldPassword = hashPassword(oldPassword);
        if (!user.getPassword().equals(hashedOldPassword)) {
            System.err.println("✗ Ancien mot de passe incorrect");
            return false;
        }

        // Mettre à jour avec le nouveau mot de passe
        user.setPassword(hashPassword(newPassword));

        if (updateUser(user)) {
            System.out.println("✓ Mot de passe changé avec succès");
            return true;
        }

        return false;
    }

    /**
     * Récupère les informations d'un utilisateur par ID
     */
    public User getUserInfo(User currentUser, int userId) {
        // Un utilisateur peut voir ses propres infos
        if (currentUser.getId() != null && currentUser.getId() == userId) {
            return getUserById(userId);
        }

        // Un ADMIN peut voir n'importe quel utilisateur
        if (hasAdminPrivileges(currentUser)) {
            return getUserById(userId);
        }

        System.err.println("✗ Accès refusé : Vous ne pouvez pas voir les informations de cet utilisateur");
        return null;
    }

    /**
     * Liste tous les utilisateurs (accessible uniquement aux ADMIN)
     */
    public List<User> listAllUsers(User currentUser) {
        if (!hasAdminPrivileges(currentUser)) {
            System.err.println("✗ Accès refusé : Seul un ADMIN peut lister tous les utilisateurs");
            return List.of();
        }

        List<User> allUsers = getAllUsers();
        System.out.println("✓ " + allUsers.size() + " utilisateur(s) trouvé(s)");
        return allUsers;
    }

    // ============================================
    // MÉTHODES PRIVÉES D'ACCÈS AUX DONNÉES
    // ============================================

    /**
     * Crée un nouvel utilisateur dans la base de données
     */
    private boolean createUser(User user) {
        String sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            pstmt.setString(1, user.getUsername());
            pstmt.setString(2, user.getEmail());
            pstmt.setString(3, user.getPassword());
            pstmt.setString(4, user.getRole().name());

            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                try (ResultSet generatedKeys = pstmt.getGeneratedKeys()) {
                    if (generatedKeys.next()) {
                        user.setId(generatedKeys.getInt(1));
                    }
                }
                System.out.println("✓ Utilisateur créé : " + user.getUsername());
                return true;
            }
            return false;

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la création de l'utilisateur : " + e.getMessage());
            return false;
        }
    }

    /**
     * Récupère un utilisateur par son username
     */
    private User getUserByUsername(String username) {
        String sql = "SELECT * FROM users WHERE username = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, username);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return mapResultSetToUser(rs);
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération de l'utilisateur : " + e.getMessage());
        }
        return null;
    }

    /**
     * Récupère un utilisateur par son email
     */
    private User getUserByEmail(String email) {
        String sql = "SELECT * FROM users WHERE email = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, email);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return mapResultSetToUser(rs);
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération de l'utilisateur par email : " + e.getMessage());
        }
        return null;
    }

    /**
     * Récupère un utilisateur par son ID
     */
    private User getUserById(int id) {
        String sql = "SELECT * FROM users WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, id);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return mapResultSetToUser(rs);
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération de l'utilisateur : " + e.getMessage());
        }
        return null;
    }

    /**
     * Met à jour un utilisateur existant
     */
    private boolean updateUser(User user) {
        String sql = "UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, user.getUsername());
            pstmt.setString(2, user.getEmail());
            pstmt.setString(3, user.getPassword());
            pstmt.setString(4, user.getRole().name());
            pstmt.setInt(5, user.getId());

            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                System.out.println("✓ Utilisateur mis à jour : " + user.getUsername());
                return true;
            }
            return false;

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la mise à jour de l'utilisateur : " + e.getMessage());
            return false;
        }
    }

    /**
     * Supprime un utilisateur par son ID
     */
    private boolean deleteUser(int id) {
        String sql = "DELETE FROM users WHERE id = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setInt(1, id);
            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                System.out.println("✓ Utilisateur supprimé (ID: " + id + ")");
                return true;
            }
            return false;

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la suppression de l'utilisateur : " + e.getMessage());
            return false;
        }
    }

    /**
     * Récupère tous les utilisateurs avec un rôle spécifique
     */
    private List<User> getAllUsersByRole(Role role) {
        String sql = "SELECT * FROM users WHERE role = ?";
        List<User> users = new ArrayList<>();

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, role.name());
            ResultSet rs = pstmt.executeQuery();

            while (rs.next()) {
                users.add(mapResultSetToUser(rs));
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération des utilisateurs : " + e.getMessage());
        }
        return users;
    }

    /**
     * Récupère tous les utilisateurs
     */
    private List<User> getAllUsers() {
        String sql = "SELECT * FROM users";
        List<User> users = new ArrayList<>();

        try (Connection conn = DatabaseConfig.getConnection();
             Statement stmt = conn.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {

            while (rs.next()) {
                users.add(mapResultSetToUser(rs));
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la récupération des utilisateurs : " + e.getMessage());
        }
        return users;
    }

    /**
     * Compte le nombre d'utilisateurs avec un rôle spécifique
     */
    private int countUsersByRole(Role role) {
        String sql = "SELECT COUNT(*) FROM users WHERE role = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, role.name());
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return rs.getInt(1);
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors du comptage des utilisateurs : " + e.getMessage());
        }
        return 0;
    }

    /**
     * Vérifie si un username existe déjà
     */
    private boolean usernameExists(String username) {
        String sql = "SELECT COUNT(*) FROM users WHERE username = ?";

        try (Connection conn = DatabaseConfig.getConnection();
             PreparedStatement pstmt = conn.prepareStatement(sql)) {

            pstmt.setString(1, username);
            ResultSet rs = pstmt.executeQuery();

            if (rs.next()) {
                return rs.getInt(1) > 0;
            }

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de la vérification du username : " + e.getMessage());
        }
        return false;
    }

    /**
     * Mappe un ResultSet vers un objet User
     */
    private User mapResultSetToUser(ResultSet rs) throws SQLException {
        return new User(
            rs.getInt("id"),
            rs.getString("username"),
            rs.getString("email"),
            rs.getString("password"),
            Role.valueOf(rs.getString("role"))
        );
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Vérifie si l'utilisateur a les privilèges ADMIN
     */
    private boolean hasAdminPrivileges(User user) {
        return user != null && user.isAdmin();
    }

    /**
     * Hash le mot de passe avec SHA-256
     * Note : En production, utilisez BCrypt ou Argon2 pour plus de sécurité
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
