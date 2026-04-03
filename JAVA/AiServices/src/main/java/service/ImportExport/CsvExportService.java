package service.ImportExport;

import java.io.BufferedWriter;
import java.io.FileWriter;
import java.io.IOException;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.SimpleDateFormat;
import java.util.Date;

import utils.Mydb;

/**
 * CSV Export Service for recruitment data
 * Supports exporting job offers, applications, and interviews to CSV files
 */
public class CsvExportService {
    
    private Connection connection;
    private SimpleDateFormat dateFormat;
    private static final boolean DEBUG = true;
    
    public CsvExportService() {
        this.connection = Mydb.getInstance().getConnection();
        if (this.connection == null) {
            throw new RuntimeException("Failed to establish database connection for CSV Export Service");
        }
        debug("CsvExportService initialized with database connection");
        this.dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
    }
    
    private void debug(String message) {
        if (DEBUG) {
            System.out.println("[CSV-DEBUG] " + message);
        }
    }
    
    /**
     * Export job offers to CSV file
     */
    public ExportResult exportJobOffers(String filePath, boolean includeDeleted) {
        String sql = "SELECT id, title, description, department, location, employmentType, salary_min, salary_max, status, created_at, created_by FROM job_offer";
        if (!includeDeleted) {
            sql += " WHERE is_deleted = false";
        }
        sql += " ORDER BY created_at DESC";
        
        return exportToCsv(sql, filePath, new String[]{
            "ID", "Title", "Description", "Department", "Location", "Employment Type", 
            "Min Salary", "Max Salary", "Status", "Created At", "Created By"
        });
    }
    
    /**
     * Export applications to CSV file
     */
    public ExportResult exportApplications(String filePath, boolean includeDeleted) {
        String sql = "SELECT id, candidate_name, job_offer_id, cv_path, cover_letter_path, status, notes, applied_at, Department, experience_level, EmailAddress, employee_id FROM applications";
        if (!includeDeleted) {
            sql += " WHERE is_deleted = false";
        }
        sql += " ORDER BY applied_at DESC";
        
        return exportToCsv(sql, filePath, new String[]{
            "ID", "Candidate Name", "Job Offer ID", "CV Path", "Cover Letter Path", 
            "Status", "Notes", "Applied At", "Department", "Experience Level", "Email Address", "Employee ID"
        });
    }
    
    /**
     * Export interviews to CSV file
     */
    public ExportResult exportInterviews(String filePath, boolean includeDeleted) {
        String sql = "SELECT id, application_id, interviewer_id, interview_date, type, meeting_link, location, feedback, score, result FROM interviews";
        if (!includeDeleted) {
            sql += " WHERE is_deleted = false";
        }
        sql += " ORDER BY interview_date DESC";
        
        return exportToCsv(sql, filePath, new String[]{
            "ID", "Application ID", "Interviewer ID", "Interview Date", "Type", 
            "Meeting Link", "Location", "Feedback", "Score", "Result"
        });
    }
    
    /**
     * Export filtered data based on custom SQL query
     */
    public ExportResult exportCustomQuery(String sql, String filePath, String[] headers) {
        return exportToCsv(sql, filePath, headers);
    }
    
    /**
     * Generic method to export SQL query results to CSV
     */
    private ExportResult exportToCsv(String sql, String filePath, String[] headers) {
        int recordCount = 0;
        
        try (BufferedWriter writer = new BufferedWriter(new FileWriter(filePath));
             PreparedStatement pstmt = connection.prepareStatement(sql);
             ResultSet rs = pstmt.executeQuery()) {
            
            // Write headers
            writeCsvLine(writer, headers);
            
            // Write data rows
            while (rs.next()) {
                String[] values = new String[headers.length];
                
                for (int i = 0; i < headers.length; i++) {
                    try {
                        Object value = rs.getObject(i + 1);
                        if (value == null) {
                            values[i] = "";
                        } else if (value instanceof Date) {
                            values[i] = dateFormat.format((Date) value);
                        } else {
                            values[i] = value.toString();
                        }
                    } catch (SQLException e) {
                        values[i] = "";
                    }
                }
                
                writeCsvLine(writer, values);
                recordCount++;
            }
            
            System.out.println("Successfully exported " + recordCount + " records to " + filePath);
            return new ExportResult(true, recordCount, null);
            
        } catch (IOException | SQLException e) {
            String error = "Export failed: " + e.getMessage();
            System.err.println(error);
            return new ExportResult(false, 0, error);
        }
    }
    
