package org.example;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class MainApp extends Application {

    @Override
    public void start(Stage primaryStage) throws Exception {
        FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Admin/MainView.fxml"));
        Parent root = loader.load();

        primaryStage.setTitle("TalentFlow - Recruitment Management Hub");
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
