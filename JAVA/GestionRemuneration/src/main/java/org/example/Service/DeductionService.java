package org.example.Service;

import org.example.Entity.Deduction;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;
import java.math.BigDecimal;
import java.time.LocalDate;

public class DeductionService {

    private final Connection connection;

    public DeductionService(Connection connection) {
        this.connection = connection;
    }

    // ==============================
    // VALIDATION
    // ==============================

    private void validateDeduction(Deduction deduction) {

        if (deduction == null) {
            throw new IllegalArgumentException("La déduction ne peut pas être null.");
        }

        if (deduction.getTypeDeduction() == null || deduction.getTypeDeduction().trim().isEmpty()) {
            throw new IllegalArgumentException("Le type de déduction est obligatoire.");
        }

        if (deduction.getMontant() == null) {
            throw new IllegalArgumentException("Le montant est obligatoire.");
        }

        if (deduction.getMontant().compareTo(BigDecimal.ZERO) <= 0) {
            throw new IllegalArgumentException("Le montant doit être supérieur à 0.");
        }

        if (deduction.getDateDeduction() == null) {
            throw new IllegalArgumentException("La date de déduction est obligatoire.");
        }

        if (deduction.getIdEmploye() <= 0) {
            throw new IllegalArgumentException("ID employé invalide.");
        }
    }

    // ==============================
    // CREATE
    // ==============================

    public void addDeduction(Deduction deduction) throws SQLException {

        validateDeduction(deduction); // 🔥 validation avant insertion

        String sql = "INSERT INTO Deduction (type_deduction, montant, date_deduction, id_employe) " +
                "VALUES (?, ?, ?, ?)";

        try (PreparedStatement stmt = connection.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            stmt.setString(1, deduction.getTypeDeduction());
            stmt.setBigDecimal(2, deduction.getMontant());
            stmt.setDate(3, Date.valueOf(deduction.getDateDeduction()));
            stmt.setInt(4, deduction.getIdEmploye());

            stmt.executeUpdate();

            ResultSet rs = stmt.getGeneratedKeys();
            if (rs.next()) {
                deduction.setIdDeduction(rs.getInt(1));
            }
        }
    }

    // ==============================
    // READ BY ID
    // ==============================

    public Deduction getDeductionById(int id) throws SQLException {

        String sql = "SELECT * FROM Deduction WHERE id_deduction = ?";

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

    public List<Deduction> getAllDeductions() throws SQLException {

        String sql = "SELECT * FROM Deduction";
        List<Deduction> deductions = new ArrayList<>();

        try (Statement stmt = connection.createStatement()) {

            ResultSet rs = stmt.executeQuery(sql);

            while (rs.next()) {
                deductions.add(mapResultSet(rs));
            }
        }

        return deductions;
    }

    // ==============================
    // UPDATE
    // ==============================

    public void updateDeduction(Deduction deduction) throws SQLException {

        validateDeduction(deduction); // 🔥 validation avant update

        String sql = "UPDATE Deduction SET type_deduction=?, montant=?, date_deduction=?, id_employe=? " +
                "WHERE id_deduction=?";

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {

            stmt.setString(1, deduction.getTypeDeduction());
            stmt.setBigDecimal(2, deduction.getMontant());
            stmt.setDate(3, Date.valueOf(deduction.getDateDeduction()));
            stmt.setInt(4, deduction.getIdEmploye());
            stmt.setInt(5, deduction.getIdDeduction());

            stmt.executeUpdate();
        }
    }

    // ==============================
    // DELETE
    // ==============================

    public void deleteDeduction(int id) throws SQLException {

        String sql = "DELETE FROM Deduction WHERE id_deduction=?";

        try (PreparedStatement stmt = connection.prepareStatement(sql)) {

            stmt.setInt(1, id);
            stmt.executeUpdate();
        }
    }

    // ==============================
    // MAPPING
    // ==============================

    private Deduction mapResultSet(ResultSet rs) throws SQLException {

        Date sqlDate = rs.getDate("date_deduction");
        LocalDate date = sqlDate != null ? sqlDate.toLocalDate() : null;

        return new Deduction(
                rs.getInt("id_deduction"),
                rs.getString("type_deduction"),
                rs.getBigDecimal("montant"),
                date,
                rs.getInt("id_employe")
        );
    }
}