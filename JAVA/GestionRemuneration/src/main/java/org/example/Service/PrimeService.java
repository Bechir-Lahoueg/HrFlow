package org.example.Service;

import org.example.Entity.Prime;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;
import java.math.BigDecimal;
import java.time.LocalDate;

public class PrimeService {

    private final Connection connection;

    public PrimeService(Connection connection) {
        this.connection = connection;
    }

    // ==============================
    // VALIDATION
    // ==============================

    private void validatePrime(Prime prime) {

        if (prime == null) {
            throw new IllegalArgumentException("La prime ne peut pas être null.");
        }

        if (prime.getTypePrime() == null || prime.getTypePrime().trim().isEmpty()) {
            throw new IllegalArgumentException("Le type de prime est obligatoire.");
        }

        if (prime.getMontant() == null) {
            throw new IllegalArgumentException("Le montant est obligatoire.");
        }

        if (prime.getMontant().compareTo(BigDecimal.ZERO) <= 0) {
            throw new IllegalArgumentException("Le montant doit être supérieur à 0.");
        }

        if (prime.getDateAttribution() == null) {
            throw new IllegalArgumentException("La date d'attribution est obligatoire.");
        }

        if (prime.getIdEmploye() <= 0) {
            throw new IllegalArgumentException("ID employé invalide.");
        }
    }

    // ==============================
    // CREATE
    // ==============================

    public void addPrime(Prime prime) throws SQLException {

        validatePrime(prime); // 🔥 validation avant insertion

        String sql = "INSERT INTO Prime (type_prime, montant, date_attribution, id_employe) " +
                "VALUES (?, ?, ?, ?)";

        try (PreparedStatement stmt = connection.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            stmt.setString(1, prime.getTypePrime());
            stmt.setBigDecimal(2, prime.getMontant());
            stmt.setDate(3, Date.valueOf(prime.getDateAttribution()));
            stmt.setInt(4, prime.getIdEmploye());

            stmt.executeUpdate();

            ResultSet rs = stmt.getGeneratedKeys();
            if (rs.next()) {
                prime.setIdPrime(rs.getInt(1));
            }
        }
    }

    // ==============================
    // READ BY ID
    // ==============================

    public Prime getPrimeById(int id) throws SQLException {

        String sql = "SELECT * FROM Prime WHERE id_prime = ?";

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

    public List<Prime> getAllPrimes() throws SQLException {

        String sql = "SELECT * FROM Prime";
        List<Prime> primes = new ArrayList<>();

        try (Statement stmt = connection.createStatement()) {

            ResultSet rs = stmt.executeQuery(sql);

            while (rs.next()) {
                primes.add(mapResultSet(rs));
            }
        }

        return primes;
    }

    // ==============================
    // UPDATE
    // ==============================

    public void updatePrime(Prime prime) throws SQLException {

        validatePrime(prime); // 🔥 validation avant update

        String sql = "UPDATE Prime SET type_prime=?, montant=?, date_attribution=?, id_employe=? " +
                "WHERE id_prime=?";

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {

            stmt.setString(1, prime.getTypePrime());
            stmt.setBigDecimal(2, prime.getMontant());
            stmt.setDate(3, Date.valueOf(prime.getDateAttribution()));
            stmt.setInt(4, prime.getIdEmploye());
            stmt.setInt(5, prime.getIdPrime());

            stmt.executeUpdate();
        }
    }

    // ==============================
    // DELETE
    // ==============================

    public void deletePrime(int id) throws SQLException {

        String sql = "DELETE FROM Prime WHERE id_prime=?";

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {

            stmt.setInt(1, id);
            stmt.executeUpdate();
        }
    }

    // ==============================
    // MAPPING
    // ==============================

    private Prime mapResultSet(ResultSet rs) throws SQLException {

        Date sqlDate = rs.getDate("date_attribution");
        LocalDate date = sqlDate != null ? sqlDate.toLocalDate() : null;

        return new Prime(
                rs.getInt("id_prime"),
                rs.getString("type_prime"),
                rs.getBigDecimal("montant"),
                date,
                rs.getInt("id_employe")
        );
    }
}