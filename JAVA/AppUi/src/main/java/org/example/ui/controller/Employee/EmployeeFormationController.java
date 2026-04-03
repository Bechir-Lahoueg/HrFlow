package org.example.ui.controller.Employee;

import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import org.example.model.Employee;
import org.example.models.Formation;
import org.example.models.ParticipationFormation;
import org.example.models.SessionFormation;
import org.example.services.FormationService;
import org.example.services.ParticipationFormationService;
import org.example.services.SessionFormationService;
import org.example.services.TTSService;
import org.example.services.QRCodeService;
import org.example.services.CertificateService;

import java.util.List;
import java.util.Optional;
import javafx.scene.layout.VBox;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import javafx.scene.layout.Priority;
import javafx.scene.control.Label;
import javafx.scene.control.Button;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import java.time.LocalDate;

public class EmployeeFormationController {

    // ====================FXML FIELDS ====================
    @FXML private VBox vboxFormations;
    @FXML private ComboBox<Formation> cbFormation;
    @FXML private VBox vboxSessions;

    // Tab Mes Inscriptions
    @FXML private VBox vboxInscriptions;
    @FXML private TextField searchInscriptions;
    @FXML private Button btnRefreshInscriptions;

    // ==================== MODERN NAVIGATION ====================
    @FXML private HBox tabNavBar;
    @FXML private Button btnRefresh;
    @FXML private Button btnTabFormations;
    @FXML private Button btnTabSessions;
    @FXML private Button btnTabInscriptions;
    @FXML private StackPane contentStackPane;
    @FXML private VBox formationsPanel;
    @FXML private VBox sessionsPanel;
    @FXML private VBox inscriptionsPanel;

    // ==================== SERVICES ====================
    private final FormationService formationService = new FormationService();
    private final SessionFormationService sessionService = new SessionFormationService();
    private final ParticipationFormationService participationService = new ParticipationFormationService();
    private final CertificateService certificateService = new CertificateService();

    // ==================== DATA ====================
    private Employee currentEmployee;
    private final ObservableList<Formation> formationsList = FXCollections.observableArrayList();
    private final ObservableList<SessionFormation> sessionsList = FXCollections.observableArrayList();
    private final ObservableList<ParticipationFormation> inscriptionsList = FXCollections.observableArrayList();

    // ==================== INITIALIZATION ====================

