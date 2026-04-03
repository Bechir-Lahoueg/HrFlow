package service.ReportGenerator;

import org.json.JSONObject;
import org.json.JSONArray;
import java.util.function.Consumer;

/**
 * Report Builder - Executes SQL queries and builds structured reports
 */
public class ReportBuilder {
    private DbQueryService dbQueryService;
    
    public ReportBuilder() {
        this.dbQueryService = new DbQueryService();
    }
    
    /**
     * Executes all SQL queries and returns results.
     */
    public ExecutionResult executeAllQueries(java.util.List<SqlPlannerLlm.SqlQuery> queries) {
        try {
            ExecutionResult result = new ExecutionResult();
            result.setSuccess(true);
            
            JSONObject allResults = new JSONObject();
            
            for (int i = 0; i < queries.size(); i++) {
                SqlPlannerLlm.SqlQuery query = queries.get(i);
                String sqlToRun = query.getSql();
                System.out.println("Executing query " + (i + 1) + ": " + sqlToRun);
                JSONObject queryResult = dbQueryService.executeSelectQuery(sqlToRun);
                
                if (queryResult.getBoolean("success")) {
                    allResults.put("query_" + i, queryResult);
                } else {
                    result.setSuccess(false);
                    result.setError("Query execution failed: " + queryResult.getString("error"));
                    return result;
                }
            }
            
            result.setResults(allResults);
            return result;
            
        } catch (Exception e) {
            ExecutionResult errorResult = new ExecutionResult();
            errorResult.setSuccess(false);
            errorResult.setError("Report building failed: " + e.getMessage());
            return errorResult;
        }
    }
    
    /**
     * Execution Result wrapper
     */
    public static class ExecutionResult {
        private boolean success;
        private String error;
        private JSONObject results;
        
        public ExecutionResult() {
            this.success = false;
            this.results = new JSONObject();
        }
        
        // Getters and setters
        public boolean isSuccess() { return success; }
        public void setSuccess(boolean success) { this.success = success; }
        public String getError() { return error; }
        public void setError(String error) { this.error = error; }
        public JSONObject getResults() { return results; }
        public void setResults(JSONObject results) { this.results = results; }
    }
}