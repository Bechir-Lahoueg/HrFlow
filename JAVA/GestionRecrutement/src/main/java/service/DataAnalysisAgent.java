package service;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.ObservableMap;
import utils.Mydb;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.sql.Connection;
import java.sql.DatabaseMetaData;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Scanner;
import java.util.function.Consumer;
import org.json.JSONArray;
import org.json.JSONObject;

public class DataAnalysisAgent {
    private Connection cnx;
    // Hardcoded API key
    private static final String GROQ_API_KEY = "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";
    private static final String GROQ_API_URL = "https://api.groq.com/openai/v1/chat/completions";
    private static final String GROQ_MODEL = "llama-3.3-70b-versatile";

    public DataAnalysisAgent() {
        this.cnx = Mydb.getInstance().getConnection();
    }

    public void generateTableData(String prompt, Consumer<TableDataResult> callback) {
        new Thread(() -> {
            try {
                // Get database schema
                String dbSchema = getDatabaseSchema();

                // Call Groq API to generate SQL in table mode
                String sqlQuery = callGeminiForSQL(prompt, dbSchema, "table");

                if (sqlQuery != null && !sqlQuery.trim().isEmpty()) {
                    // Execute the SQL query
                    TableDataResult result = executeQuery(sqlQuery);
                    callback.accept(result);
                } else {
                    // Return error result
                    TableDataResult errorResult = new TableDataResult();
                    errorResult
                            .setErrorMessage("Cannot access that table. Use only: applications, interviews, job_offer");
                    callback.accept(errorResult);
                }
            } catch (java.sql.SQLException e) {
                System.err.println("SQL Error: " + e.getMessage());
                TableDataResult errorResult = new TableDataResult();
                errorResult.setErrorMessage("SQL Error: " + e.getMessage());
                callback.accept(errorResult);
            } catch (Exception e) {
                System.err.println("Error generating table data: " + e.getMessage());
                e.printStackTrace();
                TableDataResult errorResult = new TableDataResult();
                errorResult.setErrorMessage("Error: " + e.getMessage());
                callback.accept(errorResult);
            }
        }).start();
    }

    /**
     * Generates chart data from a natural language prompt
     * 
     * @param prompt   The user's natural language query
     * @param callback Callback to handle the chart data
     */
    public void generateChartData(String prompt, Consumer<ChartDataResult> callback) {
        new Thread(() -> {
            try {
                // Get database schema
                String dbSchema = getDatabaseSchema();

                // Call Groq API to generate SQL in chart mode
                String sqlQuery = callGeminiForSQL(prompt, dbSchema, "chart");

                if (sqlQuery != null && !sqlQuery.trim().isEmpty()) {
                    // Execute the SQL query
                    TableDataResult tableData = executeQuery(sqlQuery);

                    // Convert table data to chart data
                    ChartDataResult chartData = convertToChartData(tableData);
                    callback.accept(chartData);
                } else {
                    callback.accept(new ChartDataResult());
                }
            } catch (Exception e) {
                System.err.println("Error generating chart data: " + e.getMessage());
                e.printStackTrace();
                callback.accept(new ChartDataResult());
            }
        }).start();
    }

    /**
     * Extracts database schema for only recruitment-related tables
     * Only includes: interviews, applications, job_offer
     */
    public String getDatabaseSchema() throws Exception {
        StringBuilder schema = new StringBuilder();
        DatabaseMetaData metaData = cnx.getMetaData();

        // Only get recruitment tables
        String[] tablesToInclude = { "applications", "interviews", "job_offer" };

        for (String tableName : tablesToInclude) {
            try {
                ResultSet columns = metaData.getColumns(null, null, tableName, null);
                if (columns.next()) {
                    schema.append("Table: ").append(tableName).append("\n");
                    columns.beforeFirst(); // Reset to beginning

                    while (columns.next()) {
                        String columnName = columns.getString("COLUMN_NAME");
                        String columnType = columns.getString("TYPE_NAME");
                        schema.append("  - ").append(columnName).append(" (").append(columnType).append(")\n");
                    }
                    schema.append("\n");
                }
                columns.close();
            } catch (Exception e) {
                // Table may not exist, skip it
            }
        }

        return schema.toString();
    }

