# CSV Import/Export Feature

This feature provides comprehensive CSV import and export capabilities for the recruitment platform.

## Features

### Import Capabilities
- **Job Offers**: Import job postings from CSV files
- **Applications**: Import candidate applications from CSV files  
- **Interviews**: Import interview schedules from CSV files

### Export Capabilities
- **Job Offers**: Export job postings to CSV files
- **Applications**: Export candidate applications to CSV files
- **Interviews**: Export interview data to CSV files
- **Custom Queries**: Export data using custom SQL queries

### Template Generation
- Generate sample CSV templates for each data type
- Includes proper headers and sample data

## File Formats

### Job Offers CSV Format
```csv
title,description,department,location,employmentType,salary_min,salary_max,status
Senior Software Engineer,We are looking for...,Engineering,Remote,FULL_TIME,80000,120000,OPEN
```

### Applications CSV Format
```csv
candidate_name,job_offer_id,cv_path,cover_letter_path,status,notes,Department,experience_level,EmailAddress,employee_id
John Doe,1,/uploads/cv.pdf,/uploads/cover.pdf,APPLIED,Strong candidate...,Engineering,SENIOR,john@email.com,1
```

### Interviews CSV Format
```csv
application_id,interviewer_id,interview_date,type,meeting_link,location,feedback,score,result
1,2,2024-03-05 14:00:00,TECHNICAL,https://zoom.us/j/123,Room A,Good skills,85,PASS
```

## Usage

### Using the CLI Tool
```bash
# Compile and run the CLI tool
cd /home/amin/Workforce-Platform/JAVA/AiServices
mvn compile
java -cp target/classes service.ImportExport.CsvImportExportCLI
```

### CLI Menu Options
1. **Import Data**: Choose what type of data to import
2. **Export Data**: Choose what to export and where
3. **Generate Templates**: Create sample CSV files
4. **Exit**: Leave the application

### Programmatic Usage

```java
// Import service
CsvImportService importService = new CsvImportService();

// Import job offers
CsvImportService.ImportResult jobResult = importService.importJobOffers("/path/to/job_offers.csv");
jobResult.printSummary();

// Import applications  
CsvImportService.ImportResult appResult = importService.importApplications("/path/to/applications.csv");
appResult.printSummary();

// Export service
CsvExportService exportService = new CsvExportService();

// Export job offers
CsvExportService.ExportResult exportResult = exportService.exportJobOffers("/path/to/export.csv", false);
exportResult.printSummary();

// Generate templates
exportService.generateJobOfferTemplate("/path/to/template.csv");
```

## Error Handling

The services provide comprehensive error handling:
- **File reading errors**: Reports issues with file access
- **CSV parsing errors**: Handles malformed CSV lines
- **Database errors**: Reports SQL insertion failures
- **Data validation**: Validates required fields and formats

## Database Mapping

The import/export services map to the actual database schema:

### Job Offers Table
- Maps to `job_offer` table
- Handles proper enum values for status
- Sets default values for created_at and created_by

### Applications Table  
- Maps to `applications` table
- Uses proper column names (Department, EmailAddress, experience_level)
- Handles foreign key relationships

### Interviews Table
- Maps to `interviews` table
- Uses correct column names (type, result)
- Handles datetime formatting

## Security Considerations

- Uses prepared statements to prevent SQL injection
- Validates input data types and formats
- Handles null values appropriately
- Includes soft delete flag handling

## Performance Features

- Batch processing for large files
- Memory-efficient CSV parsing
- Proper resource cleanup with try-with-resources
- Progress reporting for long operations
