# Chatbot Feature Summary

## Overview
AI-powered recruitment assistant using **Google Gemma 4 31B** (via Gemini API `generativelanguage.googleapis.com/v1beta`) with agentic tool-calling capabilities via a **ReAct loop** (max 5 iterations, max 5 tools per request). Built on **Symfony 7.x** with PHP 8.x strict typing (`declare(strict_types=1)`).

## Architecture

### Core Components
- **`AgentOrchestrator`** (`src/AI/Core/AgentOrchestrator.php:33`) — main entry point via `process(ConversationContext)`:
  1. Loads conversation history from cache
  2. Trims messages (>10 keeps last 8 + summary placeholder)
  3. Fast-paths greetings (no LLM call)
  4. Classifies intent via keyword matching (**multi-intent** support — returns `IntentType[]`)
  5. Detects entity keywords (job_offer, application, interview) for entity-aware tool selection
  6. Selects scoped Entity Manager tools (max 5) based on intents + entities
  7. Runs agentic loop: LLM → tool calls → execute → feed results back → repeat
  8. Builds **descriptive plan steps** with action labels (e.g., "Lister les offres d'emploi")
  9. Aggregates tool results into `AgentResponse` with UI payload, pending changesets, plan steps, and metrics

- **`IntentRouter`** (`src/AI/Core/IntentRouter.php:18`) — keyword-based classification:
  - `classify()` returns **`IntentType[]`** (multiple intents per message supported)
  - `detectEntities()` extracts entity keywords and maps to Entity Manager tools:
    | Entity | Keywords | Tool |
    |--------|----------|------|
    | `job_offer` | offre, emploi, poste, recrutement, job | `manage_job_offers` |
    | `application` | candidat, candidature, cv, postulant | `manage_applications` |
    | `interview` | entretien, rendez-vous, interview, rdv | `manage_interviews` |
  - `selectTools()` combines intent-based tools (reporting) + entity manager tools (CRUD)

- **`ToolRegistry`** (`src/AI/Core/ToolRegistry.php:22`) — created via Symfony `!tagged_iterator app.ai_tool` factory pattern; lookup by name O(n) iteration

- **`ConversationMemory`** (`src/AI/Core/ConversationMemory.php`) — Symfony Cache-backed (`cache.app`):
  - Cache key: `chat_memory_{sessionId}`
  - TTL: 3600 seconds (1 hour)
  - Max messages: 10 (trims to last 8 on overflow)
  - Max content length: 2000 chars per message
  - Summarization: placeholder only (`"Previous {count} messages summarized."`)

- **`ChangesetManager`** (`src/AI/Core/ChangesetManager.php`) — human-in-the-loop for destructive actions:
  - `stage()`: creates `PendingChangeset` with random hex ID, stores tool args + result + user_id
  - `confirm()`: validates PENDING status, transitions to CONFIRMED, calls `applyChange()` (stub)
  - `revert()`: transitions to REVERTED
  - `getPending()`: filters by session ID and PENDING status
  - Status enum: `PENDING`, `CONFIRMED`, `REVERTED`, `EXPIRED`

- **`RecruitmentContextProvider`** (`src/AI/Core/RecruitmentContextProvider.php:17`) — builds French system prompt (max 350 tokens × 4 bytes = 1400 chars):
  - Base: role definition, capabilities, **cross-entity chaining instruction** (`"Pour effectuer des actions sur un enregistrement spécifique, commence par interroger le gestionnaire pour récupérer l'ID unique, puis passe cet ID dans l'appel suivant"`)
  - Intent-scoped context appended
  - **Entity context** auto-appended when entities detected (e.g., `"Entités détectées: offres d'emploi (manage_job_offers: liste, détail, création, modification, changement de statut, suppression)"`)

- **`ToolValidator`** (`src/AI/Core/ToolValidator.php:11`) — validates ToolCall before execution:
  - Blocks empty tool names
  - Rejects forbidden argument keys: `password`, `secret`, `token`, `api_key`

- **`GeminiClient`** (`src/AI/Core/GeminiClient.php:27`) — HTTP client for Gemini API:
  - Base URL: `https://generativelanguage.googleapis.com/v1beta/models/gemma-4-31b-it:generateContent`
  - Generation config: temperature 0.9, topP 0.95, topK 40, maxOutputTokens 2048
  - Retry logic: max 2 retries on 500 errors with 1s sleep
  - Builds tool definitions from `ToolInterface::getDefinition()`
  - Parses `functionCall` and `functionResponse` parts for tool calling

