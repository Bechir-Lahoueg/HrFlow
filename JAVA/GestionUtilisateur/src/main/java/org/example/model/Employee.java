package org.example.model;

/**
 * Entité Employee représentant un employé du système RH
 * Chaque employé est rattaché à un RH qui l'a créé
 */
public class Employee {
    private Integer id;
    private String firstName;
    private String lastName;
    private Integer age;
    private String jobTitle;
    private String email;
    private String password;
    private Integer rhId; // ID du RH qui a créé cet employé

    // Constructeurs
    public Employee() {
    }

    public Employee(String firstName, String lastName, Integer age, String jobTitle, 
                    String email, String password, Integer rhId) {
        this.firstName = firstName;
        this.lastName = lastName;
        this.age = age;
        this.jobTitle = jobTitle;
        this.email = email;
        this.password = password;
        this.rhId = rhId;
    }

    public Employee(Integer id, String firstName, String lastName, Integer age, 
                    String jobTitle, String email, String password, Integer rhId) {
        this.id = id;
        this.firstName = firstName;
        this.lastName = lastName;
        this.age = age;
        this.jobTitle = jobTitle;
        this.email = email;
        this.password = password;
        this.rhId = rhId;
    }

    // Getters et Setters
    public Integer getId() {
        return id;
    }

    public void setId(Integer id) {
        this.id = id;
    }

    public String getFirstName() {
        return firstName;
    }

    public void setFirstName(String firstName) {
        this.firstName = firstName;
    }

    public String getLastName() {
        return lastName;
    }

    public void setLastName(String lastName) {
        this.lastName = lastName;
    }

    public Integer getAge() {
        return age;
    }

    public void setAge(Integer age) {
        this.age = age;
    }

    public String getJobTitle() {
        return jobTitle;
    }

    public void setJobTitle(String jobTitle) {
        this.jobTitle = jobTitle;
    }

    public String getEmail() {
        return email;
    }

    public void setEmail(String email) {
        this.email = email;
    }

    public String getPassword() {
        return password;
    }

    public void setPassword(String password) {
        this.password = password;
    }

    public Integer getRhId() {
        return rhId;
    }

    public void setRhId(Integer rhId) {
        this.rhId = rhId;
    }

    // Méthode utilitaire
    public String getFullName() {
        return firstName + " " + lastName;
    }

    @Override
    public String toString() {
        return "Employee{" +
                "id=" + id +
                ", firstName='" + firstName + '\'' +
                ", lastName='" + lastName + '\'' +
                ", age=" + age +
                ", jobTitle='" + jobTitle + '\'' +
                ", email='" + email + '\'' +
                ", rhId=" + rhId +
                '}';
    }
}