    /**
     * Calls Groq API to convert natural language to SQL
     * 
     * @param userPrompt The natural language query
     * @param dbSchema The database schema information
     * @param mode Either "table" or "chart" to customize the prompt
     */
    private String callGeminiForSQL(String userPrompt, String dbSchema , String mode) throws Exception {
        if (GROQ_API_KEY == null || GROQ_API_KEY.isEmpty()) {
            System.err.println("API_KEY environment variable not set");
            return null;
        }

        // Build mode-specific system prompt
        String baseRules = "You are a SQL expert. Convert the user's natural language request into a valid SQL SELECT query.\n"
                + "AVAILABLE TABLES: applications, interviews, job_offer (ONLY THESE 3).\n"
                + "CRITICAL RULES:\n"
                + "1. ONLY query tables: applications, interviews, job_offer\n"
                + "2. If user asks about other tables (candidates, users, jobs, etc.), REFUSE and return: ERROR_TABLE_NOT_AVAILABLE\n"
                + "3. ALWAYS exclude ID columns (id, user_id, job_id, etc.) unless explicitly requested\n"
                + "4. ALWAYS add 'WHERE is_deleted = false' or 'WHERE is_deleted IS NULL' to exclude deleted records\n"
                + "5. Focus on displaying business-relevant columns (names, dates, status, descriptions)\n"
                + "6. If the query has a WHERE clause, combine it with AND for the is_deleted filter\n"
                + "7. Never use SELECT * - always specify exact columns needed\n";

        String modeSpecificRules = "";
        if ("chart".equalsIgnoreCase(mode)) {
            modeSpecificRules = "8. CHART MODE - Generate aggregated queries:\n"
                    + "   - Use GROUP BY for grouping results\n"
                    + "   - Use COUNT(*) as count, SUM() as total, AVG() as average, MAX() as maximum, MIN() as minimum\n"
                    + "   - ALWAYS provide aliases for aggregation functions (COUNT(*) AS count, not just COUNT(*))\n"
                    + "   - CRITICAL: When using GROUP BY, ALL non-aggregated columns in SELECT must be in GROUP BY clause\n"
                    + "   - CORRECT: SELECT status, COUNT(*) as count FROM applications WHERE is_deleted = false GROUP BY status\n"
                    + "   - WRONG: SELECT status, candidate_name, COUNT(*) as count FROM applications WHERE is_deleted = false GROUP BY status\n"
                    + "   - Limit results to 10-15 rows for better chart visualization\n"
                    + "   - First column is category (x-axis), second column must be numeric value (y-axis)\n"
                    + "   - Order results by the numeric column DESC for best visualization\n";
        } else if ("table".equalsIgnoreCase(mode)) {
            modeSpecificRules = "8. TABLE MODE - Generate detailed row-based queries:\n"
                    + "   - Return complete records with all business-relevant columns\n"
                    + "   - Avoid GROUP BY unless specifically asked for aggregation\n"
                    + "   - Limit results to 100 rows maximum for performance\n"
                    + "   - Sort by most relevant column (e.g., date DESC for recent records)\n";
        }

        String systemPrompt = baseRules + modeSpecificRules + "Database Schema:\n" + dbSchema + "\n"
                + "Return ONLY the SQL query, nothing else. No markdown, no explanations.";

        // Build OpenAI-compatible request for Groq
        JSONObject requestBody = new JSONObject();
        requestBody.put("model", GROQ_MODEL);

        JSONArray messages = new JSONArray();

        JSONObject systemMessage = new JSONObject();
        systemMessage.put("role", "system");
        systemMessage.put("content", systemPrompt);
        messages.put(systemMessage);

        JSONObject userMessage = new JSONObject();
        userMessage.put("role", "user");
        userMessage.put("content", userPrompt);
        messages.put(userMessage);

        requestBody.put("messages", messages);

        URL url = new URL(GROQ_API_URL);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setRequestProperty("Content-Type", "application/json");
        conn.setRequestProperty("Authorization", "Bearer " + GROQ_API_KEY);
        conn.setDoOutput(true);
        conn.setConnectTimeout(10000);
        conn.setReadTimeout(10000);

        try (OutputStream os = conn.getOutputStream()) {
            byte[] input = requestBody.toString().getBytes(StandardCharsets.UTF_8);
            os.write(input, 0, input.length);
        }

        int responseCode = conn.getResponseCode();
        if (responseCode == 200) {
            String response = readResponse(conn, true);
            JSONObject jsonResponse = new JSONObject(response);

            // Extract the text from Groq response (OpenAI-compatible format)
            String sqlQuery = jsonResponse
                    .getJSONArray("choices")
                    .getJSONObject(0)
                    .getJSONObject("message")
                    .getString("content");

            // Clean up the SQL query (remove markdown code blocks if present)
            sqlQuery = sqlQuery.replaceAll("```sql", "").replaceAll("```", "").trim();

            // Check if AI rejected the query (asked about tables outside allowed set)
            if (sqlQuery.contains("ERROR_TABLE_NOT_AVAILABLE")) {
                System.err.println("\n❌ Access Denied");
                System.err.println("   You can only query: applications, interviews, job_offer");
                System.err.println("   Other tables are not available in the AI Mode.");
                return null;
            }

            System.out.println("Generated SQL: " + sqlQuery);
            return sqlQuery;
        } else {
            // Read error response
            String errorResponse = readResponse(conn, false);
            System.err.println("Groq API Error (" + responseCode + "): " + errorResponse);

            // Provide helpful debugging info for common errors
            if (responseCode == 401) {
                System.err.println("\n❌ Authentication Error (401):");
                System.err.println("   Check that your API_KEY environment variable is set correctly.");
                System.err.println("   Current API_KEY is: " + (GROQ_API_KEY == null ? "NOT SET"
                        : (GROQ_API_KEY.isEmpty() ? "EMPTY" : "SET (length: " + GROQ_API_KEY.length() + ")")));
                System.err.println("   Make sure the key is a valid Groq API key.");
            } else if (responseCode == 429) {
                System.err.println("\n❌ Rate Limit Error (429):");
                System.err.println("   You have hit the API rate limit. Please wait before retrying.");
            } else if (responseCode == 500) {
                System.err.println("\n❌ Server Error (500):");
                System.err.println("   Groq API server error. Please try again later.");
            }
            return null;
        }
    }

