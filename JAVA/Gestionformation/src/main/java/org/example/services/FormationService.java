package org.example.services;
import org.example.utils.Mydb;
import org.example.models.Formation;
import org.example.models.FeedbackFormation;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.List;
import java.util.ArrayList;

public class FormationService {

    private Connection connection;

    private FeedbackFormationService feedbackService = new FeedbackFormationService();

    public FormationService() {
        // Utilisation du singleton Mydb
        this.connection = Mydb.getInstance().getConnection();
    }

    public void addFormation(Formation f) {
        String sql = "INSERT INTO formation (titre, description, type, duree, organisme, objectifs, id_rh) VALUES (?, ?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setString(1, f.getTitre());
            ps.setString(2, f.getDescription());
            ps.setString(3, f.getType());
            ps.setInt(4, f.getDuree());
            ps.setString(5, f.getOrganisme());
            ps.setString(6, f.getObjectifs());
            ps.setObject(7, f.getIdRh());
            ps.executeUpdate();
            System.out.println("Formation ajoutée");
        } catch (SQLException e) {
            System.err.println("Erreur d'ajout de formation");
            e.printStackTrace();
        }
    }

    public List<Formation> getAllFormations() {
        List<Formation> formations = new ArrayList<>();
        String sql = "SELECT * FROM formation";
        try (Statement st = connection.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                Formation f = new Formation(
                        rs.getInt("id_formation"),
                        rs.getString("titre"),
                        rs.getString("description"),
                        rs.getString("type"),
                        rs.getInt("duree"),
                        rs.getString("organisme"),
                        rs.getString("objectifs"),
                        (Integer) rs.getObject("id_rh")
                );

                double moyenne = feedbackService.getAverageRating(f.getIdFormation());
                f.setMoyenneRating(moyenne);

                formations.add(f);
            }
        } catch (SQLException e) {
            System.err.println("Erreur récupération formations");
            e.printStackTrace();
        }
        return formations;
    }

    // Nouvelle méthode
    public Formation getFormationById(int idFormation) {
        for (Formation f : getAllFormations()) {
            if (f.getIdFormation() == idFormation) {
                return f;
            }
        }
        return null;
    }
    /**
     * Récupère les formations créées par un RH spécifique
     * @param idRh ID du RH
     * @return Liste des formations du RH
     */
    public List<Formation> getFormationsByRh(Integer idRh) {
        List<Formation> formations = new ArrayList<>();
        String sql = "SELECT * FROM formation WHERE id_rh = ?";
        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, idRh);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                Formation f = new Formation(
                        rs.getInt("id_formation"),
                        rs.getString("titre"),
                        rs.getString("description"),
                        rs.getString("type"),
                        rs.getInt("duree"),
                        rs.getString("organisme"),
                        rs.getString("objectifs"),
                        (Integer) rs.getObject("id_rh")
                );

                double moyenne = feedbackService.getAverageRating(f.getIdFormation());
                f.setMoyenneRating(moyenne);

                formations.add(f);
            }
        } catch (SQLException e) {
            System.err.println("Erreur récupération formations RH");
            e.printStackTrace();
        }
        return formations;
    }


    public void updateFormation(Formation f) {
        String sql = "UPDATE formation SET titre=?, description=?, type=?, duree=?, organisme=?, objectifs=?, id_rh=? WHERE id_formation=?";
        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setString(1, f.getTitre());
            ps.setString(2, f.getDescription());
            ps.setString(3, f.getType());
            ps.setInt(4, f.getDuree());
            ps.setString(5, f.getOrganisme());
            ps.setString(6, f.getObjectifs());
            ps.setObject(7, f.getIdRh());
            ps.setInt(8, f.getIdFormation());
            ps.executeUpdate();
            System.out.println("Formation modifiée");
        } catch (SQLException e) {
            System.err.println("Erreur modification formation");
            e.printStackTrace();
        }
    }

    public void deleteFormation(int id) {
        // 1. On prépare les requêtes pour nettoyer les tables liées
        // Note : si tu as aussi des 'sessions' liées à cette formation, il faudra les supprimer aussi ici !
        String deleteFeedbacksSql = "DELETE FROM feedback_formation WHERE formation_id=?";
        String deleteFormationSql = "DELETE FROM formation WHERE id_formation=?";

        try {
            // Optionnel mais recommandé : Utiliser une transaction pour tout supprimer d'un coup
            connection.setAutoCommit(false);

            // Supprimer les feedbacks d'abord
            try (PreparedStatement psFeed = connection.prepareStatement(deleteFeedbacksSql)) {
                psFeed.setInt(1, id);
                psFeed.executeUpdate();
            }

            // Enfin, supprimer la formation
            try (PreparedStatement psForm = connection.prepareStatement(deleteFormationSql)) {
                psForm.setInt(1, id);
                psForm.executeUpdate();
            }

            connection.commit();
            System.out.println("Formation et ses feedbacks supprimés avec succès");

        } catch (SQLException e) {
            try {
                connection.rollback(); // Annule tout si une étape échoue
            } catch (SQLException ex) {
                ex.printStackTrace();
            }
            System.err.println("Erreur lors de la suppression de la formation");
            e.printStackTrace();
        } finally {
            try {
                connection.setAutoCommit(true);
            } catch (SQLException e) {
                e.printStackTrace();
            }
        }
    }
}
