package service.ReportGenerator;

import java.util.Scanner;
import java.util.function.Consumer;

/**
 * CLI for testing Report Generation with Google Gemini API
 */
public class ReportGeneratorCLI {
    private ReportAgent reportAgent;
    private Scanner scanner;
    private boolean running;
    
    public ReportGeneratorCLI() {
        this.reportAgent = new ReportAgent();
        this.scanner = new Scanner(System.in);
        this.running = true;
    }
    
    public void run() {
        System.out.println("🤖 Report Generator CLI - Google Gemini Edition");
        System.out.println("=============================================");
        System.out.println("Type 'help' for commands, 'quit' to exit");
        System.out.println();
        
        // Check API key
        String apiKey = ApiKeyManager.getApiKey();
        if (apiKey == null || apiKey.isEmpty()) {
            System.err.println("❌ No API key found in config.properties");
            System.err.println("   Please add: gemini.api.key=YOUR_API_KEY");
            return;
        }
        
        System.out.println("✅ API key loaded (length: " + apiKey.length() + ")");
        System.out.println();
        
        while (running) {
            System.out.print("📝 Enter report request: ");
            String prompt = scanner.nextLine().trim();
            
            if (prompt.isEmpty()) {
                continue;
            }
            
            if (prompt.equalsIgnoreCase("quit") || prompt.equalsIgnoreCase("exit")) {
                running = false;
                continue;
            }
            
            if (prompt.equalsIgnoreCase("help")) {
                showHelp();
                continue;
            }
            
            if (prompt.equalsIgnoreCase("examples")) {
                showExamples();
                continue;
            }
            
            if (prompt.equalsIgnoreCase("test")) {
                runTestReport();
                continue;
            }
            
            // Generate report
            generateReport(prompt);
        }
        
        System.out.println("👋 Goodbye!");
        scanner.close();
    }
    
    private void generateReport(String prompt) {
        System.out.println("\n🔄 Generating report for: " + prompt);
        System.out.println("⏳ This may take 10-30 seconds...\n");
        
        reportAgent.generateReport(prompt, new Consumer<ReportResult>() {
            @Override
            public void accept(ReportResult result) {
                if (result.isSuccess()) {
                    System.out.println("✅ Report generated successfully!\n");
                    
                    // Display report
                    System.out.println("📊 " + (result.getTitle() != null ? result.getTitle() : "Generated Report"));
                    System.out.println("=" .repeat(50));
                    
                    if (result.getSummary() != null && !result.getSummary().isEmpty()) {
                        System.out.println("\n📋 Summary:");
                        System.out.println(result.getSummary());
                    }
                    
                    if (result.getBlocks() != null && !result.getBlocks().isEmpty()) {
                        System.out.println("\n📄 Content:");
                        int blockNum = 1;
                        for (ReportBlock block : result.getBlocks()) {
                            System.out.println("\n" + blockNum++ + ". " + block.getType().toUpperCase() + " BLOCK");
                            System.out.println("-".repeat(30));
                            System.out.println(block.getContent());
                            
                            if (block.getSql() != null && !block.getSql().isEmpty()) {
                                System.out.println("\n🔍 SQL Query:");
                                System.out.println(block.getSql());
                            }
                            
                            if (block.getChartType() != null && !block.getChartType().isEmpty()) {
                                System.out.println("\n📈 Chart Type: " + block.getChartType());
                            }
                        }
                    }
                    
                    System.out.println("\n" + "=".repeat(50));
                    System.out.println("🎉 Report complete!\n");
                    
                } else {
                    System.err.println("❌ Report generation failed: " + result.getError());
                    System.err.println("💡 Try a simpler request or check your API key\n");
                }
            }
        });
        
        // Wait for async completion
        try {
            Thread.sleep(35000); // Wait up to 35 seconds
        } catch (InterruptedException e) {
            // Continue
        }
    }
    
    private void showHelp() {
        System.out.println("\n📖 Available Commands:");
        System.out.println("  help     - Show this help message");
        System.out.println("  examples - Show example report requests");
        System.out.println("  test     - Run a test report");
        System.out.println("  quit     - Exit the CLI");
        System.out.println("\n💡 Report Request Tips:");
        System.out.println("  - Be specific about what you want to analyze");
        System.out.println("  - Use natural language (e.g., 'Show me application trends')");
        System.out.println("  - Ask for charts, tables, or summaries");
        System.out.println("  - Examples: 'application status distribution', 'interview performance'\n");
    }
    
    private void showExamples() {
        System.out.println("\n💡 Example Report Requests:");
        System.out.println("  1. 'Show me application status distribution'");
        System.out.println("  2. 'Generate a report on interview performance'");
        System.out.println("  3. 'Analyze job offer trends by department'");
        System.out.println("  4. 'Create a summary of recent applications'");
        System.out.println("  5. 'Show hiring pipeline effectiveness'");
        System.out.println("  6. 'Compare application sources'");
        System.out.println("  7. 'Analyze time-to-hire metrics'");
        System.out.println("  8. 'Report on interview success rates'");
        System.out.println("\n🎯 Try one of these or create your own!\n");
    }
    
    private void runTestReport() {
        System.out.println("\n🧪 Running test report: 'Application status overview'");
        generateReport("Generate a comprehensive report on application status distribution and trends");
    }
    
    public static void main(String[] args) {
        // Check database connection
        try {
            utils.Mydb.getInstance().getConnection();
            System.out.println("✅ Database connected successfully");
        } catch (Exception e) {
            System.err.println("❌ Database connection failed: " + e.getMessage());
            System.err.println("   Please check your database configuration");
            return;
        }
        
        ReportGeneratorCLI cli = new ReportGeneratorCLI();
        cli.run();
    }
}
