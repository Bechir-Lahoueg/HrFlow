package utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class Mydb {

    // Hardcoded database credentials
    private static final String HOST = "hrflow-hrflow.f.aivencloud.com";
    private static final String PORT = "21031";
    private static final String NAME = "defaultdb";
    private static final String USER = "avnadmin";
    private static final String PASSWORD = "AVNS_7JkV8xT8CEbJDTKe9-4";
    
    // Correct JDBC URL
    public static final String URL = "jdbc:mysql://" + HOST + ":" + PORT + "/" + NAME +
            "?sslMode=REQUIRED";
    private Connection connection;
    private static Mydb instance;

    // Private constructor (Singleton)
    private Mydb() {
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
        return connection;
    }
}