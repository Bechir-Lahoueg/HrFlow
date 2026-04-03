package org.example.config;

import java.io.IOException;
import java.io.InputStream;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.Properties;

public class DatabaseConfig {
    private static String DB_URL;
    private static String DB_USER;
    private static String DB_PASSWORD;
    private static String DB_DRIVER;

    static {
        loadProperties();
        loadDriver();
    }

    private static void loadProperties() {
        Properties properties = new Properties();
        try (InputStream input = DatabaseConfig.class.getClassLoader()
                .getResourceAsStream("application.properties")) {
            if (input == null) {
                throw new RuntimeException("Impossible de trouver application.properties");
            }
            properties.load(input);

            DB_URL = properties.getProperty("db.url");
            DB_USER = properties.getProperty("db.user");
            DB_PASSWORD = properties.getProperty("db.password");
            DB_DRIVER = properties.getProperty("db.driver");

            System.out.println("✓ Configuration chargée depuis application.properties");
        } catch (IOException e) {
            throw new RuntimeException("Erreur lors du chargement de la configuration", e);
        }
    }

    private static void loadDriver() {
        try {
            Class.forName(DB_DRIVER);
            System.out.println("✓ Driver MySQL chargé avec succès");
        } catch (ClassNotFoundException e) {
            throw new RuntimeException("Erreur lors du chargement du driver MySQL", e);
        }
    }

    public static Connection getConnection() throws SQLException {
        Connection connection = DriverManager.getConnection(DB_URL, DB_USER, DB_PASSWORD);
        System.out.println("✓ Connexion à la base de données établie");
        return connection;
    }

    /**
     * Initialise la base de données en créant les tables users et employees si elles n'existent pas
     */
    public static void initializeDatabase() {
        String createUsersTableSQL = """
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255) NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL CHECK (role IN ('ADMIN', 'RH', 'EMPLOYEE')),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            """;

        String createEmployeesTableSQL = """
            CREATE TABLE IF NOT EXISTS employees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                age INT NOT NULL,
                job_title VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                rh_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (rh_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_rh_id (rh_id),
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            """;

        try (Connection conn = getConnection();
             java.sql.Statement stmt = conn.createStatement()) {

            System.out.println("⏳ Initialisation de la base de données...");
            
            stmt.execute(createUsersTableSQL);
            System.out.println("✓ Table 'users' vérifiée/créée avec succès");
            
            stmt.execute(createEmployeesTableSQL);
            System.out.println("✓ Table 'employees' vérifiée/créée avec succès");

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de l'initialisation de la base de données : " + e.getMessage());
            throw new RuntimeException("Impossible d'initialiser la base de données", e);
        }
    }

    // Getters pour les tests ou autres usages
    public static String getDbUrl() {
        return DB_URL;
    }

    public static String getDbUser() {
        return DB_USER;
    }
}
