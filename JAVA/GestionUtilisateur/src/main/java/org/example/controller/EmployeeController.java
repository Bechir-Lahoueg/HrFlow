package org.example.controller;

import org.example.model.Employee;
import org.example.model.User;
import org.example.service.EmployeeService;

import java.util.List;

/**
 * Controller pour gérer les interactions des employés
 * Orchestre les opérations entre la vue et le service employé
 */
public class EmployeeController {
    private final EmployeeService employeeService;

    public EmployeeController() {
        this.employeeService = new EmployeeService();
    }

    /**
     * Gère la connexion d'un employé
     */
    public Employee handleEmployeeLogin(String email, String password) {
        return employeeService.loginEmployee(email, password);
    }

    /**
     * Gère l'ajout d'un employé par un RH
     */
    public boolean handleAddEmployee(User currentUser, String firstName, String lastName,
                                     Integer age, String jobTitle, String email, String password) {
        return employeeService.addEmployee(currentUser, firstName, lastName, age, jobTitle, email, password);
    }

    /**
     * Gère la modification d'un employé
     */
    public boolean handleUpdateEmployee(User currentUser, int employeeId, String firstName,
                                        String lastName, Integer age, String jobTitle, String email) {
        return employeeService.updateEmployee(currentUser, employeeId, firstName, lastName, age, jobTitle, email);
    }

    /**
     * Gère la suppression d'un employé
     */
    public boolean handleDeleteEmployee(User currentUser, int employeeId) {
        return employeeService.deleteEmployee(currentUser, employeeId);
    }

    /**
     * Gère la liste des employés d'un RH
     */
    public List<Employee> handleListMyEmployees(User currentUser) {
        return employeeService.listMyEmployees(currentUser);
    }

    /**
     * Gère la récupération de tous les employés (pour admin)
     */
    public List<Employee> handleListAllEmployees(User currentUser) {
        return employeeService.listAllEmployees(currentUser);
    }

    /**
     * Gère la récupération des détails d'un employé
     */
    public Employee handleGetEmployeeDetails(User currentUser, int employeeId) {
        return employeeService.getEmployeeDetails(currentUser, employeeId);
    }

    /**
     * Gère le changement de mot de passe d'un employé
     */
    public boolean handleChangeEmployeePassword(Employee employee, String oldPassword, String newPassword) {
        return employeeService.changeEmployeePassword(employee, oldPassword, newPassword);
    }
}
