# Chatbot Recruitement - Session Summary

## Date: April 23, 2026

## Overview
Redesigned the AI chatbot for the recruitment module to use Groq's native local tool calling pattern instead of the previous two-step approach (TaskPlanner → execute).

## Problems Fixed

### 1. Tool Schema Validation Error
**Error:** `invalid JSON schema for tool get_pipeline_stats`
**Cause:** `'properties' => []` (array) should be `'properties' => (object)[]`
**Solution:** Cast all properties to objects in tool definitions:
```php
'properties' => (object)[
    'param' => ['type' => 'string', ...]
],
'additionalProperties' => false
```

### 2. Tool Calling Pattern - FIXED APRIL 23
**Problem:** "give all candidates" triggers 4+ API calls (model repeats same tool)
**Cause:** Model kept calling get_candidates repeatedly
**Solution:** Updated system prompt with rules:
- "Only call ONE tool at a time"
- "NEVER repeat the same tool call"
- Example: Call get_candidates ONCE, use those results

### 3. Echo Responses
**Problem:** User says "hello" → Bot replies "hello"
**Solution:** Enhanced system prompt with rules

### 4. Raw JSON in Responses
**Problem:** Bot outputs raw JSON to users
**Solution:** Added `cleanMessage()` function to strip JSON artifacts

### 5. Multi-step Tasks
**Problem:** "recrute [name]" fails
**Solution:** System prompt now explains: find ID first → then move stage

### 6. Security - Ownership
**Problem:** move_candidate_stage didn't check ownership
**Solution:** Added DbUser verification in tool

## Files Created

### New Tools (Reusing Existing Repository Methods)
- `src/Service/AI/Tool/Application/GetApplicationsTool.php` → uses `ApplicationRepository::findByRh()`
- `src/Service/AI/Tool/Interview/GetInterviewsTool.php` → uses `InterviewRepository::findByRh()`
- `src/Service/AI/Tool/Candidate/GetCandidatesTool.php` → uses `CandidateRepository::findAll()`

### Existing Repository Methods Used
- `ApplicationRepository::findByRh()`, `getStatusStats()`, `countByRh()`
- `InterviewRepository::findByRh()`, `countByRh()`, `countUpcoming()`
- `JobOfferRepository::findByRh()`, `getStatusStats()`, `countByRh()`
- `CandidateRepository::findAll()`

### Modified Files
- `AgentOrchestrator.php` - Direct tool loop + single-call rule
- All tools - Fixed JSON schema format
- `MoveStageTool.php` - Added security check
- UI template - Table rendering

## Architecture
```
User → [Model + Tools] → ToolCalls?
  ↓ Yes → Execute → Add results → Call again
  ↓ No → Present results (ONE call only!)
```

## Available Tools
| Tool | Description | Uses Repository |
|------|-------------|----------------|
| `get_job_offers` | Lists RH's job offers | `JobOfferRepository::findByRh()` |
| `get_pipeline_stats` | Pipeline statistics | `ApplicationRepository::getStatusStats()` |
| `get_applications` | Lists applications | `ApplicationRepository::findByRh()` |
| `rank_candidates` | Ranks by score | Application + Interview data |
| `move_candidate_stage` | Move status (secure) | `ApplicationRepository::findOneByRh()` |
| `get_interviews` | Lists interviews | `InterviewRepository::findByRh()` |
| `schedule_interview` | Schedules interviews | `ApplicationRepository` |
| `get_available_slots` | Available slots | `InterviewRepository` |
| `get_candidates` | Lists all candidates | `CandidateRepository::findAll()` |

## Key Fix - Single Call Rule
```php
// In system prompt:
// 1. Only call ONE tool at a time
// 2. NEVER repeat the same tool call  
// 3. Use the results you get
```

## UI Features
- Data tables for applications, interviews, candidates
- Status badges (PENDING→En attente, HIRED→Recruté)
- Clean conversational responses
- Error handling

## To Test
- "donne-moi tous mes candidats" → Should call get_candidates ONCE
- "bonjour" → Conversational response
- "recrute [nom]" → Find → move to HIRED