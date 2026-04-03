package org.example;

import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.List;

import models.Application;
import models.Interview;
import models.JobOffer;
import service.ApplicationService; // Fixed package name from service to service
import service.InterviewService;
import service.JobOfferService;
import utils.Mydb;

public class Main {
    public static void main(String[] args) {
        // Correct way to initialize Singleton
        Mydb.getInstance();

        JobOfferService jobService = new JobOfferService();
        ApplicationService appService = new ApplicationService();
        InterviewService interviewService = new InterviewService();

        try {
            System.out.println("=========================================");
            System.out.println("       STARTING CRUD TEST SCENARIO       ");
            System.out.println("=========================================");

            // --- 1. CREATE PHASE ---
            System.out.println("\n--- 1. CREATE PHASE --");

            JobOffer jo = new JobOffer("Senior Java Developer", "Build scalable backend systems", "Engineering",
                    "Tunis", "Full-Time", 6000, 9000, "OPEN", LocalDateTime.now(), 1);
            jobService.createOffer(jo);

            List<JobOffer> jobs = jobService.getAllActiveOffers();
            JobOffer createdJob = jobs.get(jobs.size() - 1);
            int jobId = createdJob.getId();
            System.out.println("[SUCCESS] Job Created (ID: " + jobId + ")");

            // Manual name instead of candidate name (updated with default new fields)
            Application app = new Application("Jane Doe", jobId, "resume.pdf", "motivation.pdf", "APPLIED",
                    "Strong candidate", LocalDateTime.now(), "Engineering", "Entry Level (0-2 yrs)", "jane@doe.com", 1);
            appService.addApplication(app);

            List<Application> apps = appService.getActiveApplications();
            Application createdApp = apps.get(apps.size() - 1);
            int appId = createdApp.getId();
            System.out.println("[SUCCESS] Application Created (ID: " + appId + ")");

            // Type must be 'TECHNICAL', 'HR', or 'FINAL'
            Interview interview = new Interview(appId, 202, LocalDateTime.now(), "TECHNICAL", "Google Meet",
                    "meet.google.com/abc", "TBD", 0, "PENDING");
            interviewService.scheduleInterview(interview);

            List<Interview> interviews = interviewService.getActiveInterviews();
            int interviewId = interviews.get(interviews.size() - 1).getId();
            System.out.println("[SUCCESS] Interview Scheduled (ID: " + interviewId + ")");

            // --- 2. UPDATE PHASE ---
            System.out.println("\n--- 2. UPDATE PHASE ---");

            // Fix: Status must be 'OPEN' or 'CLOSED' (per your schema)
            createdJob.setSalaryMin(6500);
            createdJob.setStatus("OPEN");
            // jobService.updateJobOffer(createdJob); // Ensure this method exists in your
            // service

            // Fix: Status must match your ENUM ('INTERVIEW' exists, 'INTERVIEW_SCHEDULED'
            // does not)
            createdApp.setStatus("INTERVIEW");
            appService.updateApplication(createdApp);
            System.out.println("[UPDATE] Application moved to INTERVIEW status.");

            // Fix: Result must be 'PASS' or 'FAIL' (your schema says 'PASS', not 'PASSED')
            Interview interviewToUpdate = interviews.get(interviews.size() - 1);
            interviewToUpdate.setScore(85);
            interviewToUpdate.setResult("PASS");
            interviewToUpdate.setFeedback("Excellent coding skills.");
            interviewService.updateInterview(interviewToUpdate);
            System.out.println("[UPDATE] Interview Result set to PASS.");

            // --- 3. DELETE PHASE ---
            System.out.println("\n--- 3. DELETE PHASE ---");

            System.out.println("Soft deleting interview " + interviewId + "...");
            interviewService.softDeleteInterview(interviewId);

            // Hard delete the application (Cascade will clean up the actual DB rows)
            System.out.println("Hard deleting application " + appId + "...");
            appService.hardDeleteApplication(appId);

            System.out.println("Hard deleting job offer " + jobId + "...");
            jobService.hardDeleteJobOffer(jobId);

            System.out.println("\n=========================================");
            System.out.println("           TEST SCENARIO COMPLETE        ");
            System.out.println("=========================================");

        } catch (SQLException e) {
            System.err.println("Database Error: " + e.getMessage());
            e.printStackTrace();
        }
    }
}