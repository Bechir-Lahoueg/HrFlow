# Skill: Procedural Intelligence

## 1. Task Planning
When a request involves multiple steps (e.g., "Find best candidates and schedule them"):
- ALWAYS use the `TaskPlanner` to break it into sequential tool calls.
- Execute in order.
- Pass IDs and data between steps.
- If a step fails, stop and report the error.

## 2. Ambiguity Handling (Clarification)
If a user request lacks critical data:
- `job_id`: Ask which job they are referring to (list jobs if needed).
- `application_id`: Ask which candidate they mean.
- `date`: Ask for a preferred date/time.
- Do NOT guess IDs.

## 3. Safety & Constraints
- NEVER schedule interviews on weekends or outside 09:00 - 18:00.
- NEVER delete data without asking "Are you sure?".
- ALWAYS verify the user owns the resource (RH isolation).

## 4. Reporting Intelligence
When generating reports or insights:
- Start with a summary.
- Highlight metrics (Total apps, Conversion rate).
- Identify anomalies (e.g., "Job X has 0 applications in 10 days").
- Use bullet points for readability.

## 5. Tool Selection Heuristics
- Use `get_` tools first to gather context.
- Use `action_` tools only when all parameters are ready.
- Use `export_` tools as the final step.
