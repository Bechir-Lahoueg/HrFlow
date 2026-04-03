package service.ImportExport;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileReader;
import java.io.IOException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;

import utils.Mydb;

/**
 * CSV Import Service for recruitment data
 * Supports importing job offers, applications, and interviews from CSV files
 */
public class CsvImportService {
    
    private Connection connection;
    private static final boolean DEBUG = true;
    
    public CsvImportService() {
        this.connection = Mydb.getInstance().getConnection();
        if (this.connection == null) {
            throw new RuntimeException("Failed to establish database connection for CSV Import Service");
        }
        debug("CsvImportService initialized with database connection");
    }
    
    private void debug(String message) {
        if (DEBUG) {
            System.out.println("[CSV-DEBUG] " + message);
        }
    }
    
    /**
     * Import job offers from CSV file
     * Expected columns: title, description, department, location, employmentType, salary_min, salary_max, status
     */
    public ImportResult importJobOffers(String filePath) {
        debug("Starting job offers import from: " + filePath);
        List<String> errors = new ArrayList<>();
        int successCount = 0;
        
        // Validate file exists and is readable
        File file = new File(filePath);
        if (!file.exists()) {
            errors.add("File not found: " + filePath);
            return new ImportResult(0, errors);
        }
        if (!file.canRead()) {
            errors.add("File not readable: " + filePath);
            return new ImportResult(0, errors);
        }
        
        try (BufferedReader br = new BufferedReader(new FileReader(filePath))) {
            String line;
            boolean isHeader = true;
            int lineNumber = 0;
            
            while ((line = br.readLine()) != null) {
                lineNumber++;
                if (isHeader) {
                    isHeader = false;
                    debug("Header line skipped: " + line);
                    continue;
                }
                
                try {
                    String[] values = parseCsvLine(line);
                    debug("Processing line " + lineNumber + ": " + values.length + " columns");
                    
                    if (values.length >= 8) {
                        insertJobOffer(values, errors, lineNumber);
                        successCount++;
                    } else {
                        errors.add("Invalid job offer record at line " + lineNumber + ": expected at least 8 columns, got " + values.length + " - " + line);
                    }
                } catch (Exception e) {
                    errors.add("Error processing job offer at line " + lineNumber + ": " + e.getClass().getSimpleName() + " - " + e.getMessage() + " - Line: " + line);
                }
            }
        } catch (IOException e) {
            errors.add("File reading error: " + e.getMessage());
        }
        
        debug("Job offers import completed: " + successCount + " successful, " + errors.size() + " errors");
        return new ImportResult(successCount, errors);
    }
    
    /**
     * Import applications from CSV file
     * Expected columns: candidate_name, job_offer_id, cv_path, cover_letter_path, status, notes, Department, experience_level, EmailAddress, employee_id
     */
    public ImportResult importApplications(String filePath) {
        List<String> errors = new ArrayList<>();
        int successCount = 0;
        
        try (BufferedReader br = new BufferedReader(new FileReader(filePath))) {
            String line;
            boolean isHeader = true;
            
            while ((line = br.readLine()) != null) {
                if (isHeader) {
                    isHeader = false;
                    continue;
                }
                
                try {
                    String[] values = parseCsvLine(line);
                    if (values.length >= 10) {
                        insertApplication(values, errors);
                        successCount++;
                    } else {
                        errors.add("Invalid application record at line: expected at least 10 columns, got " + values.length + " - " + line);
                    }
                } catch (Exception e) {
                    errors.add("Error processing application: " + e.getMessage() + " - Line: " + line);
                }
            }
        } catch (IOException e) {
            errors.add("File reading error: " + e.getMessage());
        }
        
        return new ImportResult(successCount, errors);
    }
    
    /**
     * Import interviews from CSV file
     * Expected columns: application_id, interviewer_id, interview_date, type, meeting_link, location, feedback, score, result
     */
    public ImportResult importInterviews(String filePath) {
        List<String> errors = new ArrayList<>();
        int successCount = 0;
        
        try (BufferedReader br = new BufferedReader(new FileReader(filePath))) {
            String line;
            boolean isHeader = true;
            
            while ((line = br.readLine()) != null) {
                if (isHeader) {
                    isHeader = false;
                    continue;
                }
                
                try {
                    String[] values = parseCsvLine(line);
                    if (values.length >= 9) {
                        insertInterview(values, errors);
                        successCount++;
                    } else {
                        errors.add("Invalid interview record at line: expected at least 9 columns, got " + values.length + " - " + line);
                    }
                } catch (Exception e) {
                    errors.add("Error processing interview: " + e.getMessage() + " - Line: " + line);
                }
            }
        } catch (IOException e) {
            errors.add("File reading error: " + e.getMessage());
        }
        
        return new ImportResult(successCount, errors);
    }
    
    /**
     * Parse CSV line handling quoted values and commas
     */
    private String[] parseCsvLine(String line) {
        List<String> values = new ArrayList<>();
        StringBuilder currentValue = new StringBuilder();
        boolean inQuotes = false;
        
        for (int i = 0; i < line.length(); i++) {
            char c = line.charAt(i);
            
            if (c == '"') {
                inQuotes = !inQuotes;
            } else if (c == ',' && !inQuotes) {
                values.add(currentValue.toString().trim());
                currentValue = new StringBuilder();
            } else {
                currentValue.append(c);
            }
        }
        
        // Add the last value
        values.add(currentValue.toString().trim());
        
        return values.toArray(new String[0]);
    }
    
