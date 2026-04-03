package service;

import utils.Mydb;
import java.util.ArrayList;
import java.util.List;
import java.util.Scanner;

/**
 * CLI program to test DataAnalysisAgent
 * Tests the AI SQL generation and database query execution
 */
public class DataAnalysisAgentCLI {
        private DataAnalysisAgent aiService;
        private Scanner scanner;
        private List<String> queryHistory;
        private boolean running;

    public DataAnalysisAgentCLI() {
        this.aiService = new DataAnalysisAgent();
        this.scanner = new Scanner(System.in);
        this.queryHistory = new ArrayList<>();
        this.running = true;
    }

    public void run() {
        System.out.println("\n" + "=".repeat(60));
        System.out.println("   AI Service Test CLI");
        System.out.println("   Test natural language to SQL conversion");
        System.out.println("=".repeat(60) + "\n");

        showDatabaseSchema();
        showMainMenu();
    }

    private void showDatabaseSchema() {
        try {
            System.out.println("📊 Loading Database Schema...\n");
            String schema = aiService.getDatabaseSchema();
            System.out.println(schema);
            System.out.println("-".repeat(60) + "\n");
        } catch (Exception e) {
            System.err.println("❌ Error loading database schema: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void showMainMenu() {
        while (running) {
            System.out.println("\n╔" + "═".repeat(58) + "╗");
            System.out.println("║ Main Menu                                                  ║");
            System.out.println("╠" + "═".repeat(58) + "╣");
            System.out.println("║ 1. Test AI Query (Interactive)                             ║");
            System.out.println("║ 2. Run Pre-defined Test Queries                            ║");
            System.out.println("║ 3. View Query History                                      ║");
            System.out.println("║ 4. Show Database Schema Again                              ║");
            System.out.println("║ 5. Test Custom SQL Query                                   ║");
            System.out.println("║ 6. Exit                                                    ║");
            System.out.println("╚" + "═".repeat(58) + "╝");
            System.out.print("\n👉 Choose option (1-6): ");

            String choice = scanner.nextLine().trim();
            System.out.println();

            switch (choice) {
                case "1":
                    testInteractiveQuery();
                    break;
                case "2":
                    runPredefinedQueries();
                    break;
                case "3":
                    viewQueryHistory();
                    break;
                case "4":
                    showDatabaseSchema();
                    break;
                case "5":
                    testCustomSQL();
                    break;
                case "6":
                    running = false;
                    System.out.println("\n👋 Goodbye!\n");
                    break;
                default:
                    System.out.println("❌ Invalid option. Please choose 1-6.");
            }
        }
        scanner.close();
    }

    private void testInteractiveQuery() {
        System.out.println("\n🤖 AI Query Test (Type 'back' to return to menu)\n");
        System.out.println("Enter your natural language query to be converted to SQL:");
        System.out.print("📝 Prompt: ");
        String prompt = scanner.nextLine().trim();

        if (prompt.equalsIgnoreCase("back")) {
            return;
        }

        if (prompt.isEmpty()) {
            System.out.println("❌ Prompt cannot be empty.");
            return;
        }

        executeAIQuery(prompt);
    }

    private void testCustomSQL() {
        System.out.println("\n🔧 Custom SQL Test (Type 'back' to return to menu)\n");
        System.out.print("📝 Enter SQL query: ");
        String sql = scanner.nextLine().trim();

        if (sql.equalsIgnoreCase("back")) {
            return;
        }

        if (sql.isEmpty()) {
            System.out.println("❌ SQL query cannot be empty.");
            return;
        }

        executeSQLQuery(sql);
    }

    private void runPredefinedQueries() {
        System.out.println("\n📋 Pre-defined Test Queries\n");
        System.out.println("1. Get all job applications");
        System.out.println("2. Get all job offers");
        System.out.println("3. Count applications by status");
        System.out.println("4. List all interviews");
        System.out.println("5. Get applications from last 30 days");
        System.out.println("6. Back to menu");
        System.out.print("\n👉 Choose test (1-6): ");

        String choice = scanner.nextLine().trim();
        System.out.println();

        String prompt = null;
        switch (choice) {
            case "1":
                prompt = "Show me all job applications";
                break;
            case "2":
                prompt = "List all job offers with their details";
                break;
            case "3":
                prompt = "Count how many applications are in each status";
                break;
            case "4":
                prompt = "Show all interviews scheduled";
                break;
            case "5":
                prompt = "Get applications submitted in the last 30 days";
                break;
            case "6":
                return;
            default:
                System.out.println("❌ Invalid option.");
                return;
        }

        if (prompt != null) {
            executeAIQuery(prompt);
        }
    }

    private void executeAIQuery(String prompt) {
        System.out.println("\n⏳ Processing natural language query...");
        System.out.println("   Converting to SQL using AI...\n");

        try {
            // Get database schema
            String dbSchema = aiService.getDatabaseSchema();

            // Generate SQL from prompt
            System.out.println("🔍 AI Analysis:");
            System.out.println("   Prompt: " + prompt);

            String sql = callAIForSQL(prompt, dbSchema);

            if (sql != null && !sql.isEmpty()) {
                System.out.println("   Generated SQL: " + sql);
                System.out.println();

                // Add to history
                queryHistory.add("Prompt: " + prompt + " | SQL: " + sql);

                // Execute the generated SQL
                System.out.println("▶️ Executing query...\n");
                executeSQLQuery(sql);
            } else {
                System.out.println("❌ Failed to generate SQL query.");
            }
        } catch (Exception e) {
            System.err.println("❌ Error: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Calls Groq API to convert natural language to SQL
     * This replicates the AIService method for testing purposes
     */
    private String callAIForSQL(String userPrompt, String dbSchema) throws Exception {
        // Hardcoded API key
        String GROQ_API_KEY = "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";
        String GROQ_API_URL = "https://api.groq.com/openai/v1/chat/completions";
        String GROQ_MODEL = "llama-3.3-70b-versatile";

        String systemPrompt = "You are a SQL expert. Convert the user's natural language request into a valid SQL SELECT query. " +
                "Database Schema:\n" + dbSchema + "\n" +
                "Return ONLY the SQL query, nothing else. No markdown, no explanations.";

        // Build OpenAI-compatible request for Groq
        org.json.JSONObject requestBody = new org.json.JSONObject();
        requestBody.put("model", GROQ_MODEL);
        
        org.json.JSONArray messages = new org.json.JSONArray();
        
        org.json.JSONObject systemMessage = new org.json.JSONObject();
        systemMessage.put("role", "system");
        systemMessage.put("content", systemPrompt);
        messages.put(systemMessage);
        
        org.json.JSONObject userMessage = new org.json.JSONObject();
        userMessage.put("role", "user");
        userMessage.put("content", userPrompt);
        messages.put(userMessage);
        
        requestBody.put("messages", messages);

        java.net.URL url = new java.net.URL(GROQ_API_URL);
        java.net.HttpURLConnection conn = (java.net.HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setRequestProperty("Content-Type", "application/json");
        conn.setRequestProperty("Authorization", "Bearer " + GROQ_API_KEY);
        conn.setDoOutput(true);
        conn.setConnectTimeout(15000);
        conn.setReadTimeout(15000);

        try (java.io.OutputStream os = conn.getOutputStream()) {
            byte[] input = requestBody.toString().getBytes(java.nio.charset.StandardCharsets.UTF_8);
            os.write(input, 0, input.length);
        }

        int responseCode = conn.getResponseCode();
        if (responseCode == 200) {
            String response = readResponse(conn, true);
            org.json.JSONObject jsonResponse = new org.json.JSONObject(response);
            
            // Extract the text from Groq response
            String sqlQuery = jsonResponse
                    .getJSONArray("choices")
                    .getJSONObject(0)
                    .getJSONObject("message")
                    .getString("content");
            
            // Clean up the SQL query
            sqlQuery = sqlQuery.replaceAll("```sql", "").replaceAll("```", "").trim();
            
            return sqlQuery;
        } else {
            String errorResponse = readResponse(conn, false);
            System.err.println("❌ API Error (" + responseCode + "): " + errorResponse);
            
            if (responseCode == 401) {
                System.err.println("   Authentication failed. Check your API_KEY.");
                System.err.println("   API_KEY is: " + (GROQ_API_KEY == null ? "NOT SET" : 
                    (GROQ_API_KEY.isEmpty() ? "EMPTY" : "SET")));
            }
            return null;
        }
    }

    private String readResponse(java.net.HttpURLConnection conn, boolean isSuccess) throws Exception {
        java.io.InputStream stream = isSuccess ? conn.getInputStream() : conn.getErrorStream();
        
        if (stream == null) {
            return "No response body";
        }
        
        java.util.Scanner scanner = new java.util.Scanner(stream, java.nio.charset.StandardCharsets.UTF_8);
        StringBuilder response = new StringBuilder();
        while (scanner.hasNext()) {
            response.append(scanner.nextLine());
        }
        scanner.close();
        return response.toString();
    }

    private void executeSQLQuery(String sql) {
        try {
            java.sql.Connection cnx = Mydb.getInstance().getConnection();
            java.sql.Statement stmt = cnx.createStatement();
            java.sql.ResultSet rs = stmt.executeQuery(sql);
            java.sql.ResultSetMetaData metaData = rs.getMetaData();
            int columnCount = metaData.getColumnCount();

            // Print column headers
            System.out.println("📊 Results:\n");
            for (int i = 1; i <= columnCount; i++) {
                System.out.printf("%-20s", metaData.getColumnName(i));
            }
            System.out.println("\n" + "-".repeat(columnCount * 20));

            // Print rows
            int rowCount = 0;
            while (rs.next()) {
                for (int i = 1; i <= columnCount; i++) {
                    Object value = rs.getObject(i);
                    System.out.printf("%-20s", value != null ? value.toString().substring(0, Math.min(19, value.toString().length())) : "NULL");
                }
                System.out.println();
                rowCount++;
            }

            System.out.println("\n✅ Query executed successfully. " + rowCount + " row(s) returned.\n");

            rs.close();
            stmt.close();
        } catch (Exception e) {
            System.err.println("❌ SQL Execution Error: " + e.getMessage());
            if (e.getMessage().contains("syntax")) {
                System.err.println("   Check the SQL syntax above.");
            }
        }
    }

    private void viewQueryHistory() {
        System.out.println("\n📜 Query History\n");
        if (queryHistory.isEmpty()) {
            System.out.println("No queries executed yet.");
        } else {
            for (int i = 0; i < queryHistory.size(); i++) {
                System.out.println((i + 1) + ". " + queryHistory.get(i));
                System.out.println();
            }
        }
    }

    public static void main(String[] args) {
        // Check if database is accessible
        try {
            java.sql.Connection cnx = Mydb.getInstance().getConnection();
            if (cnx == null || cnx.isClosed()) {
                System.err.println("❌ Failed to connect to database. Check your database configuration in Mydb.java");
                System.exit(1);
            }
        } catch (Exception e) {
            System.err.println("❌ Database connection error: " + e.getMessage());
            System.exit(1);
        }

        // Check if API key is set
        if (System.getenv("API_KEY") == null || System.getenv("API_KEY").isEmpty()) {
            System.err.println("❌ API_KEY environment variable not set. Please set it before running.");
            System.err.println("   export API_KEY='your-groq-api-key'");
            System.exit(1);
        }

        // Run CLI
        DataAnalysisAgentCLI cli = new DataAnalysisAgentCLI();
        cli.run();
    }
}
