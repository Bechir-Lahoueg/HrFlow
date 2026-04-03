package org.example.Service;

import org.example.Entity.FichePaie;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;
import java.math.BigDecimal;

public class FichePaieService {

    private final Connection connection;

    public FichePaieService(Connection connection) {
        this.connection = connection;
    }

    // ==============================
    // VALIDATION
    // ==============================

    private void validateFiche(FichePaie fiche) {

        if (fiche == null) {
            throw new IllegalArgumentException("La fiche de paie ne peut pas être null.");
        }

        if (fiche.getMois() == null || fiche.getMois().trim().isEmpty()) {
            throw new IllegalArgumentException("Le mois est obligatoire.");
        }

        if (fiche.getAnnee() < 2000 || fiche.getAnnee() > 2100) {
            throw new IllegalArgumentException("Année invalide.");
        }

        if (fiche.getSalaireBrut() == null || fiche.getSalaireBrut().compareTo(BigDecimal.ZERO) < 0) {
            throw new IllegalArgumentException("Le salaire brut doit être positif.");
        }

        if (fiche.getTotalPrimes() == null || fiche.getTotalPrimes().compareTo(BigDecimal.ZERO) < 0) {
            throw new IllegalArgumentException("Le total des primes doit être positif.");
        }

        if (fiche.getTotalDeductions() == null || fiche.getTotalDeductions().compareTo(BigDecimal.ZERO) < 0) {
            throw new IllegalArgumentException("Le total des déductions doit être positif.");
        }

        if (fiche.getSalaireNet() == null || fiche.getSalaireNet().compareTo(BigDecimal.ZERO) < 0) {
            throw new IllegalArgumentException("Le salaire net doit être positif.");
        }

        if (fiche.getIdEmployees() <= 0) {
            throw new IllegalArgumentException("ID employé invalide.");
        }

        // Vérification logique métier
        BigDecimal expectedNet = fiche.getSalaireBrut()
                .add(fiche.getTotalPrimes())
                .subtract(fiche.getTotalDeductions());

        if (fiche.getSalaireNet().compareTo(expectedNet) != 0) {
            throw new IllegalArgumentException(
                    "Le salaire net doit être égal à Brut + Primes - Déductions."
            );
        }
    }

    // ==============================
    // CREATE
    // ==============================

    public void addFiche(FichePaie fiche) throws SQLException {

        validateFiche(fiche); // 🔥 validation avant insertion

        String sql = "INSERT INTO FichePaie (mois, annee, salaire_brut, total_primes, total_deductions, salaire_net, id_employees) " +
                "VALUES (?, ?, ?, ?, ?, ?, ?)";

        try (PreparedStatement stmt = connection.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            stmt.setString(1, fiche.getMois());
            stmt.setInt(2, fiche.getAnnee());
            stmt.setBigDecimal(3, fiche.getSalaireBrut());
            stmt.setBigDecimal(4, fiche.getTotalPrimes());
            stmt.setBigDecimal(5, fiche.getTotalDeductions());
            stmt.setBigDecimal(6, fiche.getSalaireNet());
            stmt.setInt(7, fiche.getIdEmployees());

            stmt.executeUpdate();

            ResultSet rs = stmt.getGeneratedKeys();
            if (rs.next()) {
                fiche.setIdFiche(rs.getInt(1));
            }
        }
    }

    // ==============================
    // READ BY ID
    // ==============================

    public FichePaie getFicheById(int id) throws SQLException {

        String sql = "SELECT * FROM FichePaie WHERE id_fiche = ?";

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {

            stmt.setInt(1, id);
            ResultSet rs = stmt.executeQuery();

            if (rs.next()) {
                return mapResultSet(rs);
            }
        }

        return null;
    }

    // ==============================
    // READ ALL
    // ==============================

    public List<FichePaie> getAllFiches() throws SQLException {

        String sql = "SELECT * FROM FichePaie";
        List<FichePaie> fiches = new ArrayList<>();

        try (Statement stmt = connection.createStatement()) {

            ResultSet rs = stmt.executeQuery(sql);

            while (rs.next()) {
                fiches.add(mapResultSet(rs));
            }
        }

        return fiches;
    }

    // ==============================
    // UPDATE
    // ==============================

    public void updateFiche(FichePaie fiche) throws SQLException {

        validateFiche(fiche); // 🔥 validation avant update

        String sql = "UPDATE FichePaie SET mois=?, annee=?, salaire_brut=?, total_primes=?, total_deductions=?, salaire_net=?, id_employees=? " +
                "WHERE id_fiche=?";

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {

            stmt.setString(1, fiche.getMois());
            stmt.setInt(2, fiche.getAnnee());
            stmt.setBigDecimal(3, fiche.getSalaireBrut());
            stmt.setBigDecimal(4, fiche.getTotalPrimes());
            stmt.setBigDecimal(5, fiche.getTotalDeductions());
            stmt.setBigDecimal(6, fiche.getSalaireNet());
            stmt.setInt(7, fiche.getIdEmployees());
            stmt.setInt(8, fiche.getIdFiche());

            stmt.executeUpdate();
        }
    }

    // ==============================
    // READ BY EMPLOYE
    // ==============================

    public List<FichePaie> getFichesByEmploye(int idEmploye) throws SQLException {

        String sql = "SELECT * FROM FichePaie WHERE id_employees = ? ORDER BY annee DESC, mois ASC";
        List<FichePaie> fiches = new ArrayList<>();

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {
            stmt.setInt(1, idEmploye);
            ResultSet rs = stmt.executeQuery();
            while (rs.next()) {
                fiches.add(mapResultSet(rs));
            }
        }

        return fiches;
    }

    // ==============================
    // DELETE
    // ==============================

    public void deleteFiche(int id) throws SQLException {

        String sql = "DELETE FROM FichePaie WHERE id_fiche=?";

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {

            stmt.setInt(1, id);
            stmt.executeUpdate();
        }
    }

    // ==============================
    // MAPPING
    // ==============================

    private FichePaie mapResultSet(ResultSet rs) throws SQLException {

        return new FichePaie(
                rs.getInt("id_fiche"),
                rs.getString("mois"),
                rs.getInt("annee"),
                rs.getBigDecimal("salaire_brut"),
                rs.getBigDecimal("total_primes"),
                rs.getBigDecimal("total_deductions"),
                rs.getBigDecimal("salaire_net"),
                rs.getInt("id_employees")
        );
    }
}