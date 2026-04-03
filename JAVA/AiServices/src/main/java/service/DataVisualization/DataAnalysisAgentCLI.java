package service.DataVisualization;

import utils.Mydb;
import service.DataVisualization.DataAnalysisAgent;
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
            // Generate SQL from prompt using standardized DataAnalysisAgent
            System.out.println("🔍 AI Analysis:");
            System.out.println("   Prompt: " + prompt);

            // Use the standardized DataAnalysisAgent for table generation
            aiService.generateTableData(prompt, (result) -> {
                if (result != null && !result.hasError()) {
                    System.out.println("   Generated SQL: " + result.getGeneratedSql());
                    System.out.println();

                    // Add to history
                    queryHistory.add("Prompt: " + prompt + " | SQL: " + result.getGeneratedSql());

                    // Execute the generated SQL
                    System.out.println("▶️ Executing query...\n");
                    executeSQLQuery(result.getGeneratedSql());
                } else {
                    System.out.println("❌ Failed to generate SQL query.");
                    if (result != null && result.hasError()) {
                        System.out.println("   Error: " + result.getErrorMessage());
                    }
                }
            });

        } catch (Exception e) {
            System.err.println("❌ Error: " + e.getMessage());
            e.printStackTrace();
        }
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
                    System.out.printf("%-20s",
                            value != null ? value.toString().substring(0, Math.min(19, value.toString().length()))
                                    : "NULL");
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
        String apiKey = service.ReportGenerator.ApiKeyManager.getApiKey();
        if (apiKey == null || apiKey.isEmpty()) {
            System.err.println("❌ Gemini API key not found in config.properties or system properties.");
            System.err.println("   Please verify config.properties contains a valid gemini.api.key");
        }

        // Run CLI
        DataAnalysisAgentCLI cli = new DataAnalysisAgentCLI();
        cli.run();
    }
}
