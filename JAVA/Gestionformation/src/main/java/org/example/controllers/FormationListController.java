package org.example.controllers;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.stage.Modality;
import javafx.stage.Stage;
import org.example.models.Formation;
import org.example.services.FormationService;

import java.net.URL;
import java.util.List;
import java.util.ResourceBundle;

public class FormationListController implements Initializable {

    @FXML
    private FlowPane formationContainer;

    private FormationService formationService;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        formationService = new FormationService();
        loadFormations();
    }

    private void loadFormations() {

        formationContainer.getChildren().clear();

        // Afficher toutes les formations disponibles
        List<Formation> formations = formationService.getAllFormations();

        for (Formation f : formations) {
            VBox card = createFormationCard(f);
            formationContainer.getChildren().add(card);
        }
    }

    private VBox createFormationCard(Formation f) {

        VBox card = new VBox();
        card.getStyleClass().add("card");
        card.setSpacing(10);

        Label titleLabel = new Label(f.getTitre());
        titleLabel.getStyleClass().add("card-title");

        Label descriptionLabel = new Label(f.getDescription());
        descriptionLabel.getStyleClass().add("card-description");

        Label typeLabel = new Label("Type: " + f.getType());
        typeLabel.getStyleClass().add("card-description");

        Label dureeLabel = new Label("Durée: " + f.getDuree() + " jours");
        dureeLabel.getStyleClass().add("card-description");

        Label orgLabel = new Label("Organisme: " + f.getOrganisme());
        orgLabel.getStyleClass().add("card-description");

        // --- Objectifs ---
        VBox objectifsBox = new VBox(2); // petit espacement
        if (f.getObjectifs() != null && !f.getObjectifs().isEmpty()) {
            Label objectifsLabel = new Label("Objectifs :");
            objectifsLabel.getStyleClass().add("card-subtitle");
            objectifsBox.getChildren().add(objectifsLabel);

            String[] points = f.getObjectifs().split("\n");
            for (String point : points) {
                Label pointLabel = new Label("• " + point.trim());
                pointLabel.getStyleClass().add("card-description");
                objectifsBox.getChildren().add(pointLabel);
            }

        }

        // --- Boutons ---
        HBox buttonBox = new HBox(10);
        buttonBox.setAlignment(Pos.CENTER_RIGHT);

        Button btnModifier = new Button("Modifier");
        btnModifier.getStyleClass().add("primary-button");
        btnModifier.setOnAction(e -> openEditFormation(f));

        Button btnSupprimer = new Button("Supprimer");
        btnSupprimer.getStyleClass().add("delete-button");
        btnSupprimer.setOnAction(e -> supprimerFormation(f));

        Button btnVoirSessions = new Button("Voir sessions");
        btnVoirSessions.getStyleClass().add("primary-button");
        btnVoirSessions.setOnAction(e -> openFormationSessions(f));
        buttonBox.getChildren().addAll(btnModifier, btnSupprimer, btnVoirSessions);


        card.getChildren().addAll(
                titleLabel,
                descriptionLabel,
                typeLabel,
                dureeLabel,
                orgLabel,
                objectifsBox,
                buttonBox
        );

        return card;
    }
    // --- Ouvrir la modale Ajouter Formation ---
    @FXML
    private void openAddFormation() {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/views/AddFormation.fxml"));
            Parent root = loader.load();

            Stage stage = new Stage();
            stage.setTitle("Ajouter une formation");
            stage.setScene(new Scene(root));
            stage.initModality(Modality.APPLICATION_MODAL); // bloque la fenêtre principale
            stage.showAndWait();

            loadFormations(); // recharge la liste après fermeture
        } catch (Exception e) {
            e.printStackTrace();
        }

}

    private void openEditFormation(Formation formation) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/views/AddFormation.fxml"));
            Parent root = loader.load();

            // Récupérer le controller de la fenêtre
            AddFormationController controller = loader.getController();

            // Envoyer la formation au controller
            controller.setFormationToEdit(formation);

            Stage stage = new Stage();
            stage.setTitle("Modifier la formation");
            stage.setScene(new Scene(root));
            stage.initModality(Modality.APPLICATION_MODAL);
            stage.showAndWait();

            loadFormations();

        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void supprimerFormation(Formation formation) {

        Alert confirmation = new Alert(Alert.AlertType.CONFIRMATION);
        confirmation.setTitle("Confirmation de suppression");
        confirmation.setHeaderText("Supprimer la formation");
        confirmation.setContentText(
                "Êtes-vous sûr de vouloir supprimer la formation :\n\n"
                        + formation.getTitre() + " ?"
        );

        ButtonType btnOui = new ButtonType("Oui", ButtonBar.ButtonData.YES);
        ButtonType btnNon = new ButtonType("Non", ButtonBar.ButtonData.NO);

        confirmation.getButtonTypes().setAll(btnOui, btnNon);

        confirmation.showAndWait().ifPresent(response -> {
            if (response == btnOui) {

                formationService.deleteFormation(formation.getIdFormation());

                loadFormations();

                Alert succes = new Alert(Alert.AlertType.INFORMATION);
                succes.setTitle("Succès");
                succes.setHeaderText(null);
                succes.setContentText("La formation a été supprimée avec succès.");
                succes.showAndWait();
            }
        });
    }

    private void openFormationSessions(Formation formation) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/views/FormationSessions.fxml"));
            Parent root = loader.load();

            FormationSessionsController controller = loader.getController();
            controller.setFormation(formation);

            Stage stage = new Stage();
            stage.setTitle("Sessions de " + formation.getTitre());
            stage.setScene(new Scene(root));
            stage.initModality(Modality.APPLICATION_MODAL); // bloque la fenêtre principale
            stage.showAndWait();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }



}