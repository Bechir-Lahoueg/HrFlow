package service.ReportGenerator;

import org.json.JSONObject;
import org.json.JSONArray;
import java.util.function.Consumer;

/**
 * Report Result - Success/error wrapper for report generation
 */
public class ReportResult {
    private boolean success;
    private String error;
    private String title;
    private String summary;
    private String content;
    private java.util.List<ReportBlock> blocks;
    
    public ReportResult() {
        this.success = false;
        this.blocks = new java.util.ArrayList<>();
    }
    
    // Getters and setters
    public boolean isSuccess() { return success; }
    public void setSuccess(boolean success) { this.success = success; }
    public String getError() { return error; }
    public void setError(String error) { this.error = error; }
    public String getTitle() { return title; }
    public void setTitle(String title) { this.title = title; }
    public String getSummary() { return summary; }
    public void setSummary(String summary) { this.summary = summary; }
    public String getContent() { return content; }
    public void setContent(String content) { this.content = content; }
    public java.util.List<ReportBlock> getBlocks() { return blocks; }
    public void setBlocks(java.util.List<ReportBlock> blocks) { this.blocks = blocks; }
}
