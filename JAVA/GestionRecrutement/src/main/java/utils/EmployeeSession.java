package utils;

/**
 * EmployeeSession - Utility class for managing the current employee's session
 * Stores employee-specific information during the application session
 */
public class EmployeeSession {
    
    private static EmployeeSession instance;
    
    private int employeeId;
    private String employeeName;
    private String employeeEmail;
    private String employeeDepartment;
    
    // Private constructor for Singleton pattern
    private EmployeeSession() {
        // Default values - can be initialized from login
        this.employeeId = 1;  // Default to employee 1
        this.employeeName = "John Doe";
        this.employeeEmail = "john.doe@company.com";
        this.employeeDepartment = "Engineering";
    }
    
    /**
     * Get the singleton instance of EmployeeSession
     */
    public static EmployeeSession getInstance() {
        if (instance == null) {
            instance = new EmployeeSession();
        }
        return instance;
    }
    
    /**
     * Initialize the session with employee login data
     */
    public static void initialize(int employeeId, String name, String email, String department) {
        instance = new EmployeeSession();
        instance.employeeId = employeeId;
        instance.employeeName = name;
        instance.employeeEmail = email;
        instance.employeeDepartment = department;
        System.out.println("✅ Employee Session Initialized - ID: " + employeeId + ", Email: " + email);
    }
    
    /**
     * Clear the session (logout)
     */
    public static void clear() {
        if (instance != null) {
            System.out.println("🚪 Employee Session Cleared - Employee: " + instance.employeeId);
            instance = null;
        }
    }
    
    // Getters
    public int getEmployeeId() {
        return employeeId;
    }
    
    public String getEmployeeName() {
        return employeeName;
    }
    
    public String getEmployeeEmail() {
        return employeeEmail;
    }
    
    public String getEmployeeDepartment() {
        return employeeDepartment;
    }
    
    // Setters
    public void setEmployeeId(int employeeId) {
        this.employeeId = employeeId;
    }
    
    public void setEmployeeName(String employeeName) {
        this.employeeName = employeeName;
    }
    
    public void setEmployeeEmail(String employeeEmail) {
        this.employeeEmail = employeeEmail;
    }
    
    public void setEmployeeDepartment(String employeeDepartment) {
        this.employeeDepartment = employeeDepartment;
    }
    
    @Override
    public String toString() {
        return "EmployeeSession{" +
                "employeeId=" + employeeId +
                ", employeeName='" + employeeName + '\'' +
                ", employeeEmail='" + employeeEmail + '\'' +
                ", employeeDepartment='" + employeeDepartment + '\'' +
                '}';
    }
}