    /**
     * Write a line to CSV file with proper escaping
     */
    private void writeCsvLine(BufferedWriter writer, String[] values) throws IOException {
        for (int i = 0; i < values.length; i++) {
            String value = values[i] != null ? values[i] : "";
            
            // Escape quotes and wrap in quotes if value contains comma or quote
            if (value.contains("\"") || value.contains(",")) {
                value = value.replace("\"", "\"\"");
                writer.write("\"" + value + "\"");
            } else {
                writer.write(value);
            }
            
            if (i < values.length - 1) {
                writer.write(",");
            }
        }
        writer.newLine();
    }
    
    /**
     * Generate sample CSV templates
     */
    public void generateJobOfferTemplate(String filePath) {
        String[] headers = new String[]{
            "title", "description", "department", "location", "employmentType", 
            "salary_min", "salary_max", "status"
        };
        
        String[] sampleData = new String[]{
            "Senior Software Engineer", 
            "We are looking for an experienced software engineer...", 
            "Engineering", 
            "Remote", 
            "FULL_TIME", 
            "80000", 
            "120000", 
            "OPEN"
        };
        
        try (BufferedWriter writer = new BufferedWriter(new FileWriter(filePath))) {
            writeCsvLine(writer, headers);
            writeCsvLine(writer, sampleData);
            System.out.println("Job offer template generated: " + filePath);
        } catch (IOException e) {
            System.err.println("Failed to generate template: " + e.getMessage());
        }
    }
    
    public void generateApplicationTemplate(String filePath) {
        String[] headers = new String[]{
            "candidate_name", "job_offer_id", "cv_path", "cover_letter_path", 
            "status", "notes", "Department", "experience_level", "EmailAddress", "employee_id"
        };
        
        String[] sampleData = new String[]{
            "John Doe", 
            "1", 
            "/uploads/john_doe_cv.pdf", 
            "/uploads/john_doe_cover.pdf", 
            "APPLIED", 
            "Strong candidate with 5 years experience...", 
            "Engineering", 
            "SENIOR", 
            "john.doe@email.com", 
            "1"
        };
        
        try (BufferedWriter writer = new BufferedWriter(new FileWriter(filePath))) {
            writeCsvLine(writer, headers);
            writeCsvLine(writer, sampleData);
            System.out.println("Application template generated: " + filePath);
        } catch (IOException e) {
            System.err.println("Failed to generate template: " + e.getMessage());
        }
    }
    
    public void generateInterviewTemplate(String filePath) {
        String[] headers = new String[]{
            "application_id", "interviewer_id", "interview_date", "type", 
            "meeting_link", "location", "feedback", "score", "result"
        };
        
        String[] sampleData = new String[]{
            "1", 
            "2", 
            "2024-03-05 14:00:00", 
            "TECHNICAL", 
            "https://zoom.us/j/123456789", 
            "Conference Room A", 
            "Good technical skills, needs work on communication", 
            "85", 
            "PASS"
        };
        
        try (BufferedWriter writer = new BufferedWriter(new FileWriter(filePath))) {
            writeCsvLine(writer, headers);
            writeCsvLine(writer, sampleData);
            System.out.println("Interview template generated: " + filePath);
        } catch (IOException e) {
            System.err.println("Failed to generate template: " + e.getMessage());
        }
    }
    
    /**
     * Result class for export operations
     */
    public static class ExportResult {
        private final boolean success;
        private final int recordCount;
        private final String error;
        
        public ExportResult(boolean success, int recordCount, String error) {
            this.success = success;
            this.recordCount = recordCount;
            this.error = error;
        }
        
        public boolean isSuccess() {
            return success;
        }
        
        public int getRecordCount() {
            return recordCount;
        }
        
        public String getError() {
            return error;
        }
        
        public void printSummary() {
            if (success) {
                System.out.println("=== Export Summary ===");
                System.out.println("Successfully exported: " + recordCount + " records");
            } else {
                System.out.println("=== Export Failed ===");
                System.out.println("Error: " + error);
            }
        }
    }
}
