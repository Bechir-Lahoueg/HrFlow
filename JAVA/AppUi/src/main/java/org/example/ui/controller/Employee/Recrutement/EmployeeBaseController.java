package org.example.ui.controller.Employee.Recrutement;

import javafx.scene.control.Alert;
import org.example.model.Employee;

public abstract class EmployeeBaseController {

    protected EmployeeMainController mainController;
    protected Employee currentEmployee;

    public void setMainController(EmployeeMainController mainController) {
        this.mainController = mainController;
    }

    public void setCurrentEmployee(Employee employee) {
        this.currentEmployee = employee;
    }

    protected void setFormError(String error) {
        if (mainController != null) {
            mainController.setFormError(error);
        }
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
