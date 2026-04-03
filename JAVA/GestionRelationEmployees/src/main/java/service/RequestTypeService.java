package service;

import models.RequestType;
import utils.Mydb;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class RequestTypeService {

    private final Connection conn;

    public RequestTypeService() {
        this.conn = Mydb.getInstance().getConnection();
    }

    // ─── CREATE ──────────────────────────────────────────────────────

    public boolean add(RequestType rt) {
        String sql = "INSERT INTO request_types (name, description, requires_approval) VALUES (?, ?, ?)";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, rt.getName());
            ps.setString(2, rt.getDescription());
            ps.setBoolean(3, rt.isRequiresApproval());
            ps.executeUpdate();
            System.out.println("✅ Type de demande ajouté : " + rt.getName());
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur add RequestType : " + e.getMessage());
            return false;
        }
    }

    // ─── READ ALL ────────────────────────────────────────────────────

    public List<RequestType> getAll() {
        List<RequestType> list = new ArrayList<>();
        String sql = "SELECT * FROM request_types";
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(sql)) {
            while (rs.next()) {
                list.add(mapRow(rs));
            }
        } catch (SQLException e) {
            System.err.println("❌ Erreur getAll RequestType : " + e.getMessage());
        }
        return list;
    }

    // ─── READ BY ID ──────────────────────────────────────────────────

    public RequestType getById(int id) {
        String sql = "SELECT * FROM request_types WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ResultSet rs = ps.executeQuery();
            if (rs.next()) return mapRow(rs);
        } catch (SQLException e) {
            System.err.println("❌ Erreur getById RequestType : " + e.getMessage());
        }
        return null;
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public boolean update(RequestType rt) {
        String sql = "UPDATE request_types SET name=?, description=?, requires_approval=? WHERE id=?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, rt.getName());
            ps.setString(2, rt.getDescription());
            ps.setBoolean(3, rt.isRequiresApproval());
            ps.setInt(4, rt.getId());
            ps.executeUpdate();
            System.out.println("✅ Type de demande mis à jour : " + rt.getName());
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur update RequestType : " + e.getMessage());
            return false;
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────

    public boolean delete(int id) {
        String sql = "DELETE FROM request_types WHERE id = ?";
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
            System.out.println("✅ Type de demande supprimé (id=" + id + ")");
            return true;
        } catch (SQLException e) {
            System.err.println("❌ Erreur delete RequestType : " + e.getMessage());
            return false;
        }
    }

    // ─── HELPER : mapper un ResultSet → RequestType ──────────────────

    private RequestType mapRow(ResultSet rs) throws SQLException {
        return new RequestType(
                rs.getInt("id"),
                rs.getString("name"),
                rs.getString("description"),
                rs.getBoolean("requires_approval"),
                rs.getTimestamp("created_at")
        );
    }
}