package service.ReportGenerator;

import org.json.JSONObject;
import org.json.JSONArray;
import java.util.function.Consumer;

/**
 * SQL Planner LLM - Generates SQL queries from natural language
 */
public class SqlPlannerLlm {
    private String apiKey;
    private String model;

    private static final String GEMINI_MODEL = "gemini-3-flash-preview";
    private static final String GEMINI_API_URL = "https://generativelanguage.googleapis.com/v1beta/models/" + GEMINI_MODEL
            + ":generateContent";

    private static final String DEFAULT_GROQ_MODEL = "llama-3.3-70b-versatile";
    private static final String GROQ_API_URL = "https://api.groq.com/openai/v1/chat/completions";

    public SqlPlannerLlm(String apiKey) {
        this.apiKey = apiKey;
        if (ApiKeyManager.getLlmProvider() == ApiKeyManager.LlmProvider.GROQ) {
            this.model = DEFAULT_GROQ_MODEL;
        } else {
            this.model = GEMINI_MODEL;
        }
    }

    /**
     * Plans SQL queries from natural language prompt
     */
    public SqlPlanResult planQueries(String prompt) {
        try {
            System.out.println(" [DEBUG] Starting SQL planning for prompt: " + prompt);
            System.out.println(" [DEBUG] Using model: " + model);

            // Build system prompt
            String systemPrompt = buildSqlPlanningSystemPrompt();

            String response;
            if (ApiKeyManager.getLlmProvider() == ApiKeyManager.LlmProvider.GROQ) {
                response = callGroqApi(systemPrompt, prompt);
            } else {
                response = callGeminiApi(systemPrompt, prompt);
            }

            if (response != null && !response.isEmpty()) {
                // Parse response (Gemini returns plain text, not JSON with choices)
                System.out.println(" [DEBUG] LLM Response: " + response);
                
                SqlPlanResult result = new SqlPlanResult();
                
                try {
                    // Try to parse as JSON
                    JSONObject jsonResponse = new JSONObject(response);
                    if (jsonResponse.has("queries")) {
                        result.setQueries(jsonResponse.getJSONArray("queries"));
                        result.setSuccess(true);
                    } else {
                        result.setErrorMessage("Invalid response format: missing 'queries' field");
                    }
                } catch (Exception e) {
                    result.setErrorMessage("Failed to parse JSON response: " + e.getMessage());
                }
                
                return result;
            } else {
                SqlPlanResult errorResult = new SqlPlanResult();
                errorResult.setErrorMessage("No response from API");
                return errorResult;
            }
        } catch (Exception e) {
            System.err.println("SQL planning failed: " + e.getMessage());
            e.printStackTrace();
            SqlPlanResult errorResult = new SqlPlanResult();
            errorResult.setErrorMessage("Planning failed: " + e.getMessage());
            return errorResult;
        }
    }

