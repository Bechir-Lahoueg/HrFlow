package service.ReportGenerator;

import org.json.JSONObject;
import org.json.JSONArray;
import java.util.function.Consumer;

/**
 * Report Agent - ADK-style orchestrator for flexible AI report generation
 * Coordinates SQL planning, query execution, and narrative generation
 * 
 * NOTE: SQL validation removed. Only checks for presence of SQL text and logs warnings for INSERT/UPDATE/DELETE.
 */
public class ReportAgent {
    
    private SqlPlannerLlm sqlPlanner;
    private DbQueryService dbQueryService;
    private ReportLlm reportLlm;
    private ReportBuilder reportBuilder;
    private String apiKey;
    
    public ReportAgent() {
        ApiKeyManager.LlmProvider provider = ApiKeyManager.getLlmProvider();
        this.apiKey = (provider == ApiKeyManager.LlmProvider.GROQ) ? ApiKeyManager.getGroqApiKey() : ApiKeyManager.getApiKey();
        System.out.println("DEBUG: ReportAgent LLM provider: " + provider);
        System.out.println("DEBUG: ReportAgent initialized with API key (length: "
                + (apiKey != null ? apiKey.length() : "null") + ")");
        this.sqlPlanner = new SqlPlannerLlm(apiKey);
        this.dbQueryService = new DbQueryService();
        this.reportLlm = new ReportLlm(apiKey);
        this.reportBuilder = new ReportBuilder();
    }
    
