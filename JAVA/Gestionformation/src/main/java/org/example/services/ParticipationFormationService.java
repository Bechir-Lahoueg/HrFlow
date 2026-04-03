package org.example.services;

import org.example.utils.Mydb;
import org.example.models.ParticipationFormation;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service de gestion des participations aux formations
 * Utilise le singleton Mydb pour la connexion
 */
public class ParticipationFormationService {

    private Connection connection;

    public ParticipationFormationService() {
        // Utilisation du singleton Mydb
        this.connection = Mydb.getInstance().getConnection();
    }

    // 🔹 ADD
    public void addParticipation(ParticipationFormation p) {
        String sql = "INSERT INTO participation_formation " +
                "(id_session, id_utilisateur, date_inscription, statut_participation, resultat) " +
                "VALUES (?, ?, ?, ?, ?)";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, p.getIdSession());
            ps.setInt(2, p.getIdEmployee());
            ps.setDate(3, Date.valueOf(p.getDateInscription()));
            ps.setString(4, p.getStatutParticipation());
            ps.setString(5, p.getResultat());

            ps.executeUpdate();
            System.out.println("Participation ajoutée avec succès");
        } catch (SQLException e) {
            System.err.println("Erreur ajout participation");
            e.printStackTrace();
        }
    }

    // 🔹 GET ALL
    public List<ParticipationFormation> getAllParticipations() {
        List<ParticipationFormation> participations = new ArrayList<>();
        String sql = "SELECT * FROM participation_formation";

        try (Statement st = connection.createStatement();
             ResultSet rs = st.executeQuery(sql)) {

            while (rs.next()) {
                ParticipationFormation p = new ParticipationFormation(
                        rs.getInt("id_participation"),
                        rs.getInt("id_session"),
                        rs.getInt("id_utilisateur"),
                        rs.getDate("date_inscription").toLocalDate(),
                        rs.getString("statut_participation"),
                        rs.getString("resultat")
                );
                participations.add(p);
            }
        } catch (SQLException e) {
            System.err.println("Erreur récupération participations");
            e.printStackTrace();
        }
        return participations;
    }

    // 🔹 GET BY ID
    public ParticipationFormation getParticipationById(int id) {
        String sql = "SELECT * FROM participation_formation WHERE id_participation = ?";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, id);
            ResultSet rs = ps.executeQuery();

            if (rs.next()) {
                return new ParticipationFormation(
                        rs.getInt("id_participation"),
                        rs.getInt("id_session"),
                        rs.getInt("id_utilisateur"),
                        rs.getDate("date_inscription").toLocalDate(),
                        rs.getString("statut_participation"),
                        rs.getString("resultat")
                );
            }
        } catch (SQLException e) {
            System.err.println("Erreur récupération participation par ID");
            e.printStackTrace();
        }
        return null;
    }

    // 🔹 GET BY SESSION
    public List<ParticipationFormation> getParticipationsBySession(int idSession) {
        List<ParticipationFormation> list = new ArrayList<>();
        String sql = "SELECT p.*, e.first_name, e.last_name FROM participation_formation p " +
                "JOIN employees e ON p.id_utilisateur = e.id " +
                "WHERE p.id_session = ?";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, idSession);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                String nomComplet = rs.getString("first_name") + " " + rs.getString("last_name");

                list.add(new ParticipationFormation(
                        rs.getInt("id_participation"),
                        rs.getInt("id_session"),
                        rs.getInt("id_utilisateur"),
                        rs.getDate("date_inscription").toLocalDate(),
                        rs.getString("statut_participation"),
                        rs.getString("resultat"),
                        nomComplet
                ));
            }
        } catch (SQLException e) {
            System.err.println("Erreur SQL dans getParticipationsBySession");
            e.printStackTrace();
        }
        return list;
    }

    // 🔹 UPDATE
    public void updateParticipation(ParticipationFormation p) {
        String sql = "UPDATE participation_formation SET " +
                "statut_participation = ?, resultat = ? " +
                "WHERE id_participation = ?";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setString(1, p.getStatutParticipation());
            ps.setString(2, p.getResultat());
            ps.setInt(3, p.getIdParticipation());

            ps.executeUpdate();
            System.out.println("Participation modifiée avec succès");
        } catch (SQLException e) {
            System.err.println("Erreur modification participation");
            e.printStackTrace();
        }
    }

    // 🔹 DELETE
    public void deleteParticipation(int idParticipation) {
        String sql = "DELETE FROM participation_formation WHERE id_participation = ?";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, idParticipation);
            ps.executeUpdate();
            System.out.println("Participation supprimée");
        } catch (SQLException e) {
            System.err.println("Erreur suppression participation");
            e.printStackTrace();
        }
    }
}
