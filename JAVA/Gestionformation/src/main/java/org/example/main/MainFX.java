package org.example.main;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class MainFX extends Application {

    @Override
    public void start(Stage primaryStage) throws Exception {
        // Charge le fichier FXML que tu veux tester (ex: FormationList.fxml)
        // Le chemin commence par "/" pour chercher dans le dossier 'resources'
        FXMLLoader loader = new FXMLLoader(getClass().getResource("/views/FormationList.fxml"));
        Parent root = loader.load();

        primaryStage.setTitle("Test Interface - Gestion Formation");

        // Création de la scène (Largeur x Hauteur)
        Scene scene = new Scene(root, 1100, 700);

        // Optionnel : ajouter un fichier CSS si tu en as un
        // scene.getStylesheets().add(getClass().getResource("/css/style.css").toExternalForm());

        primaryStage.setScene(scene);
        primaryStage.show();
    }

    public static void main(String[] args) {
        launch(args);
    }
}