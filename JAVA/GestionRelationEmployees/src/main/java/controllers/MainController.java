package controllers;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Parent;
import javafx.scene.layout.StackPane;

import java.io.IOException;
import java.net.URL;
import java.util.ResourceBundle;

public class MainController implements Initializable {

    @FXML private StackPane contentArea;

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        showRequests();
    }

    @FXML
    public void showRequests() {
        loadView("/fxml/RequestListView.fxml");
    }

    @FXML
    public void showRequestTypes() {
        loadView("/fxml/RequestTypeView.fxml");
    }

    @FXML
    public void showFeedbacks() {
        loadView("/fxml/FeedbackView.fxml"); // temporaire
    }

    @FXML
    public void showNotifications() {
        loadView("/fxml/NotificationView.fxml"); // temporaire
    }

    private void loadView(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            Parent view = loader.load();
            contentArea.getChildren().setAll(view);
        } catch (IOException e) {
            System.err.println("❌ Erreur chargement vue : " + fxmlPath);
            e.printStackTrace();
        }
    }
}