    @FXML
    public void initialize() {
        setupModernNavigation();
        loadFormations();
        setupInscriptionsTable();

        cbFormation.setConverter(new javafx.util.StringConverter<Formation>() {
            @Override
            public String toString(Formation object) {
                return object == null ? "Toutes les formations" : object.getTitre();
            }

            @Override
            public Formation fromString(String string) {
                return null; // Pas utilisé
            }
        });

        // Listener pour charger les sessions quand une formation est sélectionnée
        cbFormation.valueProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null) {
                loadSessionsForFormation(newVal.getIdFormation());
            } else {
                loadAllSessions();
            }
        });
    }

    public void setCurrentEmployee(Employee employee) {
        this.currentEmployee = employee;
        loadData();
    }

    // ==================== MODERN NAVIGATION ====================

    private void setupModernNavigation() {
        if (btnTabFormations != null) {
            btnTabFormations.setOnAction(e -> navigateToPanel("formations"));
        }
        if (btnTabSessions != null) {
            btnTabSessions.setOnAction(e -> navigateToPanel("sessions"));
        }
        if (btnTabInscriptions != null) {
            btnTabInscriptions.setOnAction(e -> navigateToPanel("inscriptions"));
        }

        navigateToPanel("formations");
    }

    private void navigateToPanel(String panelName) {
        if (formationsPanel != null) formationsPanel.setVisible(false);
        if (sessionsPanel != null) sessionsPanel.setVisible(false);
        if (inscriptionsPanel != null) inscriptionsPanel.setVisible(false);

        updateNavigationButtonStyles(panelName);

        switch (panelName.toLowerCase()) {
            case "formations":
                if (formationsPanel != null) formationsPanel.setVisible(true);
                break;
            case "sessions":
                if (sessionsPanel != null) sessionsPanel.setVisible(true);
                break;
            case "inscriptions":
                if (inscriptionsPanel != null) inscriptionsPanel.setVisible(true);
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
        if (btnTabInscriptions != null) {
            btnTabInscriptions.setStyle("-fx-background-color: transparent; -fx-text-fill: #a0aec0; -fx-padding: 16 20; -fx-cursor: hand; -fx-font-weight: bold; -fx-font-size: 13px; -fx-border-width: 0 0 3 0; -fx-border-color: transparent; -fx-border-radius: 0; -fx-effect: null;");
        }

        String activeStyle = "-fx-background-color: transparent; -fx-text-fill: #667eea; -fx-padding: 16 20; -fx-cursor: hand; -fx-font-weight: bold; -fx-font-size: 13px; -fx-border-width: 0 0 3 0; -fx-border-color: #667eea; -fx-border-radius: 0; -fx-effect: null;";
        switch (activePanel.toLowerCase()) {
            case "formations":
                if (btnTabFormations != null) btnTabFormations.setStyle(activeStyle);
                break;
            case "sessions":
                if (btnTabSessions != null) btnTabSessions.setStyle(activeStyle);
                break;
            case "inscriptions":
                if (btnTabInscriptions != null) btnTabInscriptions.setStyle(activeStyle);
                break;
        }
    }

    private void loadData() {
        loadFormations();
        loadAllSessions();
        loadInscriptions();
        displayInscriptions("");
    }

    // ==================== TABLE SETUP ====================

    private VBox createFormationCard(Formation f) {
        VBox card = new VBox(8);
        card.setPadding(new Insets(12));
        card.setStyle("-fx-background-color: white; -fx-border-radius: 10; -fx-background-radius: 10;" +
                "-fx-border-color: #D0D0D0; -fx-border-width: 1; -fx-effect: dropshadow(gaussian, rgba(0,0,0,0.08), 6,0,0,2);");

        Label title = new Label(f.getTitre());
        title.setStyle("-fx-font-size: 16px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

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

        Label type = new Label("🎯 " + f.getType());
        type.setStyle("-fx-text-fill: #34495e;");

        Label duree = new Label("⏱️ Durée: " + f.getDuree() + " jours");
        duree.setStyle("-fx-text-fill: #7f8c8d; -fx-font-style: italic;");

        Label objectifs = new Label("🎯 Objectifs: " + f.getObjectifs());
        objectifs.setWrapText(true);



        Button qrBtn = new Button("scanner QR Code");
        qrBtn.setOnAction(e -> handleAfficherQRCode(f));
        qrBtn.setStyle("-fx-background-color: #022E69; -fx-text-fill: white; -fx-background-radius: 6;");

        Button btnEcouter = new Button("🎧 Écouter");
        btnEcouter.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-background-radius: 6;");
        btnEcouter.setOnAction(e -> handleEcouterFormation(f));

        card.getChildren().addAll(title, ratingBox, type, duree, objectifs, btnEcouter, qrBtn);
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

    private VBox createSessionCard(SessionFormation session) {
        VBox card = new VBox(10);
        card.setPadding(new Insets(15));
        card.setPrefWidth(300);
        card.setCursor(javafx.scene.Cursor.HAND);
        card.setStyle("-fx-background-color: white; -fx-border-color: #D0D0D0; -fx-border-radius: 8; -fx-background-radius: 8; -fx-border-width: 1; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 6, 0, 0, 2);");

        javafx.scene.effect.DropShadow hoverShadow = new javafx.scene.effect.DropShadow();
        hoverShadow.setRadius(6);
        hoverShadow.setOffsetX(0);
        hoverShadow.setOffsetY(2);
        hoverShadow.setColor(javafx.scene.paint.Color.rgb(20, 94, 183, 0.2));

        card.setOnMouseEntered(e -> card.setEffect(hoverShadow));
        card.setOnMouseExited(e -> card.setEffect(null));

        String nomFormation = "Formation inconnue";
        for (Formation f : formationsList) {
            if (f.getIdFormation() == session.getIdFormation()) {
                nomFormation = f.getTitre();
                break;
            }
        }

        Label lblFormation = new Label("📚 " + nomFormation);
        lblFormation.setFont(javafx.scene.text.Font.font("System", javafx.scene.text.FontWeight.BOLD, 14));
        lblFormation.setStyle("-fx-text-fill: #022E69;");
        lblFormation.setWrapText(true);
        lblFormation.setMaxWidth(280);

        HBox header = new HBox(8);
        header.setAlignment(Pos.CENTER_LEFT);
        Label lblLieu = new Label("📍 " + session.getLieu());
        lblLieu.setFont(javafx.scene.text.Font.font("System", javafx.scene.text.FontWeight.BOLD, 13));
        lblLieu.setStyle("-fx-text-fill: #145EB7;");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Label lblCap = new Label(session.getCapaciteMax() + " places");
        lblCap.setStyle("-fx-text-fill: #7f8c8d; -fx-font-size: 11px;");
        header.getChildren().addAll(lblLieu, spacer, lblCap);

        Label lblDate = new Label("📅 " + session.getDateDebut() + " au " + session.getDateFin());
        lblDate.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

        Label lblMode = new Label("💻 Mode: " + session.getMode());
        lblMode.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

   /// ///////////////////
        java.time.LocalDate today = java.time.LocalDate.now();
        String statutCalcule;
        String color;

        if (today.isBefore(session.getDateDebut())) {
            statutCalcule = "Planifiée";
            color = "#f39c12";
        } else if (today.isAfter(session.getDateFin())) {
            statutCalcule = "Terminée";
            color = "#3FA9F5";
        } else {
            statutCalcule = "En cours";
            color = "#27ae60";
        }

        Label lblStatut = new Label("● " + statutCalcule);
        lblStatut.setStyle("-fx-text-fill: " + color + "; -fx-font-weight: bold; -fx-font-size: 12px;");

        HBox buttonBox = new HBox(8);
        buttonBox.setAlignment(Pos.CENTER);
        buttonBox.setPadding(new Insets(10, 0, 0, 0));

        Button btnInscrire = new Button("✅ S'inscrire");
        btnInscrire.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-padding: 8 16; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");
        btnInscrire.setOnAction(e -> handleInscriptionForCard(session));

        Button btnDetails = new Button("📋 Détails");
        btnDetails.setStyle("-fx-background-color: #3FA9F5; -fx-text-fill: white; -fx-padding: 8 16; -fx-background-radius: 5; -fx-cursor: hand; -fx-font-size: 12px;");
        btnDetails.setOnAction(e -> showSessionDetails(session));

        buttonBox.getChildren().addAll(btnInscrire, btnDetails);

        card.getChildren().addAll(lblFormation, header, lblDate, lblMode, lblStatut, buttonBox);
        return card;
    }

    private void showSessionDetails(SessionFormation session) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("Détails de la session");
        dialog.setHeaderText(null);

        DialogPane dialogPane = dialog.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #f5f5f5;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: linear-gradient(to right, #145EB7, #3FA9F5);");

        Label headerTitle = new Label("📅 Détails de la session");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");

        Label headerSub = new Label(session.getLieu());
        headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 12px;");

        header.getChildren().addAll(headerTitle, headerSub);
        dialogPane.setHeader(header);

        VBox contentBox = new VBox(15);
        contentBox.setPadding(new Insets(25, 30, 25, 30));
        contentBox.setStyle("-fx-background-color: #ffffff;");

        String formationTitle = getFormationTitle(session.getIdSession());

        Label lblFormation = new Label("📚 Formation: " + formationTitle);
        lblFormation.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 13px;");

        Label lblDate = new Label("📅 Période: " + session.getDateDebut() + " au " + session.getDateFin());
        lblDate.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

        Label lblLieu = new Label("📍 Lieu: " + session.getLieu());
        lblLieu.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

        Label lblMode = new Label("💻 Mode: " + session.getMode());
        lblMode.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

        Label lblCapacite = new Label("👥 Capacité: " + session.getCapaciteMax() + " places");
        lblCapacite.setStyle("-fx-text-fill: #34495e; -fx-font-size: 12px;");

        Label lblStatut = new Label("⚙️ Statut: " + session.getStatut());
        String statutColor = "#7f8c8d";
        if ("Planifiée".equals(session.getStatut())) {
            statutColor = "#f39c12";
        } else if ("EnCours".equals(session.getStatut())) {
            statutColor = "#27ae60";
        } else if ("Terminée".equals(session.getStatut())) {
            statutColor = "#3FA9F5";
        }
        lblStatut.setStyle("-fx-text-fill: " + statutColor + "; -fx-font-weight: bold; -fx-font-size: 12px;");

        contentBox.getChildren().addAll(lblFormation, lblDate, lblLieu, lblMode, lblCapacite, lblStatut);
        dialogPane.setContent(contentBox);

        ButtonType closeButton = new ButtonType("Fermer", ButtonBar.ButtonData.CANCEL_CLOSE);
        dialogPane.getButtonTypes().add(closeButton);

        Button btnClose = (Button) dialogPane.lookupButton(closeButton);
        btnClose.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand;");

        dialog.showAndWait();
    }

    private void setupInscriptionsTable() {
        if (searchInscriptions != null) {
            searchInscriptions.textProperty().addListener((obs, oldVal, newVal) -> {
                displayInscriptions(newVal);
            });
        }

        if (btnRefreshInscriptions != null) {
            btnRefreshInscriptions.setOnAction(e -> {
                loadInscriptions();
                displayInscriptions("");
            });
        }
    }

    // ==================== DATA LOADING ====================

    private void loadFormations() {
        vboxFormations.getChildren().clear();
        try {
            if (currentEmployee == null) {
                return;
            }

            List<Formation> formations = formationService.getFormationsByRh(currentEmployee.getRhId());
            formationsList.clear();
            formationsList.addAll(formations);


            cbFormation.setItems(formationsList);


            for (Formation f : formations) {
                vboxFormations.getChildren().add(createFormationCard(f));
            }
        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger les formations: " + e.getMessage());
        }
    }

    private void loadSessionsForFormation(Integer idFormation) {
        vboxSessions.getChildren().clear();

        try {
            List<SessionFormation> allSessions = sessionService.getAllSessions();
            int sessionCount = 0;

            for (SessionFormation session : allSessions) {
                // Filtrer par formation sélectionnée si idFormation n'est pas null
                if (idFormation != null && session.getIdFormation() != idFormation) {
                    continue;
                }

                boolean belongsToEmployeeRh = false;
                for (Formation f : formationsList) {
                    if (f.getIdFormation() == session.getIdFormation()) {
                        belongsToEmployeeRh = true;
                        break;
                    }
                }

                if (belongsToEmployeeRh) {
                    VBox card = createSessionCard(session);
                    vboxSessions.getChildren().add(card);
                    sessionCount++;
                }
            }

            if (sessionCount == 0) {
                displayNoSessionsMessage();
            }
        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger les sessions: " + e.getMessage());
        }
    }

    private void loadAllSessions() {
        vboxSessions.getChildren().clear();

        try {
            List<SessionFormation> allSessions = sessionService.getAllSessions();
            int sessionCount = 0;

            for (SessionFormation session : allSessions) {
                boolean belongsToEmployeeRh = false;
                for (Formation f : formationsList) {
                    if (f.getIdFormation() == session.getIdFormation()) {
                        belongsToEmployeeRh = true;
                        break;
                    }
                }

                if (belongsToEmployeeRh) {
                    VBox card = createSessionCard(session);
                    vboxSessions.getChildren().add(card);
                    sessionCount++;
                }
            }

            if (sessionCount == 0) {
                displayNoSessionsMessage();
            }
        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger les sessions: " + e.getMessage());
        }
    }

    private void displayNoSessionsMessage() {
        VBox emptyBox = new VBox();
        emptyBox.setSpacing(15);
        emptyBox.setAlignment(Pos.CENTER);
        emptyBox.setPadding(new Insets(50, 30, 50, 30));
        emptyBox.setStyle("-fx-background-color: #f8f9fa; -fx-border-radius: 10; -fx-background-radius: 10; -fx-border-color: #D0D0D0; -fx-border-width: 1;");

        Label iconLabel = new Label("📭");
        iconLabel.setStyle("-fx-font-size: 48px;");

        Label titleLabel = new Label("Aucune session disponible");
        titleLabel.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

        Label messageLabel = new Label("Il n'y a actuellement aucune session disponible pour cette formation.\nRevenez plus tard ou sélectionnez une autre formation.");
        messageLabel.setStyle("-fx-font-size: 13px; -fx-text-fill: #34495e; -fx-text-alignment: center;");
        messageLabel.setWrapText(true);

        emptyBox.getChildren().addAll(iconLabel, titleLabel, messageLabel);
        vboxSessions.getChildren().add(emptyBox);
    }

    private void loadInscriptions() {
        try {
            if (currentEmployee == null) return;


            List<ParticipationFormation> allParticipations = participationService.getAllParticipations();
            inscriptionsList.clear();
            for (ParticipationFormation p : allParticipations) {
                if (p.getIdEmployee() == currentEmployee.getId()) {
                    inscriptionsList.add(p);
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger vos inscriptions: " + e.getMessage());
        }
    }

    // ==================== BUTTON HANDLERS ====================



    @FXML
    private void handleRefresh() {
        loadData();

        if (cbFormation.getValue() != null) {
            loadSessionsForFormation(cbFormation.getValue().getIdFormation());
        }

        showAlert(Alert.AlertType.INFORMATION, "Actualisé", "Les données ont été actualisées avec succès !");
    }

    private void handleInscriptionForCard(SessionFormation session) {
        try {
            if (isAlreadyRegistered(session.getIdSession())) {
                showAlert(Alert.AlertType.WARNING, "Déjà inscrit",
                        "Vous êtes déjà inscrit à cette session.");
                return;
            }

            Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
            confirm.setTitle("Confirmer l'inscription");

            DialogPane dialogPane = confirm.getDialogPane();
            dialogPane.setStyle("-fx-background-color: #ffffff;");

            VBox header = new VBox(8);
            header.setPadding(new Insets(25));
            header.setStyle("-fx-background-color: linear-gradient(to right, #145EB7, #3FA9F5);");

            Label headerTitle = new Label("✅ S'inscrire à cette session");
            headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");

            Label headerSub = new Label("Confirmez votre inscription");
            headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 12px;");

            header.getChildren().addAll(headerTitle, headerSub);
            dialogPane.setHeader(header);

            VBox contentBox = new VBox(12);
            contentBox.setPadding(new Insets(20, 25, 25, 25));

            Label lblSession = new Label("📅 Période: " + session.getDateDebut() + " → " + session.getDateFin());
            lblSession.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");

            Label lblLieu = new Label("📍 Lieu: " + session.getLieu());
            lblLieu.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");

            Label lblMode = new Label("💻 Mode: " + session.getMode());
            lblMode.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");

            Label infoLabel = new Label("Êtes-vous sûr de vouloir vous inscrire à cette session ?");
            infoLabel.setStyle("-fx-text-fill: #145EB7; -fx-font-weight: bold; -fx-font-size: 12px;");
            infoLabel.setWrapText(true);

            contentBox.getChildren().addAll(lblSession, lblLieu, lblMode, new Separator(), infoLabel);
            dialogPane.setContent(contentBox);

            for (ButtonType buttonType : dialogPane.getButtonTypes()) {
                Button btn = (Button) dialogPane.lookupButton(buttonType);
                if (buttonType == ButtonType.OK) {
                    btn.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand;");
                } else {
                    btn.setStyle("-fx-background-color: #D0D0D0; -fx-text-fill: #34495e; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand;");
                }
            }

            confirm.showAndWait().ifPresent(response -> {
                if (response == ButtonType.OK) {
                    try {

                        ParticipationFormation participation = new ParticipationFormation(
                                session.getIdSession(),
                                currentEmployee.getId(),
                                java.time.LocalDate.now(),
                                "Pending",
                                ""
                        );

                        participationService.addParticipation(participation);

                        loadInscriptions();
                        if (cbFormation.getValue() != null) {
                            loadSessionsForFormation(cbFormation.getValue().getIdFormation());
                        }

                        showAlert(Alert.AlertType.INFORMATION, "Succès",
                                "Votre inscription a été enregistrée avec succès !");

                    } catch (Exception e) {
                        e.printStackTrace();
                        showAlert(Alert.AlertType.ERROR, "Erreur",
                                "Erreur lors de l'inscription: " + e.getMessage());
                    }
                }
            });

        } catch (Exception ex) {
            showAlert(Alert.AlertType.ERROR, "Erreur",
                    "Impossible de s'inscrire : " + ex.getMessage());
        }
    }

    // ==================== HELPER METHODS ====================

    private boolean isAlreadyRegistered(Integer idSession) {
        return inscriptionsList.stream()
            .anyMatch(p -> Integer.valueOf(p.getIdSession()).equals(idSession));
    }

    private String getFormationTitle(Integer idSession) {
        try {
            for (SessionFormation session : sessionService.getAllSessions()) {
                if (session.getIdSession() == idSession) {
                    for (Formation formation : formationService.getAllFormations()) {
                        if (formation.getIdFormation() == session.getIdFormation()) {
                            return formation.getTitre();
                        }
                    }
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return "N/A";
    }

    private String getSessionInfo(Integer idSession) {
        try {
            for (SessionFormation session : sessionService.getAllSessions()) {
                if (session.getIdSession() == idSession) {
                    return session.getDateDebut() + " - " + session.getLieu();
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return "N/A";
    }

    private void showAlert(Alert.AlertType type, String title, String content) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(content);

        DialogPane dialogPane = alert.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #f5f5f5;");

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

        Label contentLabel = new Label(content);
        contentLabel.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");
        contentLabel.setWrapText(true);
        VBox contentBox = new VBox(15);
        contentBox.setPadding(new Insets(20, 25, 25, 25));
        contentBox.getChildren().add(contentLabel);
        dialogPane.setContent(contentBox);

        for (ButtonType buttonType : dialogPane.getButtonTypes()) {
            Button btn = (Button) dialogPane.lookupButton(buttonType);
            btn.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 8 20; -fx-background-radius: 5;");
        }

        alert.showAndWait();
    }

    

   

    private void displayInscriptions(String searchText) {
        vboxInscriptions.getChildren().clear();

        try {
            List<ParticipationFormation> employeeParticipations = inscriptionsList.stream()
                    .filter(p -> p.getIdEmployee() == currentEmployee.getId())
                    .toList();

            if (employeeParticipations.isEmpty()) {
                displayNoInscriptionsMessage();
                return;
            }

            java.util.Map<Formation, List<ParticipationFormation>> participationsByFormation = new java.util.HashMap<>();

            for (ParticipationFormation participation : employeeParticipations) {
                SessionFormation session = null;
                for (SessionFormation s : sessionService.getAllSessions()) {
                    if (s.getIdSession() == participation.getIdSession()) {
                        session = s;
                        break;
                    }
                }

                if (session != null) {
                    Formation formation = null;
                    for (Formation f : formationsList) {
                        if (f.getIdFormation() == session.getIdFormation()) {
                            formation = f;
                            break;
                        }
                    }

                    if (formation != null) {
                        if (searchText == null || searchText.isEmpty() ||
                                formation.getTitre().toLowerCase().contains(searchText.toLowerCase())) {
                            participationsByFormation.computeIfAbsent(formation, k -> new java.util.ArrayList<>())
                                    .add(participation);
                        }
                    }
                }
            }

            if (participationsByFormation.isEmpty()) {
                displayNoInscriptionsMessage();
                return;
            }

            for (Formation formation : participationsByFormation.keySet()) {
                VBox formationCard = createInscriptionFormationCard(formation, participationsByFormation.get(formation));
                vboxInscriptions.getChildren().add(formationCard);
            }

        } catch (Exception e) {
            e.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible de charger les inscriptions: " + e.getMessage());
        }
    }

    private void displayNoInscriptionsMessage() {
        VBox emptyBox = new VBox();
        emptyBox.setSpacing(15);
        emptyBox.setAlignment(Pos.CENTER);
        emptyBox.setPadding(new Insets(50, 30, 50, 30));
        emptyBox.setStyle("-fx-background-color: #f8f9fa; -fx-border-radius: 10; -fx-background-radius: 10; -fx-border-color: #D0D0D0; -fx-border-width: 1;");

        Label iconLabel = new Label("📭");
        iconLabel.setStyle("-fx-font-size: 48px;");

        Label titleLabel = new Label("Aucune inscription");
        titleLabel.setStyle("-fx-font-size: 18px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

        Label messageLabel = new Label("Vous n'avez pas encore d'inscriptions.\nAllez à l'onglet 'Sessions' pour vous inscrire à une formation.");
        messageLabel.setStyle("-fx-font-size: 13px; -fx-text-fill: #34495e; -fx-text-alignment: center;");
        messageLabel.setWrapText(true);

        emptyBox.getChildren().addAll(iconLabel, titleLabel, messageLabel);
        vboxInscriptions.getChildren().add(emptyBox);
    }

    private VBox createInscriptionFormationCard(Formation formation, List<ParticipationFormation> participations) {
        VBox mainCard = new VBox(15);
        mainCard.setPadding(new Insets(20));
        mainCard.setStyle("-fx-background-color: white; -fx-border-color: #D0D0D0; -fx-border-radius: 10; -fx-background-radius: 10; -fx-border-width: 1; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 6, 0, 0, 2);");

        VBox header = new VBox(8);
        Label titleLabel = new Label("📚 " + formation.getTitre());
        titleLabel.setStyle("-fx-font-size: 16px; -fx-font-weight: bold; -fx-text-fill: #022E69;");

        Label typeLabel = new Label("🎯 " + formation.getType() + " • ⏱️ " + formation.getDuree() + " jours");
        typeLabel.setStyle("-fx-font-size: 12px; -fx-text-fill: #7f8c8d;");

        header.getChildren().addAll(titleLabel, typeLabel);
        mainCard.getChildren().add(header);

        Separator sep1 = new Separator();
        sep1.setStyle("-fx-border-color: #e0e0e0;");
        mainCard.getChildren().add(sep1);

        List<ParticipationFormation> pendingParticipations = participations.stream()
                .filter(p -> "Pending".equals(p.getStatutParticipation()))
                .toList();

        List<ParticipationFormation> approvedParticipations = participations.stream()
                .filter(p -> "Approved".equals(p.getStatutParticipation()))
                .toList();

        List<ParticipationFormation> rejectedParticipations = participations.stream()
                .filter(p -> "Rejected".equals(p.getStatutParticipation()))
                .toList();

        if (!pendingParticipations.isEmpty()) {
            VBox pendingSection = new VBox(10);
            Label pendingTitle = new Label("⏳ Demandes en attente (" + pendingParticipations.size() + ")");
            pendingTitle.setStyle("-fx-font-weight: bold; -fx-text-fill: #f39c12; -fx-font-size: 13px;");
            pendingSection.getChildren().add(pendingTitle);

            for (ParticipationFormation p : pendingParticipations) {
                VBox participationCard = createParticipationCard(p, "pending");
                pendingSection.getChildren().add(participationCard);
            }
            mainCard.getChildren().add(pendingSection);
        }

        if (!approvedParticipations.isEmpty()) {
            VBox approvedSection = new VBox(10);
            Label approvedTitle = new Label("✅ Inscriptions acceptées (" + approvedParticipations.size() + ")");
            approvedTitle.setStyle("-fx-font-weight: bold; -fx-text-fill: #27ae60; -fx-font-size: 13px;");
            approvedSection.getChildren().add(approvedTitle);

            for (ParticipationFormation p : approvedParticipations) {
                VBox participationCard = createParticipationCard(p, "approved");
                approvedSection.getChildren().add(participationCard);
            }
            mainCard.getChildren().add(approvedSection);
        }

        if (!rejectedParticipations.isEmpty()) {
            VBox rejectedSection = new VBox(10);
            Label rejectedTitle = new Label("❌ Inscriptions refusées (" + rejectedParticipations.size() + ")");
            rejectedTitle.setStyle("-fx-font-weight: bold; -fx-text-fill: #e74c3c; -fx-font-size: 13px;");
            rejectedSection.getChildren().add(rejectedTitle);

            for (ParticipationFormation p : rejectedParticipations) {
                VBox participationCard = createParticipationCard(p, "rejected");
                rejectedSection.getChildren().add(participationCard);
            }
            mainCard.getChildren().add(rejectedSection);
        }

        return mainCard;
    }

    private VBox createParticipationCard(ParticipationFormation participation, String status) {
        VBox card = new VBox(8);
        card.setPadding(new Insets(12));
        card.setStyle("-fx-background-color: #f8f9fa; -fx-border-radius: 6; -fx-background-radius: 6; -fx-border-color: #E0E0E0; -fx-border-width: 1;");

        String sessionInfo = getSessionInfo(participation.getIdSession());

        Label sessionLabel = new Label("📅 " + sessionInfo);
        sessionLabel.setStyle("-fx-font-size: 12px; -fx-text-fill: #034495e;");

        Label dateLabel = new Label("📆 Inscrit le: " + participation.getDateInscription());
        dateLabel.setStyle("-fx-font-size: 11px; -fx-text-fill: #7f8c8d;");

        String statusColor = "#f39c12";
        String statusLabel = "⏳ En attente";

        if ("approved".equals(status)) {
            statusColor = "#27ae60";
            statusLabel = "✅ Acceptée";
        } else if ("rejected".equals(status)) {
            statusColor = "#e74c3c";
            statusLabel = "❌ Refusée";
        }

        Label statusBadge = new Label(statusLabel);
        statusBadge.setStyle("-fx-text-fill: " + statusColor + "; -fx-font-weight: bold; -fx-font-size: 11px;");

        HBox buttonBox = new HBox(8);
        buttonBox.setAlignment(Pos.CENTER_RIGHT);


        //logique bouton telecharger certificat---
        if ("approved".equals(status)) {
            SessionFormation session = sessionService.getSessionById(participation.getIdSession());
            if (session != null) {
                LocalDate today = LocalDate.now();
                if (!today.isBefore(session.getDateFin())) {
                    Button btnDownloadCert = new Button("🎓 TéléchargerCertificat");
                    btnDownloadCert.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white; -fx-padding: 6 12; -fx-background-radius: 4; -fx-cursor: hand; -fx-font-size: 11px;");

                    btnDownloadCert.setOnAction(e -> {
                        String formationTitre = getFormationTitle(session.getIdSession());

                        String employeeFullName = currentEmployee.getFullName();

                        String organismeName = "N/A";
                        for (Formation f : formationsList) {
                            if (f.getIdFormation() == session.getIdFormation()) {
                                organismeName = f.getOrganisme();
                                break;
                            }
                        }

                        certificateService.generateCertificate(
                                employeeFullName,
                                formationTitre,
                                java.sql.Date.valueOf(session.getDateDebut()),
                                java.sql.Date.valueOf(session.getDateFin()),
                                organismeName
                        );

                        showAlert(Alert.AlertType.INFORMATION, "Succès", "Certificat généré sur votre bureau !");
                    });
                    buttonBox.getChildren().add(btnDownloadCert);
                }
            }
        }

        Button btnDetails = new Button("📋 Détails");
        btnDetails.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-padding: 6 12; -fx-background-radius: 4; -fx-cursor: hand; -fx-font-size: 11px;");
        btnDetails.setOnAction(e -> handleViewInscriptionDetails(participation));

        if ("pending".equals(status)) {
            Button btnCancel = new Button("❌ Annuler");
            btnCancel.setStyle("-fx-background-color: #e74c3c; -fx-text-fill: white; -fx-padding: 6 12; -fx-background-radius: 4; -fx-cursor: hand; -fx-font-size: 11px;");
            btnCancel.setOnAction(e -> {
                Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
                confirm.setTitle("Confirmation");
                confirm.setHeaderText("Annuler cette inscription ?");
                confirm.setContentText("Cette action ne peut pas être annulée.");

                confirm.showAndWait().ifPresent(response -> {
                    if (response == ButtonType.OK) {
                        try {
                            participationService.deleteParticipation(participation.getIdParticipation());
                            loadInscriptions();
                            displayInscriptions(searchInscriptions.getText());
                            showAlert(Alert.AlertType.INFORMATION, "Succès", "Inscription annulée avec succès !");
                        } catch (Exception ex) {
                            showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'annuler l'inscription.");
                        }
                    }
                });
            });
            buttonBox.getChildren().add(btnCancel);
        }

        buttonBox.getChildren().add(btnDetails);

        card.getChildren().addAll(sessionLabel, dateLabel, statusBadge, buttonBox);
        return card;
    }

    private void handleViewInscriptionDetails(ParticipationFormation selected) {
        Dialog<Void> dialog = new Dialog<>();
        dialog.setTitle("Détails de l'inscription");
        dialog.setHeaderText(null);

        DialogPane dialogPane = dialog.getDialogPane();
        dialogPane.setStyle("-fx-background-color: #f5f5f5;");

        VBox header = new VBox(8);
        header.setPadding(new Insets(25));
        header.setStyle("-fx-background-color: linear-gradient(to right, #145EB7, #3FA9F5);");

        Label headerTitle = new Label("📋 Détails de l'inscription");
        headerTitle.setStyle("-fx-text-fill: white; -fx-font-size: 18px; -fx-font-weight: bold;");

        Label headerSub = new Label("Inscription #" + selected.getIdParticipation());
        headerSub.setStyle("-fx-text-fill: #D0D0D0; -fx-font-size: 12px;");

        header.getChildren().addAll(headerTitle, headerSub);
        dialogPane.setHeader(header);

        String formationTitle = getFormationTitle(selected.getIdSession());
        String sessionInfo = getSessionInfo(selected.getIdSession());

        String statusColor = "#3FA9F5";
        if ("Approved".equals(selected.getStatutParticipation())) {
            statusColor = "#27ae60";
        } else if ("Rejected".equals(selected.getStatutParticipation())) {
            statusColor = "#e74c3c";
        } else if ("Pending".equals(selected.getStatutParticipation())) {
            statusColor = "#f39c12";
        }

        VBox contentBox = new VBox(15);
        contentBox.setPadding(new Insets(25, 30, 25, 30));
        contentBox.setStyle("-fx-background-color: #ffffff;");

        HBox formationBox = new HBox(10);
        Label lblFormationTitle = new Label("📚 Formation");
        lblFormationTitle.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 12px;");
        Label lblFormationValue = new Label(formationTitle);
        lblFormationValue.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");
        formationBox.getChildren().addAll(lblFormationTitle);
        contentBox.getChildren().add(formationBox);
        contentBox.getChildren().add(lblFormationValue);

        HBox sessionBox = new HBox(10);
        Label lblSessionTitle = new Label("📅 Session");
        lblSessionTitle.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 12px;");
        Label lblSessionValue = new Label(sessionInfo);
        lblSessionValue.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");
        sessionBox.getChildren().addAll(lblSessionTitle);
        contentBox.getChildren().add(sessionBox);
        contentBox.getChildren().add(lblSessionValue);

        HBox dateBox = new HBox(10);
        Label lblDateTitle = new Label("📆 Date d'inscription");
        lblDateTitle.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 12px;");
        Label lblDateValue = new Label(String.valueOf(selected.getDateInscription()));
        lblDateValue.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");
        dateBox.getChildren().addAll(lblDateTitle);
        contentBox.getChildren().add(dateBox);
        contentBox.getChildren().add(lblDateValue);

        HBox statutBox = new HBox(10);
        Label lblStatutTitle = new Label("⚙️ Statut");
        lblStatutTitle.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 12px;");
        Label lblStatutValue = new Label(selected.getStatutParticipation());
        lblStatutValue.setStyle("-fx-text-fill: " + statusColor + "; -fx-font-weight: bold; -fx-font-size: 13px;");
        statutBox.getChildren().addAll(lblStatutTitle);
        contentBox.getChildren().add(statutBox);
        contentBox.getChildren().add(lblStatutValue);

        if (selected.getResultat() != null && !selected.getResultat().isEmpty()) {
            HBox resultatBox = new HBox(10);
            Label lblResultatTitle = new Label("🎯 Résultat");
            lblResultatTitle.setStyle("-fx-text-fill: #022E69; -fx-font-weight: bold; -fx-font-size: 12px;");
            Label lblResultatValue = new Label(selected.getResultat());
            lblResultatValue.setStyle("-fx-text-fill: #34495e; -fx-font-size: 13px;");
            resultatBox.getChildren().addAll(lblResultatTitle);
            contentBox.getChildren().add(resultatBox);
            contentBox.getChildren().add(lblResultatValue);
        }

        dialogPane.setContent(contentBox);

        ButtonType closeButton = new ButtonType("Fermer", ButtonBar.ButtonData.CANCEL_CLOSE);
        dialogPane.getButtonTypes().add(closeButton);

        Button btnClose = (Button) dialogPane.lookupButton(closeButton);
        btnClose.setStyle("-fx-background-color: #145EB7; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 10 25; -fx-background-radius: 5; -fx-cursor: hand;");

        dialog.showAndWait();
    }
   
   private final QRCodeService qrCodeService = new QRCodeService();
    private void handleAfficherQRCode(Formation f) {
        String nomFichier = "qrcode_formation_" + f.getIdFormation() + ".png";


        String contenuQR = "FORMATION : " + f.getTitre() + "\n" +
                "TYPE : " + f.getType() + "\n" +
                "DURÉE : " + f.getDuree() + " jours\n" +
                "OBJECTIFS : " + f.getObjectifs();

        javafx.concurrent.Task<String> task = new javafx.concurrent.Task<>() {
            @Override
            protected String call() throws Exception {
                return qrCodeService.genererQRCode(contenuQR, nomFichier);
            }
        };

        task.setOnSucceeded(ev -> {
            try {
                java.io.File file = new java.io.File(nomFichier);
                javafx.scene.image.Image image = new javafx.scene.image.Image(file.toURI().toString());
                javafx.scene.image.ImageView imageView = new javafx.scene.image.ImageView(image);

                Alert alert = new Alert(Alert.AlertType.INFORMATION);
                alert.setTitle("Scanner la formation");
                alert.setHeaderText("Scannez ce code pour emporter les détails");
                alert.setGraphic(imageView);
                alert.setContentText("Formation : " + f.getTitre());
                alert.showAndWait();

                file.deleteOnExit();

            } catch (Exception e) {
                showAlert(Alert.AlertType.ERROR, "Erreur", "Impossible d'afficher le QR Code");
            }
        });

        task.setOnFailed(ev -> {
            showAlert(Alert.AlertType.ERROR, "Erreur API", "La génération du QR Code a échoué.");
        });

        new Thread(task).start();
    }

    private final TTSService ttsService = new TTSService();
    private javafx.scene.media.MediaPlayer currentMediaPlayer;


    private void handleEcouterFormation(Formation f) {
        try {
            if (currentMediaPlayer != null) {
                currentMediaPlayer.stop();
            }

            String cheminFichier = "formation_" + f.getIdFormation() + ".mp3";

            javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
                @Override
                protected Void call() throws Exception {
                    StringBuilder texteALire = new StringBuilder();
                    texteALire.append("Formation : ").append(f.getTitre()).append(". ");
                    texteALire.append("Type : ").append(f.getType()).append(". ");
                    texteALire.append("Durée : ").append(f.getDuree()).append(" jours. ");

                    if (f.getObjectifs() != null && !f.getObjectifs().isEmpty()) {
                        texteALire.append("Objectifs de la formation : ").append(f.getObjectifs());
                    }
                    // ---------------------------------------

                    ttsService.genererAudio(texteALire.toString(), cheminFichier);
                    return null;
                }
            };

            task.setOnSucceeded(ev -> {
                try {
                    javafx.scene.media.Media media = new javafx.scene.media.Media(new java.io.File(cheminFichier).toURI().toString());
                    currentMediaPlayer = new javafx.scene.media.MediaPlayer(media);
                    currentMediaPlayer.play();
                } catch (Exception e) {
                    System.err.println("Erreur de lecture : " + e.getMessage());
                }
            });

            task.setOnFailed(ev -> {
                showAlert(Alert.AlertType.ERROR, "Erreur TTS", "Impossible de générer l'audio : " + task.getException().getMessage());
            });

            Thread thread = new Thread(task);
            thread.setDaemon(true);
            thread.start();

        } catch (Exception ex) {
            ex.printStackTrace();
            showAlert(Alert.AlertType.ERROR, "Erreur", "Erreur TTS: " + ex.getMessage());
        }
    }
}
