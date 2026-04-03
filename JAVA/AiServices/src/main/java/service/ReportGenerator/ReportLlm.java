package service.ReportGenerator;

import org.json.JSONObject;
import org.json.JSONArray;
import java.util.function.Consumer;

/**
 * Report LLM - Generates narrative reports using Google Gemini
 */
public class ReportLlm {
    private String apiKey;
    private String model;

    private static final String GEMINI_MODEL = "gemini-3-flash-preview";
    private static final String GEMINI_API_URL = "https://generativelanguage.googleapis.com/v1beta/models/" + GEMINI_MODEL
            + ":generateContent";

    private static final String DEFAULT_GROQ_MODEL = "llama-3.3-70b-versatile";
    private static final String GROQ_API_URL = "https://api.groq.com/openai/v1/chat/completions";
    
    public ReportLlm(String apiKey) {
        this.apiKey = apiKey;
        if (ApiKeyManager.getLlmProvider() == ApiKeyManager.LlmProvider.GROQ) {
            this.model = DEFAULT_GROQ_MODEL;
        } else {
            this.model = GEMINI_MODEL;
        }
    }
    
    /**
     * Generates structured report from user prompt and query results
     */
    public LlmResponse generateReport(String userPrompt, JSONObject queryResults, java.util.List<SqlPlannerLlm.SqlQuery> queries) {
        try {
            System.out.println(" [DEBUG] Starting report generation for prompt: " + userPrompt);
            System.out.println(" [DEBUG] Using model: " + model);
            System.out.println(" [DEBUG] Query results provided: " + (queryResults == null ? "null" : queryResults.toString(2)));
            System.out.println(" [DEBUG] SQL queries count: " + (queries == null ? "null" : String.valueOf(queries.size())));
            
            // Build system prompt
            String systemPrompt = buildReportGenerationSystemPrompt();
            
            // Build user prompt with data
            String userPromptWithData = buildUserPrompt(userPrompt, queryResults, queries);
            
            String response;
            if (ApiKeyManager.getLlmProvider() == ApiKeyManager.LlmProvider.GROQ) {
                response = callGroqApi(systemPrompt, userPromptWithData);
            } else {
                response = callGeminiApi(systemPrompt, userPromptWithData);
            }
            
            if (response != null && !response.isEmpty()) {
                // Parse response (Gemini returns plain text, not JSON with choices)
                System.out.println(" [DEBUG] LLM Response: " + response);
                
                LlmResponse result = new LlmResponse();
                result.setSuccess(true);
                result.setContent(response);
                
                return result;
            } else {
                LlmResponse errorResult = new LlmResponse();
                errorResult.setSuccess(false);
                errorResult.setError("No response from API");
                return errorResult;
            }
        } catch (Exception e) {
            System.err.println("Report generation failed: " + e.getMessage());
            e.printStackTrace();
            LlmResponse errorResult = new LlmResponse();
            errorResult.setSuccess(false);
            errorResult.setError("Report generation failed: " + e.getMessage());
            return errorResult;
        }
    }
    
    private String buildReportGenerationSystemPrompt() {
        return "You are an expert HR analyst. Create a concise report from query results.\n\n" +
                "Output MUST be valid JSON only (no markdown, no extra text).\n" +
                "Do NOT include SQL. Do NOT include tables.\n\n" +
                "JSON format:\n" +
                "{\n" +
                "  \"title\": \"...\",\n" +
                "  \"summary\": \"...\",\n" +
                "  \"sections\": [\n" +
                "    {\"index\": 0, \"description\": \"short explanation for section 0\"},\n" +
                "    {\"index\": 1, \"description\": \"...\"}\n" +
                "  ],\n" +
                "  \"conclusion\": \"...\"\n" +
                "}";
    }
    
    private String buildUserPrompt(String userPrompt, JSONObject queryResults, java.util.List<SqlPlannerLlm.SqlQuery> queries) {
        StringBuilder prompt = new StringBuilder();
        prompt.append("User Request: ").append(userPrompt).append("\n\n");

        if (queries != null && !queries.isEmpty()) {
            prompt.append("Planned sections (do not repeat SQL):\n");
            for (int i = 0; i < queries.size(); i++) {
                SqlPlannerLlm.SqlQuery q = queries.get(i);
                prompt.append(i).append(": ")
                        .append(q.getDescription() != null ? q.getDescription() : "Section " + i)
                        .append(" (type: ")
                        .append(q.getChartType() != null ? q.getChartType() : "TABLE")
                        .append(")\n");
            }
            prompt.append("\n");
        }

        if (queryResults != null) {
            prompt.append("Query results (truncated):\n");
            JSONObject compact = new JSONObject();
            int maxRows = 5;
            for (String key : queryResults.keySet()) {
                JSONObject qr = queryResults.optJSONObject(key);
                if (qr == null) continue;

                JSONObject item = new JSONObject();
                item.put("success", qr.optBoolean("success", false));
                item.put("rowCount", qr.optInt("rowCount", 0));
                item.put("columns", qr.optJSONArray("columns"));

                JSONArray rows = qr.optJSONArray("rows");
                JSONArray sample = new JSONArray();
                if (rows != null) {
                    for (int i = 0; i < Math.min(maxRows, rows.length()); i++) {
                        sample.put(rows.get(i));
                    }
                }
                item.put("sampleRows", sample);
                compact.put(key, item);
            }

            prompt.append(compact.toString()).append("\n\n");
        }

        prompt.append("Return JSON following the required format.");
        return prompt.toString();
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

            JSONObject requestBody = new JSONObject();
            requestBody.put("model", modelToUse);

            JSONArray messages = new JSONArray();
            messages.put(new JSONObject().put("role", "system").put("content", systemPrompt));
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
     * LLM Response wrapper
     */
    public static class LlmResponse {
        private boolean success;
        private String error;
        private String content;

        public LlmResponse() {
            this.success = false;
        }

        public boolean isSuccess() {
            return success;
        }

        public void setSuccess(boolean success) {
            this.success = success;
        }

        public String getError() {
            return error;
        }

        public void setError(String error) {
            this.error = error;
        }

        public String getContent() {
            return content;
        }

        public void setContent(String content) {
            this.content = content;
        }
    }
}
