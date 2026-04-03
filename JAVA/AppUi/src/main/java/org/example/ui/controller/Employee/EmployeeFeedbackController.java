package org.example.ui.controller.Employee;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.StackPane;
import javafx.scene.control.Button;
import javafx.scene.layout.VBox;
import javafx.geometry.Insets;
import models.Feedback;
import models.FeedbackFormation;
import service.FeedbackFormationService;
import service.FeedbackService;
import service.NotificationService;
import org.example.model.Employee;
import org.example.ui.MainApp;
import utils.Mydb;

import java.net.URL;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;
import java.util.ResourceBundle;

/**
 * Contrôleur pour la gestion des feedbacks côté Employé
 * Gère à la fois les feedbacks employés et les feedbacks formation
 */
public class EmployeeFeedbackController implements Initializable {

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 1 : FEEDBACK EMPLOYÉ - FEEDBACKS REÇUS
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Feedback> tableReceivedFeedbacks;
    @FXML private TableColumn<Feedback, String> colReceivedFrom;
    @FXML private TableColumn<Feedback, String> colReceivedType;
    @FXML private TableColumn<Feedback, String> colReceivedRating;
    @FXML private TableColumn<Feedback, String> colReceivedDate;

    @FXML private ComboBox<String> filterTypeReceived;
    @FXML private Button btnViewReceivedDetails;
    @FXML private Label lblTotalReceived;
    @FXML private Label lblAvgRating;
    @FXML private VBox panelEmployee;
    @FXML private VBox panelFormation;
    @FXML private Button btnTabEmployee;
    @FXML private Button btnTabFormation;
    @FXML private StackPane contentStackPane;

    // ═══════════════════════════════════════════════════════════════
    // ONGLET  : FEEDBACK EMPLOYÉ - FEEDBACKS Envoyees
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<Feedback> tableSentFeedbacks;
    @FXML private TableColumn<Feedback, String> colSentTo;
    @FXML private TableColumn<Feedback, String> colSentType;
    @FXML private TableColumn<Feedback, String> colSentRating;
    @FXML private TableColumn<Feedback, String> colSentDate;

    @FXML private Label lblTotalSent;
    @FXML private Button btnEditSent;
    @FXML private Button btnDeleteSent;

    private ObservableList<Feedback> sentList = FXCollections.observableArrayList();

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 1 : FEEDBACK EMPLOYÉ - FORMULAIRE ENVOI
    // ═══════════════════════════════════════════════════════════════

    @FXML private ComboBox<String> cbToUser;
    @FXML private ComboBox<String> cbFeedbackType;
    @FXML private Slider sliderRating;
    @FXML private Label lblRatingValue;
    @FXML private TextArea txtComment;
    @FXML private CheckBox chkAnonymous;
    @FXML private Button btnSendFeedback;

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 2 : FEEDBACK FORMATION - MES FEEDBACKS
    // ═══════════════════════════════════════════════════════════════

    @FXML private TableView<FeedbackFormation> tableMyFF;
    @FXML private TableColumn<FeedbackFormation, String> colFFFormation;
    @FXML private TableColumn<FeedbackFormation, String> colFFSession;
    @FXML private TableColumn<FeedbackFormation, String> colFFRating;
    @FXML private TableColumn<FeedbackFormation, String> colFFRecommande;
    @FXML private TableColumn<FeedbackFormation, String> colFFDate;

    @FXML private ComboBox<String> filterFormationFF;
    @FXML private Button btnViewFFDetails;
    @FXML private Label lblTotalFF;
    @FXML private Label lblAvgRatingFF;
    @FXML private Button btnEditFF;
    @FXML private Button btnDeleteFF;

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 2 : FEEDBACK FORMATION - FORMULAIRE
    // ═══════════════════════════════════════════════════════════════

    @FXML private ComboBox<String> cbFormation;
    @FXML private ComboBox<String> cbSession;
    @FXML private Slider sliderRatingFF;
    @FXML private Label lblRatingValueFF;
    @FXML private TextArea txtContenu;
    @FXML private TextArea txtFormateur;
    @FXML private TextArea txtOrganisation;
    @FXML private CheckBox chkRecommande;
    @FXML private Button btnSubmitFF;

    @FXML private Button backButton;