    /**
     * Main entry point for flexible report generation
     * @param prompt User's report request
     * @param callback Callback to handle result
     */
    public void generateReport(String prompt, Consumer<ReportResult> callback) {
        new Thread(() -> {
            try {
                System.out.println("🤖 Starting flexible report generation for prompt: " + prompt);
                
                // Step 1: SQL Planning - Generate dynamic queries
                System.out.println("📋 Step 1: Planning SQL queries...");
                SqlPlannerLlm.SqlPlanResult sqlPlan = sqlPlanner.planQueries(prompt);
                
                if (!sqlPlan.isSuccess()) {
                    ReportResult errorResult = new ReportResult();
                    errorResult.setError("SQL planning failed: " + sqlPlan.getErrorMessage());
                    callback.accept(errorResult);
                    return;
                }

                // Step 2: Build query list (no SQL validator)
                System.out.println("� Step 2: Preparing SQL queries...");
                java.util.List<SqlPlannerLlm.SqlQuery> plannedQueryList = new java.util.ArrayList<>();
                org.json.JSONArray plannedQueries = sqlPlan.getQueries();
                for (int i = 0; i < plannedQueries.length(); i++) {
                    org.json.JSONObject planned = plannedQueries.getJSONObject(i);
                    SqlPlannerLlm.SqlQuery query = new SqlPlannerLlm.SqlQuery();
                    query.setSql(planned.optString("sql", null));
                    query.setDescription(planned.optString("description", null));
                    query.setChartType(planned.optString("chartType", null));

                    if (query.getSql() == null || query.getSql().trim().isEmpty()) {
                        ReportResult errorResult = new ReportResult();
                        errorResult.setError("SQL planning returned an empty query at index " + i);
                        callback.accept(errorResult);
                        return;
                    }

                    // Simple check: log if query contains INSERT, UPDATE, DELETE
                    String sqlLower = query.getSql().toLowerCase();
                    if (sqlLower.contains("insert") || sqlLower.contains("update") || sqlLower.contains("delete")) {
                        System.out.println("⚠️ Warning: Query at index " + i + " contains INSERT/UPDATE/DELETE. Executing anyway.");
                    }

                    plannedQueryList.add(query);
                }
                
                // Step 3: Query Execution - Run all queries
                System.out.println("⚡ Step 3: Executing " + plannedQueryList.size() + " SQL queries...");
                ReportBuilder.ExecutionResult executionResult = reportBuilder.executeAllQueries(plannedQueryList);
                
                if (!executionResult.isSuccess()) {
                    ReportResult errorResult = new ReportResult();
                    errorResult.setError("Query execution failed: " + executionResult.getError());
                    callback.accept(errorResult);
                    return;
                }
                
                // Step 4: Narrative Generation - Create comprehensive report
                System.out.println("📝 Step 4: Generating narrative report...");
                ReportLlm.LlmResponse narrativeResult = reportLlm.generateReport(
                    prompt, 
                    executionResult.getResults(),
                    plannedQueryList
                );
                
                if (!narrativeResult.isSuccess()) {
                    ReportResult errorResult = new ReportResult();
                    errorResult.setError("Narrative generation failed: " + narrativeResult.getError());
                    callback.accept(errorResult);
                    return;
                }
                
                // Step 5: Success - Return complete report
                System.out.println("✅ Report generation completed successfully!");
                ReportResult successResult = new ReportResult();
                successResult.setSuccess(true);
                successResult.setContent(narrativeResult.getContent());

                java.util.List<ReportBlock> blocks = new java.util.ArrayList<>();

                try {
                    JSONObject json = new JSONObject(narrativeResult.getContent());
                    successResult.setTitle(json.optString("title", "Generated Report"));
                    successResult.setSummary(json.optString("summary", ""));

                    JSONArray sections = json.optJSONArray("sections");
                    for (int i = 0; i < plannedQueryList.size(); i++) {
                        SqlPlannerLlm.SqlQuery q = plannedQueryList.get(i);
                        ReportBlock b = new ReportBlock();
                        b.setSql(q.getSql());
                        b.setDescription(q.getDescription() != null ? q.getDescription() : ("Section " + (i + 1)));

                        // Populate query results from execution result
                        JSONObject queryResult = executionResult.getResults().optJSONObject("query_" + i);
                        if (queryResult != null) {
                            b.setQuerySuccess(queryResult.optBoolean("success", false));
                            if (queryResult.optBoolean("success", false)) {
                                // Extract columns and data from the query result
                                java.util.List<String> columns = new java.util.ArrayList<>();
                                java.util.List<java.util.Map<String, Object>> data = new java.util.ArrayList<>();
                                
                                JSONArray cols = queryResult.optJSONArray("columns");
                                if (cols != null) {
                                    for (int c = 0; c < cols.length(); c++) {
                                        columns.add(cols.getString(c));
                                    }
                                }
                                
                                JSONArray rows = queryResult.optJSONArray("rows");
                                if (rows != null) {
                                    for (int r = 0; r < rows.length(); r++) {
                                        JSONObject row = rows.getJSONObject(r);
                                        java.util.Map<String, Object> rowMap = new java.util.HashMap<>();
                                        for (String col : columns) {
                                            rowMap.put(col, row.opt(col));
                                        }
                                        data.add(rowMap);
                                    }
                                }
                                
                                b.setColumns(columns);
                                b.setData(data);
                            } else {
                                b.setQueryError(queryResult.optString("error", "Unknown error"));
                            }
                        } else {
                            b.setQuerySuccess(false);
                            b.setQueryError("Query result not found");
                        }

                        if (q.getChartType() != null && !q.getChartType().trim().isEmpty() && !"TABLE".equalsIgnoreCase(q.getChartType())) {
                            b.setType("chart");
                            b.setChartType(q.getChartType());
                        } else {
                            b.setType("table");
                        }

                        if (sections != null) {
                            for (int s = 0; s < sections.length(); s++) {
                                JSONObject sec = sections.optJSONObject(s);
                                if (sec != null && sec.optInt("index", -1) == i) {
                                    b.setContent(sec.optString("description", null));
                                    break;
                                }
                            }
                        }

                        blocks.add(b);
                    }

                    String conclusion = json.optString("conclusion", null);
                    if (conclusion != null && !conclusion.trim().isEmpty()) {
                        ReportBlock conclusionBlock = new ReportBlock();
                        conclusionBlock.setType("text");
                        conclusionBlock.setDescription("Conclusion");
                        conclusionBlock.setContent(conclusion);
                        blocks.add(conclusionBlock);
                    }
                } catch (Exception parseErr) {
                    successResult.setTitle("Generated Report");
                    successResult.setSummary("");
                }

                successResult.setBlocks(blocks);
                callback.accept(successResult);
                
            } catch (Exception e) {
                System.err.println("❌ Report generation failed: " + e.getMessage());
                e.printStackTrace();
                ReportResult errorResult = new ReportResult();
                errorResult.setError("Report generation failed: " + e.getMessage());
                callback.accept(errorResult);
            }
        }).start();
    }
}