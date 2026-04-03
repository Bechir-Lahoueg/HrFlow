package org.example.ui.controller.Rh;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.*;
import javafx.scene.text.Font;
import javafx.scene.text.FontWeight;
import javafx.scene.Cursor;
import javafx.scene.effect.DropShadow;
import javafx.scene.paint.Color;
import org.example.model.User;
import org.example.models.Formation;
import org.example.models.SessionFormation;
import org.example.models.ParticipationFormation;
import org.example.services.FormationService;
import org.example.services.SessionFormationService;
import org.example.services.ParticipationFormationService;
import org.example.services.OpenAIService;

import java.sql.Date;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

/**
 * Contrôleur unifié pour la gestion des formations côté RH
 */
public class RHFormationController {

    // ==================== TAB 1: FORMATIONS ====================
    @FXML private FlowPane formationsContainer;
    @FXML private TextField searchFieldFormations;
    @FXML private Button btnRefresh;
    @FXML private Button btnAddFormation;
    @FXML
    private Button btnGenerateObjectives;

    @FXML
    private TextArea txtObjectifs;

    @FXML
    private TextField txtTitre;

    // ==================== TAB 2: SESSIONS ====================
    @FXML private FlowPane sessionsContainer;
    @FXML private ComboBox<Formation> filterSessionFormation;
    @FXML private Button btnAddSession;
    @FXML private Label lblTotalSessions;
    @FXML private Label lblSessionsPlanifiees;
    @FXML private Label lblSessionsEnCours;
    @FXML private Label lblSessionsTerminees;

    // ==================== TAB 3: PARTICIPANTS ====================
    @FXML private ComboBox<Formation> cbFormationForParticipants;
    @FXML private VBox vboxParticipants;
    @FXML private Button btnRefreshParticipants;

    // ==================== MODERN NAVIGATION ====================
    @FXML private HBox tabNavBar;
    @FXML private Button btnTabFormations;
    @FXML private Button btnTabSessions;
    @FXML private Button btnTabParticipants;
    @FXML private StackPane contentStackPane;
    @FXML private VBox formationsPanel;
    @FXML private VBox sessionsPanel;
    @FXML private VBox participantsPanel;

    // Services
    private FormationService formationService;
    private SessionFormationService sessionService;
    private ParticipationFormationService participationService;

    // Listes observables
    private ObservableList<Formation> formationsList;
    private ObservableList<SessionFormation> sessionsList;
    private ObservableList<ParticipationFormation> participantsList;

    // User context
    private User currentUser;
    private int rhId;

    public RHFormationController() {
        // Initialize services
        this.formationService = new FormationService();
        this.sessionService = new SessionFormationService();
        this.participationService = new ParticipationFormationService();

        // Initialize lists
        this.formationsList = FXCollections.observableArrayList();
        this.sessionsList = FXCollections.observableArrayList();
        this.participantsList = FXCollections.observableArrayList();
    }

    @FXML
    public void initialize() {
        setupFormationsTab();
        setupSessionsTab();
        setupParticipantsTab();
        setupModernNavigation();

        if (formationsContainer != null) {
            formationsContainer.setHgap(12);
            formationsContainer.setVgap(12);
            formationsContainer.setPadding(new Insets(12));
        }
        if (sessionsContainer != null) {
            sessionsContainer.setHgap(12);
            sessionsContainer.setVgap(12);
            sessionsContainer.setPadding(new Insets(12));
        }

        // ======= GPT API: Génération d'objectifs automatiquement =======
        if (btnGenerateObjectives != null && txtObjectifs != null && txtTitre != null) {
            btnGenerateObjectives.setOnAction(event -> {
                String titre = txtTitre.getText().trim();
                if (titre.isEmpty()) {
                    showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez entrer un titre avant de générer les objectifs.");
                    return;
                }

                txtObjectifs.setText("Génération en cours...");

                // Thread séparé pour ne pas bloquer l'UI
                new Thread(() -> {
                    try {
                        String generated = OpenAIService.generateObjectives(titre);

                        // Mise à jour de l'UI sur le thread JavaFX
                        javafx.application.Platform.runLater(() -> txtObjectifs.setText(generated));
                    } catch (Exception ex) {
                        ex.printStackTrace();
                        javafx.application.Platform.runLater(() ->
                                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de générer les objectifs.")
                        );
                    }
                }).start();
            });
        }
    }

    public void setCurrentUser(User user) {
        this.currentUser = user;
        this.rhId = user.getId();

        loadAllData();
        displayParticipantsForFormation();  // Afficher TOUTES les formations/sessions au démarrage
    }

    // ==================== SETUP METHODS ====================

    private void setupFormationsTab() {
        // Search filter
        searchFieldFormations.textProperty().addListener((obs, oldVal, newVal) -> filterFormations(newVal));
    }

    private void setupSessionsTab() {
        // Configurer le ComboBox pour filtrer par formation
        if (filterSessionFormation != null) {
            filterSessionFormation.setItems(formationsList);
            filterSessionFormation.setConverter(new javafx.util.StringConverter<Formation>() {
                @Override
                public String toString(Formation f) {
                    return f == null ? "Toutes les formations" : f.getTitre();
                }

                @Override
                public Formation fromString(String string) {
                    return null;
                }
            });
            filterSessionFormation.setOnAction(e -> loadSessions());
        }
    }
    private void setupParticipantsTab() {
        // Configurer le ComboBox pour filtrer par formation
        if (cbFormationForParticipants != null) {
            cbFormationForParticipants.setItems(formationsList);
            cbFormationForParticipants.setConverter(new javafx.util.StringConverter<Formation>() {
                @Override
                public String toString(Formation f) {
                    return f == null ? "Toutes les formations" : f.getTitre();
                }

                @Override
                public Formation fromString(String string) {
                    return null;
                }
            });
            cbFormationForParticipants.setOnAction(e -> displayParticipantsForFormation());
        }

        if (btnRefreshParticipants != null) {
            btnRefreshParticipants.setOnAction(e -> {
                loadAllData();
                displayParticipantsForFormation();
            });
        }
    }

    private void setupModernNavigation() {
        if (btnTabFormations != null) {
            btnTabFormations.setOnAction(e -> navigateToPanel("formations"));
        }
        if (btnTabSessions != null) {
            btnTabSessions.setOnAction(e -> navigateToPanel("sessions"));
        }
        if (btnTabParticipants != null) {
            btnTabParticipants.setOnAction(e -> navigateToPanel("participants"));
        }

        navigateToPanel("formations");
    }

    private void navigateToPanel(String panelName) {
        if (formationsPanel != null) formationsPanel.setVisible(false);
        if (sessionsPanel != null) sessionsPanel.setVisible(false);
        if (participantsPanel != null) participantsPanel.setVisible(false);

        updateNavigationButtonStyles(panelName);

        switch (panelName.toLowerCase()) {
            case "formations":
                if (formationsPanel != null) formationsPanel.setVisible(true);
                break;
            case "sessions":
                if (sessionsPanel != null) sessionsPanel.setVisible(true);
                break;
            case "participants":
                if (participantsPanel != null) participantsPanel.setVisible(true);
                break;
        }
    }

