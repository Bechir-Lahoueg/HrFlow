package org.example.ui.controller.Rh;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.StackPane; // MANQUANT dans ta liste
import javafx.scene.layout.VBox;      // MANQUANT (tu l'utilises pour tes panels)
import javafx.scene.layout.HBox;
import javafx.geometry.Insets;
import models.*;
import service.*;
import org.example.model.User;
import org.example.ui.MainApp;

import java.sql.Timestamp;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

/**
 * Contrôleur unifié pour la gestion des relations employés côté RH
 * Gère les demandes, types de demandes, feedbacks employés, feedbacks formation et notifications
 */
public class RHRelationEmployeesController {

    // ═══════════════════════════════════════════════════════════════
    // SECTION: DEMANDES
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Request> tableRequests;
    @FXML private TableColumn<Request, Integer> colRequestId;
    @FXML private TableColumn<Request, String> colRequestEmployee;  // ✅ AJOUTEZ CETTE LIGNE
    @FXML private TableColumn<Request, String> colRequestTitle;
    @FXML private TableColumn<Request, String> colRequestType;
    @FXML private TableColumn<Request, Request.Priority> colRequestPriority;
    @FXML private TableColumn<Request, Request.Status> colRequestStatus;
    @FXML private TableColumn<Request, Timestamp> colRequestDate;

    @FXML private ComboBox<String> filterStatusRequests;
    @FXML private TextField searchFieldRequests;
    @FXML private Button btnApproveRequest;
    @FXML private Button btnRejectRequest;
    @FXML private Button btnViewRequestDetails;

    @FXML private Label lblTotalRequests;
    @FXML private Label lblPendingRequests;
    @FXML private Label lblApprovedRequests;
    @FXML private Label lblRejectedRequests;
    @FXML private Button btnTabRequests, btnTabConfig, btnTabFeedbacks, btnTabAlerts;
    @FXML private VBox requestsPanel, configPanel, feedbacksPanel, alertsPanel;
    @FXML private StackPane contentStackPane;

    // ═══════════════════════════════════════════════════════════════
    // SECTION: TYPES DE DEMANDES
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<RequestType> tableRequestTypes;
    @FXML private TableColumn<RequestType, Integer> colTypeId;
    @FXML private TableColumn<RequestType, String> colTypeName;
    @FXML private TableColumn<RequestType, String> colTypeDescription;
    @FXML private TableColumn<RequestType, Boolean> colTypeApproval;
    @FXML private TableColumn<RequestType, Timestamp> colTypeCreated;

    @FXML private Button btnAddRequestType;
    @FXML private Button btnEditRequestType;
    @FXML private Button btnDeleteRequestType;

    // ═══════════════════════════════════════════════════════════════
    // SECTION: FEEDBACKS EMPLOYÉS
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Feedback> tableFeedbacks;
    @FXML private TableColumn<Feedback, Integer> colFeedbackId;
    @FXML private TableColumn<Feedback, String> colFeedbackFrom;
    @FXML private TableColumn<Feedback, String> colFeedbackTo;
    @FXML private TableColumn<Feedback, String> colFeedbackType;
    @FXML private TableColumn<Feedback, Integer> colFeedbackRating;
    @FXML private TableColumn<Feedback, String> colFeedbackComment;
    @FXML private TableColumn<Feedback, Timestamp> colFeedbackDate;

    @FXML private ComboBox<String> filterTypeFeedback;
    @FXML private TextField searchFieldFeedbacks;
    @FXML private Button btnViewFeedbackDetails;

    @FXML private Label lblTotalFeedbacks;
    @FXML private Label lblAverageRating;

    // ═══════════════════════════════════════════════════════════════
    // SECTION: FEEDBACKS FORMATION
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<FeedbackFormation> tableFF;
    @FXML private TableColumn<FeedbackFormation, Integer> colFFId;
    @FXML private TableColumn<FeedbackFormation, String> colFFUser;
    @FXML private TableColumn<FeedbackFormation, String> colFFFormation;
    @FXML private TableColumn<FeedbackFormation, String> colFFSession;
    @FXML private TableColumn<FeedbackFormation, String> colFFRating;
    @FXML private TableColumn<FeedbackFormation, String> colFFRecommande;
    @FXML private TableColumn<FeedbackFormation, String> colFFDate;