    private String callGeminiApi(String systemPrompt, String userPrompt) {
        try {
            java.net.URL url = new java.net.URL(GEMINI_API_URL);
            java.net.HttpURLConnection conn = (java.net.HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("x-goog-api-key", apiKey);
            conn.setDoOutput(true);
            conn.setConnectTimeout(30000);
            conn.setReadTimeout(90000);

            // Build Gemini API request
            JSONObject requestBody = new JSONObject();
            JSONArray contents = new JSONArray();
            JSONObject content = new JSONObject();
            JSONArray parts = new JSONArray();
            JSONObject part = new JSONObject();
            
            // Combine system and user prompts for Gemini
            String combinedPrompt = systemPrompt + "\n\n" + userPrompt;
            part.put("text", combinedPrompt);
            parts.put(part);
            content.put("parts", parts);
            contents.put(content);
            requestBody.put("contents", contents);

            // Send request
            try (java.io.OutputStream os = conn.getOutputStream()) {
                byte[] input = requestBody.toString().getBytes(java.nio.charset.StandardCharsets.UTF_8);
                os.write(input, 0, input.length);
            }

            int responseCode = conn.getResponseCode();
            System.out.println(" [DEBUG] Gemini API response code: " + responseCode);

            if (responseCode == 200) {
                String response = readResponse(conn, true);
                System.out.println(" [DEBUG] Gemini API response received");
                
                // Parse Gemini response
                JSONObject jsonResponse = new JSONObject(response);
                JSONArray candidates = jsonResponse.getJSONArray("candidates");
                if (candidates.length() > 0) {
                    JSONObject candidate = candidates.getJSONObject(0);
                    JSONObject content_response = candidate.getJSONObject("content");
                    JSONArray parts_response = content_response.getJSONArray("parts");
                    if (parts_response.length() > 0) {
                        return parts_response.getJSONObject(0).getString("text").trim();
                    }
                }
                return null;
            } else {
                String errorResponse = readResponse(conn, false);
                System.err.println("Gemini API Error (" + responseCode + "): " + errorResponse);
                return null;
            }
        } catch (Exception e) {
            System.err.println("Error calling Gemini API: " + e.getMessage());
            e.printStackTrace();
            return null;
        }
    }

    private String callGroqApi(String systemPrompt, String userPrompt) {
        String groqKey = ApiKeyManager.getGroqApiKey();
        if (groqKey == null || groqKey.isEmpty()) {
            return null;
        }

        try {
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

            java.net.URL url = new java.net.URL(GROQ_API_URL);
            java.net.HttpURLConnection conn = (java.net.HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/json");
            conn.setRequestProperty("Authorization", "Bearer " + groqKey);
            conn.setDoOutput(true);
            conn.setConnectTimeout(30000);
            conn.setReadTimeout(90000);

            String enforcedSystemPrompt = systemPrompt + "\n\nReturn ONLY valid JSON. No markdown, no code fences.";

            JSONObject requestBody = new JSONObject();
            requestBody.put("model", modelToUse);

            JSONArray messages = new JSONArray();
            messages.put(new JSONObject().put("role", "system").put("content", enforcedSystemPrompt));
            messages.put(new JSONObject().put("role", "user").put("content", userPrompt));
            requestBody.put("messages", messages);

            try (java.io.OutputStream os = conn.getOutputStream()) {
                byte[] input = requestBody.toString().getBytes(java.nio.charset.StandardCharsets.UTF_8);
                os.write(input, 0, input.length);
            }

            int responseCode = conn.getResponseCode();
            System.out.println(" [DEBUG] Groq API response code: " + responseCode);
            if (responseCode == 200) {
                String response = readResponse(conn, true);
                JSONObject json = new JSONObject(response);
                return json.getJSONArray("choices").getJSONObject(0).getJSONObject("message").getString("content").trim();
            }

            String errorResponse = readResponse(conn, false);
            System.err.println("Groq API Error (" + responseCode + "): " + errorResponse);
            return null;
        } catch (Exception e) {
            System.err.println("Error calling Groq API: " + e.getMessage());
            e.printStackTrace();
            return null;
        }
    }
    
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
     * SQL Plan Result class
     */
    public static class SqlPlanResult {
        private boolean success;
        private String errorMessage;
        private JSONArray queries;

        public SqlPlanResult() {
            this.success = false;
            this.queries = new JSONArray();
        }

        public boolean isSuccess() {
            return success;
        }

        public void setSuccess(boolean success) {
            this.success = success;
        }

        public String getErrorMessage() {
            return errorMessage;
        }

        public void setErrorMessage(String errorMessage) {
            this.errorMessage = errorMessage;
        }

        public JSONArray getQueries() {
            return queries;
        }

        public void setQueries(JSONArray queries) {
            this.queries = queries;
        }
    }

    /**
     * SQL Query representation
     */
    public static class SqlQuery {
        private String sql;
        private String description;
        private String chartType;

        public SqlQuery() {
        }

        public String getSql() {
            return sql;
        }

        public void setSql(String sql) {
            this.sql = sql;
        }

        public String getDescription() {
            return description;
        }

        public void setDescription(String description) {
            this.description = description;
        }

        public String getChartType() {
            return chartType;
        }

        public void setChartType(String chartType) {
            this.chartType = chartType;
        }
    }

    /**
     * Build SQL planning system prompt
     */
    private String buildSqlPlanningSystemPrompt() {
        try {
            // Get actual database schema
            DbQueryService dbService = new DbQueryService();
            String actualSchema = dbService.getCompactRecruitmentSchema();

            return "You are a SQL expert. Generate 3-6 MySQL SELECT queries for analytics.\n"
                    + "IMPORTANT: The schema below is the ONLY source of truth for columns.\n"
                    + "If the user mentions a field that does NOT exist, treat it as a business description and choose the closest matching column from the schema.\n"
                    + "NEVER invent columns.\n\n"
                    + "CHART RULE: If chartType != TABLE, your SQL MUST return EXACTLY 2 columns in this order with aliases: category, value.\n"
                    + "Use ONLY these tables: applications, interviews, job_offer.\n"
                    + "Always include: WHERE is_deleted = false.\n"
                    + "No SELECT *. Use explicit columns.\n\n"
                    + "Schema (columns only):\n" + actualSchema + "\n"
                    + "Return ONLY valid JSON: {\"queries\":[{\"sql\":\"...\",\"description\":\"...\",\"chartType\":\"BAR|PIE|LINE|AREA|TABLE\"}]}";

        } catch (Exception e) {
            System.err.println("Failed to get actual schema, using fallback: " + e.getMessage());
            return getFallbackPrompt();
        }
    }

    private String getFallbackPrompt() {
        return "You are a SQL expert. Generate simple SELECT queries for job_offer, applications, interviews tables only.";
    }
}