    /**
     * Reads the response from an HTTP connection
     * 
     * @param conn      The HTTP connection
     * @param isSuccess Whether to read from input stream (true) or error stream
     *                  (false)
     */
    private String readResponse(HttpURLConnection conn, boolean isSuccess) throws Exception {
        java.io.InputStream stream = isSuccess ? conn.getInputStream() : conn.getErrorStream();

        if (stream == null) {
            return "No response body";
        }

        Scanner scanner = new Scanner(stream, StandardCharsets.UTF_8);
        StringBuilder response = new StringBuilder();
        while (scanner.hasNext()) {
            response.append(scanner.nextLine());
        }
        scanner.close();
        return response.toString();
    }

    /**
     * Executes a SQL query and returns results as TableDataResult
     */
    private TableDataResult executeQuery(String sqlQuery) throws Exception {
        TableDataResult result = new TableDataResult();

        try {
            try (Statement stmt = cnx.createStatement();
                    ResultSet rs = stmt.executeQuery(sqlQuery)) {

                ResultSetMetaData metaData = rs.getMetaData();
                int columnCount = metaData.getColumnCount();

                // Get column names (excluding unnecessary ID columns)
                List<String> columnNames = new ArrayList<>();
                for (int i = 1; i <= columnCount; i++) {
                    String colName = metaData.getColumnName(i);
                    // Skip internal ID columns but keep primary identifiers
                    if (!colName.toLowerCase().matches("(user_id|job_id|interview_id|application_id|offer_id)") ||
                            colName.toLowerCase().matches("(application_id|interview_id|offer_id)")) {
                        columnNames.add(colName);
                    } else if (!colName.toLowerCase().endsWith("_id")) {
                        columnNames.add(colName);
                    }
                }

                result.getColumns().addAll(columnNames);

                // Get data rows, filtering out is_deleted records
                while (rs.next()) {
                    // Check if row has is_deleted column and skip if true
                    boolean isDeleted = false;
                    try {
                        Object deletedValue = rs.getObject("is_deleted");
                        if (deletedValue != null) {
                            isDeleted = (boolean) deletedValue ||
                                    (deletedValue instanceof Integer && (Integer) deletedValue == 1);
                        }
                    } catch (Exception e) {
                        // Column doesn't exist, continue
                    }

                    if (isDeleted) {
                        continue; // Skip deleted records
                    }

                    ObservableMap<String, Object> row = FXCollections.observableHashMap();
                    for (String colName : columnNames) {
                        Object value = rs.getObject(colName);
                        row.put(colName, value != null ? value : "");
                    }
                    result.getData().add(row);
                }
            }
        } catch (java.sql.SQLSyntaxErrorException e) {
            String errorMsg = e.getMessage();
            if (errorMsg.contains("ONLY_FULL_GROUP_BY") || errorMsg.contains("GROUP BY")) {
                System.err.println("SQL Group By Error: " + errorMsg);
                result.setErrorMessage("Query error: When grouping results, all selected columns must be in the GROUP BY clause. Try a simpler query without GROUP BY or ensure all columns are properly grouped.");
            } else if (errorMsg.contains("Table") && errorMsg.contains("doesn't exist")) {
                System.err.println("SQL Table Error: " + errorMsg);
                result.setErrorMessage("Query error: One or more tables don't exist. You can only query: applications, interviews, job_offer");
            } else {
                System.err.println("SQL Syntax Error: " + errorMsg);
                result.setErrorMessage("Query error: " + errorMsg);
            }
        } catch (Exception e) {
            System.err.println("Query Execution Error: " + e.getMessage());
            result.setErrorMessage("Error executing query: " + e.getMessage());
        }

        return result;
    }