## Tools (6 total: 3 Entity Managers + 3 Reporting)

### Architecture: Entity-Centric Manager Pattern

Granular tools consolidated into **3 Entity Manager Tools**, each accepting a single structured payload (DTO) as parameter. All managers extend `AbstractEntityManagerTool` which provides:

- **Symfony Serializer** to map LLM JSON arguments → PHP 8.3 Read-Only DTO
- **Symfony Validator** to reject hallucinated/invalid data with structured error feedback
- **JSON Schema auto-generation** from DTO property types + `#[Assert]` attributes via reflection
- **ChangesetManager integration** for all mutation actions

### Abstract Base Tool
`src/AI/Tool/AbstractEntityManagerTool.php` — replaces 3 duplicated abstract bases:
```php
abstract class AbstractEntityManagerTool implements ToolInterface {
    // Auto-deserializes args into DTO via Serializer
    // Validates DTO with Symfony Validator  
    // Routes to handler based on $dto->action
    abstract protected function getDtoClass(): string;      // DTO class
    abstract protected function getToolName(): string;
    abstract protected function getToolDescription(): string;
    abstract protected function handle(object $dto, object $user): ToolOutput;
}
```

### Entity Manager Tools (6 → 3 consolidation)

| Tool | File | DTO | Actions | Output Types |
|------|------|-----|---------|--------------|
| `manage_job_offers` | `Tool/JobOffer/JobOfferManagerTool.php` | `JobOfferPayload` | list, search, view, create, update, change_status, delete | `job_offers_list`, `job_offer_card`, `job_offer_changeset` |
| `manage_applications` | `Tool/Application/ApplicationManagerTool.php` | `ApplicationPayload` | list, view, move, rank, create, delete | `applications_table`, `application_card`, `stage_move`, `candidate_grid` |
| `manage_interviews` | `Tool/Interview/InterviewManagerTool.php` | `InterviewPayload` | list, view, schedule, update, cancel, delete | `interviews_table`, `interview_card`, `interview_scheduled` |

**Read actions** (list, search, view, rank) execute DQL directly — no pending change.  
**Mutation actions** (create, update, change_status, move, schedule, cancel, delete) return `hasPendingChange: true` → staged via `ChangesetManager`.

### DTOs (Read-Only PHP 8.3 with Validation)

| DTO | Validation Attributes | Key Fields |
|-----|----------------------|------------|
| `JobOfferPayload` | `#[Assert\Choice]` (action, status, new_status), `#[Assert\Positive]`, `#[Assert\LessThan(200)]` | action, id, status, department, search, limit, title, description, location, employment_type, salary_min, salary_max, new_status |
| `ApplicationPayload` | `#[Assert\Choice]` (action: list/view/move/rank/create/delete, status: PENDING→REJECTED) | action, id, job_offer_id, candidate_name, email, new_status, status, limit |
| `InterviewPayload` | `#[Assert\Choice]` (action: list/view/schedule/update/cancel/delete, type: PHONE/VIDEO/IN_PERSON/TECHNICAL, result: SCHEDULED/COMPLETED/CANCELLED), `#[Assert\Positive]`, `#[Assert\LessThan(480)]` | action, id, application_id, from_date, to_date, type, date, duration, notes, meeting_link, location, result, limit |

### Validation Error Feedback
If the LLM provides invalid data (e.g., wrong status value), the tool returns:
```
Erreur de validation:
- new_status: 'INVALID' — La valeur choisie n'est pas valide. Valeurs possibles: PENDING, REVIEWING, INTERVIEW, OFFER, HIRED, REJECTED
```
This is fed back to the LLM for self-correction in the next ReAct iteration.

### Reporting Tools

| Tool | File | Parameters | Output Type |
|------|------|------------|-------------|
| `generate_report` | `Tool/Reporting/GenerateReportTool.php` | type (pipeline/performance), job_offer_id, from_date, to_date | `pipeline_report` |
| `export_pdf` | `Tool/Reporting/ExportPdfTool.php` | type (candidates/job_offers/interviews/pipeline_report/performance_report), title, job_offer_id, status, from_date, to_date | `pdf_export` |
| `generate_chart` | `Tool/Reporting/GenerateChartTool.php` | chart_type (bar/line/pie/doughnut/radar), data_type (pipeline/applications_by_offer/applications_over_time/interview_results/hiring_funnel), title, job_offer_id, from_date, to_date | `chart` |

