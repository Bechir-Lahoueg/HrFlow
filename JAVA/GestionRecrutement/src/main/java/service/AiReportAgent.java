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
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import java.util.Scanner;
import java.util.function.Consumer;
import org.json.JSONArray;
import org.json.JSONObject;

/**
 * AI Report Agent - Generates structured reports with text, tables, and charts
 * using Groq API with JSON schema response format
 */
public class AiReportAgent {
    private Connection cnx;
    // Hardcoded API keys
    private static final String GROQ_API_KEY = "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";
    private static final String GROQ_API_URL = "https://api.groq.com/openai/v1/chat/completions";
    private static final String GROQ_MODEL = "openai/gpt-oss-20b";

    public AiReportAgent() {
        this.cnx = Mydb.getInstance().getConnection();
    }

    /**
     * Generates a structured report from a user prompt
     * @param prompt The user's report request
     * @param callback Callback to handle the report result
     */
    public void generateReport(String prompt, Consumer<ReportResult> callback) {
        new Thread(() -> {
            try {
                String dbSchema = getDatabaseSchema();
                String systemPrompt = buildReportSystemPrompt(dbSchema);
                
                ReportResult reportResult = callGroqForReportStructure(prompt, systemPrompt);
                callback.accept(reportResult);
            } catch (Exception e) {
                System.err.println("Error generating report: " + e.getMessage());
                e.printStackTrace();
                ReportResult errorResult = new ReportResult();
                errorResult.setError("Error: " + e.getMessage());
                callback.accept(errorResult);
            }
        }).start();
    }

