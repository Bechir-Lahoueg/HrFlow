package controllers;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import models.Feedback;
import models.FeedbackFormation;
import service.FeedbackFormationService;
import service.FeedbackService;
import service.NotificationService;
import utils.Mydb;

import java.net.URL;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;
import java.util.ResourceBundle;

public class FeedbackController implements Initializable {

    // ════════════════════════════════════════════════════════════════
    // ONGLET 1 : Feedback Employé
    // ════════════════════════════════════════════════════════════════

    @FXML private TableView<Feedback>              tableFeedbacks;
    @FXML private TableColumn<Feedback, Integer>   colId;
    @FXML private TableColumn<Feedback, String>    colFrom;
    @FXML private TableColumn<Feedback, String>    colTo;
    @FXML private TableColumn<Feedback, String>    colType;
    @FXML private TableColumn<Feedback, String>    colRating;
    @FXML private TableColumn<Feedback, String>    colStatus;
    @FXML private TableColumn<Feedback, String>    colDate;

    @FXML private ComboBox<String>   cbToUser;
    @FXML private ComboBox<String>   cbFeedbackType;
    @FXML private Slider             sliderRating;
    @FXML private Label              lblRatingValue;
    @FXML private TextArea           txtComment;
    @FXML private CheckBox           chkAnonymous;
    @FXML private Label              lblFormTitle;
    @FXML private ComboBox<String>   filterType;
    @FXML private ComboBox<String>   filterStatus;
    @FXML private Button             btnSave;
    @FXML private Button             btnClear;
    @FXML private Button             btnDelete;
    @FXML private Button             btnAcknowledge;
    @FXML private Label              lblTotal;
    @FXML private Label              lblAvgRating;

    // ════════════════════════════════════════════════════════════════
    // ONGLET 2 : Feedback Formation
    // ════════════════════════════════════════════════════════════════

    @FXML private TableView<FeedbackFormation>              tableFF;
    @FXML private TableColumn<FeedbackFormation, Integer>   colFFId;
    @FXML private TableColumn<FeedbackFormation, String>    colFFUser;
    @FXML private TableColumn<FeedbackFormation, String>    colFFFormation;
    @FXML private TableColumn<FeedbackFormation, String>    colFFSession;
    @FXML private TableColumn<FeedbackFormation, String>    colFFRating;
    @FXML private TableColumn<FeedbackFormation, String>    colFFRecommande;
    @FXML private TableColumn<FeedbackFormation, String>    colFFDate;

    @FXML private ComboBox<String>   cbFormation;
    @FXML private ComboBox<String>   cbSession;
    @FXML private Slider             sliderRatingFF;
    @FXML private Label              lblRatingValueFF;
    @FXML private TextArea           txtContenu;
    @FXML private TextArea           txtFormateur;
    @FXML private TextArea           txtOrganisation;
    @FXML private CheckBox           chkRecommande;
    @FXML private Label              lblFormTitleFF;
    @FXML private ComboBox<String>   filterFormation;
    @FXML private Button             btnSaveFF;
    @FXML private Button             btnClearFF;
    @FXML private Button             btnDeleteFF;
    @FXML private Label              lblTotalFF;
    @FXML private Label              lblAvgRatingFF;
    @FXML private Label              lblRecommandation;

    // ─── Services ────────────────────────────────────────────────────
    private final FeedbackService          feedbackService  = new FeedbackService();
    private final FeedbackFormationService ffService        = new FeedbackFormationService();
    private final NotificationService      notifService     = new NotificationService();

    private ObservableList<Feedback>         feedbackList = FXCollections.observableArrayList();
    private ObservableList<FeedbackFormation> ffList      = FXCollections.observableArrayList();

    private Feedback         selectedFeedback = null;
    private FeedbackFormation selectedFF      = null;

    private List<String> formationItems = new ArrayList<>();
    private List<String> sessionItems   = new ArrayList<>();

    private static final int CURRENT_USER_ID = 1; // à remplacer lors de l'intégration

    // ─── Initialisation ──────────────────────────────────────────────

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        // Onglet 1
        setupColumnsTab1();
        setupFormTab1();
        setupFiltersTab1();
        loadDataTab1();
        setupTableSelectionTab1();
        setupRatingSlider(sliderRating, lblRatingValue);

