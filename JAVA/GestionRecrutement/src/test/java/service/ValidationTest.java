package service;

import models.Application;
import models.Interview;
import models.JobOffer;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import java.time.LocalDateTime;

import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

public class ValidationTest {

    private final JobOfferService jobOfferService = new JobOfferService();
    private final ApplicationService applicationService = new ApplicationService();
    private final InterviewService interviewService = new InterviewService();

    @Test
    @DisplayName("JobOffer: Should throw Exception when Title is empty")
    void testCreateJobOfferEmptyTitle() {
        JobOffer offer = new JobOffer("", "Description", "Dept", "Location", "Full-Time", 1000, 2000, "OPEN",
                LocalDateTime.now(), 1);
        Exception exception = assertThrows(IllegalArgumentException.class, () -> {
            jobOfferService.createOffer(offer);
        });
        assertTrue(exception.getMessage().contains("Title cannot be empty"));
    }

    @Test
    @DisplayName("JobOffer: Should throw Exception when Salary is negative")
    void testCreateJobOfferNegativeSalary() {
        JobOffer offer = new JobOffer("Title", "Description", "Dept", "Location", "Full-Time", -100, 2000, "OPEN",
                LocalDateTime.now(), 1);
        Exception exception = assertThrows(IllegalArgumentException.class, () -> {
            jobOfferService.createOffer(offer);
        });
        assertTrue(exception.getMessage().contains("Salary cannot be negative"));
    }

    @Test
    @DisplayName("Application: Should throw Exception when Candidate Name is empty")
    void testAddApplicationInvalidCandidateName() {
        Application app = new Application("", 1, "cv.pdf", "cover.pdf", "APPLIED", "Notes", LocalDateTime.now(),
                "Engineering", "Entry Level (0-2 yrs)", "test@email.com", 1);
        Exception exception = assertThrows(IllegalArgumentException.class, () -> {
            applicationService.addApplication(app);
        });
        assertTrue(exception.getMessage().contains("Candidate name cannot be empty"));
    }

    @Test
    @DisplayName("Interview: Should throw Exception when Date is null")
    void testScheduleInterviewNullDate() {
        Interview interview = new Interview(1, 1, null, "TECHNICAL", "Loc", "Link", "Feedback", 0, "PENDING");
        Exception exception = assertThrows(IllegalArgumentException.class, () -> {
            interviewService.scheduleInterview(interview);
        });
        assertTrue(exception.getMessage().contains("Interview date cannot be null"));
    }

    @Test
    @DisplayName("JobOffer: Should throw Exception when Title is whitespace only")
    void testCreateJobOfferWhitespaceTitle() {
        JobOffer offer = new JobOffer("   ", "Description", "Dept", "Location", "Full-Time", 1000, 2000, "OPEN",
                LocalDateTime.now(), 1);
        Exception exception = assertThrows(IllegalArgumentException.class, () -> {
            jobOfferService.createOffer(offer);
        });
        assertTrue(exception.getMessage().contains("Title cannot be empty"));
    }

    @Test
    @DisplayName("JobOffer: Should throw Exception when Status is invalid")
    void testCreateJobOfferInvalidStatus() {
        JobOffer offer = new JobOffer("Title", "Description", "Dept", "Location", "Full-Time", 1000, 2000,
                "INVALID_STATUS", LocalDateTime.now(), 1);
        Exception exception = assertThrows(IllegalArgumentException.class, () -> {
            jobOfferService.createOffer(offer);
        });
        assertTrue(exception.getMessage().contains("Invalid status"));
    }

    @Test
    @DisplayName("Interview: Should throw Exception when Type is invalid")
    void testScheduleInterviewInvalidType() {
        Interview interview = new Interview(1, 1, LocalDateTime.now(), "INVALID_TYPE", "Loc", "Link", "Feedback", 0,
                "PENDING");
        Exception exception = assertThrows(IllegalArgumentException.class, () -> {
            interviewService.scheduleInterview(interview);
        });
        assertTrue(exception.getMessage().contains("Invalid interview type"));
    }
}