    // ═══════════════════════════════════════════════════════════════
    // SERVICES ET DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private final FeedbackService feedbackService = new FeedbackService();
    private final FeedbackFormationService ffService = new FeedbackFormationService();
    private final NotificationService notifService = new NotificationService();

    private ObservableList<Feedback> receivedList = FXCollections.observableArrayList();
    private ObservableList<FeedbackFormation> ffList = FXCollections.observableArrayList();

    private List<String> userItems = new ArrayList<>();
    private List<String> formationItems = new ArrayList<>();
    private List<String> sessionItems = new ArrayList<>();

    private Employee currentEmployee;
    private int currentEmployeeId;

    // ═══════════════════════════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    public void initialize(URL url, ResourceBundle rb) {
        // Onglet 1
        setupReceivedTable();
        setupSentTable();
        setupFeedbackForm();
        setupReceivedFilters();
        setupRatingSlider(sliderRating, lblRatingValue);

        // Onglet 2
        setupFFTable();
        setupFFFilters();
        setupRatingSlider(sliderRatingFF, lblRatingValueFF);
    }

    public void initData(int employeeId, String employeeName, Employee employee) {
        this.currentEmployeeId = employeeId;
        this.currentEmployee = employee;

        loadUsers();
        loadFormations();
        loadReceivedFeedbacks();
        loadSentFeedbacks();
        loadMyFeedbacksFormation();
    }

    @FXML
    private void handleBackToDashboard() {
        if (currentEmployee != null) {
            MainApp.showEmployeeDashboard(currentEmployee);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ONGLET 1 : SETUP FEEDBACK EMPLOYÉ
    // ═══════════════════════════════════════════════════════════════

    private void setupReceivedTable() {
        colReceivedFrom.setCellValueFactory(new PropertyValueFactory<>("fromUsername"));
        colReceivedType.setCellValueFactory(new PropertyValueFactory<>("feedbackType"));
        colReceivedRating.setCellValueFactory(new PropertyValueFactory<>("ratingStars"));
        colReceivedDate.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        tableReceivedFeedbacks.setItems(receivedList);
        tableReceivedFeedbacks.getSelectionModel().selectedItemProperty().addListener(
                (obs, oldVal, newVal) -> btnViewReceivedDetails.setDisable(newVal == null));

        btnViewReceivedDetails.setDisable(true);
    }

    private void setupFeedbackForm() {
        cbFeedbackType.setItems(FXCollections.observableArrayList(
                "performance", "behavior", "collaboration", "other"));
        cbFeedbackType.setValue("performance");
    }

    private void setupReceivedFilters() {
        filterTypeReceived.setItems(FXCollections.observableArrayList(
                "Tous", "performance", "behavior", "collaboration", "other"));
        filterTypeReceived.setValue("Tous");
        filterTypeReceived.setOnAction(e -> applyReceivedFilters());
    }

    // ═══════════════════════════════════════════════════════════════
    // ONGLET  : SETUP FEEDBACK EMPLOYÉ
    // ═══════════════════════════════════════════════════════════════
    private void setupSentTable() {
        colSentTo.setCellValueFactory(new PropertyValueFactory<>("toUsername"));
        colSentType.setCellValueFactory(new PropertyValueFactory<>("feedbackType"));
        colSentRating.setCellValueFactory(new PropertyValueFactory<>("ratingStars"));
        colSentDate.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        tableSentFeedbacks.setItems(sentList);
        // Désactiver par défaut
        btnEditSent.setDisable(true);
        btnDeleteSent.setDisable(true);

        // Listener de sélection
        tableSentFeedbacks.getSelectionModel().selectedItemProperty().addListener((obs, oldVal, newVal) -> {
                    boolean hasSelection = (newVal != null);
            btnEditSent.setDisable(!hasSelection);
            btnDeleteSent.setDisable(!hasSelection);
        });
    }
    @FXML
    private void showEmployeePanel() {
        panelEmployee.setVisible(true);
        panelFormation.setVisible(false);

        // Style bouton actif
        btnTabEmployee.setStyle("-fx-background-color: transparent; -fx-text-fill: #667eea; -fx-border-width: 0 0 3 0; -fx-border-color: #667eea; -fx-font-weight: bold;");
        btnTabFormation.setStyle("-fx-background-color: transparent; -fx-text-fill: #a0aec0; -fx-border-width: 0 0 3 0; -fx-border-color: transparent; -fx-font-weight: bold;");
    }

    @FXML
    private void showFormationPanel() {
        panelEmployee.setVisible(false);
        panelFormation.setVisible(true);

        // Style bouton actif
        btnTabFormation.setStyle("-fx-background-color: transparent; -fx-text-fill: #667eea; -fx-border-width: 0 0 3 0; -fx-border-color: #667eea; -fx-font-weight: bold;");
        btnTabEmployee.setStyle("-fx-background-color: transparent; -fx-text-fill: #a0aec0; -fx-border-width: 0 0 3 0; -fx-border-color: transparent; -fx-font-weight: bold;");
    }
    // ═══════════════════════════════════════════════════════════════
    // ONGLET 2 : SETUP FEEDBACK FORMATION
    // ═══════════════════════════════════════════════════════════════

    private void setupFFTable() {
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

        tableMyFF.setItems(ffList);
        // Désactiver par défaut
        btnEditFF.setDisable(true);
        btnDeleteFF.setDisable(true);
        tableMyFF.getSelectionModel().selectedItemProperty().addListener((obs, oldVal, newVal) -> {
            boolean hasSelection = (newVal != null);
            btnEditFF.setDisable(!hasSelection);
            btnDeleteFF.setDisable(!hasSelection);
            btnViewFFDetails.setDisable(!hasSelection);
        });
    }

    private void setupFFFilters() {
        filterFormationFF.setValue("Toutes");
        filterFormationFF.setOnAction(e -> applyFFFilters());
    }

    // ═══════════════════════════════════════════════════════════════
    // CHARGEMENT DONNÉES
    // ═══════════════════════════════════════════════════════════════

    private void loadUsers() {
        userItems.clear();
        // ✅ Charge uniquement les employés du même RH
        String sql = "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS fullname " +
                "FROM employees e " +
                "WHERE e.rh_id = (SELECT rh_id FROM employees WHERE id = ?) " +
                "AND e.id != ?";
        try (PreparedStatement ps = Mydb.getInstance().getConnection().prepareStatement(sql)) {
            ps.setInt(1, currentEmployeeId);  // Pour trouver le rh_id de l'employé connecté
            ps.setInt(2, currentEmployeeId);  // Pour exclure l'employé lui-même
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                userItems.add(rs.getInt("id") + ":" + rs.getString("fullname"));
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur loadUsers : " + e.getMessage());
        }
        cbToUser.setItems(FXCollections.observableArrayList(userItems));
    }


    private void loadFormations() {
        formationItems.clear();
        formationItems.add("Toutes");

        // Requête avec jointure pour ne prendre que les formations où l'employé est 'Approved'
        String sql = "SELECT DISTINCT f.id_formation, f.titre " +
                "FROM formation f " +
                "JOIN session_formation s ON f.id_formation = s.id_formation " +
                "JOIN participation_formation p ON s.id_session = p.id_session " +
                "WHERE p.id_utilisateur = ? AND p.statut_participation = 'Approved'";

        try (PreparedStatement ps = Mydb.getInstance().getConnection().prepareStatement(sql)) {
            ps.setInt(1, currentEmployeeId);
            ResultSet rs = ps.executeQuery();

            while (rs.next()) {
                String item = rs.getInt("id_formation") + ":" + rs.getString("titre");
                formationItems.add(item);
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur loadFormations filtrées : " + e.getMessage());
        }

        // Mise à jour des ComboBox
        if (formationItems.size() > 1) {
            cbFormation.setItems(FXCollections.observableArrayList(formationItems.subList(1, formationItems.size())));
        } else {
            cbFormation.setItems(FXCollections.observableArrayList());
            cbFormation.setPromptText("Aucune formation approuvée");
        }

        filterFormationFF.setItems(FXCollections.observableArrayList(formationItems));

        cbFormation.setOnAction(e -> {
            if (cbFormation.getValue() != null) {
                int selectedId = Integer.parseInt(cbFormation.getValue().split(":")[0]);
                loadSessions(selectedId);
            }
        });
    }

    private void loadSessions(int formationId) {
        sessionItems.clear();

        // On sélectionne uniquement la session où l'utilisateur est inscrit et approuvé
        String sql = "SELECT s.id_session, s.date_debut, s.lieu " +
                "FROM session_formation s " +
                "JOIN participation_formation p ON s.id_session = p.id_session " +
                "WHERE s.id_formation = ? AND p.id_utilisateur = ? AND p.statut_participation = 'Approved'";

        try (PreparedStatement ps = Mydb.getInstance().getConnection().prepareStatement(sql)) {
            ps.setInt(1, formationId);
            ps.setInt(2, currentEmployeeId);
            ResultSet rs = ps.executeQuery();

            while (rs.next()) {
                String label = rs.getString("date_debut") + " — " + rs.getString("lieu");
                sessionItems.add(rs.getInt("id_session") + ":" + label);
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur loadSessions filtrées : " + e.getMessage());
        }

        cbSession.setItems(FXCollections.observableArrayList(sessionItems));

        // Sélection automatique s'il n'y a qu'une seule session
        if (!sessionItems.isEmpty()) {
            cbSession.getSelectionModel().selectFirst();
        }
    }

    private void loadReceivedFeedbacks() {
        receivedList.clear();
        receivedList.addAll(feedbackService.getReceivedByUser(currentEmployeeId));
        applyReceivedFilters();
        updateReceivedStats();
    }
    private void loadSentFeedbacks() {
        sentList.clear();
        sentList.addAll(feedbackService.getSentByUser(currentEmployeeId));
        lblTotalSent.setText("Total envoyés : " + sentList.size());
    }

    private void loadMyFeedbacksFormation() {
        ffList.clear();
        ffList.addAll(ffService.getByUser(currentEmployeeId));
        applyFFFilters();
        updateFFStats();
    }

    // ═══════════════════════════════════════════════════════════════
    // FILTRES
    // ═══════════════════════════════════════════════════════════════

    private void applyReceivedFilters() {
        String typeFilter = filterTypeReceived.getValue();
        List<Feedback> all = feedbackService.getReceivedByUser(currentEmployeeId);
        List<Feedback> filtered = all.stream()
                .filter(f -> typeFilter.equals("Tous") || f.getFeedbackType().name().equals(typeFilter))
                .toList();
        receivedList.setAll(filtered);
        updateReceivedStats();
    }

    private void applyFFFilters() {
        String formationFilter = filterFormationFF.getValue();
        List<FeedbackFormation> all = ffService.getByUser(currentEmployeeId);

        if (formationFilter != null && !formationFilter.equals("Toutes")) {
            int formationId = Integer.parseInt(formationFilter.split(":")[0]);
            all = all.stream().filter(ff -> ff.getFormationId() == formationId).toList();
        }

        ffList.setAll(all);
        updateFFStats();
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    private void updateReceivedStats() {
        lblTotalReceived.setText("Total : " + receivedList.size());
        double avg = receivedList.stream().mapToInt(Feedback::getRating).average().orElse(0);
        lblAvgRating.setText(String.format("Note moyenne : %.1f ⭐", avg));
    }

    private void updateFFStats() {
        lblTotalFF.setText("Total : " + ffList.size());
        double avg = ffList.stream().mapToInt(FeedbackFormation::getRating).average().orElse(0);
        lblAvgRatingFF.setText(String.format("Note moyenne : %.1f ⭐", avg));
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - FEEDBACK EMPLOYÉ
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleSendFeedback() {
        if (cbToUser.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Veuillez choisir un destinataire.");
            return;
        }
        if (txtComment.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Le commentaire est obligatoire.");
            return;
        }

        int toUserId = Integer.parseInt(cbToUser.getValue().split(":")[0]);
        Feedback f = new Feedback(
                currentEmployeeId, toUserId,
                Feedback.FeedbackType.valueOf(cbFeedbackType.getValue()),
                (int) sliderRating.getValue(),
                txtComment.getText().trim(),
                chkAnonymous.isSelected()
        );

        if (feedbackService.add(f)) {
            notifService.notify(toUserId, "feedback",
                    "Vous avez reçu un nouveau feedback",
                    "Un feedback de type '" + cbFeedbackType.getValue() + "' vous a été envoyé.",
                    null, "feedback");
            showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback envoyé avec succès !");
            handleClearFeedbackForm();
            loadSentFeedbacks();
        } else {
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'envoyer le feedback.");
        }
    }

    @FXML
    private void handleClearFeedbackForm() {
        cbToUser.setValue(null);
        cbFeedbackType.setValue("performance");
        sliderRating.setValue(3);
        txtComment.clear();
        chkAnonymous.setSelected(false);
    }

    @FXML
    private void handleRefreshReceived() {
        loadReceivedFeedbacks();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Feedbacks actualisés.");
    }

    @FXML
    private void handleViewReceivedDetails() {
        Feedback selected = tableReceivedFeedbacks.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        StringBuilder details = new StringBuilder();
        details.append("═══ FEEDBACK #").append(selected.getId()).append(" ═══\n\n");
        details.append("De: ").append(selected.isAnonymous() ? "Anonyme" : selected.getFromUsername()).append("\n");
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
    @FXML
    private void handleDeleteSentFeedback() {
        Feedback selected = tableSentFeedbacks.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation de suppression");
        confirm.setHeaderText("Supprimer le feedback envoyé ?");
        confirm.setContentText("Voulez-vous vraiment supprimer ce feedback ? Cette action est irréversible.");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                if (feedbackService.delete(selected.getId())) {
                    loadSentFeedbacks();
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback supprimé.");
                }
            }
        });
    }

    @FXML
    private void handleEditSentFeedback() {
        Feedback selected = tableSentFeedbacks.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        // Création du dialogue de modification
        Dialog<Feedback> dialog = new Dialog<>();
        dialog.setTitle("Modifier mon feedback");
        dialog.setHeaderText("Modification du feedback pour " + selected.getToUsername());

        ButtonType saveButtonType = new ButtonType("Enregistrer", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        // Formulaire de modification
        VBox content = new VBox(15);
        ComboBox<String> typeEdit = new ComboBox<>(FXCollections.observableArrayList("performance", "behavior", "collaboration", "other"));
        typeEdit.setValue(selected.getFeedbackType().name());

        Slider ratingEdit = new Slider(1, 5, selected.getRating());
        ratingEdit.setSnapToTicks(true);
        ratingEdit.setMajorTickUnit(1);

        TextArea commentEdit = new TextArea(selected.getComment());
        CheckBox anonEdit = new CheckBox("Rester anonyme");
        anonEdit.setSelected(selected.isAnonymous());

        content.getChildren().addAll(new Label("Type:"), typeEdit, new Label("Note:"), ratingEdit, new Label("Commentaire:"), commentEdit, anonEdit);
        dialog.getDialogPane().setContent(content);

        dialog.setResultConverter(btn -> {
            if (btn == saveButtonType) {
                selected.setFeedbackType(Feedback.FeedbackType.valueOf(typeEdit.getValue()));
                selected.setRating((int) ratingEdit.getValue());
                selected.setComment(commentEdit.getText().trim());
                selected.setAnonymous(anonEdit.isSelected());
                return selected;
            }
            return null;
        });

        dialog.showAndWait().ifPresent(updated -> {
            if (feedbackService.update(updated)) {
                loadSentFeedbacks();
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback mis à jour avec succès.");
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // HANDLERS - FEEDBACK FORMATION
    // ═══════════════════════════════════════════════════════════════

    @FXML
    private void handleSubmitFF() {
        if (cbFormation.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Veuillez sélectionner une formation.");
            return;
        }
        if (txtContenu.getText().trim().isEmpty()
                && txtFormateur.getText().trim().isEmpty()
                && txtOrganisation.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Veuillez remplir au moins un commentaire.");
            return;
        }

        int formationId = Integer.parseInt(cbFormation.getValue().split(":")[0]);
        String sessionVal = cbSession.getValue();
        Integer sessionId = (sessionVal != null && !sessionVal.startsWith("0:"))
                ? Integer.parseInt(sessionVal.split(":")[0]) : null;

        FeedbackFormation ff = new FeedbackFormation(
                currentEmployeeId, formationId, sessionId,
                (int) sliderRatingFF.getValue(),
                txtContenu.getText().trim(),
                txtFormateur.getText().trim(),
                txtOrganisation.getText().trim(),
                chkRecommande.isSelected()
        );

        if (ffService.add(ff)) {
            showAlert(Alert.AlertType.INFORMATION, "Succès",
                    "Votre feedback formation a été enregistré. Merci !");
            handleClearFFForm();
            loadMyFeedbacksFormation();
        } else {
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'enregistrer le feedback.");
        }
    }

    @FXML
    private void handleClearFFForm() {
        cbFormation.setValue(null);
        cbSession.getItems().clear();
        sliderRatingFF.setValue(3);
        txtContenu.clear();
        txtFormateur.clear();
        txtOrganisation.clear();
        chkRecommande.setSelected(true);
    }

    @FXML
    private void handleRefreshFF() {
        loadMyFeedbacksFormation();
        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "Feedbacks formation actualisés.");
    }

    @FXML
    private void handleViewFFDetails() {
        FeedbackFormation selected = tableMyFF.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        StringBuilder details = new StringBuilder();
        details.append("═══ FEEDBACK FORMATION #").append(selected.getId()).append(" ═══\n\n");
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
        alert.setHeaderText("Feedback #" + selected.getId());
        alert.setContentText(details.toString());
        alert.setResizable(true);
        alert.getDialogPane().setPrefWidth(550);
        alert.getDialogPane().setPrefHeight(450);
        alert.showAndWait();
    }
    @FXML
    private void handleDeleteFF() {
        FeedbackFormation selected = tableMyFF.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION, "Supprimer ce feedback formation ?", ButtonType.YES, ButtonType.NO);
        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.YES) {
                if (ffService.delete(selected.getId())) {
                    loadMyFeedbacksFormation(); // Rafraîchir la liste et les stats
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback formation supprimé.");
                }
            }
        });
    }

    @FXML
    private void handleEditFF() {
        FeedbackFormation selected = tableMyFF.getSelectionModel().getSelectedItem();
        if (selected == null) return;

        // Création d'un dialogue personnalisé
        Dialog<FeedbackFormation> dialog = new Dialog<>();
        dialog.setTitle("Modifier mon feedback formation");
        dialog.setHeaderText("Formation : " + selected.getFormationName());

        ButtonType saveButtonType = new ButtonType("Enregistrer", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        // Formulaire de modification
        VBox content = new VBox(10);
        content.setPadding(new Insets(20));

        Slider slider = new Slider(1, 5, selected.getRating());
        slider.setBlockIncrement(1);
        slider.setSnapToTicks(true);
        slider.setMajorTickUnit(1);
        slider.setShowTickLabels(true);

        TextArea txtC = new TextArea(selected.getContenuComment());
        txtC.setPromptText("Commentaire sur le contenu...");
        txtC.setPrefRowCount(3);

        TextArea txtF = new TextArea(selected.getFormateurComment());
        txtF.setPromptText("Commentaire sur le formateur...");
        txtF.setPrefRowCount(3);

        CheckBox checkRec = new CheckBox("Je recommande cette formation");
        checkRec.setSelected(selected.isRecommande());

        content.getChildren().addAll(
                new Label("Note :"), slider,
                new Label("Contenu :"), txtC,
                new Label("Formateur :"), txtF,
                checkRec
        );

        dialog.getDialogPane().setContent(content);

        dialog.setResultConverter(btn -> {
            if (btn == saveButtonType) {
                selected.setRating((int) slider.getValue());
                selected.setContenuComment(txtC.getText());
                selected.setFormateurComment(txtF.getText());
                selected.setRecommande(checkRec.isSelected());
                return selected;
            }
            return null;
        });

        dialog.showAndWait().ifPresent(updated -> {
            if (ffService.update(updated)) {
                loadMyFeedbacksFormation();
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback formation mis à jour.");
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // UTILITAIRES
    // ═══════════════════════════════════════════════════════════════

    private void setupRatingSlider(Slider slider, Label label) {
        slider.setMin(1);
        slider.setMax(5);
        slider.setValue(3);
        slider.setMajorTickUnit(1);
        slider.setSnapToTicks(true);
        label.setText("⭐ 3 / 5");
        slider.valueProperty().addListener((obs, o, n) ->
                label.setText("⭐ " + n.intValue() + " / 5"));
    }

    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);
        alert.showAndWait();
    }
}