    /**
     * Insert job offer into database
     */
    private void insertJobOffer(String[] values, List<String> errors, int lineNumber) {
        debug("Inserting job offer: " + values[0]);
        String sql = "INSERT INTO job_offer (title, description, department, location, employmentType, salary_min, salary_max, status, created_at, created_by, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {
            // Validate required fields
            if (values[0] == null || values[0].trim().isEmpty()) {
                errors.add("Line " + lineNumber + ": Job title is required");
                return;
            }
            
            pstmt.setString(1, values[0]); // title
            pstmt.setString(2, values[1]); // description
            pstmt.setString(3, values[2]); // department
            pstmt.setString(4, values[3]); // location
            pstmt.setString(5, values[4]); // employmentType
            pstmt.setDouble(6, parseDouble(values[5])); // salary_min
            pstmt.setDouble(7, parseDouble(values[6])); // salary_max
            pstmt.setString(8, values.length > 7 ? values[7] : "OPEN"); // status
            pstmt.setTimestamp(9, new Timestamp(System.currentTimeMillis())); // created_at
            pstmt.setInt(10, 1); // created_by (default admin user)
            pstmt.setBoolean(11, false); // is_deleted
            
            pstmt.executeUpdate();
            debug("Successfully inserted job offer: " + values[0]);
        } catch (SQLException e) {
            errors.add("Line " + lineNumber + ": Database error inserting job offer '" + values[0] + "': " + e.getMessage());
        }
    }
    
    /**
     * Insert application into database
     */
    private void insertApplication(String[] values, List<String> errors) {
        String sql = "INSERT INTO applications (candidate_name, job_offer_id, cv_path, cover_letter_path, status, notes, applied_at, is_deleted, Department, experience_level, EmailAddress, employee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {
            pstmt.setString(1, values[0]); // candidate_name
            pstmt.setInt(2, parseInt(values[1])); // job_offer_id
            pstmt.setString(3, values[2]); // cv_path
            pstmt.setString(4, values[3]); // cover_letter_path
            pstmt.setString(5, values[4]); // status
            pstmt.setString(6, values[5]); // notes
            pstmt.setTimestamp(7, new Timestamp(System.currentTimeMillis())); // applied_at
            pstmt.setBoolean(8, false); // is_deleted
            pstmt.setString(9, values[6]); // Department
            pstmt.setString(10, values.length > 7 ? values[7] : "ENTRY"); // experience_level
            pstmt.setString(11, values.length > 8 ? values[8] : ""); // EmailAddress
            pstmt.setInt(12, parseInt(values.length > 9 ? values[9] : "0")); // employee_id
            
            pstmt.executeUpdate();
        } catch (SQLException e) {
            errors.add("Database error inserting application: " + e.getMessage());
        }
    }
    
    /**
     * Insert interview into database
     */
    private void insertInterview(String[] values, List<String> errors) {
        String sql = "INSERT INTO interviews (application_id, interviewer_id, interview_date, type, meeting_link, location, feedback, score, result, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {
            pstmt.setInt(1, parseInt(values[0])); // application_id
            pstmt.setInt(2, parseInt(values[1])); // interviewer_id
            pstmt.setTimestamp(3, parseTimestamp(values[2])); // interview_date
            pstmt.setString(4, values[3]); // type
            pstmt.setString(5, values[4]); // meeting_link
            pstmt.setString(6, values[5]); // location
            pstmt.setString(7, values[6]); // feedback
            pstmt.setInt(8, parseInt(values[7])); // score
            pstmt.setString(9, values.length > 8 ? values[8] : "PENDING"); // result
            pstmt.setBoolean(10, false); // is_deleted
            
            pstmt.executeUpdate();
        } catch (SQLException e) {
            errors.add("Database error inserting interview: " + e.getMessage());
        }
    }
    
    /**
     * Helper methods for parsing values
     */
    private double parseDouble(String value) {
        try {
            return Double.parseDouble(value.trim());
        } catch (NumberFormatException e) {
            return 0.0;
        }
    }
    
    private int parseInt(String value) {
        try {
            return Integer.parseInt(value.trim());
        } catch (NumberFormatException e) {
            return 0;
        }
    }
    
    private Timestamp parseTimestamp(String value) {
        try {
            SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US);
            Date date = sdf.parse(value.trim());
            return new Timestamp(date.getTime());
        } catch (Exception e) {
            return new Timestamp(System.currentTimeMillis());
        }
    }
    
    /**
     * Result class for import operations
     */
    public static class ImportResult {
        private final int successCount;
        private final List<String> errors;
        
        public ImportResult(int successCount, List<String> errors) {
            this.successCount = successCount;
            this.errors = errors;
        }
        
        public int getSuccessCount() {
            return successCount;
        }
        
        public List<String> getErrors() {
            return errors;
        }
        
        public boolean hasErrors() {
            return !errors.isEmpty();
        }
        
        public void printSummary() {
            System.out.println("=== Import Summary ===");
            System.out.println("Successfully imported: " + successCount + " records");
            if (hasErrors()) {
                System.out.println("Errors encountered: " + errors.size());
                for (String error : errors) {
                    System.out.println("  - " + error);
                }
            } else {
                System.out.println("Import completed successfully with no errors!");
            }
        }
    }
}
