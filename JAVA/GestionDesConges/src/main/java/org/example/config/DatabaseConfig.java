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
        System.out.println("→ Tentative de connexion à la base de données...");
        Connection connection = DriverManager.getConnection(DB_URL, DB_USER, DB_PASSWORD);
        System.out.println("✓ Base de données connectée avec succès !");
        return connection;
    }

    public static void closeConnection(Connection connection) {
        if (connection != null) {
            try {
                connection.close();
                System.out.println("✓ Connexion fermée");
            } catch (SQLException e) {
                System.err.println("✗ Erreur lors de la fermeture de la connexion: " + e.getMessage());
            }
        }
    }

    public static void testConnection() {
        Connection connection = null;
        try {
            connection = getConnection();
            System.out.println("✓ Test de connexion réussi !");
        } catch (SQLException e) {
            System.err.println("✗ Erreur de connexion: " + e.getMessage());
            e.printStackTrace();
        } finally {
            closeConnection(connection);
        }
    }

    /**
     * Initialise la base de données en créant la table leave_requests si elle n'existe pas
     */
    public static void initializeDatabase() {
        String createLeaveRequestsTableSQL = """
            CREATE TABLE IF NOT EXISTS leave_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                employee_id INT NOT NULL,
                employee_name VARCHAR(255) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                leave_type VARCHAR(100) NOT NULL,
                reason TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'ATTENTE',
                request_date DATE NOT NULL,
                rh_comment TEXT,
                days_count INT NOT NULL,
                FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_employee_id (employee_id),
                INDEX idx_status (status),
                INDEX idx_request_date (request_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            """;

        try (Connection conn = getConnection();
             java.sql.Statement stmt = conn.createStatement()) {

            System.out.println("⏳ Initialisation de la table leave_requests...");
            
            stmt.execute(createLeaveRequestsTableSQL);
            System.out.println("✓ Table 'leave_requests' vérifiée/créée avec succès");

        } catch (SQLException e) {
            System.err.println("✗ Erreur lors de l'initialisation de la base de données: " + e.getMessage());
            e.printStackTrace();
        }
    }
}
