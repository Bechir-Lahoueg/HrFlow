package models;

import java.time.LocalDateTime;
import java.util.Objects;

public class Interview {

    private int id;
    private int applicationId;
    private int interviewerId;
    private LocalDateTime interviewDate;
    private String type;
    private String location;
    private String meetingLink;
    private String feedback;
    private int score;
    private String result;
    private String candidateName; // Joined field
    private String interviewerName; // Joined field
    private String jobTitle; // Joined field

    public Interview() {
    }

    // Without id
    public Interview(int applicationId, int interviewerId,
            LocalDateTime interviewDate, String type,
            String location, String meetingLink,
            String feedback, int score, String result) {
        this.applicationId = applicationId;
        this.interviewerId = interviewerId;
        this.interviewDate = interviewDate;
        this.type = type;
        this.location = location;
        this.meetingLink = meetingLink;
        this.feedback = feedback;
        this.score = score;
        this.result = result;
    }

    // Full constructor
    public Interview(int id, int applicationId, int interviewerId,
            LocalDateTime interviewDate, String type,
            String location, String meetingLink,
            String feedback, int score, String result) {
        this(applicationId, interviewerId, interviewDate, type,
                location, meetingLink, feedback, score, result);
        this.id = id;
    }

    // Full constructor (including joined fields)
    public Interview(int id, int applicationId, int interviewerId,
            LocalDateTime interviewDate, String type,
            String location, String meetingLink,
            String feedback, int score, String result,
            String candidateName, String interviewerName, String jobTitle) {
        this(id, applicationId, interviewerId, interviewDate, type,
                location, meetingLink, feedback, score, result);
        this.candidateName = candidateName;
        this.interviewerName = interviewerName;
        this.jobTitle = jobTitle;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public int getApplicationId() {
        return applicationId;
    }

    public void setApplicationId(int applicationId) {
        this.applicationId = applicationId;
    }

    public int getInterviewerId() {
        return interviewerId;
    }

    public void setInterviewerId(int interviewerId) {
        this.interviewerId = interviewerId;
    }

    public LocalDateTime getInterviewDate() {
        return interviewDate;
    }

    public void setInterviewDate(LocalDateTime interviewDate) {
        this.interviewDate = interviewDate;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public String getLocation() {
        return location;
    }

    public void setLocation(String location) {
        this.location = location;
    }

    public String getMeetingLink() {
        return meetingLink;
    }

    public void setMeetingLink(String meetingLink) {
        this.meetingLink = meetingLink;
    }

    public String getFeedback() {
        return feedback;
    }

    public void setFeedback(String feedback) {
        this.feedback = feedback;
    }

    public int getScore() {
        return score;
    }

    public void setScore(int score) {
        this.score = score;
    }

    public String getResult() {
        return result;
    }

    public void setResult(String result) {
        this.result = result;
    }

    public String getCandidateName() {
        return candidateName;
    }

    public void setCandidateName(String candidateName) {
        this.candidateName = candidateName;
    }

    public String getInterviewerName() {
        return interviewerName;
    }

    public void setInterviewerName(String interviewerName) {
        this.interviewerName = interviewerName;
    }

    public String getJobTitle() {
        return jobTitle;
    }

    public void setJobTitle(String jobTitle) {
        this.jobTitle = jobTitle;
    }

    @Override
    public String toString() {
        return "Interview{id=" + id +
                ", applicationId=" + applicationId +
                ", date=" + interviewDate + "}";
    }

    @Override
    public boolean equals(Object o) {
        if (this == o)
            return true;
        if (!(o instanceof Interview))
            return false;
        Interview that = (Interview) o;
        return id == that.id;
    }

    @Override
    public int hashCode() {
        return Objects.hash(id);
    }
}
