package utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import io.github.cdimascio.dotenv.Dotenv;

public class Mydb {

    private static final Dotenv dotenv = Dotenv.configure().ignoreIfMissing().load();
    private static final String HOST = dotenv.get("DB_HOST", "hrflow-hrflow.f.aivencloud.com");

    private static final String PORT = dotenv.get("DB_PORT", "21031");

    private static final String NAME = dotenv.get("DB_NAME", "defaultdb");
    // Correct JDBC URL
    public static final String URL = "jdbc:mysql://" + HOST + ":" + PORT + "/" + NAME +
            "?sslMode=REQUIRED";
    private static final String USER = dotenv.get("DB_USER");
    private static final String PASSWORD = dotenv.get("DB_PASSWORD");

    private Connection connection;
    private static Mydb instance;

    // Private constructor (Singleton)
    public Mydb() {
        try {
            connection = DriverManager.getConnection(URL, USER, PASSWORD);
            System.out.println("✅ Connected to Aiven MySQL");
        } catch (SQLException e) {
            System.err.println("❌ Database connection failed:");
            e.printStackTrace();
        }
    }

    public static Mydb getInstance() {
        if (instance == null) {
            instance = new Mydb();
        }
        return instance;
    }

    public Connection getConnection() {
        try {
            if (connection == null || connection.isClosed()) {
                connection = DriverManager.getConnection(URL, USER, PASSWORD);
            }
        } catch (SQLException e) {
            System.err.println("❌ Reconnection failed: " + e.getMessage());
        }
        return connection;
    }
}
