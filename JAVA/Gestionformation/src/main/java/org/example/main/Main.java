package org.example.main;

import org.example.utils.Mydb;

public class Main {
    public static void main(String[] args) {

        // 🔹 Test Singleton Mydb
        Mydb mydb = Mydb.getInstance();
        Mydb mydb1 = Mydb.getInstance();
        Mydb mydb2 = Mydb.getInstance();

        System.out.println(mydb);
        System.out.println(mydb1);
        System.out.println(mydb2);
        Mydb.getInstance().initializeDatabasePublic();



    }
}