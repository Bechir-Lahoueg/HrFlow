package service.ReportGenerator;

import org.json.JSONArray;
import org.json.JSONObject;
import utils.Mydb;
import java.sql.Connection;
import java.sql.DatabaseMetaData;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.Statement;

/**
 * Database query service for executing SQL queries and retrieving schema information
 */
public class DbQueryService {
    private Connection cnx;

    public DbQueryService() {
        this.cnx = Mydb.getInstance().getConnection();
    }

    /**
     * Gets the actual database schema from the database
     */
    public String getActualDatabaseSchema() {
        StringBuilder schema = new StringBuilder();
        
        try {
            DatabaseMetaData metaData = cnx.getMetaData();
            
            // Get all tables
            ResultSet tables = metaData.getTables(null, null, "%", new String[]{"TABLE"});
            
            while (tables.next()) {
                String tableName = tables.getString("TABLE_NAME");
                
                // Skip system tables
                if (tableName.startsWith("sys_") || tableName.startsWith("information_schema")) {
                    continue;
                }
                
                schema.append("Table: ").append(tableName).append("\n");
                
                // Get columns for this table
                ResultSet columns = metaData.getColumns(null, null, tableName, null);
                while (columns.next()) {
                    String columnName = columns.getString("COLUMN_NAME");
                    String dataType = columns.getString("TYPE_NAME");
                    int columnSize = columns.getInt("COLUMN_SIZE");
                    
                    schema.append("  - ").append(columnName)
                           .append(" (").append(dataType);
                    if (columnSize > 0) {
                        schema.append("(").append(columnSize).append(")");
                    }
                    schema.append(")\n");
                }
                columns.close();
                schema.append("\n");
            }
            tables.close();
            
        } catch (Exception e) {
            System.err.println("Error retrieving database schema: " + e.getMessage());
            // Return hardcoded schema as fallback
            schema.append("Table: applications\n");
            schema.append("  - id (INT)\n");
            schema.append("  - candidate_name (VARCHAR)\n");
            schema.append("  - status (VARCHAR)\n");
            schema.append("  - applied_at (TIMESTAMP)\n");
            schema.append("  - is_deleted (BOOLEAN)\n\n");
            
            schema.append("Table: interviews\n");
            schema.append("  - id (INT)\n");
            schema.append("  - application_id (INT)\n");
            schema.append("  - interview_date (DATETIME)\n");
            schema.append("  - type (VARCHAR)\n");
            schema.append("  - result (VARCHAR)\n");
            schema.append("  - is_deleted (BOOLEAN)\n\n");
            
            schema.append("Table: job_offer\n");
            schema.append("  - id (INT)\n");
            schema.append("  - title (VARCHAR)\n");
            schema.append("  - department (VARCHAR)\n");
            schema.append("  - status (VARCHAR)\n");
            schema.append("  - created_at (TIMESTAMP)\n");
            schema.append("  - is_deleted (BOOLEAN)\n\n");
        }
        
        return schema.toString();
    }

    public String getCompactRecruitmentSchema() {
        StringBuilder schema = new StringBuilder();
        try {
            DatabaseMetaData metaData = cnx.getMetaData();

            String catalog = null;
            try {
                catalog = cnx.getCatalog();
            } catch (Exception e) {
                // ignore
            }

            String[] tableNames = new String[] { "applications", "interviews", "job_offer" };
            for (String tableName : tableNames) {
                schema.append(tableName).append("(");
                java.util.List<String> cols = new java.util.ArrayList<>();

                // Try exact name first
                try (ResultSet columns = metaData.getColumns(catalog, null, tableName, null)) {
                    while (columns.next()) {
                        cols.add(columns.getString("COLUMN_NAME"));
                    }
                }

                // Retry with different casing if nothing found (some setups are case-sensitive)
                if (cols.isEmpty()) {
                    try (ResultSet columns = metaData.getColumns(catalog, null, tableName.toUpperCase(), null)) {
                        while (columns.next()) {
                            cols.add(columns.getString("COLUMN_NAME"));
                        }
                    }
                }
                if (cols.isEmpty()) {
                    try (ResultSet columns = metaData.getColumns(catalog, null, tableName.toLowerCase(), null)) {
                        while (columns.next()) {
                            cols.add(columns.getString("COLUMN_NAME"));
                        }
                    }
                }

                if (cols.isEmpty()) {
                    throw new RuntimeException("No columns returned by DatabaseMetaData for table: " + tableName + " (catalog=" + catalog + ")");
                }

                schema.append(String.join(",", cols));
                schema.append(")\n");
            }

            System.out.println("DEBUG: Compact schema loaded from DatabaseMetaData (catalog=" + catalog + "), tables=" + java.util.Arrays.toString(tableNames));
        } catch (Exception e) {
            System.err.println("Error retrieving compact schema: " + e.getMessage());
            schema.append("applications(id,candidate_name,job_offer_id,cv_path,cover_letter_path,status,notes,applied_at,is_deleted,Department,experience_level,EmailAddress,employee_id)\n");
            schema.append("interviews(id,application_id,interviewer_id,interview_date,type,meeting_link,location,feedback,score,result,is_deleted)\n");
            schema.append("job_offer(id,title,description,department,location,employmentType,salary_min,salary_max,status,created_by,created_at,is_deleted)\n");
        }
        return schema.toString();
    }

    /**
     * Executes a SQL query and returns the result
     */
    public java.sql.ResultSet executeQuery(String sql) throws Exception {
        Statement stmt = cnx.createStatement();
        return stmt.executeQuery(sql);
    }

    public JSONObject executeSelectQuery(String sql) {
        JSONObject result = new JSONObject();
        try (Statement stmt = cnx.createStatement();
                ResultSet rs = stmt.executeQuery(sql)) {

            ResultSetMetaData meta = rs.getMetaData();
            int columnCount = meta.getColumnCount();

            JSONArray columns = new JSONArray();
            for (int i = 1; i <= columnCount; i++) {
                columns.put(meta.getColumnLabel(i));
            }

            JSONArray rows = new JSONArray();
            while (rs.next()) {
                JSONObject row = new JSONObject();
                for (int i = 1; i <= columnCount; i++) {
                    String col = meta.getColumnLabel(i);
                    Object value = rs.getObject(i);
                    row.put(col, value == null ? JSONObject.NULL : value);
                }
                rows.put(row);
            }

            result.put("success", true);
            result.put("columns", columns);
            result.put("rows", rows);
            result.put("rowCount", rows.length());
            return result;
        } catch (Exception e) {
            result.put("success", false);
            result.put("error", e.getMessage() == null ? "Query execution failed" : e.getMessage());
            return result;
        }
    }
}
