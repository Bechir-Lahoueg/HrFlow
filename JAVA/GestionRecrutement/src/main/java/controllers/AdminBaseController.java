package controllers;

public abstract class AdminBaseController {
    
    protected AdminMainController mainController;
    
    public void setMainController(AdminMainController mainController) {
        this.mainController = mainController;
    }
    
    public abstract void handleSave();
    
    public abstract void clearFields();
    
    protected void showErrorAlert(String title, String content) {
        if (mainController != null) {
            mainController.showErrorAlert(title, content);
        }
    }
    
    protected void showSuccessAlert(String title, String content) {
        if (mainController != null) {
            mainController.showSuccessAlert(title, content);
        }
    }
}
