package org.example.ui;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;
import org.example.model.Employee;
import org.example.model.User;
//import service.SchedulerManager;

import java.lang.reflect.Method;

/**
 * Application principale JavaFX pour l'interface graphique du système RH
 */
public class MainApp extends Application {

    private static Stage primaryStage;

    @Override
    public void start(Stage stage) throws Exception {
        primaryStage = stage;

        // Initialiser la base de données
        System.out.println("═══════════════════════════════════════════════════");
        System.out.println("   WORKFORCE PLATFORM - INTERFACE GRAPHIQUE");
        System.out.println("═══════════════════════════════════════════════════\n");

        // Initialiser les bases de données de tous les modules
        System.out.println("📦 Initialisation des bases de données...\n");

        // Module Utilisateur
        System.out.println("   → Module Utilisateur");
        initializeUserDatabase();

        // Module Formation
        System.out.println("   → Module Formation");
        initializeFormationDatabase();

        System.out.println("\n✅ Toutes les bases de données sont initialisées\n");

        // Initialiser le compte admin par défaut
        org.example.controller.UserController userController = new org.example.controller.UserController();
        userController.initializeApplication();

        // Charger l'écran de login
        showLoginScreen();

        primaryStage.setTitle("Workforce Platform - Connexion");
        primaryStage.setResizable(true);
        primaryStage.setMinWidth(900);
        primaryStage.setMinHeight(600);
        primaryStage.show();
        // 1. Démarrer l'automatisation des emails au lancement
        //SchedulerManager.startDailyCheck();
    }

    /**
     * Affiche l'écran de connexion
     */
    public static void showLoginScreen() {
        try {
            FXMLLoader loader = new FXMLLoader(MainApp.class.getResource("/fxml/Login.fxml"));
            Parent root = loader.load();

            Scene scene = new Scene(root);
            // Utiliser uniquement notre CSS personnalisé pour la page login
            scene.getStylesheets().add(MainApp.class.getResource("/css/style.css").toExternalForm());

            primaryStage.setScene(scene);
            primaryStage.setTitle("Workforce Platform - Connexion");
        } catch (Exception e) {
            e.printStackTrace();
            System.err.println("Erreur lors du chargement de l'écran de login: " + e.getMessage());
        }
    }

    /**
     * Change la scène courante
     */
    public static void changeScene(String fxmlPath, String title) {
        try {
            FXMLLoader loader = new FXMLLoader(MainApp.class.getResource(fxmlPath));
            Parent root = loader.load();

            Scene scene = new Scene(root);
            scene.getStylesheets().add(MainApp.class.getResource("/css/style.css").toExternalForm());

            primaryStage.setScene(scene);
            primaryStage.setTitle(title);
        } catch (Exception e) {
            e.printStackTrace();
            System.err.println("Erreur lors du changement de scène: " + e.getMessage());
        }
    }

    /**
     * Retourne le stage principal
     */
    public static Stage getPrimaryStage() {
        return primaryStage;
    }

    /**
     * Affiche le dashboard de l'employé
     */
    public static void showEmployeeDashboard(Employee employee) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    MainApp.class.getResource("/fxml/views/Employee-dashboard/EmployeeDashboard.fxml"));
            Parent root = loader.load();

            org.example.ui.controller.Employee.EmployeeDashboardController controller = loader.getController();
            controller.setCurrentEmployee(employee);

            Scene scene = new Scene(root, 1280, 800);
            scene.getStylesheets().add(MainApp.class.getResource("/css/style.css").toExternalForm());

            primaryStage.setScene(scene);
            primaryStage.setTitle("Dashboard Employé - " + employee.getFullName());
            primaryStage.setResizable(true);
            primaryStage.setMinWidth(1000);
            primaryStage.setMinHeight(650);
            primaryStage.setMaximized(true);
        } catch (Exception e) {
            e.printStackTrace();
            System.err.println("Erreur lors du chargement du dashboard employé: " + e.getMessage());
        }
    }

    /**
     * Affiche le dashboard du RH
     */
    public static void showRHDashboard(User rhUser) {
        try {
            FXMLLoader loader = new FXMLLoader(MainApp.class.getResource("/fxml/views/Rh-dashboard/RHDashboard.fxml"));
            Parent root = loader.load();

            org.example.ui.controller.Rh.RHDashboardController controller = loader.getController();
            controller.setCurrentUser(rhUser);

            Scene scene = new Scene(root, 1280, 800);
            scene.getStylesheets().add(MainApp.class.getResource("/css/style.css").toExternalForm());

            primaryStage.setScene(scene);
            primaryStage.setTitle("Dashboard RH - " + rhUser.getUsername());
            primaryStage.setResizable(true);
            primaryStage.setMinWidth(1000);
            primaryStage.setMinHeight(650);
            primaryStage.setMaximized(true);
        } catch (Exception e) {
            e.printStackTrace();
            System.err.println("Erreur lors du chargement du dashboard RH: " + e.getMessage());
        }
    }

    /**
     * Initialise la base de données du module Utilisateur
     */
    private static void initializeUserDatabase() {
        try {
            Class<?> dbConfigClass = Class.forName("org.example.config.DatabaseConfig");
            Method initMethod = dbConfigClass.getMethod("initializeDatabase");
            initMethod.invoke(null);
        } catch (Exception e) {
            System.err
                    .println("❌ Erreur lors de l'initialisation de la base de données Utilisateur: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Initialise la base de données du module Formation
     * Appelle directement Mydb.initializeDatabase() du module Formation
     */
    private static void initializeFormationDatabase() {
        try {
            // Charger le module Formation et appeler Mydb.initializeDatabase()
            ClassLoader classLoader = Thread.currentThread().getContextClassLoader();

            // Vérifier que le module Formation est bien présent
            try {
                classLoader.loadClass("org.example.services.FormationService");
                // Module Formation trouvé, appeler Mydb.initializeDatabase() s'il existe
                Class<?> mydbClass = classLoader.loadClass("org.example.utils.Mydb");
                try {
                    Method initMethod = mydbClass.getMethod("initializeDatabase");
                    initMethod.invoke(null);
                } catch (NoSuchMethodException e) {
                    System.out.println("ℹ️ Mydb.initializeDatabase() non trouvé, sauts de l'initialisation auto.");
                }
            } catch (ClassNotFoundException e) {
                System.err.println("⚠️ Module Formation non trouvé dans le classpath");
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur lors de l'initialisation de la base de données Formation: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public static void main(String[] args) {
        launch(args);
    }
}
