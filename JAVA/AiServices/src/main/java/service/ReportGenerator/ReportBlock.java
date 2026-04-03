package service.ReportGenerator;

import java.util.List;
import java.util.Map;

/**
 * Report Block - Individual content block in a report
 */
public class ReportBlock {
    private String type;
    private String content;
    private String sql;
    private String chartType;
    private String description;
    
    // Query result data
    private List<String> columns;
    private List<Map<String, Object>> data;
    private boolean querySuccess;
    private String queryError;
    
    public ReportBlock() {}
    
    // Getters and setters
    public String getType() { return type; }
    public void setType(String type) { this.type = type; }
    public String getContent() { return content; }
    public void setContent(String content) { this.content = content; }
    public String getSql() { return sql; }
    public void setSql(String sql) { this.sql = sql; }
    public String getChartType() { return chartType; }
    public void setChartType(String chartType) { this.chartType = chartType; }
    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
    
    // Query result getters and setters
    public List<String> getColumns() { return columns; }
    public void setColumns(List<String> columns) { this.columns = columns; }
    public List<Map<String, Object>> getData() { return data; }
    public void setData(List<Map<String, Object>> data) { this.data = data; }
    public boolean isQuerySuccess() { return querySuccess; }
    public void setQuerySuccess(boolean querySuccess) { this.querySuccess = querySuccess; }
    public String getQueryError() { return queryError; }
    public void setQueryError(String queryError) { this.queryError = queryError; }
}