    private void updateNavigationButtonStyles(String activePanel) {
        if (btnTabFormations != null) {
            btnTabFormations.setStyle("-fx-background-color: transparent; -fx-text-fill: #a0aec0; -fx-padding: 16 20; -fx-cursor: hand; -fx-font-weight: bold; -fx-font-size: 13px; -fx-border-width: 0 0 3 0; -fx-border-color: transparent; -fx-border-radius: 0; -fx-effect: null;");
        }
        if (btnTabSessions != null) {
            btnTabSessions.setStyle("-fx-background-color: transparent; -fx-text-fill: #a0aec0; -fx-padding: 16 20; -fx-cursor: hand; -fx-font-weight: bold; -fx-font-size: 13px; -fx-border-width: 0 0 3 0; -fx-border-color: transparent; -fx-border-radius: 0; -fx-effect: null;");
        }
        if (btnTabParticipants != null) {
            btnTabParticipants.setStyle("-fx-background-color: transparent; -fx-text-fill: #a0aec0; -fx-padding: 16 20; -fx-cursor: hand; -fx-font-weight: bold; -fx-font-size: 13px; -fx-border-width: 0 0 3 0; -fx-border-color: transparent; -fx-border-radius: 0; -fx-effect: null;");
        }

        String activeStyle = "-fx-background-color: transparent; -fx-text-fill: #667eea; -fx-padding: 16 20; -fx-cursor: hand; -fx-font-weight: bold; -fx-font-size: 13px; -fx-border-width: 0 0 3 0; -fx-border-color: #667eea; -fx-border-radius: 0; -fx-effect: null;";
        switch (activePanel.toLowerCase()) {
            case "formations":
                if (btnTabFormations != null) btnTabFormations.setStyle(activeStyle);
                break;
            case "sessions":
                if (btnTabSessions != null) btnTabSessions.setStyle(activeStyle);
                break;
            case "participants":
                if (btnTabParticipants != null) btnTabParticipants.setStyle(activeStyle);
                break;
        }
    }

    // ==================== LOAD DATA METHODS ====================

    private void loadAllData() {
        loadFormations();
        loadSessions();
    }

    @FXML
    private void handleRefresh() {
        loadAllData();

        if (cbFormationForParticipants.getValue() != null) {
            displayParticipantsForFormation();
        }

        showAlert(Alert.AlertType.INFORMATION, "Actualisation", "✅ Les données ont été actualisées avec succès !");
    }


    private void loadFormations() {
        formationsContainer.getChildren().clear();
        formationsList.clear();

        List<Formation> formations = formationService.getFormationsByRh(this.rhId);

        formationsList.addAll(formations);

        String search = searchFieldFormations.getText().toLowerCase();
        for (Formation f : formations) {
            if (f.getTitre().toLowerCase().contains(search)) {
                VBox card = createFormationCard(f);
                formationsContainer.getChildren().add(card);
            }
        }
    }

    private VBox createFormationCard(Formation f) {
        VBox card = new VBox(10);
        card.getStyleClass().add("card");
        card.setPrefWidth(300);
        card.setPadding(new Insets(15));
        card.setStyle("-fx-background-color: white; -fx-border-radius: 10; -fx-background-radius: 10; -fx-border-color: #D0D0D0; -fx-border-width: 1; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 8, 0, 0, 3);");

        // --- titre ---
        Label titleLabel = new Label(f.getTitre());
        titleLabel.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

        // --- NOUVEAU : Affichage de la moyenne des avis ---
        HBox ratingBox = new HBox(5);
        ratingBox.setAlignment(Pos.CENTER_LEFT);

        Label lblStars = new Label(getStarRating(f.getMoyenneRating()));
        lblStars.setStyle("-fx-text-fill: #f1c40f; -fx-font-size: 14px;"); // Couleur Or

        Label lblNote = new Label(String.format("(%.1f/5)", f.getMoyenneRating()));
        lblNote.setStyle("-fx-text-fill: #7f8c8d; -fx-font-size: 12px;");

        if (f.getMoyenneRating() <= 0) {
            lblStars.setText("☆☆☆☆☆");
            lblNote.setText("(Aucun avis)");
        }
        ratingBox.getChildren().addAll(lblStars, lblNote);
        // --------------------------------------------------

        // --- Description ---
        Label descriptionLabel = new Label(f.getDescription());
        descriptionLabel.setWrapText(true);
        descriptionLabel.setMaxHeight(60);
        descriptionLabel.setStyle("-fx-text-fill: #34495e;");

        // --- type+duréé ---
        Label infoLabel = new Label(f.getType() + " • " + f.getDuree() + " jours");
        infoLabel.setStyle("-fx-text-fill: #7f8c8d; -fx-font-style: italic;");

        // --- OBJECTIFS ---
        Label objectifsTitle = new Label("🎯 Objectifs");
        objectifsTitle.setStyle("-fx-font-weight: bold; -fx-text-fill: #145EB7; -fx-font-size: 13px;");

        Label objectifsContent = new Label(
                f.getObjectifs() != null ? f.getObjectifs() : "Non définis"
        );
        objectifsContent.setWrapText(true);
        objectifsContent.setMaxHeight(80);
        objectifsContent.setStyle("-fx-text-fill: #2c3e50; -fx-font-size: 12px;");

        VBox objectifsBox = new VBox(5, objectifsTitle, objectifsContent);


        // --- Boutons d'action ---
        HBox buttonBox = new HBox(8);
        buttonBox.setAlignment(Pos.CENTER);
        buttonBox.setPadding(new Insets(10, 0, 0, 0));

        Button btnEdit = new Button("✏️ Modifier");
        btnEdit.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-padding: 8 16; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");
        btnEdit.setOnAction(e -> {
            handleEditFormationFromCard(f);
        });

        Button btnDelete = new Button("🗑️ Supprimer");
        btnDelete.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-padding: 8 16; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");
        btnDelete.setOnAction(e -> {
            handleDeleteFormationFromCard(f);
        });

        Button btnSessions = new Button("📅 Sessions");
        btnSessions.setStyle("-fx-background-color: #3FA9F5; -fx-text-fill: white; -fx-padding: 8 16; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");
        btnSessions.setOnAction(e -> {
            filterSessionFormation.setValue(f);
            navigateToPanel("sessions");
        });

        buttonBox.getChildren().addAll(btnEdit, btnDelete, btnSessions);

        card.getChildren().addAll(titleLabel,ratingBox, infoLabel, descriptionLabel, objectifsBox, buttonBox);
        return card;
    }

