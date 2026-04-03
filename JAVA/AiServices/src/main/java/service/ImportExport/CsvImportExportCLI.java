package service.ImportExport;

import java.util.Scanner;

/**
 * CLI tool for CSV Import/Export operations
 * Provides command-line interface for managing recruitment data
 */
public class CsvImportExportCLI {
    
    private static final CsvImportService importService = new CsvImportService();
    private static final CsvExportService exportService = new CsvExportService();
    private static final Scanner scanner = new Scanner(System.in);
    
    public static void main(String[] args) {
        System.out.println("=== Recruitment CSV Import/Export Tool ===");
        System.out.println();
        
        while (true) {
            showMainMenu();
            int choice = getIntInput("Enter your choice: ");
            
            switch (choice) {
                case 1:
                    handleImport();
                    break;
                case 2:
                    handleExport();
                    break;
                case 3:
                    handleTemplates();
                    break;
                case 4:
                    System.out.println("Goodbye!");
                    return;
                default:
                    System.out.println("Invalid choice. Please try again.");
            }
            
            System.out.println();
        }
    }
    
    private static void showMainMenu() {
        System.out.println("1. Import Data");
        System.out.println("2. Export Data");
        System.out.println("3. Generate Templates");
        System.out.println("4. Exit");
    }
    
    private static void handleImport() {
        System.out.println("\n=== Import Data ===");
        System.out.println("1. Import Job Offers");
        System.out.println("2. Import Applications");
        System.out.println("3. Import Interviews");
        System.out.println("4. Back to Main Menu");
        
        int choice = getIntInput("Enter your choice: ");
        String filePath = getStringInput("Enter CSV file path: ");
        
        switch (choice) {
            case 1:
                importJobOffers(filePath);
                break;
            case 2:
                importApplications(filePath);
                break;
            case 3:
                importInterviews(filePath);
                break;
            case 4:
                return;
            default:
                System.out.println("Invalid choice.");
        }
    }
    
    private static void handleExport() {
        System.out.println("\n=== Export Data ===");
        System.out.println("1. Export Job Offers");
        System.out.println("2. Export Applications");
        System.out.println("3. Export Interviews");
        System.out.println("4. Export Custom Query");
        System.out.println("5. Back to Main Menu");
        
        int choice = getIntInput("Enter your choice: ");
        String filePath = getStringInput("Enter output CSV file path: ");
        boolean includeDeleted = getYesNoInput("Include deleted records? (y/n): ");
        
        switch (choice) {
            case 1:
                exportJobOffers(filePath, includeDeleted);
                break;
            case 2:
                exportApplications(filePath, includeDeleted);
                break;
            case 3:
                exportInterviews(filePath, includeDeleted);
                break;
            case 4:
                exportCustomQuery(filePath);
                break;
            case 5:
                return;
            default:
                System.out.println("Invalid choice.");
        }
    }
    
    private static void handleTemplates() {
        System.out.println("\n=== Generate Templates ===");
        System.out.println("1. Job Offer Template");
        System.out.println("2. Application Template");
        System.out.println("3. Interview Template");
        System.out.println("4. Back to Main Menu");
        
        int choice = getIntInput("Enter your choice: ");
        String filePath = getStringInput("Enter output file path: ");
        
        switch (choice) {
            case 1:
                generateJobOfferTemplate(filePath);
                break;
            case 2:
                generateApplicationTemplate(filePath);
                break;
            case 3:
                generateInterviewTemplate(filePath);
                break;
            case 4:
                return;
            default:
                System.out.println("Invalid choice.");
        }
    }
    
    private static void importJobOffers(String filePath) {
        System.out.println("\nImporting job offers from: " + filePath);
        CsvImportService.ImportResult result = importService.importJobOffers(filePath);
        result.printSummary();
        waitForEnter();
    }
    
    private static void importApplications(String filePath) {
        System.out.println("\nImporting applications from: " + filePath);
        CsvImportService.ImportResult result = importService.importApplications(filePath);
        result.printSummary();
        waitForEnter();
    }
    
    private static void importInterviews(String filePath) {
        System.out.println("\nImporting interviews from: " + filePath);
        CsvImportService.ImportResult result = importService.importInterviews(filePath);
        result.printSummary();
        waitForEnter();
    }
    
    private static void exportJobOffers(String filePath, boolean includeDeleted) {
        System.out.println("\nExporting job offers to: " + filePath);
        CsvExportService.ExportResult result = exportService.exportJobOffers(filePath, includeDeleted);
        result.printSummary();
        waitForEnter();
    }
    
    private static void exportApplications(String filePath, boolean includeDeleted) {
        System.out.println("\nExporting applications to: " + filePath);
        CsvExportService.ExportResult result = exportService.exportApplications(filePath, includeDeleted);
        result.printSummary();
        waitForEnter();
    }
    
    private static void exportInterviews(String filePath, boolean includeDeleted) {
        System.out.println("\nExporting interviews to: " + filePath);
        CsvExportService.ExportResult result = exportService.exportInterviews(filePath, includeDeleted);
        result.printSummary();
        waitForEnter();
    }
    
    private static void exportCustomQuery(String filePath) {
        System.out.println("\n=== Custom Query Export ===");
        String sql = getStringInput("Enter SQL SELECT query: ");
        String headersInput = getStringInput("Enter comma-separated headers: ");
        String[] headers = headersInput.split(",");
        
        // Trim headers
        for (int i = 0; i < headers.length; i++) {
            headers[i] = headers[i].trim();
        }
        
        System.out.println("\nExporting custom query to: " + filePath);
        CsvExportService.ExportResult result = exportService.exportCustomQuery(sql, filePath, headers);
        result.printSummary();
        waitForEnter();
    }
    
    private static void generateJobOfferTemplate(String filePath) {
        System.out.println("\nGenerating job offer template: " + filePath);
        exportService.generateJobOfferTemplate(filePath);
        waitForEnter();
    }
    
    private static void generateApplicationTemplate(String filePath) {
        System.out.println("\nGenerating application template: " + filePath);
        exportService.generateApplicationTemplate(filePath);
        waitForEnter();
    }
    
    private static void generateInterviewTemplate(String filePath) {
        System.out.println("\nGenerating interview template: " + filePath);
        exportService.generateInterviewTemplate(filePath);
        waitForEnter();
    }
    
    // Helper methods for user input
    private static int getIntInput(String prompt) {
        System.out.print(prompt);
        while (true) {
            try {
                return Integer.parseInt(scanner.nextLine());
            } catch (NumberFormatException e) {
                System.out.print("Please enter a valid number: ");
            }
        }
    }
    
    private static String getStringInput(String prompt) {
        System.out.print(prompt);
        return scanner.nextLine();
    }
    
    private static boolean getYesNoInput(String prompt) {
        System.out.print(prompt);
        while (true) {
            String input = scanner.nextLine().toLowerCase().trim();
            if (input.equals("y") || input.equals("yes")) {
                return true;
            } else if (input.equals("n") || input.equals("no")) {
                return false;
            } else {
                System.out.print("Please enter 'y' or 'n': ");
            }
        }
    }
    
    private static void waitForEnter() {
        System.out.println("\nPress Enter to continue...");
        scanner.nextLine();
    }
}