    /**
     * Converts table data to chart data
     * Assumes first column is category (x-axis) and second column is numeric value (y-axis)
     */
    private ChartDataResult convertToChartData(TableDataResult tableData) {
        ChartDataResult chartData = new ChartDataResult();

        if (tableData.getData().isEmpty()) {
            System.err.println("Warning: No data returned for chart");
            return chartData;
        }

        // Determine chart type based on data structure
        List<String> columns = tableData.getColumns();
        if (columns.size() >= 2) {
            // Use first column as category, second as value
            String categoryCol = columns.get(0);
            String valueCol = columns.get(1);

            System.out.println("Chart conversion - Category: " + categoryCol + ", Value: " + valueCol);
            System.out.println("Total rows to convert: " + tableData.getData().size());

            ObservableMap<String, Object> data = FXCollections.observableHashMap();
            for (ObservableMap<String, Object> row : tableData.getData()) {
                String categoryValue = row.get(categoryCol) != null ? 
                    row.get(categoryCol).toString() : "Unknown";
                Object rawValue = row.get(valueCol);
                
                // Ensure value is numeric
                Number numericValue = 0;
                if (rawValue != null) {
                    try {
                        if (rawValue instanceof Number) {
                            numericValue = (Number) rawValue;
                        } else {
                            numericValue = Double.parseDouble(rawValue.toString());
                        }
                    } catch (NumberFormatException e) {
                        System.err.println("Warning: Could not parse value as number: " + rawValue);
                        numericValue = 0;
                    }
                }
                
                System.out.println("  Adding chart data: " + categoryValue + " = " + numericValue);
                data.put(categoryValue, numericValue);
            }

            System.out.println("Chart data map size: " + data.size());
            chartData.setChartData(data);
            
            // Recommend chart type based on number of categories
            if (data.size() <= 5) {
                chartData.setRecommendedType("PIE");
            } else {
                chartData.setRecommendedType("BAR");
            }
        } else {
            System.err.println("Error: Not enough columns for chart. Expected at least 2, got " + columns.size());
        }

        return chartData;
    }

    /**
     * Inner class representing table data results
     */
    public static class TableDataResult {
        private ObservableList<ObservableMap<String, Object>> data;
        private java.util.List<String> columns;
        private String errorMessage;

        public TableDataResult() {
            this.data = FXCollections.observableArrayList();
            this.columns = new java.util.ArrayList<>();
            this.errorMessage = null;
        }

        public ObservableList<ObservableMap<String, Object>> getData() {
            return data;
        }

        public java.util.List<String> getColumns() {
            return columns;
        }

        public void setData(ObservableList<ObservableMap<String, Object>> data) {
            this.data = data;
        }

        public void setColumns(java.util.List<String> columns) {
            this.columns = columns;
        }

        public String getErrorMessage() {
            return errorMessage;
        }

        public void setErrorMessage(String errorMessage) {
            this.errorMessage = errorMessage;
        }

        public boolean hasError() {
            return errorMessage != null && !errorMessage.isEmpty();
        }
    }

    /**
     * Inner class representing chart data results
     */
    public static class ChartDataResult {
        private String recommendedType; // BAR, LINE, PIE, AREA
        private ObservableMap<String, Object> chartData;

        public ChartDataResult() {
            this.recommendedType = "BAR";
            this.chartData = FXCollections.observableHashMap();
        }

        public ChartDataResult(String recommendedType) {
            this.recommendedType = recommendedType;
            this.chartData = FXCollections.observableHashMap();
        }

        public String getRecommendedType() {
            return recommendedType;
        }

        public ObservableMap<String, Object> getChartData() {
            return chartData;
        }

        public void setRecommendedType(String recommendedType) {
            this.recommendedType = recommendedType;
        }

        public void setChartData(ObservableMap<String, Object> chartData) {
            this.chartData = chartData;
        }
    }
}
