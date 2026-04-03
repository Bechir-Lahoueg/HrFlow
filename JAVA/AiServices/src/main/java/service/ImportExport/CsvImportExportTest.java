package service.ImportExport;

/**
 * Test class to demonstrate CSV Import/Export functionality
 */
public class CsvImportExportTest {
    
    public static void main(String[] args) {
        System.out.println("=== CSV Import/Export Feature Test ===");
        
        CsvExportService exportService = new CsvExportService();
        
        // Test template generation
        System.out.println("\n1. Testing Template Generation...");
        exportService.generateJobOfferTemplate("job_offer_template.csv");
        exportService.generateApplicationTemplate("application_template.csv");
        exportService.generateInterviewTemplate("interview_template.csv");
        
        // Test export functionality
        System.out.println("\n2. Testing Export Functionality...");
        CsvExportService.ExportResult result1 = exportService.exportJobOffers("job_offers_export.csv", false);
        result1.printSummary();
        
        CsvExportService.ExportResult result2 = exportService.exportApplications("applications_export.csv", false);
        result2.printSummary();
        
        CsvExportService.ExportResult result3 = exportService.exportInterviews("interviews_export.csv", false);
        result3.printSummary();
        
        System.out.println("\n=== Test Complete ===");
        System.out.println("Generated files:");
        System.out.println("- job_offer_template.csv");
        System.out.println("- application_template.csv"); 
        System.out.println("- interview_template.csv");
        System.out.println("- job_offers_export.csv");
        System.out.println("- applications_export.csv");
        System.out.println("- interviews_export.csv");
    }
}
