package org.example.ui.controller.Employee;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.stage.FileChooser;
import models.Request;
import models.RequestType;
import service.RequestService;
import service.RequestTypeService;
import service.CloudinaryService;
import org.example.model.Employee;
import org.example.ui.MainApp;
import java.io.File;

import java.sql.Timestamp;
import java.util.List;

/**
 * Contrôleur pour la gestion des demandes côté Employé
 */
public class EmployeeRequestController {

    // ═══════════════════════════════════════════════════════════════
    // FORMULAIRE DE NOUVELLE DEMANDE
    // ═══════════════════════════════════════════════════════════════

    @FXML private ComboBox<RequestType> requestTypeComboBox;
    @FXML private ComboBox<String> priorityComboBox;
    @FXML private TextField titleField;
    @FXML private TextArea descriptionArea;
    @FXML private Button submitButton;
    @FXML private Button clearButton;
    @FXML private Button btnAttachment;
    @FXML private Label lblAttachmentName;



    // ═══════════════════════════════════════════════════════════════
    // TABLEAU DES DEMANDES
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Request> requestsTable;
    @FXML private TableColumn<Request, Integer> idColumn;
    @FXML private TableColumn<Request, String> titleColumn;
    @FXML private TableColumn<Request, String> typeColumn;
    @FXML private TableColumn<Request, Request.Priority> priorityColumn;
    @FXML private TableColumn<Request, Request.Status> statusColumn;
    @FXML private TableColumn<Request, Timestamp> submittedDateColumn;
    @FXML private TableColumn<Request, Timestamp> reviewedDateColumn;

    @FXML private ComboBox<String> filterStatusComboBox;
    @FXML private TextField searchField;
    @FXML private Button viewDetailsButton;
    @FXML private Button cancelButton;
    @FXML private Button backButton;

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    @FXML private Label totalRequestsLabel;
    @FXML private Label pendingRequestsLabel;
    @FXML private Label approvedRequestsLabel;
    @FXML private Label rejectedRequestsLabel;
    @FXML private Button editButton;      // ✅ Ajoutez
    @FXML private Button deleteButton;

    // ═══════════════════════════════════════════════════════════════
    // SERVICES ET DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private final RequestService requestService = new RequestService();
    private final RequestTypeService requestTypeService = new RequestTypeService();
    private ObservableList<Request> requestsList = FXCollections.observableArrayList();
    private ObservableList<RequestType> requestTypesList = FXCollections.observableArrayList();
    private File selectedAttachment = null;
    private final CloudinaryService cloudinaryService = new CloudinaryService();

    private Employee currentEmployee;
    private int currentEmployeeId;

