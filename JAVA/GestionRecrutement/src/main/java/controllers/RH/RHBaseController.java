package controllers.RH;

import javafx.scene.control.Alert;

public abstract class RHBaseController {


    public void handleSave() {
        // default: do nothing
    }

    public void clearFields() {
        // default: do nothing
    }
    
    protected RHMainController mainController;
    
    public void setMainController(RHMainController mainController) {
        this.mainController = mainController;
    }
    
    public void showErrorAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
    
    public void showSuccessAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
    
    public void showInfoAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
