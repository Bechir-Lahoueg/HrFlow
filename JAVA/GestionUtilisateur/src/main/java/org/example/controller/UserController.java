package org.example.controller;

import org.example.model.User;
import org.example.service.UserService;

import java.util.List;

/**
 * Controller pour gérer les interactions utilisateur
 * Architecture MVC : contrôle le flux entre la vue (Main) et le service
 */
public class UserController {
    private final UserService userService;

    public UserController() {
        this.userService = new UserService();
    }

    /**
     * Initialise l'application (base de données + admin par défaut)
     */
    public void initializeApplication() {
        userService.initializeDefaultAdmin();
    }

    /**
     * Gère la connexion d'un utilisateur
     */
    public User handleLogin(String username, String password) {
        return userService.login(username, password);
    }

    /**
     * Gère l'ajout d'un RH
     */
    public boolean handleAddRH(User currentUser, String username, String email, String password) {
        return userService.addRH(currentUser, username, email, password);
    }

    /**
     * Gère la modification d'un RH
     */
    public boolean handleUpdateRH(User currentUser, int rhId, String newUsername, String newEmail, String newPassword) {
        return userService.updateRH(currentUser, rhId, newUsername, newEmail, newPassword);
    }

    /**
     * Gère la suppression d'un RH
     */
    public boolean handleDeleteRH(User currentUser, int rhId) {
        return userService.deleteRH(currentUser, rhId);
    }

    /**
     * Gère la liste de tous les RH
     */
    public List<User> handleListAllRH(User currentUser) {
        return userService.listAllRH(currentUser);
    }

    /**
     * Gère la liste de tous les utilisateurs
     */
    public List<User> handleListAllUsers(User currentUser) {
        return userService.listAllUsers(currentUser);
    }

    /**
     * Gère le changement de mot de passe
     */
    public boolean handleChangePassword(User user, String oldPassword, String newPassword) {
        return userService.changePassword(user, oldPassword, newPassword);
    }
}
