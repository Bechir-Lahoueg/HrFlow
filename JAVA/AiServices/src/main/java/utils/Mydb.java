package utils;

import java.sql.Connection;
import java.sql.DriverManager;

/**
 * Database utility class for AiServices
 */
public class Mydb {
    private static Mydb instance;
    private Connection connection;
    
    // Cloud database configuration (same as other modules)
    private static final String DEFAULT_URL = "jdbc:mysql://hrflow-hrflow.f.aivencloud.com:21031/defaultdb?useSSL=true&requireSSL=true";
    private static final String DEFAULT_USER = "avnadmin";
    private static final String DEFAULT_PASSWORD = "AVNS_7JkV8xT8CEbJDTKe9-4";
    
    private Mydb() {
        try {
            String url = System.getenv("DB_URL") != null ? System.getenv("DB_URL") : DEFAULT_URL;
            String user = System.getenv("DB_USER") != null ? System.getenv("DB_USER") : DEFAULT_USER;
            String password = System.getenv("DB_PASSWORD") != null ? System.getenv("DB_PASSWORD") : DEFAULT_PASSWORD;

            connection = DriverManager.getConnection(url, user, password);
        } catch (Exception e) {
            System.err.println("Failed to connect to database: " + e.getMessage());
        }
    }
    
    public static Mydb getInstance() {
        if (instance == null) {
            instance = new Mydb();
        }
        return instance;
    }
    
    public Connection getConnection() {
        return connection;
    }
}