    /**
     * Builds the system prompt for report generation
     */
    private String buildReportSystemPrompt(String dbSchema) {
        return "You are an expert HR Business Analyst specializing in recruitment metrics and workforce intelligence.\n" +
                "Your task is to generate a COMPREHENSIVE, DETAILED, NARRATIVE-HEAVY structured report.\n\n" +
                
                "ABSOLUTE CONTENT REQUIREMENTS:\n" +
                "USE THESE REQUIREMENTS BY DEFAULT BUT IF USER SUGGEST A SPECEFIC FORMAT YOU MUST FOLLOW IT:\n" +
                "1. TEXT-FIRST APPROACH: MINIMUM 70% text/narrative content, 30% tables/charts\n" +
                "2. MANDATORY STRUCTURE:\n" +
                "   - EVERY table or chart MUST be preceded by 1-2 text blocks explaining the context and insights\n" +
                "   - EVERY table or chart MUST be followed by 1-2 text blocks analyzing the findings\n" +
                "   - Never place two visualizations (tables/charts) directly next to each other without text between them\n" +
                "   - Total minimum blocks: 8-12 blocks per report\n" +
                "   - At least 5-7 of these MUST be text blocks\n\n" +
                
                "MANDATORY TEXT BLOCK PATTERN:\n" +
                "[text] Context/Setup → [table/chart] Data → [text] Analysis/Interpretation\n" +
                "This pattern MUST repeat multiple times throughout the report.\n\n" +
                
                "REQUIRED SECTIONS IN ORDER:\n" +
                "1. [text] Executive Summary - Key findings with specific numbers and metrics\n" +
                "2. [text] Strategic Context - Background on recruitment challenges and opportunities\n" +
                "3. [text] Pipeline Overview - Initial insights into the recruitment funnel\n" +
                "4. [table] Application Details - Complete application data with status breakdown\n" +
                "5. [text] Application Analysis - Deep dive into patterns, trends, bottlenecks, and anomalies\n" +
                "6. [text] Candidate Quality Insights - Assessment of applicant quality metrics\n" +
                "7. [chart] Key Metric Visualization - BAR or PIE chart of primary metric\n" +
                "8. [text] Visualization Interpretation - Explain what the chart reveals and why it matters\n" +
                "9. [text] Interview Funnel Analysis - Performance metrics and conversion rates\n" +
                "10. [table] Interview Performance - Interview data with outcomes\n" +
                "11. [text] Interview Success Factors - Statistical insights and patterns\n" +
                "12. [chart] Trend or Comparison Chart - LINE, AREA, or secondary visualization\n" +
                "13. [text] Chart Analysis - Business implications of trends shown\n" +
                "14. [text] Strategic Recommendations - Specific, actionable next steps\n\n" +
                
                "TEXT BLOCK CONTENT REQUIREMENTS:\n" +
                "- Each text block must be 3-5 sentences minimum (NOT 1-2 sentences)\n" +
                "- ALWAYS include specific metrics: percentages, counts, averages, growth rates\n" +
                "- ALWAYS include context: why this matters, impact on business goals\n" +
                "- ALWAYS compare: trends, year-over-year, department benchmarks\n" +
                "- ALWAYS highlight: outliers, successes, areas for improvement\n" +
                "- ALWAYS conclude with: implications or recommended actions\n" +
                "- Use concrete language: '42% of applicants', 'average interview duration of 35 minutes', not vague statements\n\n" +
                
                "VISUALIZATION REQUIREMENTS:\n" +
                "- Use ONLY when necessary to show key metrics visually\n" +
                "- EACH visualization MUST be surrounded by text blocks (before + after)\n" +
                "- Include SQL description explaining data source\n" +
                "- Chart types: BAR (comparisons), PIE (proportions/composition), LINE (trends over time), AREA (cumulative trends)\n" +
                "- Maximum 3 visualizations per report\n" +
                "- Each visualization must directly support narrative findings\n\n" +
                
                "DATABASE QUERY REQUIREMENTS:\n" +
                "- Only query tables: applications, interviews, job_offer\n" +
                "- CRITICAL: Use VALID MySQL 8.0 syntax ONLY - NO PostgreSQL functions\n" +
                "- FORBIDDEN functions: DATE_TRUNC, DATE_PART, TO_DATE (these are PostgreSQL)\n" +
                "- REQUIRED MySQL functions: DATE_FORMAT(), DATE_ADD(), YEAR(), MONTH(), DAY(), STR_TO_DATE()\n" +
                "- Always use backticks for table/column names: `table_name`, `column_name`\n" +
                "- Example valid query: SELECT status, COUNT(*) as count FROM `applications` WHERE is_deleted = false GROUP BY status;\n" +
                "- ALWAYS include: WHERE is_deleted = false OR WHERE is_deleted IS NULL\n" +
                "- All GROUP BY columns must be in SELECT clause\n" +
                "- Test queries mentally for correctness BEFORE including them\n" +
                "- Use GROUP BY for aggregations with proper syntax\n" +
                "- Include relevant date filters when appropriate\n" +
                "- Never split SQL or use incomplete parentheses\n\n" +
                
                "CRITICAL SUCCESS CRITERIA:\n" +
                "✓ Report reads like a professional HR analytics document, not a data dump\n" +
                "✓ Anyone reading should understand 'so what?' for each visualization\n" +
                "✓ Clear narrative thread connecting sections\n" +
                "✓ Every number mentioned has context and implication\n" +
                "✓ Text blocks are substantive, detailed, and insightful\n" +
                "✓ All SQL queries must be valid MySQL 8.0 syntax\n" +
                "✗ DO NOT generate only charts/tables with minimal text\n" +
                "✗ DO NOT place visualizations side-by-side without text between\n" +
                "✗ DO NOT include single-sentence text blocks\n" +
                "✗ DO NOT skip analysis of visualization results\n" +
                "✗ DO NOT use PostgreSQL functions - ONLY valid MySQL syntax\n" +
                "✗ DO NOT use incomplete or broken SQL statements\n" +
                "✗ DO NOT use functions that don't exist in MySQL 8.0\n\n" +
                
                "Database Schema:\n" + dbSchema + "\n" +
                "RETURN ONLY VALID JSON. Generate a NARRATIVE-RICH report that tells a compelling HR analytics story using data, insights, and visualizations strategically.";
    }