### Tool Output Structure
```php
ToolOutput {
    llmSummary: string        // summary text fed back to LLM for next iteration
    uiPayload: array          // structured data for frontend rendering
    hasPendingChange: bool    // flags changeset staging
    pendingChangeset: ?PendingChangeset
}
```

## Data Flow

```
Frontend (chat.html.twig)
    ↓ POST /rh/ai/chat {message, session_id}
ChatbotController
    ↓ creates ConversationContext
AgentOrchestrator::process()
    ↓ load history
ConversationMemory::load(sessionId)
    ↓ classify intent + detect entities (multi-intent)
IntentRouter::classify(messages) → IntentType[]
IntentRouter::detectEntities(messages) → [job_offer, application, interview]
    ↓ select Entity Manager tools based on detected entities + intents
IntentRouter::selectTools(intents, registry, entities)
    ↓ build system prompt (with cross-entity chaining instructions)
RecruitmentContextProvider::buildSystemPrompt(user, intentType, entities)
    ↓ agentic loop (max 5 iterations)
    │   GeminiClient::chat(request)
    │   ↓ parse functionCall parts
    │   ToolValidator::validate(toolCall, user)
    │   ↓ execute — Entity Manager Tool
    │   AbstractEntityManagerTool::execute(args, user)
    │       │
    │       ├── 1. Deserialize JSON → DTO (Symfony Serializer)
    │       ├── 2. Validate DTO (Symfony Validator) → errors → return feedback to LLM
    │       ├── 3. Route by $dto->action → handle(dto, user)
    │       │
    │       ├── Read: DQL query → ToolOutput {llmSummary, uiPayload}
    │       └── Mutation: Check entity exists → ToolOutput {llmSummary, uiPayload, hasPendingChange: true}
    │   ↓ if hasPendingChange (always, no null check)
    │   ChangesetManager::stage(toolCall, result, user) → PendingChangeset
    │   ↓ feed tool result back as functionResponse
    │   messages[] = tool call + tool response
    │   ↓ build descriptive plan steps with action labels
    │   "Lister les offres d'emploi", "Déplacer la candidature #42"
    ↓ aggregate results
AgentResponse { message, uiPayload, pendingChangesets, toolCalls, plan, candidatesAnalyzed, interviewsPlanned }
    ↓ JSON response
Frontend renders tool cards, charts, PDF download links, changeset confirmations
```

## Key Capabilities
- **Entity-Centric CRUD:** 3 Entity Manager Tools covering full CRUD for JobOffers, Applications, and Interviews — single tool per entity replaces 6 granular tools
- **DTO Validation:** PHP 8.3 Read-Only DTOs with Symfony `#[Assert]` attributes; invalid LLM data returns structured error feedback for self-correction
- **JSON Schema auto-generation:** `AbstractEntityManagerTool::getDefinition()` builds parameter schema from DTO reflection — no manual `getParameters()`/`getRequired()` per tool
- **Cross-Entity Orchestration:** Multi-intent detection + entity keyword matching loads multiple managers per request; ID-forwarding instruction in system prompt enables chaining (e.g., find job → list candidates)
- **Job offer management:** list, search (LIKE), view detail, create, update, change status (DRAFT/OPEN/CLOSED/ON_HOLD), delete — all actions in single tool
- **Application management:** list by offer/status, view detail, pipeline stage moves (PENDING→REVIEWING→INTERVIEW→OFFER→HIRED→REJECTED), candidate ranking (random scores 60-98), create, delete
- **Interview management:** list by date range/status, view detail, schedule (PHONE/VIDEO/IN_PERSON/TECHNICAL), update (reschedule, change notes/link/location), cancel, delete
- **Descriptive plan steps:** AgentOrchestrator extracts action labels from tool call args (e.g., "Lister les offres d'emploi", "Déplacer la candidature #42")
- **Report generation:** pipeline distribution (6 stages), performance metrics (conversion rate, hired/rejected counts)
- **PDF export:** 5 export types with styled HTML templates (tables, stat boxes, pipeline bars with color-coded segments)
- **Chart generation:** 5 chart types × 5 data sources with pre-configured color palettes
- **Changeset pattern:** mutations staged as `PendingChangeset` (random 16-char hex ID) awaiting user confirm/revert — fixed staging logic (no null-guard bug)

