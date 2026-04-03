package org.example.main;

import org.example.Utils.BD;


public class Main {
    public static void main(String[] args) {

        // 🔹 Test Singleton Mydb
        BD mydb = BD.getInstance();
        BD mydb1 = BD.getInstance();
        BD mydb2 = BD.getInstance();

        System.out.println(mydb);
        System.out.println(mydb1);
        System.out.println(mydb2);
    }
}
