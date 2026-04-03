package controllers;

public abstract class BaseController {
    protected MainController mainController;

    public void setMainController(MainController mainController) {
        this.mainController = mainController;
    }

    /**
     * Called by MainController when the global save button is clicked.
     */
    public void handleSave() {
        // Implementation varies by form
    }

    /**
     * Resets form fields.
     */
    public void clearFields() {
        // Implementation varies by form
    }
}