    @FXML private ComboBox<String> filterFormationFF;
    @FXML private Button btnViewFFDetails;
    @FXML private Label lblTotalFF;
    @FXML private Label lblAvgRatingFF;
    @FXML private Label lblRecommandationFF;

    // ═══════════════════════════════════════════════════════════════
    // SECTION: NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Notification> tableNotifications;
    @FXML private TableColumn<Notification, Integer> colNotificationId;
    @FXML private TableColumn<Notification, String> colNotificationType;
    @FXML private TableColumn<Notification, String> colNotificationTitle;
    @FXML private TableColumn<Notification, String> colNotificationMessage;
    @FXML private TableColumn<Notification, String> colNotificationRead;
    @FXML private TableColumn<Notification, Timestamp> colNotificationDate;

    @FXML private ComboBox<String> filterReadNotifications;
    @FXML private TextField searchFieldNotifications;
    @FXML private Button btnMarkAsRead;
    @FXML private Button btnDeleteNotification;

    @FXML private Label lblTotalNotifications;
    @FXML private Label lblUnreadNotifications;

    // ═══════════════════════════════════════════════════════════════
    // SERVICES & DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private final RequestService requestService = new RequestService();
    private final RequestTypeService requestTypeService = new RequestTypeService();
    private final FeedbackService feedbackService = new FeedbackService();
    private final FeedbackFormationService ffService = new FeedbackFormationService();
    private final NotificationService notificationService = new NotificationService();

    private ObservableList<Request> requestsList = FXCollections.observableArrayList();
    private ObservableList<RequestType> requestTypesList = FXCollections.observableArrayList();
    private ObservableList<Feedback> feedbacksList = FXCollections.observableArrayList();
    private ObservableList<FeedbackFormation> ffList = FXCollections.observableArrayList();
    private ObservableList<Notification> notificationsList = FXCollections.observableArrayList();

    private User currentUser;
    private int rhId;

    // ═══════════════════════════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    public void initialize() {
        setupRequestsTable();
        setupRequestTypesTable();
        setupFeedbacksTable();
        setupFFTable();
        setupNotificationsTable();
        setupFilters();
        disableAllActionButtons();
        // Gestion de la navigation
        btnTabRequests.setOnAction(e -> switchTab(requestsPanel, btnTabRequests));
        btnTabConfig.setOnAction(e -> switchTab(configPanel, btnTabConfig));
        btnTabFeedbacks.setOnAction(e -> switchTab(feedbacksPanel, btnTabFeedbacks));
        btnTabAlerts.setOnAction(e -> switchTab(alertsPanel, btnTabAlerts));

