package service.DataVisualization;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.ObservableMap;
import service.ReportGenerator.ApiKeyManager;
import service.ReportGenerator.DbQueryService;
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
    private static final String GEMINI_MODEL = "gemini-3-flash-preview";
    private static final String GEMINI_API_URL = "https://generativelanguage.googleapis.com/v1beta/models/" + GEMINI_MODEL
            + ":generateContent";

    private static final String DEFAULT_GROQ_MODEL = "llama-3.3-70b-versatile";
    private static final String GROQ_API_URL = "https://api.groq.com/openai/v1/chat/completions";

    public DataAnalysisAgent() {
        this.cnx = Mydb.getInstance().getConnection();
    }

    private String callGroqForSql(String userPrompt, String dbSchema, String mode) throws Exception {
        String groqKey = ApiKeyManager.getGroqApiKey();
        if (groqKey == null || groqKey.isEmpty()) {
            System.err.println("CRITICAL: [DataAnalysisAgent] No Groq API key found!");
            return null;
        }

        System.out.println("DEBUG: Calling Groq API for " + mode + " generation");

        String prompt = buildPrompt(userPrompt, dbSchema, mode);
        prompt = prompt + "\n\nReturn ONLY valid JSON like {\"sql\": \"SELECT ...\"}. No markdown.";

        String modelToUse = DEFAULT_GROQ_MODEL;
        try {
            java.io.InputStream input = ApiKeyManager.class.getClassLoader().getResourceAsStream("config.properties");
            if (input != null) {
                java.util.Properties props = new java.util.Properties();
                props.load(input);
                input.close();
                String configured = props.getProperty("groq.api.model");
                if (configured != null && !configured.trim().isEmpty()) {
                    modelToUse = configured.trim();
                }
            }
        } catch (Exception e) {
            // ignore
        }

        JSONObject requestBody = new JSONObject();
        requestBody.put("model", modelToUse);

        JSONArray messages = new JSONArray();
        messages.put(new JSONObject().put("role", "system").put("content", "You are a SQL expert."));
        messages.put(new JSONObject().put("role", "user").put("content", prompt));
        requestBody.put("messages", messages);

        int maxAttempts = 3;
        long backoffMs = 1500;
        for (int attempt = 1; attempt <= maxAttempts; attempt++) {
            java.net.HttpURLConnection conn = null;
            try {
                java.net.URL url = new java.net.URL(GROQ_API_URL);
                conn = (java.net.HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("Authorization", "Bearer " + groqKey);
                conn.setDoOutput(true);
                conn.setConnectTimeout(30000);
                conn.setReadTimeout(90000);

                try (java.io.OutputStream os = conn.getOutputStream()) {
                    byte[] input = requestBody.toString().getBytes(java.nio.charset.StandardCharsets.UTF_8);
                    os.write(input, 0, input.length);
                }

                int responseCode = conn.getResponseCode();
                System.out.println("DEBUG: Groq API response code: " + responseCode + " (attempt " + attempt + "/" + maxAttempts + ")");

                if (responseCode == 200) {
                    String response = readResponse(conn, true);
                    JSONObject json = new JSONObject(response);
                    String content = json.getJSONArray("choices").getJSONObject(0).getJSONObject("message").getString("content").trim();
                    JSONObject sqlJson = new JSONObject(content);
                    String sqlQuery = sqlJson.getString("sql");
                    sqlQuery = sqlQuery.replaceAll("```sql", "").replaceAll("```", "").trim();
                    System.out.println("Generated SQL: " + sqlQuery);
                    return sqlQuery;
                }

                String errorResponse = readResponse(conn, false);
                System.err.println("Groq API Error (" + responseCode + ") [attempt " + attempt + "]: " + errorResponse);

                if ((responseCode == 429 || responseCode == 503) && attempt < maxAttempts) {
                    Thread.sleep(backoffMs);
                    backoffMs *= 2;
                    continue;
                }

                return null;
            } catch (java.net.SocketTimeoutException timeout) {
                System.err.println("Groq API timeout [attempt " + attempt + "]: " + timeout.getMessage());
                if (attempt < maxAttempts) {
                    Thread.sleep(backoffMs);
                    backoffMs *= 2;
                    continue;
                }
                return null;
            } finally {
                if (conn != null) {
                    conn.disconnect();
                }
            }
        }

        return null;
    }

    private String buildPrompt(String userPrompt, String dbSchema, String mode) {
        String baseRules = "You are a SQL expert. Convert the user's request into a valid MySQL SELECT query.\n"
        + "IMPORTANT: The database schema below is the ONLY source of truth for table/column names.\n"
        + "If the user mentions a field name that does NOT exist, treat it as a business description and choose the closest matching column from the schema.\n"
        + "NEVER invent columns. NEVER use unknown identifiers.\n"
        + "Use the exact column spelling/casing as provided in the schema below.\n\n"
        + "AVAILABLE TABLES (ONLY): applications, interviews, job_offer\n"
        + "CRITICAL RULES:\n"
        + "1. ONLY query those 3 tables\n"
        + "2. ALWAYS add 'WHERE is_deleted = false'\n"
        + "3. NEVER use SELECT *\n"
        + "4. Use exact column names and casing as provided in the schema.\n";
        String modeSpecificRules = "";
        if ("chart".equalsIgnoreCase(mode)) {
            modeSpecificRules = "CHART MODE: use aggregation + GROUP BY; return exactly 2 columns with aliases: category, value.\n"
                            + "ALWAYS fully qualify columns with table names (e.g., applications.Department).\n";
        } else {
            modeSpecificRules = "TABLE MODE: return detailed rows; LIMIT 100.\n"
                                  + "ALWAYS fully qualify columns with table names (e.g., applications.Department).\n";
        }

        return baseRules + modeSpecificRules
                + "Database Schema:\n" + dbSchema + "\n"
                + "User Request: " + userPrompt + "\n\n"
                + "Return ONLY the SQL query, no explanation.";
    }

    public void generateTableData(String prompt, Consumer<TableDataResult> callback) {
        System.out.println("DEBUG: DataAnalysisAgent.generateTableData() called with prompt: " + prompt);
        new Thread(() -> {
            try {
                // Get database schema
                String dbSchema = getDatabaseSchema();
                System.out.println("DEBUG: Database schema retrieved, length: " + dbSchema.length());

                String sqlQuery;
                if (ApiKeyManager.getLlmProvider() == ApiKeyManager.LlmProvider.GROQ) {
                    sqlQuery = callGroqForSql(prompt, dbSchema, "table");
                } else {
                    sqlQuery = callGeminiAPI(prompt, dbSchema, "table");
                }
                System.out.println("DEBUG: SQL query generated: " + (sqlQuery != null ? "SUCCESS" : "NULL"));

                if (sqlQuery != null && !sqlQuery.trim().isEmpty()) {
                    // Execute the SQL query
                    TableDataResult result = executeQuery(sqlQuery);
                    result.setGeneratedSql(sqlQuery);
                    callback.accept(result);
                } else {
                    // Return error result
                    TableDataResult errorResult = new TableDataResult();
                    // Distinguish between API failure and access restriction
                    String errorMsg = "AI generation failed. Please check your API key and connection in the console.";
                    if (prompt.toLowerCase().contains("user") || prompt.toLowerCase().contains("salary")) {
                        errorMsg = "Table Access Denied. Use only: applications, interviews, job_offer";
                    }
                    errorResult.setErrorMessage(errorMsg);
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
        System.out.println("DEBUG: DataAnalysisAgent.generateChartData() called with prompt: " + prompt);
        new Thread(() -> {
            try {
                // Get database schema
                String dbSchema = getDatabaseSchema();
                System.out.println("DEBUG: Database schema retrieved for chart, length: " + dbSchema.length());

                String sqlQuery;
                if (ApiKeyManager.getLlmProvider() == ApiKeyManager.LlmProvider.GROQ) {
                    sqlQuery = callGroqForSql(prompt, dbSchema, "chart");
                } else {
                    sqlQuery = callGeminiAPI(prompt, dbSchema, "chart");
                }
                System.out.println("DEBUG: SQL query generated for chart: " + (sqlQuery != null ? "SUCCESS" : "NULL"));

                if (sqlQuery != null && !sqlQuery.trim().isEmpty()) {
                    // Execute the SQL query and convert to chart data
                    try {
                        TableDataResult tableData = executeQuery(sqlQuery);
                        if (tableData.hasError()) {
                            ChartDataResult errorResult = new ChartDataResult();
                            errorResult.setErrorMessage(tableData.getErrorMessage());
                            callback.accept(errorResult);
                            return;
                        }

                        ChartDataResult result = convertToChartData(tableData);
                        callback.accept(result);
                    } catch (Exception execErr) {
                        ChartDataResult errorResult = new ChartDataResult();
                        errorResult.setErrorMessage("Query execution failed: " + execErr.getMessage());
                        callback.accept(errorResult);
                    }
                } else {
                    callback.accept(new ChartDataResult());
                }
            } catch (Exception e) {
                System.err.println("Error generating chart data: " + e.getMessage());
                e.printStackTrace();
                ChartDataResult errorResult = new ChartDataResult();
                errorResult.setErrorMessage("Error generating chart data: " + e.getMessage());
                callback.accept(errorResult);
            }
        }).start();
    }

    /**
     * Extracts database schema for only recruitment-related tables
     * Only includes: interviews, applications, job_offer
     */
    public String getDatabaseSchema() throws Exception {
        return new service.ReportGenerator.DbQueryService().getCompactRecruitmentSchema();
    }

    /**
     * Calls Google Gemini API to convert natural language to SQL
     * 
     * @param userPrompt The natural language query
     * @param mode       Either "table" or "chart" to customize the prompt
     */
    private String callGeminiAPI(String userPrompt, String mode) throws Exception {
        return callGeminiAPI(userPrompt, getDatabaseSchema(), mode);
    }

    /**
     * Calls Google Gemini API to convert natural language to SQL
     * 
     * @param userPrompt The natural language query
     * @param dbSchema   The database schema
     * @param mode       Either "table" or "chart" to customize the prompt
     */
    private String callGeminiAPI(String userPrompt, String dbSchema, String mode) throws Exception {
        System.out.println("DEBUG: Calling Google Gemini API for " + mode + " generation");
        
        // Get API key using ApiKeyManager
        String apiKey = ApiKeyManager.getApiKey();
        if (apiKey == null || apiKey.isEmpty()) {
            System.err.println("CRITICAL: [DataAnalysisAgent] No Gemini API key found!");
            return null;
        }
        System.out.println("DEBUG: [DataAnalysisAgent] Using API key from ApiKeyManager (length: " + apiKey.length() + ")");
        

   

        String prompt = buildPrompt(userPrompt, getDatabaseSchema(), mode);

        // Build Gemini API request
        JSONObject requestBody = new JSONObject();
        JSONArray contents = new JSONArray();
        JSONObject content = new JSONObject();
        JSONArray parts = new JSONArray();
        JSONObject part = new JSONObject();
        part.put("text", prompt);
        parts.put(part);
        content.put("parts", parts);
        contents.put(content);
        requestBody.put("contents", contents);

        // Request structured JSON output: {"sql": "..."}
        JSONObject genConfig = new JSONObject();
        genConfig.put("responseMimeType", "application/json");
        JSONObject responseSchema = new JSONObject();
        responseSchema.put("type", "object");
        JSONObject props = new JSONObject();
        props.put("sql", new JSONObject().put("type", "string"));
        responseSchema.put("properties", props);
        responseSchema.put("required", new JSONArray().put("sql"));
        genConfig.put("responseJsonSchema", responseSchema);
        requestBody.put("generationConfig", genConfig);

        int maxAttempts = 3;
        long backoffMs = 1500;
        for (int attempt = 1; attempt <= maxAttempts; attempt++) {
            java.net.HttpURLConnection conn = null;
            try {
                // Make HTTP request to Gemini API (curl-compatible headers)
                java.net.URL url = new java.net.URL(GEMINI_API_URL);
                conn = (java.net.HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setRequestProperty("x-goog-api-key", apiKey);
                conn.setDoOutput(true);
                conn.setConnectTimeout(30000);
                conn.setReadTimeout(90000);

                try (java.io.OutputStream os = conn.getOutputStream()) {
                    byte[] input = requestBody.toString().getBytes(java.nio.charset.StandardCharsets.UTF_8);
                    os.write(input, 0, input.length);
                }

                int responseCode = conn.getResponseCode();
                System.out.println("DEBUG: Gemini API response code: " + responseCode + " (attempt " + attempt + "/" + maxAttempts + ")");

                if (responseCode == 200) {
                    String response = readResponse(conn, true);
                    System.out.println("DEBUG: Gemini API response received");

                    // Parse Gemini response
                    JSONObject jsonResponse = new JSONObject(response);
                    JSONArray candidates = jsonResponse.optJSONArray("candidates");
                    if (candidates == null || candidates.isEmpty()) {
                        return null;
                    }

                    JSONObject candidate = candidates.getJSONObject(0);
                    JSONObject contentResponse = candidate.getJSONObject("content");
                    JSONArray partsResponse = contentResponse.getJSONArray("parts");
                    if (partsResponse.isEmpty()) {
                        return null;
                    }

                    String responseText = partsResponse.getJSONObject(0).getString("text").trim();
                    JSONObject sqlJson = new JSONObject(responseText);
                    String sqlQuery = sqlJson.getString("sql");

                    sqlQuery = sqlQuery.replaceAll("```sql", "").replaceAll("```", "").trim();
                    System.out.println("Generated SQL: " + sqlQuery);
                    return sqlQuery;
                }

                String errorResponse = readResponse(conn, false);
                System.err.println("Gemini API Error (" + responseCode + ") [attempt " + attempt + "]: " + errorResponse);

                if (responseCode == 429 || responseCode == 503) {
                    if (attempt < maxAttempts) {
                        Thread.sleep(backoffMs);
                        backoffMs *= 2;
                        continue;
                    }
                }

                return null;
            } catch (java.net.SocketTimeoutException timeout) {
                System.err.println("Gemini API timeout [attempt " + attempt + "]: " + timeout.getMessage());
                if (attempt < maxAttempts) {
                    Thread.sleep(backoffMs);
                    backoffMs *= 2;
                    continue;
                }
                return null;
            } finally {
                if (conn != null) {
                    conn.disconnect();
                }
            }
        }

        return null;
    }

    /**
     * Reads response from HTTP connection
     * @param conn The HTTP connection
     * @param isSuccess Whether to read from input stream (true) or error stream (false)
     */
    private String readResponse(java.net.HttpURLConnection conn, boolean isSuccess) throws Exception {
        java.io.InputStream stream = isSuccess ? conn.getInputStream() : conn.getErrorStream();
        
        if (stream == null) {
            return "No response body";
        }
        
        try (java.io.BufferedReader reader = new java.io.BufferedReader(new java.io.InputStreamReader(stream, java.nio.charset.StandardCharsets.UTF_8))) {
            StringBuilder response = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                response.append(line);
                response.append('\n');
            }
            return response.toString();
        }
    }

    /**
     * Executes a SQL query and returns results as TableDataResult
     */
    public TableDataResult executeQuery(String sqlQuery) throws Exception {
        TableDataResult result = new TableDataResult();

        try {
            try (Statement stmt = cnx.createStatement();
                    ResultSet rs = stmt.executeQuery(sqlQuery)) {

                ResultSetMetaData metaData = rs.getMetaData();
                int columnCount = metaData.getColumnCount();

                // Get all column names
                List<String> columnNames = new ArrayList<>();
                for (int i = 1; i <= columnCount; i++) {
                    String colName = metaData.getColumnName(i);
                    columnNames.add(colName);
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
                    for (int i = 0; i < columnNames.size(); i++) {
                        String colName = columnNames.get(i);
                        // Use column index (i+1) to get the actual value, handling aliases correctly
                        Object value = rs.getObject(i + 1);
                        row.put(colName, value != null ? value : "");
                    }
                    result.getData().add(row);
                }
            }
        } catch (java.sql.SQLSyntaxErrorException e) {
            String errorMsg = e.getMessage();
            if (errorMsg.contains("ONLY_FULL_GROUP_BY") || errorMsg.contains("GROUP BY")) {
                System.err.println("SQL Group By Error: " + errorMsg);
                result.setErrorMessage(
                        "Query error: When grouping results, all selected columns must be in the GROUP BY clause. Try a simpler query without GROUP BY or ensure all columns are properly grouped.");
            } else if (errorMsg.contains("Table") && errorMsg.contains("doesn't exist")) {
                System.err.println("SQL Table Error: " + errorMsg);
                result.setErrorMessage(
                        "Query error: One or more tables don't exist. You can only query: applications, interviews, job_offer");
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
     * Assumes first column is category (x-axis) and second column is numeric value
     * (y-axis)
     */
    public ChartDataResult convertToChartData(TableDataResult tableData) {
        ChartDataResult chartData = new ChartDataResult();

        if (tableData == null) {
            chartData.setErrorMessage("No table data returned");
            return chartData;
        }

        if (tableData.hasError()) {
            chartData.setErrorMessage(tableData.getErrorMessage());
            return chartData;
        }

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
                String categoryValue = row.get(categoryCol) != null ? row.get(categoryCol).toString() : "Unknown";
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
     * Executes a SQL query for chart data and returns ChartDataResult
     */
    public ChartDataResult executeChartQuery(String sqlQuery) throws Exception {
        // First execute the query to get table data
        TableDataResult tableData = executeQuery(sqlQuery);

        if (tableData.hasError()) {
            ChartDataResult errorResult = new ChartDataResult();
            errorResult.setRecommendedType("BAR");
            errorResult.setChartData(FXCollections.observableHashMap());
            errorResult.setErrorMessage(tableData.getErrorMessage());
            return errorResult;
        }

        // Convert table data to chart data
        return convertToChartData(tableData);
    }

    /**
     * Gets actual database schema from DbQueryService
     */
    private String getActualDatabaseSchema() {
        DbQueryService dbService = new DbQueryService();
        return dbService.getActualDatabaseSchema();
    }
}