    //-----------------------------------
    private String getStarRating(double moyenne) {
        int stars = (int) Math.round(moyenne);
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < 5; i++) {
            if (i < stars) sb.append("★");
            else sb.append("☆");
        }
        return sb.toString();
    }
    //----------------------------------



    private void openSessionsDetail(Formation f) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("Sessions : " + f.getTitre());
        dialog.setHeaderText("Liste des sessions pour cette formation");

        ButtonType closeButton = new ButtonType("Fermer", ButtonBar.ButtonData.CANCEL_CLOSE);
        dialog.getDialogPane().getButtonTypes().add(closeButton);

        // Création d'un tableau pour les sessions
        TableView<SessionFormation> table = new TableView<>();

        TableColumn<SessionFormation, String> colDate = new TableColumn<>("Début");
        colDate.setCellValueFactory(new PropertyValueFactory<>("dateDebut"));

        TableColumn<SessionFormation, String> colLieu = new TableColumn<>("Lieu");
        colLieu.setCellValueFactory(new PropertyValueFactory<>("lieu"));

        TableColumn<SessionFormation, Integer> colCap = new TableColumn<>("Capacité");
        colCap.setCellValueFactory(new PropertyValueFactory<>("capacite"));

        table.getColumns().addAll(colDate, colLieu, colCap);

        // Charger les sessions de la formation f
        List<SessionFormation> sessions = sessionService.getSessionsByFormation(f.getIdFormation());
        table.setItems(FXCollections.observableArrayList(sessions));

        VBox content = new VBox(10, new Label("Détails des sessions organisées :"), table);
        content.setPrefSize(500, 400);
        dialog.getDialogPane().setContent(content);

        dialog.showAndWait();
    }


    private VBox createSessionCard(SessionFormation s) {
        VBox card = new VBox(10);
        card.setPadding(new Insets(15));
        card.setPrefWidth(300);
        card.setCursor(Cursor.HAND);
        card.setStyle("-fx-background-color: white; -fx-border-color: #D0D0D0; -fx-border-radius: 8; -fx-background-radius: 8; -fx-border-width: 1; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 6, 0, 0, 2);");

        DropShadow hoverShadow = new DropShadow();
        hoverShadow.setRadius(6);
        hoverShadow.setOffsetX(0);
        hoverShadow.setOffsetY(2);
        hoverShadow.setColor(Color.rgb(20, 94, 183, 0.2));

        card.setOnMouseEntered(e -> card.setEffect(hoverShadow));
        card.setOnMouseExited(e -> card.setEffect(null));

        String nomFormation = "Formation inconnue";
        for (Formation f : formationsList) {
            if (f.getIdFormation() == s.getIdFormation()) {
                nomFormation = f.getTitre();
                break;
            }
        }

        Label lblFormation = new Label("📚 " + nomFormation);
        lblFormation.setFont(Font.font("System", FontWeight.BOLD, 14));
        lblFormation.setStyle("-fx-text-fill: #022E69;");
        lblFormation.setWrapText(true);
        lblFormation.setMaxWidth(280);

        HBox header = new HBox(8);
        header.setAlignment(Pos.CENTER_LEFT);
        Label lblLieu = new Label("📍 " + s.getLieu());
        lblLieu.setFont(Font.font("System", FontWeight.BOLD, 13));
        lblLieu.setStyle("-fx-text-fill: #145EB7;");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);
        
        // Calculer les places disponibles pour cette session
        int placesDisponibles = sessionService.getPlacesDisponibles(s.getIdSession());
        int capaciteMax = s.getCapaciteMax();
        String placesText = placesDisponibles + "/" + capaciteMax + " places disponibles";
        Label lblCap = new Label(placesText);

        // Changer la couleur en fonction de la disponibilité
        if (placesDisponibles == 0) {
            lblCap.setStyle("-fx-text-fill: #e74c3c; -fx-font-size: 11px; -fx-font-weight: bold;"); // Rouge
        } else if (placesDisponibles <= capaciteMax / 4) {
            lblCap.setStyle("-fx-text-fill: #f39c12; -fx-font-size: 11px; -fx-font-weight: bold;"); // Orange
        } else {
            lblCap.setStyle("-fx-text-fill: #27ae60; -fx-font-size: 11px; -fx-font-weight: bold;"); // Vert
        }
        header.getChildren().addAll(lblLieu, spacer, lblCap);

        Label lblDate = new Label("📅 " + s.getDateDebut() + " au " + s.getDateFin());
        lblDate.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");
        
        Label lblMode = new Label("💻 Mode: " + s.getMode());
        lblMode.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

        LocalDate today = LocalDate.now();
        String statutCalcule;

        if (today.isBefore(s.getDateDebut())) {
            statutCalcule = "Planifiée";
        } else if (today.isAfter(s.getDateFin())) {
            statutCalcule = "Terminée";
        } else {
            statutCalcule = "En cours";
        }

        Label lblStatut = new Label("● " + statutCalcule);
        lblStatut.setStyle("-fx-font-weight: bold; -fx-font-size: 12px;");

        if ("Planifiée".equals(statutCalcule)) {
            lblStatut.setStyle("-fx-text-fill: #f39c12; -fx-font-weight: bold;"); // Orange
        } else if ("En cours".equals(statutCalcule)) {
            lblStatut.setStyle("-fx-text-fill: #27ae60; -fx-font-weight: bold;"); // Vert
        } else if ("Terminée".equals(statutCalcule)) {
            lblStatut.setStyle("-fx-text-fill: #3FA9F5; -fx-font-weight: bold;"); // Bleu
        }

        HBox buttonBox = new HBox(8);
        buttonBox.setAlignment(Pos.CENTER);
        buttonBox.setPadding(new Insets(10, 0, 0, 0));

        Button btnEdit = new Button("✏️ Modifier");
        btnEdit.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-padding: 8 16; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");
        btnEdit.setOnAction(e -> handleEditSessionFromCard(s));

        Button btnDelete = new Button("🗑️ Supprimer");
        btnDelete.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-padding: 8 16; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");
        btnDelete.setOnAction(e -> handleDeleteSessionFromCard(s));

        buttonBox.getChildren().addAll(btnEdit, btnDelete);

        card.getChildren().addAll(lblFormation, header, lblDate, lblMode, lblStatut, buttonBox);
        return card;
    }

    private void loadParticipants(int idSession) {
    }

    @FXML
    private void handleRefreshSessions() {
        loadSessions();
    }

    private void loadSessions() {
        System.out.println("Chargement des sessions du RH connecté... ID RH: " + this.rhId);
        try {
            sessionsList.clear();
            
            List<SessionFormation> allSessions = sessionService.getAllSessions();
            
            List<Formation> rhFormations = formationService.getFormationsByRh(this.rhId);
            
            for (SessionFormation session : allSessions) {
                for (Formation formation : rhFormations) {
                    if (session.getIdFormation() == formation.getIdFormation()) {
                        sessionsList.add(session);
                        break;
                    }
                }
            }

            sessionsContainer.getChildren().clear();

            Formation selectedFormation = filterSessionFormation.getValue();

            for (SessionFormation session : sessionsList) {
                if (selectedFormation == null || session.getIdFormation() == selectedFormation.getIdFormation()) {
                    VBox card = createSessionCard(session);
                    sessionsContainer.getChildren().add(card);
                }
            }

            updateSessionStats();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void displayParticipantsForFormation() {
        vboxParticipants.getChildren().clear();

        Formation selectedFormation = cbFormationForParticipants.getValue();

        try {
            List<Formation> formationsToDisplay;
            if (selectedFormation != null) {
                formationsToDisplay = formationsList.stream()
                        .filter(f -> f.getIdFormation() == selectedFormation.getIdFormation())
                        .toList();
            } else {
                formationsToDisplay = new ArrayList<>(formationsList);
            }

            if (formationsToDisplay.isEmpty()) {
                displayNoParticipantsMessage();
                return;
            }

            boolean hasParticipants = false;

            for (Formation formation : formationsToDisplay) {
                List<SessionFormation> sessionsList = sessionService.getAllSessions().stream()
                        .filter(s -> s.getIdFormation() == formation.getIdFormation())
                        .toList();

                VBox formationCard = createFormationWithParticipantsCard(formation, sessionsList);

                boolean formationHasParticipants = false;
                for (SessionFormation session : sessionsList) {
                    List<ParticipationFormation> participants = participationService.getParticipationsBySession(session.getIdSession());
                    if (!participants.isEmpty()) {
                        formationHasParticipants = true;
                        hasParticipants = true;
                        break;
                    }
                }

                if (formationHasParticipants) {
                    vboxParticipants.getChildren().add(formationCard);
                }
            }

            if (!hasParticipants) {
                displayNoParticipantsMessage();
            }

        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger les participants: " + e.getMessage());
        }
    }

    private VBox createFormationWithParticipantsCard(Formation formation, List<SessionFormation> sessionsList) {
        VBox mainCard = new VBox(15);
        mainCard.setPadding(new Insets(20));
        mainCard.setStyle("-fx-background-color: white; -fx-border-color: #D0D0D0; -fx-border-radius: 10; -fx-background-radius: 10; -fx-border-width: 1; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 6, 0, 0, 2);");

        // En-tête de la formation
        VBox formationHeader = new VBox(8);
        Label formationTitle = new Label("📚 " + formation.getTitre());
        formationTitle.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

        Label formationInfo = new Label("🎯 " + formation.getType() + " • ⏱️ " + formation.getDuree() + " jours");
        formationInfo.setStyle("-fx-font-size: 12px; -fx-text-fill: #7f8c8d;");

        formationHeader.getChildren().addAll(formationTitle, formationInfo);
        mainCard.getChildren().add(formationHeader);

        // Séparateur
        Separator sep = new Separator();
        sep.setStyle("-fx-border-color: #e0e0e0;");
        mainCard.getChildren().add(sep);

        // Afficher les sessions avec leurs participants
        VBox sessionsBox = new VBox(12);
        for (SessionFormation session : sessionsList) {
            List<ParticipationFormation> participants = participationService.getParticipationsBySession(session.getIdSession());

            if (!participants.isEmpty()) {
                VBox sessionCard = createSessionParticipantsCard(session, participants);
                sessionsBox.getChildren().add(sessionCard);
            }
        }

        mainCard.getChildren().add(sessionsBox);
        return mainCard;
    }

    private void displayNoParticipantsMessage() {
        VBox emptyBox = new VBox();
        emptyBox.setSpacing(15);
        emptyBox.setAlignment(Pos.CENTER);
        emptyBox.setPadding(new Insets(50, 30, 50, 30));
        emptyBox.setStyle("-fx-background-color: #f8f9fa; -fx-border-radius: 10; -fx-background-radius: 10; -fx-border-color: #D0D0D0; -fx-border-width: 1;");

        Label iconLabel = new Label("📭");
        iconLabel.setStyle("-fx-font-size: 48px;");

        Label titleLabel = new Label("Aucun participant");
        titleLabel.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

        Label messageLabel = new Label("Cette formation n'a pas encore de participants inscrits.");
        messageLabel.setStyle("-fx-font-size: 13px; -fx-text-fill: #34495e; -fx-text-alignment: center;");
        messageLabel.setWrapText(true);

        emptyBox.getChildren().addAll(iconLabel, titleLabel, messageLabel);
        vboxParticipants.getChildren().add(emptyBox);
    }

    private VBox createSessionParticipantsCard(SessionFormation session, List<ParticipationFormation> allParticipants) {
        VBox mainCard = new VBox(15);
        mainCard.setPadding(new Insets(20));
        mainCard.setStyle("-fx-background-color: white; -fx-border-color: #D0D0D0; -fx-border-radius: 10; -fx-background-radius: 10; -fx-border-width: 1; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 6, 0, 0, 2);");

        // En-tête de la session
        VBox header = new VBox(8);
        Label titleLabel = new Label("📅 Session du " + session.getDateDebut() + " au " + session.getDateFin());
        titleLabel.setStyle("-fx-font-size: 16px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

        Label locationLabel = new Label("📍 " + session.getLieu() + " • 💻 " + session.getMode());
        locationLabel.setStyle("-fx-font-size: 12px; -fx-text-fill: #7f8c8d;");

        // Afficher les places disponibles
        int placesDisponibles = sessionService.getPlacesDisponibles(session.getIdSession());
        int capaciteMax = session.getCapaciteMax();
        Label placesLabel = new Label("👥 Places: " + placesDisponibles + "/" + capaciteMax);

        if (placesDisponibles == 0) {
            placesLabel.setStyle("-fx-font-size: 12px; -fx-text-fill: #e74c3c; -fx-font-weight: bold;"); // Rouge
        } else if (placesDisponibles <= capaciteMax / 4) {
            placesLabel.setStyle("-fx-font-size: 12px; -fx-text-fill: #f39c12; -fx-font-weight: bold;"); // Orange
        } else {
            placesLabel.setStyle("-fx-font-size: 12px; -fx-text-fill: #27ae60; -fx-font-weight: bold;"); // Vert
        }

        header.getChildren().addAll(titleLabel, locationLabel, placesLabel);
        mainCard.getChildren().add(header);

        // Séparateur
        Separator sep = new Separator();
        sep.setStyle("-fx-border-color: #e0e0e0;");
        mainCard.getChildren().add(sep);

        // Grouper par statut
        List<ParticipationFormation> pendingParticipations = allParticipants.stream()
                .filter(p -> "Pending".equals(p.getStatutParticipation()))
                .toList();

        List<ParticipationFormation> approvedParticipations = allParticipants.stream()
                .filter(p -> "Approved".equals(p.getStatutParticipation()))
                .toList();

        List<ParticipationFormation> rejectedParticipations = allParticipants.stream()
                .filter(p -> "Rejected".equals(p.getStatutParticipation()))
                .toList();

        // Section: Demandes en attente
        if (!pendingParticipations.isEmpty()) {
            VBox pendingSection = new VBox(10);
            Label pendingTitle = new Label("⏳ Demandes en attente (" + pendingParticipations.size() + ")");
            pendingTitle.setStyle("-fx-font-weight: bold; -fx-text-fill: #f39c12; -fx-font-size: 13px;");
            pendingSection.getChildren().add(pendingTitle);

            for (ParticipationFormation p : pendingParticipations) {
                VBox participantCard = createParticipantCard(p, session, "pending");
                pendingSection.getChildren().add(participantCard);
            }
            mainCard.getChildren().add(pendingSection);
        }

        // Section: Participants acceptés
        if (!approvedParticipations.isEmpty()) {
            VBox approvedSection = new VBox(10);
            Label approvedTitle = new Label("✅ Participants acceptés (" + approvedParticipations.size() + ")");
            approvedTitle.setStyle("-fx-font-weight: bold; -fx-text-fill: #27ae60; -fx-font-size: 13px;");
            approvedSection.getChildren().add(approvedTitle);

            for (ParticipationFormation p : approvedParticipations) {
                VBox participantCard = createParticipantCard(p, session, "approved");
                approvedSection.getChildren().add(participantCard);
            }
            mainCard.getChildren().add(approvedSection);
        }

        if (!rejectedParticipations.isEmpty()) {
            VBox rejectedSection = new VBox(10);
            Label rejectedTitle = new Label("❌ Participants refusés (" + rejectedParticipations.size() + ")");
            rejectedTitle.setStyle("-fx-font-weight: bold; -fx-text-fill: #e74c3c; -fx-font-size: 13px;");
            rejectedSection.getChildren().add(rejectedTitle);

            for (ParticipationFormation p : rejectedParticipations) {
                VBox participantCard = createParticipantCard(p, session, "rejected");
                rejectedSection.getChildren().add(participantCard);
            }
            mainCard.getChildren().add(rejectedSection);
        }

        return mainCard;
    }

    private VBox createParticipantCard(ParticipationFormation participation, SessionFormation session, String status) {
        VBox card = new VBox(8);
        card.setPadding(new Insets(12));
        card.setStyle("-fx-background-color: #f8f9fa; -fx-border-radius: 6; -fx-background-radius: 6; -fx-border-color: #E0E0E0; -fx-border-width: 1;");

        Label employeeLabel = new Label("👤 " + participation.getNomEmployee());
        employeeLabel.setStyle("-fx-font-weight: bold; -fx-text-fill: #022E69; -fx-font-size: 12px;");

        Label dateLabel = new Label("📅 Inscription: " + participation.getDateInscription());
        dateLabel.setStyle("-fx-font-size: 11px; -fx-text-fill: #7f8c8d;");

        String statusColor = "#f39c12";
        String statusLabel = "⏳ En attente";

        if ("approved".equals(status)) {
            statusColor = "#27ae60";
            statusLabel = "✅ Accepté";
        } else if ("rejected".equals(status)) {
            statusColor = "#e74c3c";
            statusLabel = "❌ Refusé";
        }

        Label statusBadge = new Label(statusLabel);
        statusBadge.setStyle("-fx-text-fill: " + statusColor + "; -fx-font-weight: bold; -fx-font-size: 11px;");

        HBox buttonBox = new HBox(8);
        buttonBox.setAlignment(Pos.CENTER_RIGHT);

        // Boutons différents selon le statut
        if ("pending".equals(status)) {
            Button btnValidate = new Button("✅ Accepter");
            btnValidate.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-padding: 6 12; -fx-background-radius: 4; -fx-cursor: hand; -fx-font-size: 11px;");
            btnValidate.setOnAction(e -> handleValidateParticipantCard(participation));

            Button btnReject = new Button("❌ Refuser");
            btnReject.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-padding: 6 12; -fx-background-radius: 4; -fx-cursor: hand; -fx-font-size: 11px;");
            btnReject.setOnAction(e -> handleRejectParticipantCard(participation));

            buttonBox.getChildren().addAll(btnValidate, btnReject);
        } else if ("approved".equals(status)) {
            Button btnDetails = new Button("📋 Détails");
            btnDetails.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-padding: 6 12; -fx-background-radius: 4; -fx-cursor: hand; -fx-font-size: 11px;");
            btnDetails.setOnAction(e -> showParticipantDetails(participation));

            Button btnRemove = new Button("🔄 Modifier");
            btnRemove.setStyle("-fx-background-color: #3FA9F5; -fx-text-fill: white; -fx-padding: 6 12; -fx-background-radius: 4; -fx-cursor: hand; -fx-font-size: 11px;");
            btnRemove.setOnAction(e -> {
                Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
                confirm.setTitle("Modification du statut");
                confirm.setHeaderText("Modifier le statut de ce participant ?");
                confirm.setContentText("Employé: " + participation.getNomEmployee());

                confirm.showAndWait().ifPresent(response -> {
                    if (response == ButtonType.OK) {
                        participation.setStatutParticipation("Pending");
                        participationService.updateParticipation(participation);
                        displayParticipantsForFormation();
                        showAlert(Alert.AlertType.INFORMATION, "Succès", "Statut modifié avec succès !");
                    }
                });
            });

            buttonBox.getChildren().addAll(btnDetails, btnRemove);
        }

        card.getChildren().addAll(employeeLabel, dateLabel, statusBadge, buttonBox);
        return card;
    }

    private void handleValidateParticipantCard(ParticipationFormation participation) {
        // Vérifier le nombre de places disponibles
        int placesDisponibles = sessionService.getPlacesDisponibles(participation.getIdSession());
        SessionFormation session = sessionService.getSessionById(participation.getIdSession());

        if (placesDisponibles <= 0) {
            showAlert(Alert.AlertType.WARNING, "Aucune place disponible",
                "Cette session n'a plus de places disponibles. Impossible d'accepter ce participant.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Accepter ce participant ?");
        String capacityInfo = "";
        if (session != null) {
            capacityInfo = "\n\nPlaces restantes après acceptation: " + (placesDisponibles - 1) + "/" + session.getCapaciteMax();
        }
        confirm.setContentText("Employé: " + participation.getNomEmployee() + capacityInfo);

        DialogPane dialogPane = confirm.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #ffffff;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: #27ae60;");
        Label headerTitle = new Label("✅ Accepter le participant");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");
        header.getChildren().add(headerTitle);
        dialogPane.setHeader(header);

        for (ButtonType buttonType : dialogPane.getButtonTypes()) {
            Button btn = (Button) dialogPane.lookupButton(buttonType);
            if (buttonType == ButtonType.OK) {
                btn.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            } else {
                btn.setStyle("-fx-background-color: #D0D0D0; -fx-text-fill: #34495e; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            }
        }

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                participation.setStatutParticipation("Approved");
                participationService.updateParticipation(participation);

                // Mettre à jour et afficher le nombre de places disponibles pour la session
                SessionFormation updatedSession = sessionService.getSessionWithPlaces(participation.getIdSession());
                if (updatedSession != null) {
                    int newPlaces = sessionService.getPlacesDisponibles(participation.getIdSession());
                    System.out.println("✅ Participation acceptée. Places disponibles restantes pour la session " + participation.getIdSession() + ": " + newPlaces + "/" + updatedSession.getCapaciteMax());
                }

                displayParticipantsForFormation();
                showAlert(Alert.AlertType.INFORMATION, "Succès", "✅ Participant accepté !");
            }
        });
    }

    private void handleRejectParticipantCard(ParticipationFormation participation) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Refuser ce participant ?");
        confirm.setContentText("Employé: " + participation.getNomEmployee());

        DialogPane dialogPane = confirm.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #ffffff;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: #e74c3c;");
        Label headerTitle = new Label("❌ Refuser le participant");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");
        header.getChildren().add(headerTitle);
        dialogPane.setHeader(header);

        for (ButtonType buttonType : dialogPane.getButtonTypes()) {
            Button btn = (Button) dialogPane.lookupButton(buttonType);
            if (buttonType == ButtonType.OK) {
                btn.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            } else {
                btn.setStyle("-fx-background-color: #D0D0D0; -fx-text-fill: #34495e; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            }
        }

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                participation.setStatutParticipation("Rejected");
                participationService.updateParticipation(participation);
                displayParticipantsForFormation();
                showAlert(Alert.AlertType.INFORMATION, "Succès", "❌ Participant refusé !");
            }
        });
    }

    private void showParticipantDetails(ParticipationFormation participation) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("Détails du participant");
        dialog.setHeaderText(null);

        DialogPane dialogPane = dialog.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #f5f5f5;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: linear-gradient(to right, #145EB7, #3FA9F5);");

        Label headerTitle = new Label("👤 Détails du participant");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");

        Label headerSub = new Label("ID: #" + participation.getIdParticipation());
        headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 12px;");

        header.getChildren().addAll(headerTitle, headerSub);
        dialogPane.setHeader(header);

        VBox contentBox = new VBox(15);
        contentBox.setPadding(new Insets(25, 30, 25, 30));
        contentBox.setStyle("-fx-background-color: #ffffff;");

        Label employeeLabel = new Label("👤 Employé: " + participation.getNomEmployee());
        employeeLabel.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 13px;");

        Label dateLabel = new Label("📅 Date d'inscription: " + participation.getDateInscription());
        dateLabel.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

        String statusColor = "#f39c12";
        String statusText = "En attente";
        if ("Approved".equals(participation.getStatutParticipation())) {
            statusColor = "#27ae60";
            statusText = "Accepté";
        } else if ("Rejected".equals(participation.getStatutParticipation())) {
            statusColor = "#e74c3c";
            statusText = "Refusé";
        }

        Label statusLabel = new Label("⚙️ Statut: " + statusText);
        statusLabel.setStyle("-fx-text-fill: " + statusColor + "; -fx-font-weight: bold; -fx-font-size: 12px;");

        if (participation.getResultat() != null && !participation.getResultat().isEmpty()) {
            Label resultLabel = new Label("🎯 Résultat: " + participation.getResultat());
            resultLabel.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");
            contentBox.getChildren().addAll(employeeLabel, dateLabel, statusLabel, resultLabel);
        } else {
            contentBox.getChildren().addAll(employeeLabel, dateLabel, statusLabel);
        }

        dialogPane.setContent(contentBox);

        ButtonType closeButton = new ButtonType("Fermer", ButtonBar.ButtonData.CANCEL_CLOSE);
        dialogPane.getButtonTypes().add(closeButton);

        Button btnClose = (Button) dialogPane.lookupButton(closeButton);
        btnClose.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand;");

        dialog.showAndWait();
    }

    // ==================== FORMATION HANDLERS ====================

    @FXML
    private void handleAddFormation() {
        Dialog<Formation> dialog = createFormationDialog(null);
        Optional<Formation> result = dialog.showAndWait();

        result.ifPresent(formation -> {
            formationService.addFormation(formation);
            loadFormations();
            showAlert(Alert.AlertType.INFORMATION, "Succès", "✅ Formation ajoutée avec succès !");
        });
    }

    private void handleEditFormationFromCard(Formation formation) {
        if (formation == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une formation.");
            return;
        }

        Dialog<Formation> dialog = createFormationDialog(formation);
        Optional<Formation> result = dialog.showAndWait();

        result.ifPresent(updatedFormation -> {
            formationService.updateFormation(updatedFormation);
            loadFormations();
            showAlert(Alert.AlertType.INFORMATION, "Succès", "✅ Formation modifiée avec succès !");
        });
    }

    private void handleDeleteFormationFromCard(Formation formation) {
        if (formation == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une formation.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation de suppression");
        confirm.setHeaderText("Êtes-vous sûr de supprimer cette formation ?");
        confirm.setContentText("Formation : " + formation.getTitre()
                + "\n\n⚠️ Cette action ne peut pas être annulée !");

        DialogPane dialogPane = confirm.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #ffffff;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: #e74c3c;");
        Label headerTitle = new Label("🗑️ Supprimer la formation");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");
        Label headerSub = new Label("Cette action est permanente");
        headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 12px;");
        header.getChildren().addAll(headerTitle, headerSub);
        dialogPane.setHeader(header);

        for (ButtonType buttonType : dialogPane.getButtonTypes()) {
            Button btn = (Button) dialogPane.lookupButton(buttonType);
            if (buttonType == ButtonType.OK) {
                btn.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            } else {
                btn.setStyle("-fx-background-color: #D0D0D0; -fx-text-fill: #34495e; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            }
        }

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                formationService.deleteFormation(formation.getIdFormation());
                loadFormations();
                showAlert(Alert.AlertType.INFORMATION, "Succès", "✅ Formation supprimée avec succès !");
            }
        });
    }

    // ==================== SESSION HANDLERS ====================

    @FXML
    private void handleAddSession() {
        Dialog<SessionFormation> dialog = createSessionDialog(null);
        Optional<SessionFormation> result = dialog.showAndWait();

        result.ifPresent(session -> {
            sessionService.addSession(session);
            loadSessions();
            showAlert(Alert.AlertType.INFORMATION, "Succès", "✅ Session ajoutée avec succès !");
        });
    }

    private void handleEditSessionFromCard(SessionFormation session) {
        if (session == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une session.");
            return;
        }

        Dialog<SessionFormation> dialog = createSessionDialog(session);
        Optional<SessionFormation> result = dialog.showAndWait();

        result.ifPresent(updatedSession -> {
            sessionService.updateSession(updatedSession);
            loadSessions();
            showAlert(Alert.AlertType.INFORMATION, "Succès", "✅ Session modifiée avec succès !");
        });
    }

    private void handleDeleteSessionFromCard(SessionFormation session) {
        if (session == null) {
            showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez sélectionner une session.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation de suppression");
        confirm.setHeaderText("Êtes-vous sûr de supprimer cette session ?");
        confirm.setContentText("Session à " + session.getLieu()
                + " du " + session.getDateDebut()
                + " au " + session.getDateFin()
                + "\n\n⚠️ Cette action ne peut pas être annulée !");

        DialogPane dialogPane = confirm.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #ffffff;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: #e74c3c;");
        Label headerTitle = new Label("🗑️ Supprimer la session");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");
        Label headerSub = new Label("Cette action est permanente");
        headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 12px;");
        header.getChildren().addAll(headerTitle, headerSub);
        dialogPane.setHeader(header);

        for (ButtonType buttonType : dialogPane.getButtonTypes()) {
            Button btn = (Button) dialogPane.lookupButton(buttonType);
            if (buttonType == ButtonType.OK) {
                btn.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            } else {
                btn.setStyle("-fx-background-color: #D0D0D0; -fx-text-fill: #34495e; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5;");
            }
        }

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                try {
                    sessionService.deleteSession(session.getIdSession());
                    loadSessions();
                    showAlert(Alert.AlertType.INFORMATION, "Succès", "✅ Session supprimée avec succès !");
                } catch (Exception e) {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "❌ Erreur lors de la suppression de la session.");
                }
            }
        });
    }

    // ==================== DIALOG CREATORS ====================

    private Dialog<Formation> createFormationDialog(Formation formation) {
        Dialog<Formation> dialog = new Dialog<>();
        dialog.setTitle(formation == null ? "Nouvelle Formation" : "Modifier Formation");

        DialogPane dialogPane = dialog.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #ffffff;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: linear-gradient(to right, #022E69, #145EB7); -fx-background-radius: 0;");

        Label headerTitle = new Label(formation == null ? "🆕 Créer une nouvelle formation" : "✏️ Modifier la formation");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 20px; -fx-font-weight: bold;");

        Label headerSub = new Label("Remplissez les informations de la formation ci-dessous");
        headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 13px;");

        header.getChildren().addAll(headerTitle, headerSub);
        dialogPane.setHeader(header);

        ButtonType saveButtonType = new ButtonType("✅ ENREGISTRER", ButtonBar.ButtonData.OK_DONE);
        dialogPane.getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        String inputStyle = "-fx-background-color: #f8f9fa; -fx-border-color: #D0D0D0; -fx-border-radius: 6; -fx-background-radius: 6; -fx-padding: 10; -fx-font-size: 12px;";
        String labelStyle = "-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 13px;";

        GridPane grid = new GridPane();
        grid.setHgap(20);
        grid.setVgap(18);
        grid.setPadding(new Insets(30, 35, 25, 35));
        grid.setStyle("-fx-background-color: #ffffff;");

        // --- 1. TITRE ---
        Label lblErrorTitre = createErrorLabel();
        TextField txtTitre = new TextField(formation != null ? formation.getTitre() : "");
        txtTitre.setPromptText("Saisissez le titre de la formation...");
        txtTitre.setStyle(inputStyle);
        txtTitre.setPrefHeight(35);
        txtTitre.textProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal.trim().isEmpty()) {
                lblErrorTitre.setText("⚠️ Le titre est obligatoire");
            } else {
                lblErrorTitre.setText("");
            }
        });

        // --- 2. ORGANISME ---
        Label lblErrorOrg = createErrorLabel();
        TextField txtOrganisme = new TextField(formation != null ? formation.getOrganisme() : "");
        txtOrganisme.setPromptText("Nom de l'organisme de formation...");
        txtOrganisme.setStyle(inputStyle);
        txtOrganisme.setPrefHeight(35);
        txtOrganisme.textProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal.trim().isEmpty()) {
                lblErrorOrg.setText("⚠️ L'organisme est obligatoire");
            } else {
                lblErrorOrg.setText("");
            }
        });

        // --- 3. TYPE ---
        ComboBox<String> cbType = new ComboBox<>(FXCollections.observableArrayList(
                "Technique", "Soft Skills", "Management", "Langues", "Autre"
        ));
        cbType.setStyle(inputStyle);
        cbType.setMaxWidth(Double.MAX_VALUE);
        cbType.setPrefHeight(35);
        if (formation != null) cbType.setValue(formation.getType());
        else cbType.getSelectionModel().selectFirst();

        // --- 4. DURÉE ---
        Label lblErrorDuree = createErrorLabel();
        TextField txtDuree = new TextField(formation != null ? String.valueOf(formation.getDuree()) : "");
        txtDuree.setPromptText("Nombre de jours (ex: 5)");
        txtDuree.setStyle(inputStyle);
        txtDuree.setPrefHeight(35);
        txtDuree.textProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal.trim().isEmpty()) {
                lblErrorDuree.setText("⚠️ La durée est obligatoire");
            } else {
                try {
                    int d = Integer.parseInt(newVal);
                    if (d <= 0) lblErrorDuree.setText("⚠️ La durée doit être positive");
                    else lblErrorDuree.setText("");
                } catch (NumberFormatException e) {
                    lblErrorDuree.setText("⚠️ Saisissez un nombre entier");
                }
            }
        });

        // --- 5. DESCRIPTION ---
        TextArea txtDescription = new TextArea(formation != null ? formation.getDescription() : "");
        txtDescription.setPrefRowCount(2);
        txtDescription.setWrapText(true);
        txtDescription.setStyle(inputStyle);
        txtDescription.setPromptText("Décrivez le contenu et les objectifs généraux...");

        // --- 6. OBJECTIFS + BOUTON IA ---
        TextArea txtObjectifs = new TextArea(formation != null ? formation.getObjectifs() : "");
        txtObjectifs.setPrefRowCount(3);
        txtObjectifs.setWrapText(true);
        txtObjectifs.setStyle(inputStyle);
        txtObjectifs.setPromptText("Énumérez les objectifs d'apprentissage...");

        Button btnGenerateObjectifs = new Button("✨ Générer avec l'IA");
        btnGenerateObjectifs.setStyle("-fx-background-color: #3FA9F5; -fx-text-fill: white; -fx-background-radius: 20; -fx-cursor: hand; -fx-font-weight: bold; -fx-padding: 8 16; -fx-font-size: 12px;");

        btnGenerateObjectifs.setOnAction(e -> {
            String titreText = txtTitre.getText().trim();
            if (titreText.isEmpty()) {
                showAlert(Alert.AlertType.WARNING, "Attention", "Veuillez d'abord entrer le titre de la formation.");
                return;
            }
            btnGenerateObjectifs.setDisable(true);
            txtObjectifs.setText("⏳ Génération des objectifs en cours...");
            new Thread(() -> {
                try {
                    String generated = OpenAIService.generateObjectives(titreText);
                    javafx.application.Platform.runLater(() -> {
                        txtObjectifs.setText(generated);
                        btnGenerateObjectifs.setDisable(false);
                    });
                } catch (Exception ex) {
                    javafx.application.Platform.runLater(() -> {
                        btnGenerateObjectifs.setDisable(false);
                        txtObjectifs.setText("");
                        showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de générer les objectifs. Veuillez réessayer.");
                    });
                }
            }).start();
        });

        VBox objectifsBox = new VBox(10, txtObjectifs, btnGenerateObjectifs);
        objectifsBox.setAlignment(javafx.geometry.Pos.TOP_RIGHT);

        // --- AJOUT AU GRID ---
        grid.add(createStyledLabel("📋 Titre :"), 0, 0);
        grid.add(new VBox(5, txtTitre, lblErrorTitre), 1, 0);

        grid.add(createStyledLabel("🏢 Organisme :"), 0, 1);
        grid.add(new VBox(5, txtOrganisme, lblErrorOrg), 1, 1);

        grid.add(createStyledLabel("🎯 Type :"), 0, 2);
        grid.add(cbType, 1, 2);

        grid.add(createStyledLabel("⏱️ Durée (jours) :"), 0, 3);
        grid.add(new VBox(5, txtDuree, lblErrorDuree), 1, 3);

        grid.add(createStyledLabel("📝 Description :"), 0, 4);
        grid.add(txtDescription, 1, 4);

        grid.add(createStyledLabel("🎓 Objectifs :"), 0, 5);
        grid.add(objectifsBox, 1, 5);

        dialogPane.setContent(grid);

        Button btnSave = (Button) dialogPane.lookupButton(saveButtonType);
        btnSave.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");

        Button btnCancel = (Button) dialogPane.lookupButton(ButtonType.CANCEL);
        btnCancel.setStyle("-fx-background-color: #D0D0D0; -fx-text-fill: #34495e; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");

        btnSave.disableProperty().bind(txtTitre.textProperty().isEmpty().or(txtOrganisme.textProperty().isEmpty()).or(txtDuree.textProperty().isEmpty()));

        dialog.setResultConverter(db -> {
            if (db == saveButtonType) {
                int d = Integer.parseInt(txtDuree.getText());
                if (formation == null) return new Formation(txtTitre.getText(), txtDescription.getText(), cbType.getValue(), d, txtOrganisme.getText(), txtObjectifs.getText(), this.rhId);
                else {
                    formation.setTitre(txtTitre.getText());
                    formation.setDescription(txtDescription.getText());
                    formation.setType(cbType.getValue());
                    formation.setDuree(d);
                    formation.setOrganisme(txtOrganisme.getText());
                    formation.setObjectifs(txtObjectifs.getText());
                    return formation;
                }
            }
            return null;
        });

        return dialog;
    }

    private Label createStyledLabel(String text) {
        Label label = new Label(text);
        label.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 13px;");
        return label;
    }

    // Méthode utilitaire pour créer les labels d'erreur
    private Label createErrorLabel() {
        Label label = new Label();
        label.setTextFill(Color.RED);
        label.setFont(new Font(10));
        return label;
    }

    private LocalDate calculateEndDate(LocalDate startDate, int workingDays) {
        if (startDate == null || workingDays <= 0) {
            return startDate;
        }

        LocalDate currentDate = startDate;
        int daysAdded = 0;

        while (daysAdded < workingDays) {
            currentDate = currentDate.plusDays(1);
            if (currentDate.getDayOfWeek().getValue() != 6 && currentDate.getDayOfWeek().getValue() != 7) {
                daysAdded++;
            }
        }

        return currentDate;
    }


    private Dialog<SessionFormation> createSessionDialog(SessionFormation session) {
        Dialog<SessionFormation> dialog = new Dialog<>();
        dialog.setTitle(session == null ? "Nouvelle Session" : "Modifier une Session");

        // --- STYLE DU PANNEAU ---
        DialogPane dialogPane = dialog.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #ffffff;");

        // --- EN-TÊTE MODERNE ---
        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: linear-gradient(to right, #145EB7, #3FA9F5); -fx-background-radius: 0;");

        Label headerTitle = new Label(session == null ? "📅 Créer une nouvelle session" : "✏️ Modifier la session");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 20px; -fx-font-weight: bold;");

        Label headerSub = new Label("Configurez les détails de la session de formation");
        headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 13px;");

        header.getChildren().addAll(headerTitle, headerSub);
        dialogPane.setHeader(header);

        // --- BOUTONS ---
        ButtonType saveButtonType = new ButtonType("✅ ENREGISTRER", ButtonBar.ButtonData.OK_DONE);
        dialogPane.getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        String fieldStyle = "-fx-padding: 10; -fx-border-color: #D0D0D0; -fx-border-radius: 6; -fx-background-color: #f8f9fa; -fx-font-size: 12px;";

        GridPane grid = new GridPane();
        grid.setHgap(20);
        grid.setVgap(18);
        grid.setPadding(new Insets(30, 35, 25, 35));
        grid.setStyle("-fx-background-color: #ffffff;");

        final Formation[] selectedFormationRef = new Formation[1];

        if (session == null) {
            ComboBox<Formation> cbFormation = new ComboBox<>();
            cbFormation.setItems(formationsList);
            cbFormation.setStyle(fieldStyle);
            cbFormation.setPrefHeight(35);
            cbFormation.setConverter(new javafx.util.StringConverter<Formation>() {
                @Override
                public String toString(Formation f) {
                    return (f == null) ? "Sélectionnez une formation" : f.getTitre();
                }

                @Override
                public Formation fromString(String string) {
                    return null;
                }
            });
            cbFormation.setMaxWidth(Double.MAX_VALUE);
            cbFormation.valueProperty().addListener((obs, oldVal, newVal) -> selectedFormationRef[0] = newVal);

            grid.add(createStyledLabel("🎓 Formation :"), 0, 0);
            grid.add(cbFormation, 1, 0);
        } else {
            final Formation existingFormation = formationsList.stream()
                    .filter(f -> f.getIdFormation() == session.getIdFormation())
                    .findFirst().orElse(null);
            selectedFormationRef[0] = existingFormation;

            Label lblFormation = new Label();
            lblFormation.setStyle(fieldStyle + "; -fx-text-fill: #022E69; -fx-font-weight: bold;");
            lblFormation.setPrefHeight(35);
            if (existingFormation != null) {
                lblFormation.setText(existingFormation.getTitre());
            } else {
                lblFormation.setText("Formation inconnue");
            }

            grid.add(createStyledLabel("🎓 Formation :"), 0, 0);
            grid.add(lblFormation, 1, 0);
        }

        DatePicker dpDebut = new DatePicker(session != null ? session.getDateDebut() : LocalDate.now());
        dpDebut.setStyle(fieldStyle);
        dpDebut.setPrefHeight(35);

        DatePicker dpFin = new DatePicker(session != null ? session.getDateFin() : LocalDate.now().plusDays(7));
        dpFin.setStyle(fieldStyle);
        dpFin.setPrefHeight(35);
        dpFin.setDisable(true);
        dpFin.setStyle(fieldStyle + "; -fx-opacity: 0.7;");

        dpDebut.valueProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null && selectedFormationRef[0] != null) {
                int duree = selectedFormationRef[0].getDuree();
                LocalDate calculatedEndDate = calculateEndDate(newVal, duree);
                dpFin.setValue(calculatedEndDate);
            }
        });

        if (session == null) {
            ComboBox<Formation> cbFormationRef = null;
            for (var node : grid.getChildren()) {
                if (node instanceof ComboBox && GridPane.getRowIndex(node) == 0 && GridPane.getColumnIndex(node) == 1) {
                    cbFormationRef = (ComboBox<Formation>) node;
                    break;
                }
            }

            if (cbFormationRef != null) {
                final ComboBox<Formation> cbFormationFinal = cbFormationRef;
                cbFormationFinal.valueProperty().addListener((obs, oldVal, newVal) -> {
                    if (newVal != null && dpDebut.getValue() != null) {
                        int duree = newVal.getDuree();
                        LocalDate calculatedEndDate = calculateEndDate(dpDebut.getValue(), duree);
                        dpFin.setValue(calculatedEndDate);
                    }
                });
            }
        }

        TextField txtLieu = new TextField(session != null ? session.getLieu() : "");
        txtLieu.setPromptText("Saisissez le lieu de la session...");
        txtLieu.setStyle(fieldStyle);
        txtLieu.setPrefHeight(35);

        ComboBox<String> cbMode = new ComboBox<>();
        cbMode.setItems(FXCollections.observableArrayList("Présentiel", "Distanciel", "Hybride"));
        cbMode.setStyle(fieldStyle);
        cbMode.setPrefHeight(35);
        cbMode.setValue(session != null ? session.getMode() : "Présentiel");

        TextField txtCapacite = new TextField(session != null ? String.valueOf(session.getCapaciteMax()) : "20");
        txtCapacite.setPromptText("Nombre de places (ex: 20)");
        txtCapacite.setStyle(fieldStyle);
        txtCapacite.setPrefHeight(35);

        ComboBox<String> cbStatut = new ComboBox<>();
        cbStatut.setItems(FXCollections.observableArrayList("Planifiée", "EnCours", "Terminée", "Annulée"));
        cbStatut.setStyle(fieldStyle);
        cbStatut.setPrefHeight(35);
        cbStatut.setValue(session != null ? session.getStatut() : "Planifiée");

        // --- Ajout au grid avec labels stylisés ---
        grid.add(createStyledLabel("📅 Date de début :"), 0, 1);
        grid.add(dpDebut, 1, 1);

        // Label pour la date de fin avec indication "Automatique"
        VBox endDateBox = new VBox(3);
        Label lblEndDateLabel = createStyledLabel("📅 Date de fin :");
        Label lblEndDateInfo = new Label("(Calculée automatiquement)");
        lblEndDateInfo.setStyle("-fx-text-fill: #7f8c8d; -fx-font-size: 10px; -fx-font-style: italic;");
        endDateBox.getChildren().addAll(lblEndDateLabel, lblEndDateInfo);

        grid.add(endDateBox, 0, 2);
        grid.add(dpFin, 1, 2);

        grid.add(createStyledLabel("📍 Lieu :"), 0, 3);
        grid.add(txtLieu, 1, 3);

        grid.add(createStyledLabel("💻 Mode :"), 0, 4);
        grid.add(cbMode, 1, 4);

        grid.add(createStyledLabel("👥 Capacité maximum :"), 0, 5);
        grid.add(txtCapacite, 1, 5);

        grid.add(createStyledLabel("⚙️ Statut :"), 0, 6);
        grid.add(cbStatut, 1, 6);

        dialogPane.setContent(grid);

        // --- Styliser les boutons ---
        Button btnSave = (Button) dialogPane.lookupButton(saveButtonType);
        btnSave.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");

        Button btnCancel = (Button) dialogPane.lookupButton(ButtonType.CANCEL);
        btnCancel.setStyle("-fx-background-color: #D0D0D0; -fx-text-fill: #34495e; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == saveButtonType) {
                if (selectedFormationRef[0] == null) {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "Veuillez sélectionner une formation pour la session !");
                    return null;
                }

                try {
                    int capacite = Integer.parseInt(txtCapacite.getText());

                    // Validation des dates
                    if (dpDebut.getValue().isAfter(dpFin.getValue())) {
                        showAlert(Alert.AlertType.ERROR, "Erreur", "La date de début ne peut pas être après la date de fin !");
                        return null;
                    }

                    if (session == null) {
                        return new SessionFormation(
                                selectedFormationRef[0].getIdFormation(),
                                dpDebut.getValue(),
                                dpFin.getValue(),
                                txtLieu.getText(),
                                cbMode.getValue(),
                                capacite,
                                cbStatut.getValue()
                        );
                    } else {
                        session.setIdFormation(selectedFormationRef[0].getIdFormation());
                        session.setDateDebut(dpDebut.getValue());
                        session.setDateFin(dpFin.getValue());
                        session.setLieu(txtLieu.getText());
                        session.setMode(cbMode.getValue());
                        session.setCapaciteMax(capacite);
                        session.setStatut(cbStatut.getValue());
                        return session;
                    }
                } catch (NumberFormatException e) {
                    showAlert(Alert.AlertType.ERROR, "Erreur", "La capacité doit être un nombre entier !");
                    return null;
                }
            }
            return null;
        });

        return dialog;
    }

    // ==================== FILTER METHODS ====================

    private void filterFormations(String searchText) {
        formationsContainer.getChildren().clear();

        for (Formation formation : formationsList) {
            if (searchText == null || searchText.isEmpty() ||
                    formation.getTitre().toLowerCase().contains(searchText.toLowerCase()) ||
                    formation.getType().toLowerCase().contains(searchText.toLowerCase()) ||
                    formation.getOrganisme().toLowerCase().contains(searchText.toLowerCase())) {

                VBox card = createFormationCard(formation);
                formationsContainer.getChildren().add(card);
            }
        }
    }

    // ==================== STATS UPDATE METHODS ====================

    private void updateSessionStats() {
        if (lblTotalSessions == null || lblSessionsPlanifiees == null ||
                lblSessionsEnCours == null || lblSessionsTerminees == null) {
            return;
        }

        int total = sessionsList.size();
        int planifiees = 0;
        int enCours = 0;
        int terminees = 0;

        for (SessionFormation s : sessionsList) {
            switch (s.getStatut()) {
                case "Planifiée" -> planifiees++;
                case "EnCours" -> enCours++;
                case "Terminée" -> terminees++;
            }
        }

        lblTotalSessions.setText("Total : " + total);
        lblSessionsPlanifiees.setText("Planifiées : " + planifiees);
        lblSessionsEnCours.setText("En cours : " + enCours);
        lblSessionsTerminees.setText("Terminées : " + terminees);
    }


    // ==================== UTILITY METHODS ====================

    private void showAlert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);

        // Style moderne pour les alertes
        DialogPane dialogPane = alert.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #f5f5f5;");

        // En-tête stylisé selon le type d'alerte
        VBox header = new VBox(10);
        header.setPadding(new Insets(20));
        header.setAlignment(Pos.CENTER_LEFT);

        String headerColor = "";
        String iconEmoji = "";
        switch (type) {
            case INFORMATION -> {
                headerColor = "#3FA9F5";
                iconEmoji = "ℹ️";
            }
            case WARNING -> {
                headerColor = "#f39c12";
                iconEmoji = "⚠️";
            }
            case ERROR -> {
                headerColor = "#e74c3c";
                iconEmoji = "❌";
            }
            case CONFIRMATION -> {
                headerColor = "#27ae60";
                iconEmoji = "❓";
            }
        }

        header.setStyle("-fx-background-color: " + headerColor + ";");
        Label headerLabel = new Label(iconEmoji + " " + title);
        headerLabel.setStyle("-fx-text-fill: white; -fx-font-size: 16px; -fx-font-weight: bold;");
        header.getChildren().add(headerLabel);

        dialogPane.setHeader(header);

        // Styliser le contenu
        Label contentLabel = new Label(message);
        contentLabel.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");
        contentLabel.setWrapText(true);
        VBox content = new VBox(15);
        content.setPadding(new Insets(20, 25, 25, 25));
        content.getChildren().add(contentLabel);
        dialogPane.setContent(content);

        // Styliser les boutons
        for (ButtonType buttonType : dialogPane.getButtonTypes()) {
            Button btn = (Button) dialogPane.lookupButton(buttonType);
            btn.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 8 20; -fx-background-radius: 5;");
        }

        alert.showAndWait();
    }

    @FXML
    private void handleRefreshFormations() {
        loadFormations();
    }
}
