package org.example.services;

import org.example.utils.Mydb;
import org.example.models.SessionFormation;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/**
 * Service de gestion des sessions de formation
 * Utilise le singleton Mydb pour la connexion
 */
public class SessionFormationService {

    private Connection connection;

    public SessionFormationService() {
        // Utilisation du singleton Mydb
        this.connection = Mydb.getInstance().getConnection();
    }

    // 🔹 ADD
    public void addSession(SessionFormation s) {
        String sql = "INSERT INTO session_formation " +
                "(id_formation, date_debut, date_fin, lieu, mode, capacite_max, statut) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?)";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, s.getIdFormation());
            ps.setDate(2, Date.valueOf(s.getDateDebut()));
            ps.setDate(3, Date.valueOf(s.getDateFin()));
            ps.setString(4, s.getLieu());
            ps.setString(5, s.getMode());
            ps.setInt(6, s.getCapaciteMax());
            ps.setString(7, s.getStatut());

            ps.executeUpdate();
            System.out.println("Session ajoutée");
        } catch (SQLException e) {
            System.err.println("Erreur ajout session");
            e.printStackTrace();
        }
    }

    // 🔹 GET ALL
    public List<SessionFormation> getAllSessions() {
        List<SessionFormation> sessions = new ArrayList<>();
        String sql = "SELECT * FROM session_formation";

        try (Statement st = connection.createStatement();
             ResultSet rs = st.executeQuery(sql)) {

            while (rs.next()) {
                sessions.add(new SessionFormation(
                        rs.getInt("id_session"),
                        rs.getInt("id_formation"),
                        rs.getDate("date_debut").toLocalDate(),
                        rs.getDate("date_fin").toLocalDate(),
                        rs.getString("lieu"),
                        rs.getString("mode"),
                        rs.getInt("capacite_max"),
                        rs.getString("statut")
                ));
            }

        } catch (SQLException e) {
            System.err.println("Erreur récupération sessions");
            e.printStackTrace();
        }

        return sessions;
    }

    // Nouvelle méthode
    public SessionFormation getSessionById(int idSession) {
        for (SessionFormation s : getAllSessions()) {
            if (s.getIdSession() == idSession) {
                return s;
            }
        }
        return null;
    }

    // 🔹 GET SESSIONS BY FORMATION ID (Pour MySQL)
    public List<SessionFormation> getSessionsByFormation(int idFormation) {
        List<SessionFormation> sessions = new ArrayList<>();
        // Requête SQL filtrée par l'ID de la formation
        String sql = "SELECT * FROM session_formation WHERE id_formation = ?";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, idFormation);

            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    sessions.add(new SessionFormation(
                            rs.getInt("id_session"),
                            rs.getInt("id_formation"),
                            rs.getDate("date_debut").toLocalDate(),
                            rs.getDate("date_fin").toLocalDate(),
                            rs.getString("lieu"),
                            rs.getString("mode"),
                            rs.getInt("capacite_max"),
                            rs.getString("statut")
                    ));
                }
            }
        } catch (SQLException e) {
            System.err.println("Erreur lors de la récupération des sessions pour la formation ID: " + idFormation);
            e.printStackTrace();
        }

        return sessions;
    }

    // 🔹 UPDATE
    public void updateSession(SessionFormation s) {
        String sql = "UPDATE session_formation SET " +
                "id_formation=?, date_debut=?, date_fin=?, lieu=?, mode=?, capacite_max=?, statut=? " +
                "WHERE id_session=?";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, s.getIdFormation());
            ps.setDate(2, Date.valueOf(s.getDateDebut()));
            ps.setDate(3, Date.valueOf(s.getDateFin()));
            ps.setString(4, s.getLieu());
            ps.setString(5, s.getMode());
            ps.setInt(6, s.getCapaciteMax());
            ps.setString(7, s.getStatut());
            ps.setInt(8, s.getIdSession());

            ps.executeUpdate();
            System.out.println("Session modifiée");
        } catch (SQLException e) {
            System.err.println("Erreur modification session");
            e.printStackTrace();
        }
    }

    // 🔹 DELETE
    public void deleteSession(int id) throws SQLException {
        String sql = "DELETE FROM session_formation WHERE id_session=?";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, id);
            int affectedRows = ps.executeUpdate();
            if (affectedRows == 0) {
                System.err.println("Aucune session trouvée avec cet ID : " + id);
            } else {
                System.out.println("Session supprimée");
            }
        } catch (SQLException e) {
            System.err.println("Erreur suppression session");
            e.printStackTrace();
        }
    }


    public int getPlacesDisponibles(int idSession) {
        String sql = "SELECT sf.capacite_max, COUNT(pf.id_participation) as nb_accepted " +
                "FROM session_formation sf " +
                "LEFT JOIN participation_formation pf ON sf.id_session = pf.id_session " +
                "AND pf.statut_participation = 'Approved' " +
                "WHERE sf.id_session = ? " +
                "GROUP BY sf.id_session, sf.capacite_max";

        try (PreparedStatement ps = connection.prepareStatement(sql)) {
            ps.setInt(1, idSession);
            ResultSet rs = ps.executeQuery();

            if (rs.next()) {
                int capaciteMax = rs.getInt("capacite_max");
                int nbAccepted = rs.getInt("nb_accepted");
                int placesDisponibles = capaciteMax - nbAccepted;
                return Math.max(0, placesDisponibles); // Retourne au minimum 0
            }
        } catch (SQLException e) {
            System.err.println("Erreur calcul places disponibles pour la session ID: " + idSession);
            e.printStackTrace();
        }
        return 0;
    }


    public SessionFormation getSessionWithPlaces(int idSession) {
        SessionFormation session = getSessionById(idSession);
        if (session != null) {
            int placesDisponibles = getPlacesDisponibles(idSession);
            session.setPlacesDisponibles(placesDisponibles);
        }
        return session;
    }
}
