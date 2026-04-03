package org.example.controllers;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.stage.Stage;
import org.example.models.Formation;
import org.example.services.FormationService;

public class AddFormationController {

    @FXML
    private TextField txtTitre, txtType, txtDuree, txtOrganisme, txtObjectif;

    @FXML
    private TextArea txtDescription;

    @FXML
    private ListView<String> listObjectifs;

    @FXML
    private Button btnAddObjectif, btnSave, btnClose;

    private ObservableList<String> objectifs = FXCollections.observableArrayList();

    private FormationService formationService = new FormationService();

    // 🔹 Pour savoir si on est en mode modification
    private Formation formationToEdit;

    @FXML
    private void initialize() {

        listObjectifs.setItems(objectifs);

        btnAddObjectif.setOnAction(e -> {
            String obj = txtObjectif.getText().trim();
            if (!obj.isEmpty()) {
                objectifs.add(obj);
                txtObjectif.clear();
            }
        });

        btnSave.setOnAction(e -> handleSave());
        btnClose.setOnAction(e -> closeWindow());
    }

    // 🔹 Cette méthode sera appelée depuis FormationListController
    public void setFormationToEdit(Formation formation) {
        this.formationToEdit = formation;

        txtTitre.setText(formation.getTitre());
        txtDescription.setText(formation.getDescription());
        txtType.setText(formation.getType());
        txtDuree.setText(String.valueOf(formation.getDuree()));
        txtOrganisme.setText(formation.getOrganisme());

        objectifs.clear();

        if (formation.getObjectifs() != null) {
            String[] objArray = formation.getObjectifs().split("\n");
            objectifs.addAll(objArray);
        }

        // Optionnel : changer texte bouton
        btnSave.setText("Mettre à jour");
    }

    // 🔹 Gestion Ajout + Modification
    private void handleSave() {

        try {

            String titre = txtTitre.getText();
            String description = txtDescription.getText();
            String type = txtType.getText();
            int duree = Integer.parseInt(txtDuree.getText());
            String organisme = txtOrganisme.getText();
            String objectifsStr = String.join("\n", objectifs);

            if (formationToEdit == null) {

                // MODE AJOUT
                // Utiliser RH par défaut (ID = 1)
                Integer rhId = 1;
                
                Formation newFormation = new Formation(
                        titre,
                        description,
                        type,
                        duree,
                        organisme,
                        objectifsStr,
                        rhId
                );

                formationService.addFormation(newFormation);

            } else {

                // MODE MODIFICATION
                formationToEdit.setTitre(titre);
                formationToEdit.setDescription(description);
                formationToEdit.setType(type);
                formationToEdit.setDuree(duree);
                formationToEdit.setOrganisme(organisme);
                formationToEdit.setObjectifs(objectifsStr);

                formationService.updateFormation(formationToEdit);
            }

            closeWindow();

        } catch (NumberFormatException ex) {
            Alert alert = new Alert(Alert.AlertType.ERROR,
                    "La durée doit être un nombre entier");
            alert.showAndWait();
        }
    }

    private void closeWindow() {
        Stage stage = (Stage) btnClose.getScene().getWindow();
        stage.close();
    }
}
