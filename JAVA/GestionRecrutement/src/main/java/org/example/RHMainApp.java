package org.example;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class RHMainApp extends Application {

    @Override
    public void start(Stage primaryStage) throws Exception {
        System.out.println("🔍 Starting RHMainApp - Loading HR Portal");
        FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/RH/HRMainView.fxml"));
        Parent root = loader.load();

        primaryStage.setTitle("HRFLOW - HR Portal");
        Scene scene = new Scene(root, 1280, 800);

        primaryStage.setMinWidth(1100);
        primaryStage.setMinHeight(700);

        primaryStage.setScene(scene);
        primaryStage.show();
    }

    public static void main(String[] args) {
        launch(args);
    }
}