## Security
- **SQL injection:** all queries via Doctrine QueryBuilder with parameterized bindings (`:status`, `:jobId`, etc.)
- **Argument sanitization:** `ToolValidator` blocks `password`, `secret`, `token`, `api_key` keys
- **System prompt guard:** no SQL generation instructions in system prompt
- **Human confirmation:** mutations (stage moves, interview scheduling) return `hasPendingChange: true` and require explicit confirm/revert via `ChangesetManager`
- **Soft deletes:** all queries filter `isDeleted = false`
- **User authentication:** controller requires authenticated user (`$this->getUser()` returns 401 if null)
- **Error isolation:** controller catches all `\Throwable`, logs with request ID, returns safe error message

## Frontend
- **Template:** `templates/ai/chat.html.twig` (5000+ lines)
- **Macros:** `templates/ai/components/_chat_macros.html.twig`
- Features:
  - Dark/light mode toggle
  - Quick action chips for common queries
  - Tool execution display with status badges
  - Session statistics (candidates analyzed, interviews planned)
  - Chart.js visualization for report data
  - PDF download links (`/uploads/reports/{filename}`)
  - Changeset confirmation UI (confirm/revert buttons)
  - Plan step progress indicator

## API Endpoints

| Method | Route | Handler | Description |
|--------|-------|---------|-------------|
| GET | `/rh/ai/interface` | `ChatbotController::interface()` | Render chat UI |
| POST | `/rh/ai/chat` | `ChatbotController::chat()` | Process user message |
| POST | `/rh/ai/chat/clear` | `ChatbotController::clear()` | Clear session memory |

### Chat Request Format
```json
{
  "message": "Show me all pending applications",
  "session_id": "abc123"
}
```

### Chat Response Format
```json
{
  "message": "3 candidature(s) trouvée(s)...",
  "ui_payload": [...],
  "pending_changesets": [{"id": "...", "tool": "...", "action": "..."}],
  "tool_calls": [{"tool_name": "...", "status": "done", "args": {...}}],
  "active_job": {"title": "...", "location": "..."},
  "candidates": [...],
  "interviews": [...],
  "candidates_analyzed": 3,
  "interviews_planned": 1,
  "plan": ["Lister les offres d'emploi", "Déplacer la candidature #42"],
  "completed_steps": 1,
  "request_id": "..."
}
```

## Configuration

### Environment Variables (`config/services.yaml`)
| Variable | Purpose |
|----------|---------|
| `GEMINI_API_KEY` | Google Gemini API authentication |
| `GROQ_API_KEY` | Groq API (legacy/fallback) |
| `GROQ_API` | Groq API endpoint |
| `NVIDIA_API_KEY` | NVIDIA API (legacy/fallback) |

### Service Wiring
```yaml
_instanceof:
    App\AI\Contract\ToolInterface:
        tags: ['app.ai_tool']

App\AI\Contract\LlmClientInterface: '@App\AI\Core\GeminiClient'

App\AI\Core\ToolRegistry:
    factory: ['App\AI\Core\ToolRegistry', 'createFromTaggedTools']
    arguments:
        - !tagged_iterator app.ai_tool

App\AI\Core\ConversationMemory:
    arguments:
        - '@cache.app'
```

## File Locations

### Core AI
| File | Lines | Purpose |
|------|-------|---------|
| `src/AI/Core/AgentOrchestrator.php` | ~340 | Main agent loop, multi-intent + entity detection, descriptive plan steps, greeting fast-path, result aggregation |
| `src/AI/Core/IntentRouter.php` | ~150 | Keyword-based intent classification (returns `IntentType[]`), entity keyword detection, entity manager tool selection |
| `src/AI/Core/ToolRegistry.php` | 58 | Tagged service collection, lookup by name |
| `src/AI/Core/ConversationMemory.php` | 96 | Cache-backed session memory (1hr TTL, 10-msg window) |
| `src/AI/Core/ChangesetManager.php` | 90 | Pending changeset lifecycle (stage/confirm/revert) + `stageFromChangeset()` |
| `src/AI/Core/RecruitmentContextProvider.php` | ~60 | French system prompt builder with intent context + entity context + cross-entity chaining instruction |
| `src/AI/Core/ToolValidator.php` | 28 | Argument validation (forbidden keys) |
| `src/AI/Core/GeminiClient.php` | 217 | Gemini API HTTP client with retry logic |

