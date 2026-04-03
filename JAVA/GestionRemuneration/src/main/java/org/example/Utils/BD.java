package org.example.Utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;


public class BD {


    private final String URL = "jdbc:mysql://hrflow-hrflow.f.aivencloud.com:21031/defaultdb?useSSL=true&requireSSL=true";
    private final String USER = "avnadmin";
    private final String PASSWORD = "AVNS_7JkV8xT8CEbJDTKe9-4";
    private Connection connection;

    private static BD instance;
    public BD() {
        try {
            connection = DriverManager.getConnection(URL, USER, PASSWORD);
            System.out.println(" Connected to the database ! ");
        } catch (SQLException e) {
            System.err.println(e.getMessage());
        }
    }

    public static BD getInstance() {
        if(instance == null)
            instance = new BD();
        return instance ;
    }
    public Connection getConnection() {
        return connection;
    }

}