    /**
     * Gets database schema for recruitment tables
     */
    private String getDatabaseSchema() throws Exception {
        StringBuilder schema = new StringBuilder();
        java.sql.DatabaseMetaData metaData = cnx.getMetaData();
        String[] tablesToInclude = {"applications", "interviews", "job_offer"};

        for (String tableName : tablesToInclude) {
            try {
                ResultSet columns = metaData.getColumns(null, null, tableName, null);
                if (columns.next()) {
                    schema.append("Table: ").append(tableName).append("\n");
                    columns.beforeFirst();
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
     * Calls Groq API with JSON schema to get structured report
     */
    private ReportResult callGroqForReportStructure(String userPrompt, String systemPrompt) throws Exception {
        if (GROQ_API_KEY == null || GROQ_API_KEY.isEmpty()) {
            throw new Exception("API_KEY not set");
        }

        // Build JSON schema for response
        JSONObject schema = buildReportJsonSchema();

        // Build request
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
        requestBody.put("response_format", schema);

        // Send request
        URL url = new URL(GROQ_API_URL);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setRequestProperty("Content-Type", "application/json");
        conn.setRequestProperty("Authorization", "Bearer " + GROQ_API_KEY);
        conn.setDoOutput(true);
        conn.setConnectTimeout(15000);
        conn.setReadTimeout(30000);

        try (OutputStream os = conn.getOutputStream()) {
            byte[] input = requestBody.toString().getBytes(StandardCharsets.UTF_8);
            os.write(input, 0, input.length);
        }

        int responseCode = conn.getResponseCode();
        if (responseCode == 200) {
            String response = readResponse(conn, true);
            JSONObject jsonResponse = new JSONObject(response);

            String reportJson = jsonResponse
                    .getJSONArray("choices")
                    .getJSONObject(0)
                    .getJSONObject("message")
                    .getString("content");

            // Parse report JSON
            JSONObject reportObj = new JSONObject(reportJson);
            ReportResult result = new ReportResult();
            result.setTitle(reportObj.getString("title"));
            result.setSummary(reportObj.getString("summary"));

            JSONArray blocksArray = reportObj.getJSONArray("blocks");
            for (int i = 0; i < blocksArray.length(); i++) {
                JSONObject blockObj = blocksArray.getJSONObject(i);
                ReportBlock block = new ReportBlock();
                
                try {
                    block.setType(blockObj.getString("type"));

                    if ("text".equals(block.getType())) {
                        if (!blockObj.has("content")) {
                            System.err.println("Skipping text block: missing 'content' field");
                            continue;
                        }
                        block.setContent(blockObj.getString("content"));
                    } else if ("table".equals(block.getType())) {
                        if (!blockObj.has("sql") || !blockObj.has("description")) {
                            System.err.println("Skipping table block: missing 'sql' or 'description' field");
                            continue;
                        }
                        block.setSql(blockObj.getString("sql"));
                        block.setDescription(blockObj.getString("description"));
                    } else if ("chart".equals(block.getType())) {
                        if (!blockObj.has("sql") || !blockObj.has("chartType") || !blockObj.has("description")) {
                            System.err.println("Skipping chart block: missing 'sql', 'chartType', or 'description' field");
                            continue;
                        }
                        block.setSql(blockObj.getString("sql"));
                        block.setChartType(blockObj.getString("chartType"));
                        block.setDescription(blockObj.getString("description"));
                    }

                    result.getBlocks().add(block);
                } catch (Exception e) {
                    System.err.println("Error parsing block " + i + ": " + e.getMessage());
                }
            }

            return result;
        } else {
            String errorResponse = readResponse(conn, false);
            System.err.println("Groq API Error (" + responseCode + "): " + errorResponse);
            ReportResult errorResult = new ReportResult();
            errorResult.setError("API Error: " + responseCode);
            return errorResult;
        }
    }

    /**
     * Builds JSON schema for Groq API response format
     */
    private JSONObject buildReportJsonSchema() {
        JSONObject schema = new JSONObject();
        schema.put("type", "json_schema");

        JSONObject jsonSchema = new JSONObject();
        jsonSchema.put("name", "recruitment_report");
        jsonSchema.put("strict", false);

        JSONObject schemaObj = new JSONObject();
        schemaObj.put("type", "object");

        JSONObject properties = new JSONObject();

        // Title property
        JSONObject titleProp = new JSONObject();
        titleProp.put("type", "string");
        properties.put("title", titleProp);

        // Summary property
        JSONObject summaryProp = new JSONObject();
        summaryProp.put("type", "string");
        properties.put("summary", summaryProp);

        // Blocks property
        JSONObject blocksProp = new JSONObject();
        blocksProp.put("type", "array");

        JSONObject itemsProp = new JSONObject();
        itemsProp.put("type", "object");

        JSONObject blockProperties = new JSONObject();

        // Block type property
        JSONObject typeProp = new JSONObject();
        typeProp.put("type", "string");
        typeProp.put("enum", new JSONArray("[\"text\", \"table\", \"chart\"]"));
        blockProperties.put("type", typeProp);

        // Content (for text)
        JSONObject contentProp = new JSONObject();
        contentProp.put("type", "string");
        blockProperties.put("content", contentProp);

        // SQL (for table/chart)
        JSONObject sqlProp = new JSONObject();
        sqlProp.put("type", "string");
        blockProperties.put("sql", sqlProp);

        // Chart type
        JSONObject chartTypeProp = new JSONObject();
        chartTypeProp.put("type", "string");
        chartTypeProp.put("enum", new JSONArray("[\"BAR\", \"PIE\", \"LINE\", \"AREA\"]"));
        blockProperties.put("chartType", chartTypeProp);

        // Description
        JSONObject descriptionProp = new JSONObject();
        descriptionProp.put("type", "string");
        blockProperties.put("description", descriptionProp);

        itemsProp.put("properties", blockProperties);
        itemsProp.put("required", new JSONArray("[\"type\"]"));

        blocksProp.put("items", itemsProp);
        properties.put("blocks", blocksProp);

        schemaObj.put("properties", properties);
        schemaObj.put("required", new JSONArray("[\"title\", \"summary\", \"blocks\"]"));

        jsonSchema.put("schema", schemaObj);
        schema.put("json_schema", jsonSchema);

        return schema;
    }

    /**
     * Reads HTTP response
     */
    private String readResponse(HttpURLConnection conn, boolean isSuccess) throws Exception {
        java.io.InputStream stream = isSuccess ? conn.getInputStream() : conn.getErrorStream();
        if (stream == null) return "No response body";

        Scanner scanner = new Scanner(stream, StandardCharsets.UTF_8);
        StringBuilder response = new StringBuilder();
        while (scanner.hasNext()) {
            response.append(scanner.nextLine());
        }
        scanner.close();
        return response.toString();
    }

    /**
     * Executes SQL query and returns table data
     */
    public DataAnalysisAgent.TableDataResult executeTableQuery(String sqlQuery) throws Exception {
        DataAnalysisAgent.TableDataResult result = new DataAnalysisAgent.TableDataResult();

        try (Statement stmt = cnx.createStatement();
             ResultSet rs = stmt.executeQuery(sqlQuery)) {

            ResultSetMetaData metaData = rs.getMetaData();
            int columnCount = metaData.getColumnCount();

            for (int i = 1; i <= columnCount; i++) {
                result.getColumns().add(metaData.getColumnName(i));
            }

            while (rs.next()) {
                ObservableMap<String, Object> row = FXCollections.observableHashMap();
                for (int i = 1; i <= columnCount; i++) {
                    row.put(metaData.getColumnName(i), rs.getObject(i));
                }
                result.getData().add(row);
            }
        } catch (Exception e) {
            result.setErrorMessage("Query error: " + e.getMessage());
        }

        return result;
    }

    /**
     * Executes SQL query and returns chart data
     */
    public DataAnalysisAgent.ChartDataResult executeChartQuery(String sqlQuery) throws Exception {
        DataAnalysisAgent.ChartDataResult result = new DataAnalysisAgent.ChartDataResult();

        try (Statement stmt = cnx.createStatement();
             ResultSet rs = stmt.executeQuery(sqlQuery)) {

            ResultSetMetaData metaData = rs.getMetaData();

            // Use first column as category, second as value
            String categoryCol = metaData.getColumnName(1);
            String valueCol = metaData.getColumnName(2);

            while (rs.next()) {
                String category = rs.getString(categoryCol);
                Object value = rs.getObject(valueCol);

                double numValue = 0;
                if (value instanceof Number) {
                    numValue = ((Number) value).doubleValue();
                } else if (value != null) {
                    try {
                        numValue = Double.parseDouble(value.toString());
                    } catch (NumberFormatException e) {
                        numValue = 0;
                    }
                }

                result.getChartData().put(category, numValue);
            }

            if (result.getChartData().size() <= 5) {
                result.setRecommendedType("PIE");
            } else {
                result.setRecommendedType("BAR");
            }
        } catch (Exception e) {
            System.err.println("Chart query error: " + e.getMessage());
        }

        return result;
    }

    // ===== INNER CLASSES =====

    /**
     * Represents a complete report
     */
    public static class ReportResult {
        private String title;
        private String summary;
        private List<ReportBlock> blocks;
        private String error;

        public ReportResult() {
            this.blocks = new ArrayList<>();
        }

        public String getTitle() { return title; }
        public void setTitle(String title) { this.title = title; }

        public String getSummary() { return summary; }
        public void setSummary(String summary) { this.summary = summary; }

        public List<ReportBlock> getBlocks() { return blocks; }
        public void setBlocks(List<ReportBlock> blocks) { this.blocks = blocks; }

        public String getError() { return error; }
        public void setError(String error) { this.error = error; }

        public boolean hasError() { return error != null && !error.isEmpty(); }
    }

    /**
     * Represents a single block in a report (text, table, or chart)
     */
    public static class ReportBlock {
        private String type; // "text", "table", "chart"
        private String content; // for text
        private String sql; // for table/chart
        private String description; // for table/chart
        private String chartType; // "BAR", "PIE", "LINE", "AREA"

        public String getType() { return type; }
        public void setType(String type) { this.type = type; }

        public String getContent() { return content; }
        public void setContent(String content) { this.content = content; }

        public String getSql() { return sql; }
        public void setSql(String sql) { this.sql = sql; }

        public String getDescription() { return description; }
        public void setDescription(String description) { this.description = description; }

        public String getChartType() { return chartType; }
        public void setChartType(String chartType) { this.chartType = chartType; }
    }
}