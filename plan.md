# 🤖 Recruitment Agentic Chatbot - Implementation Plan

## 🎯 Goal
Integrate a production-grade AI recruiter capable of performing real-time actions (tool calling) and generating complex reports within the Symfony 6.4 environment.

## 🏗️ Architecture
1. **Intelligence Layer**: Groq API (via `GroqClient`)
2. **Cognition Loop**: Direct tool calling pattern (execute → result → call again)
3. **Tool Registry**: Modular tool system implementing `ToolInterface`
4. **Security**: Tools verify ownership via DbUser
5. **UI**: LinkedIn-style premium interface with real-time thought tracking

## 🛠️ Implemented Tools
| Domain | Tool Name | Description |
|--------|-----------|-------------|
| **Job Offer** | `get_job_offers` | Lists RH's job offers with IDs and status. |
| **Job Offer** | `get_pipeline_stats` | Generates a funnel report with conversion insights. |
| **Application** | `get_applications` | Lists applications with filters for status, job, or candidate. |
| **Application** | `rank_candidates` | Scores candidates for a job based on interview results. |
| **Application** | `move_candidate_stage` | Updates candidate status in the pipeline (with ownership check). |
| **Interview** | `get_interviews` | Lists interviews with filters for result, date, or application. |
| **Interview** | `schedule_interview` | Schedules interviews for candidates. |
| **Interview** | `get_available_slots` | Finds available interview time slots. |
| **Candidate** | `get_candidates` | Lists all registered candidates in the system. |

## 🚀 Deployment Steps
1. Configure `GROQ_API_KEY` in `.env`.
2. Access via `/rh/ai/chat`
3. Monitor sessions via logs

## ✅ Working Features
- Direct tool calling (Groq native pattern)
- Tool schema validation (JSON object format)
- Multi-step tasks (find candidate → move stage)
- Raw JSON cleanup in responses
- French language responses