### Tools
| File | Lines | Tool Name | Output Types |
|------|-------|-----------|-------------|
| `src/AI/Tool/AbstractEntityManagerTool.php` | ~220 | (abstract base) | — |
| `src/AI/Tool/JobOffer/JobOfferManagerTool.php` | ~250 | `manage_job_offers` | `job_offers_list`, `job_offer_card`, `job_offer_changeset` |
| `src/AI/Tool/Application/ApplicationManagerTool.php` | ~260 | `manage_applications` | `applications_table`, `application_card`, `stage_move`, `candidate_grid` |
| `src/AI/Tool/Interview/InterviewManagerTool.php` | ~260 | `manage_interviews` | `interviews_table`, `interview_card`, `interview_scheduled` |
| `src/AI/Tool/Reporting/GenerateReportTool.php` | ~100 | `generate_report` | `pipeline_report` |
| `src/AI/Tool/Reporting/ExportPdfTool.php` | 436 | `export_pdf` | `pdf_export` |
| `src/AI/Tool/Reporting/GenerateChartTool.php` | 280 | `generate_chart` | `chart` |

### Domain Layer
| File | Purpose |
|------|---------|
| `src/AI/Domain/Enum/IntentType.php` | 5 intent types: GREETING, DATA_QUERY, MUTATION, SCHEDULE, REPORT |
| `src/AI/Domain/Enum/ChangesetStatus.php` | 4 statuses: PENDING, CONFIRMED, REVERTED, EXPIRED |
| `src/AI/Domain/DTO/JobOfferPayload.php` | Read-only DTO for `manage_job_offers` with `#[Assert\Choice]`, `#[Assert\Positive]` |
| `src/AI/Domain/DTO/ApplicationPayload.php` | Read-only DTO for `manage_applications` with status validation |
| `src/AI/Domain/DTO/InterviewPayload.php` | Read-only DTO for `manage_interviews` with type/result validation |
| `src/AI/Domain/ValueObject/ToolOutput.php` | Tool execution result (llmSummary, uiPayload, hasPendingChange) |
| `src/AI/Domain/ValueObject/PendingChangeset.php` | Immutable changeset with confirm/revert/expire transitions |

### Infrastructure Layer
| File | Purpose |
|------|---------|
| `src/AI/Infrastructure/AgentResponse.php` | Orchestrator output (message, uiPayload, toolCalls, plan, metrics) |
| `src/AI/Infrastructure/ChatMessage.php` | Message value object (role, content, toolCallId, toolCallName, toolCallArgs, toolResponse) |
| `src/AI/Infrastructure/ChatRequest.php` | LLM request (messages, systemPrompt, tools, maxTools) |
| `src/AI/Infrastructure/ChatResponse.php` | LLM response (content, toolCalls) |
| `src/AI/Infrastructure/ToolCall.php` | Parsed tool call (id, name, arguments) |
| `src/AI/Infrastructure/ConversationContext.php` | Input context (messages, user, sessionId) |
| `src/AI/Infrastructure/ToolDefinition.php` | Tool schema definition |

### Contracts
| File | Purpose |
|------|---------|
| `src/AI/Contract/ToolInterface.php` | Tool contract: getName(), getDefinition(), execute() |
| `src/AI/Contract/ToolRegistryInterface.php` | Registry contract: get(), getSubset(), all() |
| `src/AI/Contract/LlmClientInterface.php` | LLM client contract: chat() |
| `src/AI/Contract/ChangesetStorageInterface.php` | Changeset persistence contract |

### Controller & Frontend
| File | Purpose |
|------|---------|
| `src/Controller/AI/ChatbotController.php` | 3 routes: interface (GET), chat (POST), clear (POST) |
| `templates/ai/chat.html.twig` | Main chat interface (5000+ lines) |
| `templates/ai/components/_chat_macros.html.twig` | Reusable Twig macros |

### Configuration
| File | Purpose |
|------|---------|
| `config/services.yaml` | Tool auto-tagging, GeminiClient wiring, ToolRegistry factory, ConversationMemory cache |

### Tests
| Path | Status |
|------|--------|
| `tests/AI/` | Directory exists but no test files found — tests not yet implemented |