    // ═══════════════════════════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    public void initialize() {
        setupPriorityComboBox();
        setupTable();
        setupFilters();
        setupButtons();
    }

    /**
     * Initialiser avec les données de l'employé
     */
    public void initData(int employeeId, String employeeName, Employee employee) {
        this.currentEmployeeId = employeeId;
        this.currentEmployee = employee;
        System.out.println("🐛 DEBUG EmployeeRequestController:");
        System.out.println("   - employeeId reçu: " + employeeId);
        System.out.println("   - employee.getId(): " + employee.getId());
        loadRequestTypes();
        loadRequests();
        updateStatistics();
    }

    /**
     * Retourner au dashboard employé
     */
    @FXML
    private void handleBackToDashboard() {
        if (currentEmployee != null) {
            MainApp.showEmployeeDashboard(currentEmployee);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SETUP
    // ═══════════════════════════════════════════════════════════════

    private void setupPriorityComboBox() {
        priorityComboBox.setItems(FXCollections.observableArrayList(
                "low", "medium", "high"
        ));
        priorityComboBox.setValue("medium");
    }

    private void setupTable() {
        idColumn.setCellValueFactory(new PropertyValueFactory<>("id"));
        titleColumn.setCellValueFactory(new PropertyValueFactory<>("title"));
        typeColumn.setCellValueFactory(new PropertyValueFactory<>("requestTypeName"));
        priorityColumn.setCellValueFactory(new PropertyValueFactory<>("priority"));
        statusColumn.setCellValueFactory(new PropertyValueFactory<>("status"));
        submittedDateColumn.setCellValueFactory(new PropertyValueFactory<>("submittedDate"));
        reviewedDateColumn.setCellValueFactory(new PropertyValueFactory<>("reviewedDate"));

        // Style pour la colonne statut
        statusColumn.setCellFactory(col -> new TableCell<Request, Request.Status>() {
            @Override
            protected void updateItem(Request.Status status, boolean empty) {
                super.updateItem(status, empty);
                if (empty || status == null) {
                    setText(null);
                    setStyle("");
                } else {
                    setText(status.name());
                    switch (status) {
                        case pending -> setStyle("-fx-background-color: #fff3cd; -fx-text-fill: #856404; -fx-font-weight: bold;");
                        case approved -> setStyle("-fx-background-color: #d4edda; -fx-text-fill: #155724; -fx-font-weight: bold;");
                        case rejected -> setStyle("-fx-background-color: #f8d7da; -fx-text-fill: #721c24; -fx-font-weight: bold;");
                        case cancelled -> setStyle("-fx-background-color: #e2e3e5; -fx-text-fill: #383d41; -fx-font-weight: bold;");
                    }
                }
            }
        });

        // Style pour la colonne priorité
        priorityColumn.setCellFactory(col -> new TableCell<Request, Request.Priority>() {
            @Override
            protected void updateItem(Request.Priority priority, boolean empty) {
                super.updateItem(priority, empty);
                if (empty || priority == null) {
                    setText(null);
                    setStyle("");
                } else {
                    setText(priority.name());
                    switch (priority) {
                        case high -> setStyle("-fx-text-fill: #e74c3c; -fx-font-weight: bold;");
                        case medium -> setStyle("-fx-text-fill: #f39c12; -fx-font-weight: bold;");
                        case low -> setStyle("-fx-text-fill: #95a5a6;");
                    }
                }
            }
        });

        requestsTable.setItems(requestsList);

        // Listener pour la sélection
        requestsTable.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> updateButtonStates(newVal));
    }

    private void setupFilters() {
        filterStatusComboBox.setItems(FXCollections.observableArrayList(
                "Tous", "En attente", "Approuvées", "Rejetées", "Annulées"
        ));
        filterStatusComboBox.setValue("Tous");
        filterStatusComboBox.setOnAction(e -> applyFilters());
        searchField.textProperty().addListener((obs, oldVal, newVal) -> applyFilters());
    }

    private void setupButtons() {
        viewDetailsButton.setDisable(true);
        editButton.setDisable(true);        //
        deleteButton.setDisable(true);
        cancelButton.setDisable(true);
    }

    // ═══════════════════════════════════════════════════════════════
    // CHARGEMENT DES DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private void loadRequestTypes() {
        requestTypesList.clear();
        requestTypesList.addAll(requestTypeService.getAll());
        requestTypeComboBox.setItems(requestTypesList);
        if (!requestTypesList.isEmpty()) {
            requestTypeComboBox.getSelectionModel().selectFirst();
        }
    }

    private void loadRequests() {
        requestsList.clear();
        List<Request> allRequests = requestService.getByUserId(currentEmployeeId);
        requestsList.addAll(allRequests);
        applyFilters();
    }

    private void applyFilters() {
        String statusFilter = filterStatusComboBox.getValue();
        String search = searchField.getText().toLowerCase().trim();

        List<Request> allRequests = requestService.getByUserId(currentEmployeeId);
        List<Request> filtered = allRequests.stream()
                .filter(r -> {
                    if (statusFilter.equals("Tous")) return true;
                    return switch (statusFilter) {
                        case "En attente" -> r.getStatus() == Request.Status.pending;
                        case "Approuvées" -> r.getStatus() == Request.Status.approved;
                        case "Rejetées" -> r.getStatus() == Request.Status.rejected;
                        case "Annulées" -> r.getStatus() == Request.Status.cancelled;
                        default -> true;
                    };
                })
                .filter(r -> search.isEmpty() ||
                        r.getTitle().toLowerCase().contains(search) ||
                        (r.getRequestTypeName() != null && r.getRequestTypeName().toLowerCase().contains(search)))
                .toList();

        requestsList.setAll(filtered);
        updateStatistics();
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    private void updateStatistics() {
        int total = requestsList.size();
        long pending = requestsList.stream().filter(r -> r.getStatus() == Request.Status.pending).count();
        long approved = requestsList.stream().filter(r -> r.getStatus() == Request.Status.approved).count();
        long rejected = requestsList.stream().filter(r -> r.getStatus() == Request.Status.rejected).count();

        totalRequestsLabel.setText("Total : " + total);
        pendingRequestsLabel.setText("En attente : " + pending);
        approvedRequestsLabel.setText("Approuvées : " + approved);
        rejectedRequestsLabel.setText("Rejetées : " + rejected);
    }

    private void updateButtonStates(Request request) {
        if (request == null) {
            viewDetailsButton.setDisable(true);
            editButton.setDisable(true);
            deleteButton.setDisable(true);
            cancelButton.setDisable(true);
        } else {
            viewDetailsButton.setDisable(false);
            // ✅ Modifier et Supprimer : uniquement si pending
            boolean isPending = request.getStatus() == Request.Status.pending;
            editButton.setDisable(!isPending);
            deleteButton.setDisable(!isPending);
            // On peut annuler uniquement si la demande est en attente
            cancelButton.setDisable(request.getStatus() != Request.Status.pending);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - FORMULAIRE
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleSubmitRequest() {
        // Validation
        if (requestTypeComboBox.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un type de demande.");
            return;
        }
        if (titleField.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez saisir un titre.");
            return;
        }
        if (descriptionArea.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez saisir une description.");
            return;
        }

        // Création de la demande
        RequestType selectedType = requestTypeComboBox.getValue();
        String priorityStr = priorityComboBox.getValue();
        Request.Priority priority = Request.Priority.valueOf(priorityStr);

        Request newRequest = new Request(
                currentEmployeeId,
                selectedType.getId(),
                titleField.getText().trim(),
                descriptionArea.getText().trim(),
                priority
        );
        System.out.println("🐛 DEBUG AVANT requestService.add():");
        System.out.println("   - Request.getUserId(): " + newRequest.getUserId());
        System.out.println("   - currentEmployeeId: " + currentEmployeeId);
        // --- DÉBUT AJOUT CLOUDINARY ---
        if (selectedAttachment != null) {
            // 1. Afficher un indicateur de chargement
            Alert progress = new Alert(Alert.AlertType.INFORMATION);
            progress.setTitle("Upload");
            progress.setHeaderText(null);
            progress.setContentText("Envoi de la pièce jointe...");
            progress.show();

            // 2. Lancer l'upload dans un Thread pour ne pas bloquer l'interface
            new Thread(() -> {
                String url;
                String fileName = selectedAttachment.getName().toLowerCase();

                // 3. Choix du mode d'upload selon l'extension
                if (fileName.endsWith(".pdf")) {
                    url = cloudinaryService.uploadPDF(selectedAttachment.getAbsolutePath(), "requests/attachments");
                } else {
                    url = cloudinaryService.uploadImage(selectedAttachment.getAbsolutePath(), "requests/attachments");
                }

                // 4. Retour sur le thread UI pour finir le traitement
                javafx.application.Platform.runLater(() -> {
                    progress.close();
                    if (url != null) {
                        newRequest.setAttachmentUrl(url); // Assigner l'URL à l'objet

                        // --- TA LOGIQUE ORIGINALE (DANS LE CAS AVEC FICHIER) ---
                        boolean success = requestService.add(newRequest);
                        if (success) {
                            showAlert(Alert.AlertType.INFORMATION, "Succès", "Demande soumise avec succès!");
                            handleClearForm();
                            handleRemoveAttachment(); // Réinitialiser l'affichage du fichier
                            loadRequests();
                            updateStatistics();
                        } else {
                            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de soumettre la demande.");
                        }
                        // -----------------------------------------------------
                    } else {
                        showAlert(Alert.AlertType.ERROR, "Erreur", "L'upload a échoué.");
                    }
                });
            }).start();
        } else {

        // Soumission
        boolean success = requestService.add(newRequest);
        if (success) {
            showAlert(Alert.AlertType.INFORMATION, "Succès", 
                    "Votre demande a été soumise avec succès!\nElle sera traitée par le service RH.");
            handleClearForm();
            loadRequests();
            updateStatistics();
        } else {
            showAlert(Alert.AlertType.ERROR, "Erreur", 
                    "Impossible de soumettre la demande. Veuillez réessayer.");
        }
    }
    }

    @FXML
    private void handleClearForm() {
        if (!requestTypesList.isEmpty()) {
            requestTypeComboBox.getSelectionModel().selectFirst();
        }
        priorityComboBox.setValue("medium");
        titleField.clear();
        descriptionArea.clear();
        handleRemoveAttachment();
    }

    @FXML
    private void handleChooseAttachment() {
        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle("Choisir une pièce jointe");
        fileChooser.getExtensionFilters().addAll(
                new FileChooser.ExtensionFilter("Images", "*.jpg", "*.jpeg", "*.png", "*.gif"),
                new FileChooser.ExtensionFilter("PDF", "*.pdf"),
                new FileChooser.ExtensionFilter("Documents", "*.doc", "*.docx"),
                new FileChooser.ExtensionFilter("Tous les fichiers", "*.*")
        );

        selectedAttachment = fileChooser.showOpenDialog(btnAttachment.getScene().getWindow());

        if (selectedAttachment != null) {
            lblAttachmentName.setText("📎 " + selectedAttachment.getName());
            lblAttachmentName.setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;");
            System.out.println("✅ Fichier sélectionné : " + selectedAttachment.getName());
        }
    }

    @FXML
    private void handleRemoveAttachment() {
        selectedAttachment = null;
        lblAttachmentName.setText("Aucune pièce jointe");
        lblAttachmentName.setStyle("-fx-text-fill: #95a5a6;");
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - TABLEAU
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleRefresh() {
        loadRequests();
        updateStatistics();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Les demandes ont été actualisées.");
    }

    @FXML
    private void handleViewDetails() {
        Request selected = requestsTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande.");
            return;
        }

        StringBuilder details = new StringBuilder();
        details.append("═══ DEMANDE #").append(selected.getId()).append(" ═══\n\n");
        details.append("📋 Titre: ").append(selected.getTitle()).append("\n");
        details.append("🗂️ Type: ").append(selected.getRequestTypeName()).append("\n");
        details.append("⚡ Priorité: ").append(selected.getPriority()).append("\n");
        details.append("📊 Statut: ").append(selected.getStatus()).append("\n");
        details.append("📅 Date de soumission: ").append(selected.getSubmittedDate()).append("\n\n");
        
        details.append("📝 Description:\n");
        details.append(selected.getDescription()).append("\n\n");

        if (selected.getReviewedBy() != null && selected.getReviewedDate() != null) {
            details.append("─────────────────────────────\n");
            details.append("👤 Traité par: ").append(selected.getReviewerName() != null ? selected.getReviewerName() : "RH").append("\n");
            details.append("📅 Date de traitement: ").append(selected.getReviewedDate()).append("\n");
            
            if (selected.getReviewComment() != null && !selected.getReviewComment().isEmpty()) {
                details.append("\n💬 Commentaire RH:\n");
                details.append(selected.getReviewComment()).append("\n");
            }
        }

        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails de la demande");
        alert.setHeaderText("Demande #" + selected.getId() + " - " + selected.getTitle());
        alert.setContentText(details.toString());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(550);
        alert.getDialogPane().setPrefHeight(450);
        alert.showAndWait();
    }

    @FXML
    private void handleCancelRequest() {
        Request selected = requestsTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande.");
            return;
        }

        if (selected.getStatus() != Request.Status.pending) {
            showAlert(Alert.AlertType.WARNING, "Attention", 
                    "Seules les demandes en attente peuvent être annulées.");
            return;
        }

        // Confirmation
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Annuler la demande");
        confirm.setContentText("Êtes-vous sûr de vouloir annuler la demande \"" + selected.getTitle() + "\" ?");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                boolean success = requestService.updateStatus(
                        selected.getId(), 
                        Request.Status.cancelled, 
                        currentEmployeeId, 
                        "Annulée par l'employé"
                );
                if (success) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "La demande a été annulée.");
                    loadRequests();
                    updateStatistics();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'annuler la demande.");
                }
            }
        });
    }
    // ═══════════════════════════════════════════════════════════════
