package org.example.ui.controller.Rh;

/**
 * Interface for shell controllers that manage navigation and modal
 * functionality
 */
public interface ShellController {

    /**
     * Shows a modal dialog with specified title, subtitle, and content
     */
    void showModal(String title, String subtitle, javafx.scene.Parent content);

    /**
     * Shows a modal dialog and registers the form controller for Save/validation
     */
    default void showModal(String title, String subtitle, javafx.scene.Parent content,
            RHBaseController formController) {
        showModal(title, subtitle, content);
    }

    /**
     * Hides the currently shown modal dialog
     */
    void hideModal();

    /**
     * Sets an error message in the form
     */
    void setFormError(String error);

    /**
     * Clears any form error messages
     */
    void clearFormError();

    /**
     * Handles modal close event
     */
    default void handleCloseModal() {
        hideModal();
    }
}
