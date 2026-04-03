package controllers.Employee;

import javafx.scene.control.Alert;

public abstract class EmployeeBaseController {
    
    protected EmployeeMainController mainController;
    
    public void setMainController(EmployeeMainController mainController) {
        this.mainController = mainController;
    }
    
    public abstract void handleSave();
    
    public abstract void clearFields();
    
    protected void showErrorAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
    
    protected void showSuccessAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
    
    protected void showInfoAlert(String title, String content) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