// HANDLER - MODIFIER UNE DEMANDE
// ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleEditRequest() {
        Request selected = requestsTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande.");
            return;
        }

        if (selected.getStatus() != Request.Status.pending) {
            showAlert(Alert.AlertType.WARNING, "Attention",
                    "Seules les demandes en attente peuvent être modifiées.");
            return;
        }

        // Remplir le formulaire avec les données existantes
        requestTypeComboBox.setValue(
                requestTypesList.stream()
                        .filter(rt -> rt.getId() == selected.getRequestTypeId())
                        .findFirst()
                        .orElse(null)
        );

        priorityComboBox.setValue(selected.getPriority().name());
        titleField.setText(selected.getTitle());
        descriptionArea.setText(selected.getDescription());

        // Changer le texte du bouton pour indiquer la modification
        submitButton.setText("💾 Mettre à jour");
        submitButton.setOnAction(e -> handleUpdateRequest(selected.getId()));

        showAlert(Alert.AlertType.INFORMATION, "Mode modification",
                "Modifiez les champs et cliquez sur 'Mettre à jour'.");
    }

    private void handleUpdateRequest(int requestId) {
        // Validation
        if (requestTypeComboBox.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un type de demande.");
            return;
        }
        if (titleField.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez saisir un titre.");
            return;
        }
        if (descriptionArea.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez saisir une description.");
            return;
        }

        // Récupérer la demande existante
        Request request = requestService.getById(requestId);
        if (request == null) {
            showAlert(Alert.AlertType.ERROR, "Erreur", "Demande introuvable.");
            return;
        }

        // Mettre à jour les champs modifiables
        request.setTitle(titleField.getText().trim());
        request.setDescription(descriptionArea.getText().trim());
        request.setPriority(Request.Priority.valueOf(priorityComboBox.getValue()));

        // Sauvegarder
        boolean success = requestService.update(request);
        if (success) {
            showAlert(Alert.AlertType.INFORMATION, "Succès", "La demande a été mise à jour avec succès !");
            handleClearForm();
            loadRequests();
            updateStatistics();

            // Remettre le bouton en mode "Soumettre"
            submitButton.setText("✅ Soumettre la demande");
            submitButton.setOnAction(e -> handleSubmitRequest());
        } else {
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de mettre à jour la demande.");
        }
    }

// ═══════════════════════════════════════════════════════════════
// HANDLER - SUPPRIMER UNE DEMANDE
// ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleDeleteRequest() {
        Request selected = requestsTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande.");
            return;
        }

        if (selected.getStatus() != Request.Status.pending) {
            showAlert(Alert.AlertType.WARNING, "Attention",
                    "Seules les demandes en attente peuvent être supprimées.");
            return;
        }

        // Confirmation
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer la demande");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer définitivement la demande \""
                + selected.getTitle() + "\" ?\n\nCette action est irréversible.");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                boolean success = requestService.delete(selected.getId());
                if (success) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès",
                            "La demande a été supprimée avec succès.");
                    loadRequests();
                    updateStatistics();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur",
                            "Impossible de supprimer la demande.");
                }
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // UTILITAIRES
    // ═══════════════════════════════════════════════════════════════

    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}