        // Onglet 2
        setupColumnsTab2();
        loadFormations();
        setupRatingSlider(sliderRatingFF, lblRatingValueFF);
        loadDataTab2();
        setupTableSelectionTab2();
        setupFilterFormation();
    }

    // ════════════════════════════════════════════════════════════════
    // ONGLET 1 — Feedback Employé
    // ════════════════════════════════════════════════════════════════

    private void setupColumnsTab1() {
        colId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colFrom.setCellValueFactory(new PropertyValueFactory<>("fromUsername"));
        colTo.setCellValueFactory(new PropertyValueFactory<>("toUsername"));
        colType.setCellValueFactory(new PropertyValueFactory<>("feedbackType"));
        colRating.setCellValueFactory(new PropertyValueFactory<>("ratingStars"));
        colStatus.setCellValueFactory(new PropertyValueFactory<>("status"));
        colDate.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        colStatus.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String s, boolean empty) {
                super.updateItem(s, empty);
                if (empty || s == null) { setText(null); setStyle(""); return; }
                setText(s);
                switch (s) {
                    case "draft"        -> setStyle("-fx-text-fill: #95a5a6; -fx-font-weight: bold;");
                    case "submitted"    -> setStyle("-fx-text-fill: #3498db; -fx-font-weight: bold;");
                    case "acknowledged" -> setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;");
                    default             -> setStyle("");
                }
            }
        });
    }

    private void setupFormTab1() {
        cbFeedbackType.setItems(FXCollections.observableArrayList(
                "performance", "behavior", "collaboration", "other"));
        cbFeedbackType.setValue("performance");
        cbToUser.setItems(FXCollections.observableArrayList(
                "1:Admin", "2:Employé A", "3:Employé B"));
    }

    private void setupFiltersTab1() {
        filterType.setItems(FXCollections.observableArrayList(
                "Tous", "performance", "behavior", "collaboration", "other"));
        filterType.setValue("Tous");
        filterType.setOnAction(e -> applyFiltersTab1());

        filterStatus.setItems(FXCollections.observableArrayList(
                "Tous", "draft", "submitted", "acknowledged"));
        filterStatus.setValue("Tous");
        filterStatus.setOnAction(e -> applyFiltersTab1());
    }

    private void setupTableSelectionTab1() {
        tableFeedbacks.getSelectionModel().selectedItemProperty().addListener(
                (obs, o, newVal) -> {
                    if (newVal != null) {
                        selectedFeedback = newVal;
                        lblFormTitle.setText("✏️ Modifier le feedback");
                        btnSave.setText("💾 Modifier");
                        txtComment.setText(newVal.getComment());
                        cbFeedbackType.setValue(newVal.getFeedbackType().name());
                        sliderRating.setValue(newVal.getRating());
                        chkAnonymous.setSelected(newVal.isAnonymous());
                    }
                });
    }

    private void loadDataTab1() {
        List<Feedback> list = feedbackService.getAll();
        feedbackList.setAll(list);
        tableFeedbacks.setItems(feedbackList);
        updateStatsTab1(list);
    }

    private void applyFiltersTab1() {
        List<Feedback> filtered = feedbackService.getAll().stream()
                .filter(f -> filterType.getValue().equals("Tous")
                        || f.getFeedbackType().name().equals(filterType.getValue()))
                .filter(f -> filterStatus.getValue().equals("Tous")
                        || f.getStatus().name().equals(filterStatus.getValue()))
                .toList();
        feedbackList.setAll(filtered);
        tableFeedbacks.setItems(feedbackList);
        updateStatsTab1(filtered);
    }

    private void updateStatsTab1(List<Feedback> list) {
        lblTotal.setText("Total : " + list.size());
        double avg = list.stream().mapToInt(Feedback::getRating).average().orElse(0);
        lblAvgRating.setText(String.format("Note moyenne : %.1f ⭐", avg));
    }

    @FXML private void handleSave() {
        if (cbToUser.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Veuillez choisir un destinataire.");
            return;
        }
        if (txtComment.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Le commentaire est obligatoire.");
            return;
        }
        int toUserId = Integer.parseInt(cbToUser.getValue().split(":")[0]);

        if (selectedFeedback == null) {
            Feedback f = new Feedback(CURRENT_USER_ID, toUserId,
                    Feedback.FeedbackType.valueOf(cbFeedbackType.getValue()),
                    (int) sliderRating.getValue(),
                    txtComment.getText().trim(),
                    chkAnonymous.isSelected());
            if (feedbackService.add(f)) {
                notifService.notify(toUserId, "feedback",
                        "Vous avez reçu un nouveau feedback",
                        "Un feedback de type '" + cbFeedbackType.getValue() + "' vous a été envoyé.",
                        null, "feedback");
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback envoyé !");
                handleClear();
                loadDataTab1();
            }
        } else {
            selectedFeedback.setFeedbackType(Feedback.FeedbackType.valueOf(cbFeedbackType.getValue()));
            selectedFeedback.setRating((int) sliderRating.getValue());
            selectedFeedback.setComment(txtComment.getText().trim());
            selectedFeedback.setAnonymous(chkAnonymous.isSelected());
            if (feedbackService.update(selectedFeedback)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback mis à jour !");
                handleClear();
                loadDataTab1();
            }
        }
    }

    @FXML private void handleAcknowledge() {
        Feedback sel = tableFeedbacks.getSelectionModel().getSelectedItem();
        if (sel == null) { showAlert(Alert.AlertType.WARNING, "Sélection", "Sélectionnez un feedback."); return; }
        feedbackService.acknowledge(sel.getId());
        loadDataTab1();
    }

    @FXML private void handleDelete() {
        Feedback sel = tableFeedbacks.getSelectionModel().getSelectedItem();
        if (sel == null) { showAlert(Alert.AlertType.WARNING, "Sélection", "Sélectionnez un feedback."); return; }
        new Alert(Alert.AlertType.CONFIRMATION, "Supprimer ce feedback ?", ButtonType.YES, ButtonType.NO)
                .showAndWait().ifPresent(bt -> { if (bt == ButtonType.YES) { feedbackService.delete(sel.getId()); loadDataTab1(); } });
    }

    @FXML private void handleClear() {
        selectedFeedback = null;
        cbToUser.setValue(null);
        cbFeedbackType.setValue("performance");
        sliderRating.setValue(3);
        txtComment.clear();
        chkAnonymous.setSelected(false);
        tableFeedbacks.getSelectionModel().clearSelection();
        lblFormTitle.setText("➕ Nouveau feedback");
        btnSave.setText("💾 Envoyer");
    }

    // ════════════════════════════════════════════════════════════════
    // ONGLET 2 — Feedback Formation
    // ════════════════════════════════════════════════════════════════

    private void setupColumnsTab2() {
        colFFId.setCellValueFactory(new PropertyValueFactory<>("id"));
        colFFUser.setCellValueFactory(new PropertyValueFactory<>("username"));
        colFFFormation.setCellValueFactory(new PropertyValueFactory<>("formationName"));
        colFFSession.setCellValueFactory(new PropertyValueFactory<>("sessionName"));
        colFFRating.setCellValueFactory(new PropertyValueFactory<>("ratingStars"));
        colFFRecommande.setCellValueFactory(new PropertyValueFactory<>("recommendeLabel"));
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
    }

    private void loadFormations() {
        formationItems.clear();
        // ✅ Correction : id_formation au lieu de id
        String sql = "SELECT id_formation, titre FROM formation";
        try (Statement st = Mydb.getInstance().getConnection().createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next())
                formationItems.add(rs.getInt("id_formation") + ":" + rs.getString("titre"));
        } catch (SQLException e) {
            System.err.println("❌ Erreur loadFormations : " + e.getMessage());
        }
        cbFormation.setItems(FXCollections.observableArrayList(formationItems));
        cbFormation.setOnAction(e -> {
            if (cbFormation.getValue() != null)
                loadSessions(Integer.parseInt(cbFormation.getValue().split(":")[0]));
        });
    }

    private void loadSessions(int formationId) {
        sessionItems.clear();
        sessionItems.add("0:Toutes les sessions");
        // ✅ Corrections : id_session, id_formation, CONCAT date_debut+lieu (pas de colonne titre)
        String sql = "SELECT id_session, date_debut, lieu FROM session_formation WHERE id_formation = ?";
        try (PreparedStatement ps = Mydb.getInstance().getConnection().prepareStatement(sql)) {
            ps.setInt(1, formationId);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                String label = rs.getString("date_debut") + " — " + rs.getString("lieu");
                sessionItems.add(rs.getInt("id_session") + ":" + label);
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur loadSessions : " + e.getMessage());
        }
        cbSession.setItems(FXCollections.observableArrayList(sessionItems));
        cbSession.setValue("0:Toutes les sessions");
    }

    private void setupFilterFormation() {
        List<String> items = new ArrayList<>();
        items.add("Toutes");
        items.addAll(formationItems);
        filterFormation.setItems(FXCollections.observableArrayList(items));
        filterFormation.setValue("Toutes");
        filterFormation.setOnAction(e -> applyFilterTab2());
    }

    private void setupTableSelectionTab2() {
        tableFF.getSelectionModel().selectedItemProperty().addListener(
                (obs, o, newVal) -> {
                    if (newVal != null) {
                        selectedFF = newVal;
                        lblFormTitleFF.setText("✏️ Modifier le feedback");
                        btnSaveFF.setText("💾 Modifier");
                        sliderRatingFF.setValue(newVal.getRating());
                        txtContenu.setText(newVal.getContenuComment());
                        txtFormateur.setText(newVal.getFormateurComment());
                        txtOrganisation.setText(newVal.getOrganisationComment());
                        chkRecommande.setSelected(newVal.isRecommande());
                    }
                });
    }

    private void loadDataTab2() {
        List<FeedbackFormation> list = ffService.getAll();
        ffList.setAll(list);
        tableFF.setItems(ffList);
        updateStatsTab2(list);
    }

    private void applyFilterTab2() {
        String val = filterFormation.getValue();
        List<FeedbackFormation> list = (val == null || val.equals("Toutes"))
                ? ffService.getAll()
                : ffService.getByFormation(Integer.parseInt(val.split(":")[0]));
        ffList.setAll(list);
        tableFF.setItems(ffList);
        updateStatsTab2(list);
    }

    private void updateStatsTab2(List<FeedbackFormation> list) {
        lblTotalFF.setText("Total : " + list.size() + " avis");
        double avg = list.stream().mapToInt(FeedbackFormation::getRating).average().orElse(0);
        long rec = list.stream().filter(FeedbackFormation::isRecommande).count();
        double rate = list.isEmpty() ? 0 : rec * 100.0 / list.size();
        lblAvgRatingFF.setText(String.format("Note moyenne : %.1f ⭐", avg));
        lblRecommandation.setText(String.format("Recommandée : %.0f%%", rate));
    }

    @FXML private void handleSaveFF() {
        if (cbFormation.getValue() == null) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Veuillez sélectionner une formation.");
            return;
        }
        if (txtContenu.getText().trim().isEmpty()
                && txtFormateur.getText().trim().isEmpty()
                && txtOrganisation.getText().trim().isEmpty()) {
            showAlert(Alert.AlertType.WARNING, "Validation", "Remplissez au moins un commentaire.");
            return;
        }
        int formationId = Integer.parseInt(cbFormation.getValue().split(":")[0]);
        String sv = cbSession.getValue();
        Integer sessionId = (sv != null && !sv.startsWith("0:"))
                ? Integer.parseInt(sv.split(":")[0]) : null;

        if (selectedFF == null) {
            FeedbackFormation f = new FeedbackFormation(
                    CURRENT_USER_ID, formationId, sessionId,
                    (int) sliderRatingFF.getValue(),
                    txtContenu.getText().trim(),
                    txtFormateur.getText().trim(),
                    txtOrganisation.getText().trim(),
                    chkRecommande.isSelected());
            if (ffService.add(f)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback formation enregistré !");
                handleClearFF();
                loadDataTab2();
            }
        } else {
            selectedFF.setRating((int) sliderRatingFF.getValue());
            selectedFF.setContenuComment(txtContenu.getText().trim());
            selectedFF.setFormateurComment(txtFormateur.getText().trim());
            selectedFF.setOrganisationComment(txtOrganisation.getText().trim());
            selectedFF.setRecommande(chkRecommande.isSelected());
            if (ffService.update(selectedFF)) {
                showAlert(Alert.AlertType.INFORMATION, "Succès", "Feedback mis à jour !");
                handleClearFF();
                loadDataTab2();
            }
        }
    }

    @FXML private void handleDeleteFF() {
        FeedbackFormation sel = tableFF.getSelectionModel().getSelectedItem();
        if (sel == null) { showAlert(Alert.AlertType.WARNING, "Sélection", "Sélectionnez un feedback."); return; }
        new Alert(Alert.AlertType.CONFIRMATION, "Supprimer ce feedback ?", ButtonType.YES, ButtonType.NO)
                .showAndWait().ifPresent(bt -> { if (bt == ButtonType.YES) { ffService.delete(sel.getId()); loadDataTab2(); } });
    }

    @FXML private void handleClearFF() {
        selectedFF = null;
        cbFormation.setValue(null);
        cbSession.getItems().clear();
        sliderRatingFF.setValue(3);
        txtContenu.clear();
        txtFormateur.clear();
        txtOrganisation.clear();
        chkRecommande.setSelected(true);
        tableFF.getSelectionModel().clearSelection();
        lblFormTitleFF.setText("➕ Nouveau feedback formation");
        btnSaveFF.setText("💾 Soumettre");
    }

    // ─── Utilitaires partagés ────────────────────────────────────────

    private void setupRatingSlider(Slider slider, Label label) {
        slider.setMin(1); slider.setMax(5); slider.setValue(3);
        slider.setMajorTickUnit(1); slider.setSnapToTicks(true);
        label.setText("⭐ 3 / 5");
        slider.valueProperty().addListener((obs, o, n) ->
                label.setText("⭐ " + n.intValue() + " / 5"));
    }

    private void showAlert(Alert.AlertType type, String title, String msg) {
        new Alert(type, msg, ButtonType.OK) {{ setTitle(title); }}.showAndWait();
    }
}