        // Par défaut, afficher le premier onglet
        switchTab(requestsPanel, btnTabRequests);
    }

    public void setCurrentUser(User user) {
        this.currentUser = user;
        this.rhId = user.getId();
        loadAllData();
    }

    @FXML
    private void handleBackToDashboard() {
        if (currentUser != null) {
            MainApp.showRHDashboard(currentUser);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SETUP TABLES
    // ═══════════════════════════════════════════════════════════════

    private void setupRequestsTable() {
        colRequestId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colRequestEmployee.setCellValueFactory(new PropertyValueFactory<>("employeeName"));
        colRequestTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colRequestType.setCellValueFactory(new PropertyValueFactory<>("requestTypeName"));
        colRequestPriority.setCellValueFactory(new PropertyValueFactory<>("priority"));
        colRequestStatus.setCellValueFactory(new PropertyValueFactory<>("status"));
        colRequestDate.setCellValueFactory(new PropertyValueFactory<>("submittedDate"));

        // Style pour la colonne statut
        colRequestStatus.setCellFactory(col -> new TableCell<Request, Request.Status>() {
            @Override
            protected void updateItem(Request.Status status, boolean empty) {
                super.updateItem(status, empty);
                if (empty || status == null) {
                    setText(null);
                    setStyle("");
                } else {
                    setText(status.name());
                    switch (status) {
                        case pending -> setStyle("-fx-text-fill: #f39c12; -fx-font-weight: bold;");
                        case approved -> setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;");
                        case rejected -> setStyle("-fx-text-fill: #e74c3c; -fx-font-weight: bold;");
                        case cancelled -> setStyle("-fx-text-fill: #95a5a6; -fx-font-weight: bold;");
                    }
                }
            }
        });

        tableRequests.setItems(requestsList);
        tableRequests.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> updateRequestButtonStates(newVal));
    }

    private void setupRequestTypesTable() {
        colTypeId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colTypeName.setCellValueFactory(new PropertyValueFactory<>("name"));
        colTypeDescription.setCellValueFactory(new PropertyValueFactory<>("description"));
        colTypeApproval.setCellValueFactory(new PropertyValueFactory<>("requiresApproval"));
        colTypeCreated.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        tableRequestTypes.setItems(requestTypesList);
        tableRequestTypes.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> updateRequestTypeButtonStates(newVal));
    }

    private void setupFeedbacksTable() {
        colFeedbackId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colFeedbackFrom.setCellValueFactory(new PropertyValueFactory<>("fromUsername"));
        colFeedbackTo.setCellValueFactory(new PropertyValueFactory<>("toUsername"));
        colFeedbackType.setCellValueFactory(new PropertyValueFactory<>("feedbackType"));
        colFeedbackRating.setCellValueFactory(new PropertyValueFactory<>("rating"));
        colFeedbackComment.setCellValueFactory(new PropertyValueFactory<>("comment"));
        colFeedbackDate.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        tableFeedbacks.setItems(feedbacksList);
        tableFeedbacks.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> updateFeedbackButtonStates(newVal));
    }

    private void setupFFTable() {
        colFFId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colFFUser.setCellValueFactory(new PropertyValueFactory<>("username"));
        colFFFormation.setCellValueFactory(new PropertyValueFactory<>("formationName"));
        colFFSession.setCellValueFactory(new PropertyValueFactory<>("sessionName"));
        colFFRating.setCellValueFactory(new PropertyValueFactory<>("ratingStars"));
        colFFRecommande.setCellValueFactory(new PropertyValueFactory<>("recommandeLabel"));
        colFFDate.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        colFFRecommande.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String val, boolean empty) {
                super.updateItem(val, empty);
                if (empty || val == null) { setText(null); setStyle(""); return; }
                setText(val);
                setStyle(val.contains("Oui")
                        ? "-fx-text-fill: #27ae60; -fx-font-weight: bold;"
                        : "-fx-text-fill: #e74c3c; -fx-font-weight: bold;");
            }
        });

        tableFF.setItems(ffList);
        tableFF.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> {
                    if (btnViewFFDetails != null) {
                        btnViewFFDetails.setDisable(newVal == null);
                    }
                });
    }

    private void setupNotificationsTable() {
        colNotificationId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colNotificationType.setCellValueFactory(new PropertyValueFactory<>("type"));
        colNotificationTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colNotificationMessage.setCellValueFactory(new PropertyValueFactory<>("message"));
        colNotificationRead.setCellValueFactory(new PropertyValueFactory<>("readStatus"));
        colNotificationDate.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        tableNotifications.setItems(notificationsList);
        tableNotifications.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> updateNotificationButtonStates(newVal));
    }

    private void setupFilters() {
        // Filtres demandes
        filterStatusRequests.setItems(FXCollections.observableArrayList(
                "Tous", "pending", "approved", "rejected", "cancelled"));
        filterStatusRequests.setValue("Tous");
        filterStatusRequests.setOnAction(e -> applyRequestFilters());
        searchFieldRequests.textProperty().addListener((obs, old, newVal) -> applyRequestFilters());

        // Filtres feedbacks employés
        filterTypeFeedback.setItems(FXCollections.observableArrayList(
                "Tous", "performance", "behavior", "collaboration", "other"));
        filterTypeFeedback.setValue("Tous");
        filterTypeFeedback.setOnAction(e -> applyFeedbackFilters());
        searchFieldFeedbacks.textProperty().addListener((obs, old, newVal) -> applyFeedbackFilters());

        // Filtres feedbacks formation
        if (filterFormationFF != null) {
            filterFormationFF.setValue("Toutes");
            filterFormationFF.setOnAction(e -> applyFFFilters());
        }

        // Filtres notifications
        filterReadNotifications.setItems(FXCollections.observableArrayList(
                "Toutes", "Non lues", "Lues"));
        filterReadNotifications.setValue("Toutes");
        filterReadNotifications.setOnAction(e -> applyNotificationFilters());
        searchFieldNotifications.textProperty().addListener((obs, old, newVal) -> applyNotificationFilters());
    }

    private void disableAllActionButtons() {
        btnApproveRequest.setDisable(true);
        btnRejectRequest.setDisable(true);
        btnViewRequestDetails.setDisable(true);
        btnEditRequestType.setDisable(true);
        btnDeleteRequestType.setDisable(true);
        btnViewFeedbackDetails.setDisable(true);
        if (btnViewFFDetails != null) {
            btnViewFFDetails.setDisable(true);
        }
        btnMarkAsRead.setDisable(true);
        btnDeleteNotification.setDisable(true);
    }

    private void switchTab(VBox selectedPanel, Button selectedButton) {
        // 1. Masquer tous les panneaux
        requestsPanel.setVisible(false);
        configPanel.setVisible(false);
        feedbacksPanel.setVisible(false);
        alertsPanel.setVisible(false);

        // 2. Afficher le panneau sélectionné
        selectedPanel.setVisible(true);

        // 3. Réinitialiser le style de tous les boutons (gris/transparent)
        String idleStyle = "-fx-background-color: transparent; -fx-text-fill: #a0aec0; -fx-border-color: transparent; -fx-padding: 15 25; -fx-cursor: hand; -fx-font-weight: bold;";
        btnTabRequests.setStyle(idleStyle);
        btnTabConfig.setStyle(idleStyle);
        btnTabFeedbacks.setStyle(idleStyle);
        btnTabAlerts.setStyle(idleStyle);

        // 4. Appliquer le style "Actif" au bouton cliqué (Violet avec bordure)
        selectedButton.setStyle("-fx-background-color: transparent; -fx-text-fill: #667eea; -fx-padding: 15 25; -fx-cursor: hand; -fx-font-weight: bold; -fx-font-size: 13px; -fx-border-width: 0 0 3 0; -fx-border-color: #667eea;");
    }

    // ═══════════════════════════════════════════════════════════════
    // CHARGEMENT DES DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private void loadAllData() {
        loadRequests();
        loadRequestTypes();
        loadFeedbacks();
        loadFeedbacksFormation();
        loadNotifications();

    }

    private void loadRequests() {
        requestsList.clear();
        requestsList.addAll(requestService.getByRhId(rhId));
        applyRequestFilters();
        updateRequestStats();
    }

    private void loadRequestTypes() {
        requestTypesList.clear();
        requestTypesList.addAll(requestTypeService.getAll());
    }

    private void loadFeedbacks() {
        // 1. Récupérer les données depuis le service avec le rhId
        List<Feedback> list = feedbackService.getByRhId(this.rhId);

        // 2. Mettre à jour la liste observable (cela rafraîchit le tableau automatiquement)
        feedbacksList.setAll(list);

        // 3. Mettre à jour les statistiques sur l'interface
        lblTotalFeedbacks.setText("Total : " + feedbacksList.size());

        // Calcul de la moyenne
        double avg = feedbacksList.stream()
                .mapToInt(Feedback::getRating)
                .average()
                .orElse(0.0);
        lblAverageRating.setText(String.format("Note moyenne : %.1f ⭐", avg));
    }

    private void loadFeedbacksFormation() {
        ffList.clear();
        List<FeedbackFormation> all = ffService.getByRhId(rhId);
        ffList.addAll(all);

        // Populate filter dropdown
        if (filterFormationFF != null) {
            List<String> formationNames = new ArrayList<>();
            formationNames.add("Toutes");
            all.stream()
                    .map(FeedbackFormation::getFormationName)
                    .filter(name -> name != null && !name.isEmpty())
                    .distinct()
                    .forEach(formationNames::add);
            filterFormationFF.setItems(FXCollections.observableArrayList(formationNames));
            if (filterFormationFF.getValue() == null) {
                filterFormationFF.setValue("Toutes");
            }
        }

        applyFFFilters();
        updateFFStats();
    }

    private void loadNotifications() {
        notificationsList.clear();
        notificationsList.addAll(notificationService.getByUserId(rhId));
        applyNotificationFilters();
        updateNotificationStats();
    }

    // ═══════════════════════════════════════════════════════════════
    // FILTRES
    // ═══════════════════════════════════════════════════════════════

    private void applyRequestFilters() {
        String statusFilter = filterStatusRequests.getValue();
        String search = searchFieldRequests.getText().toLowerCase().trim();

        List<Request> allRequests = requestService.getByRhId(rhId);
        List<Request> filtered = allRequests.stream()
                .filter(r -> statusFilter.equals("Tous") || r.getStatus().name().equals(statusFilter))
                .filter(r -> search.isEmpty() ||
                        r.getTitle().toLowerCase().contains(search) ||
                        (r.getRequestTypeName() != null && r.getRequestTypeName().toLowerCase().contains(search)))
                .toList();

        requestsList.setAll(filtered);
        updateRequestStats();
    }

    private void applyFeedbackFilters() {
        String typeFilter = filterTypeFeedback.getValue();
        String search = searchFieldFeedbacks.getText().toLowerCase().trim();

        List<Feedback> allFeedbacks = feedbackService.getByRhId(rhId);
        List<Feedback> filtered = allFeedbacks.stream()
                .filter(f -> typeFilter.equals("Tous") || f.getFeedbackType().name().equals(typeFilter))
                .filter(f -> search.isEmpty() ||
                        (f.getFromUsername() != null && f.getFromUsername().toLowerCase().contains(search)) ||
                        (f.getToUsername() != null && f.getToUsername().toLowerCase().contains(search)))
                .toList();

        feedbacksList.setAll(filtered);
        updateFeedbackStats();
    }

    private void applyFFFilters() {
        if (filterFormationFF == null) return;

        String filter = filterFormationFF.getValue();
        List<FeedbackFormation> all = ffService.getByRhId(rhId);

        if (filter != null && !filter.equals("Toutes")) {
            all = all.stream()
                    .filter(ff -> ff.getFormationName() != null && ff.getFormationName().equals(filter))
                    .toList();
        }

        ffList.setAll(all);
        updateFFStats();
    }

    private void applyNotificationFilters() {
        String readFilter = filterReadNotifications.getValue();
        String search = searchFieldNotifications.getText().toLowerCase().trim();

        List<Notification> allNotifs = notificationService.getByUserId(rhId);
        List<Notification> filtered = allNotifs.stream()
                .filter(n -> {
                    if (readFilter.equals("Non lues")) return !n.isRead();
                    if (readFilter.equals("Lues")) return n.isRead();
                    return true;
                })
                .filter(n -> search.isEmpty() ||
                        n.getTitle().toLowerCase().contains(search) ||
                        n.getMessage().toLowerCase().contains(search))
                .toList();

        notificationsList.setAll(filtered);
        updateNotificationStats();
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    private void updateRequestStats() {
        int total = requestsList.size();
        long pending = requestsList.stream().filter(r -> r.getStatus() == Request.Status.pending).count();
        long approved = requestsList.stream().filter(r -> r.getStatus() == Request.Status.approved).count();
        long rejected = requestsList.stream().filter(r -> r.getStatus() == Request.Status.rejected).count();

        lblTotalRequests.setText("Total : " + total);
        lblPendingRequests.setText("En attente : " + pending);
        lblApprovedRequests.setText("Approuvées : " + approved);
        lblRejectedRequests.setText("Rejetées : " + rejected);
    }

    private void updateFeedbackStats() {
        int total = feedbacksList.size();
        double avgRating = feedbacksList.stream()
                .mapToInt(Feedback::getRating)
                .average()
                .orElse(0.0);

        lblTotalFeedbacks.setText("Total : " + total);
        lblAverageRating.setText(String.format("Note moyenne : %.1f/5", avgRating));
    }

    private void updateFFStats() {
        if (lblTotalFF == null || lblAvgRatingFF == null || lblRecommandationFF == null) return;

        int total = ffList.size();
        double avg = ffList.stream().mapToInt(FeedbackFormation::getRating).average().orElse(0);
        long rec = ffList.stream().filter(FeedbackFormation::isRecommande).count();
        double rate = ffList.isEmpty() ? 0 : rec * 100.0 / ffList.size();

        lblTotalFF.setText("Total : " + total + " avis");
        lblAvgRatingFF.setText(String.format("Note moyenne : %.1f ⭐", avg));
        lblRecommandationFF.setText(String.format("Recommandée : %.0f%%", rate));
    }

    private void updateNotificationStats() {
        int total = notificationsList.size();
        long unread = notificationsList.stream().filter(n -> !n.isRead()).count();

        lblTotalNotifications.setText("Total : " + total);
        lblUnreadNotifications.setText("Non lues : " + unread);
    }

    // ═══════════════════════════════════════════════════════════════
    // GESTION DES BOUTONS
    // ═══════════════════════════════════════════════════════════════

    private void updateRequestButtonStates(Request request) {
        if (request == null) {
            btnApproveRequest.setDisable(true);
            btnRejectRequest.setDisable(true);
            btnViewRequestDetails.setDisable(true);
        } else {
            boolean isPending = request.getStatus() == Request.Status.pending;
            btnApproveRequest.setDisable(!isPending);
            btnRejectRequest.setDisable(!isPending);
            btnViewRequestDetails.setDisable(false);
        }
    }

    private void updateRequestTypeButtonStates(RequestType type) {
        boolean hasSelection = type != null;
        btnEditRequestType.setDisable(!hasSelection);
        btnDeleteRequestType.setDisable(!hasSelection);
    }

    private void updateFeedbackButtonStates(Feedback feedback) {
        btnViewFeedbackDetails.setDisable(feedback == null);
    }

    private void updateNotificationButtonStates(Notification notif) {
        btnMarkAsRead.setDisable(notif == null);
        btnDeleteNotification.setDisable(notif == null);
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - DEMANDES
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleRefreshRequests() {
        loadRequests();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Les demandes ont été actualisées.");
    }

    @FXML
    private void handleApproveRequest() {
        Request selected = tableRequests.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande.");
            return;
        }

        TextInputDialog dialog = new TextInputDialog();
        dialog.setTitle("Approuver la demande");
        dialog.setHeaderText("Approuver la demande #" + selected.getId());
        dialog.setContentText("Commentaire (optionnel):");

        dialog.showAndWait().ifPresent(comment -> {
            boolean success = requestService.updateStatus(
                    selected.getId(),
                    Request.Status.approved,
                    rhId,
                    comment
            );
            if (success) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "La demande a été approuvée!");
                loadRequests();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'approuver la demande.");
            }
        });
    }

    @FXML
    private void handleRejectRequest() {
        Request selected = tableRequests.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une demande.");
            return;
        }

        TextInputDialog dialog = new TextInputDialog();
        dialog.setTitle("Rejeter la demande");
        dialog.setHeaderText("Rejeter la demande #" + selected.getId());
        dialog.setContentText("Raison du rejet:");

        dialog.showAndWait().ifPresent(reason -> {
            if (reason.trim().isEmpty()) {
                showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez indiquer une raison pour le rejet.");
                return;
            }
            boolean success = requestService.updateStatus(
                    selected.getId(),
                    Request.Status.rejected,
                    rhId,
                    reason
            );
            if (success) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "La demande a été rejetée.");
                loadRequests();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de rejeter la demande.");
            }
        });
    }

    @FXML
    private void handleViewRequestDetails() {
        Request selected = tableRequests.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        StringBuilder details = new StringBuilder();
        details.append("=== DEMANDE #").append(selected.getId()).append(" ===\n\n");
        details.append("Titre: ").append(selected.getTitle()).append("\n");
        details.append("Type: ").append(selected.getRequestTypeName()).append("\n");
        details.append("Priorité: ").append(selected.getPriority()).append("\n");
        details.append("Statut: ").append(selected.getStatus()).append("\n");
        details.append("Date de soumission: ").append(selected.getSubmittedDate()).append("\n\n");
        details.append("Description:\n").append(selected.getDescription()).append("\n\n");

        if (selected.getReviewComment() != null && !selected.getReviewComment().isEmpty()) {
            details.append("Commentaire RH:\n").append(selected.getReviewComment()).append("\n");
        }

        // --- PRÉPARATION DU DIALOGUE ---
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails de la demande");
        alert.setHeaderText("Demande #" + selected.getId());

        // Création d'un conteneur VBox pour pouvoir mettre du texte ET un lien
        javafx.scene.layout.VBox content = new javafx.scene.layout.VBox(10);
        javafx.scene.control.Label mainText = new javafx.scene.control.Label(details.toString());
        content.getChildren().add(mainText);

        // ✅ AJOUT DE LA PIÈCE JOINTE
        if (selected.getAttachmentUrl() != null && !selected.getAttachmentUrl().isEmpty()) {
            javafx.scene.control.Hyperlink link = new javafx.scene.control.Hyperlink("📎 Voir la pièce jointe (Cloudinary)");
            link.setStyle("-fx-text-fill: #3498db; -fx-font-weight: bold;");

            link.setOnAction(e -> {
                try {
                    // Ouvre l'URL dans le navigateur par défaut
                    java.awt.Desktop.getDesktop().browse(new java.net.URI(selected.getAttachmentUrl()));
                } catch (Exception ex) {
                    System.err.println("❌ Erreur lors de l'ouverture du lien : " + ex.getMessage());
                }
            });

            content.getChildren().add(link);
        }

        // On remplace le texte par défaut par notre VBox personnalisée
        alert.getDialogPane().setContent(content);
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(500);
        alert.showAndWait();
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - TYPES DE DEMANDES
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleRefreshRequestTypes() {
        loadRequestTypes();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Les types ont été actualisés.");
    }

    @FXML
    private void handleAddRequestType() {
        Dialog<RequestType> dialog = new Dialog<>();
        dialog.setTitle("Ajouter un type de demande");
        dialog.setHeaderText("Nouveau type de demande");

        ButtonType addButtonType = new ButtonType("Ajouter", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(addButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        TextField nameField = new TextField();
        nameField.setPromptText("Nom");
        TextArea descField = new TextArea();
        descField.setPromptText("Description");
        descField.setPrefRowCount(3);
        CheckBox approvalCheckbox = new CheckBox("Nécessite une approbation");
        approvalCheckbox.setSelected(true);

        grid.add(new Label("Nom:"), 0, 0);
        grid.add(nameField, 1, 0);
        grid.add(new Label("Description:"), 0, 1);
        grid.add(descField, 1, 1);
        grid.add(approvalCheckbox, 1, 2);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == addButtonType) {
                return new RequestType(
                        nameField.getText(),
                        descField.getText(),
                        approvalCheckbox.isSelected()
                );
            }
            return null;
        });

        Optional<RequestType> result = dialog.showAndWait();
        result.ifPresent(type -> {
            if (requestTypeService.add(type)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Type ajouté avec succès!");
                loadRequestTypes();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'ajouter le type.");
            }
        });
    }

    @FXML
    private void handleEditRequestType() {
        RequestType selected = tableRequestTypes.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un type.");
            return;
        }

        Dialog<RequestType> dialog = new Dialog<>();
        dialog.setTitle("Modifier le type de demande");
        dialog.setHeaderText("Modifier: " + selected.getName());

        ButtonType saveButtonType = new ButtonType("Sauvegarder", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        TextField nameField = new TextField(selected.getName());
        TextArea descField = new TextArea(selected.getDescription());
        descField.setPrefRowCount(3);
        CheckBox approvalCheckbox = new CheckBox("Nécessite une approbation");
        approvalCheckbox.setSelected(selected.isRequiresApproval());

        grid.add(new Label("Nom:"), 0, 0);
        grid.add(nameField, 1, 0);
        grid.add(new Label("Description:"), 0, 1);
        grid.add(descField, 1, 1);
        grid.add(approvalCheckbox, 1, 2);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == saveButtonType) {
                selected.setName(nameField.getText());
                selected.setDescription(descField.getText());
                selected.setRequiresApproval(approvalCheckbox.isSelected());
                return selected;
            }
            return null;
        });

        Optional<RequestType> result = dialog.showAndWait();
        result.ifPresent(type -> {
            if (requestTypeService.update(type)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Type modifié avec succès!");
                loadRequestTypes();
            } else {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de modifier le type.");
            }
        });
    }

    @FXML
    private void handleDeleteRequestType() {
        RequestType selected = tableRequestTypes.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner un type.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer le type de demande");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer '" + selected.getName() + "' ?");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                if (requestTypeService.delete(selected.getId())) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "Type supprimé avec succès!");
                    loadRequestTypes();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de supprimer le type.");
                }
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - FEEDBACKS EMPLOYÉS
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleRefreshFeedbacks() {
        loadFeedbacks();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Les feedbacks ont été actualisés.");
    }

    @FXML
    private void handleViewFeedbackDetails() {
        Feedback selected = tableFeedbacks.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        StringBuilder details = new StringBuilder();
        details.append("=== FEEDBACK #").append(selected.getId()).append(" ===\n\n");
        details.append("De: ").append(selected.isAnonymous() ? "Anonyme" : selected.getFromUsername()).append("\n");
        details.append("À: ").append(selected.getToUsername()).append("\n");
        details.append("Type: ").append(selected.getFeedbackType()).append("\n");
        details.append("Note: ").append(selected.getRatingStars()).append(" (").append(selected.getRating()).append("/5)\n");
        details.append("Date: ").append(selected.getCreatedAt()).append("\n\n");
        details.append("Commentaire:\n").append(selected.getComment()).append("\n");

        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails du feedback");
        alert.setHeaderText("Feedback #" + selected.getId());
        alert.setContentText(details.toString());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(500);
        alert.showAndWait();
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - FEEDBACKS FORMATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleRefreshFF() {
        loadFeedbacksFormation();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Les feedbacks formation ont été actualisés.");
    }

    @FXML
    private void handleViewFFDetails() {
        FeedbackFormation selected = tableFF.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        StringBuilder details = new StringBuilder();
        details.append("=== FEEDBACK FORMATION #").append(selected.getId()).append(" ===\n\n");
        details.append("Employé: ").append(selected.getUsername()).append("\n");
        details.append("Formation: ").append(selected.getFormationName()).append("\n");
        if (selected.getSessionName() != null) {
            details.append("Session: ").append(selected.getSessionName()).append("\n");
        }
        details.append("Note: ").append(selected.getRatingStars()).append(" (").append(selected.getRating()).append("/5)\n");
        details.append("Recommandé: ").append(selected.isRecommande() ? "✅ Oui" : "❌ Non").append("\n");
        details.append("Date: ").append(selected.getCreatedAt()).append("\n\n");

        if (selected.getContenuComment() != null && !selected.getContenuComment().isEmpty()) {
            details.append("📚 Contenu:\n").append(selected.getContenuComment()).append("\n\n");
        }
        if (selected.getFormateurComment() != null && !selected.getFormateurComment().isEmpty()) {
            details.append("👨‍🏫 Formateur:\n").append(selected.getFormateurComment()).append("\n\n");
        }
        if (selected.getOrganisationComment() != null && !selected.getOrganisationComment().isEmpty()) {
            details.append("🗂️ Organisation:\n").append(selected.getOrganisationComment()).append("\n");
        }

        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails du feedback formation");
        alert.setHeaderText("Feedback Formation #" + selected.getId());
        alert.setContentText(details.toString());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(550);
        alert.getDialogPane().setPrefHeight(450);
        alert.showAndWait();
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleRefreshNotifications() {
        loadNotifications();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Les notifications ont été actualisées.");
    }

    @FXML
    private void handleMarkAsRead() {
        Notification selected = tableNotifications.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une notification.");
            return;
        }

        if (notificationService.markAsRead(selected.getId())) {
            showAlert(Alert.AlertType.INFORMATION, "Succès", "Notification marquée comme lue.");
            loadNotifications();
        } else {
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de marquer la notification.");
        }
    }

    @FXML
    private void handleMarkAllAsRead() {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Marquer toutes comme lues");
        confirm.setContentText("Êtes-vous sûr de vouloir marquer toutes les notifications comme lues ?");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                if (notificationService.markAllAsRead(rhId)) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "Toutes les notifications ont été marquées comme lues.");
                    loadNotifications();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de marquer les notifications.");
                }
            }
        });
    }

    @FXML
    private void handleDeleteNotification() {
        Notification selected = tableNotifications.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une notification.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer la notification");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer cette notification ?");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                if (notificationService.delete(selected.getId())) {
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "Notification supprimée.");
                    loadNotifications();
                } else {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de supprimer la notification.");
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