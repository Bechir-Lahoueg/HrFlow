# HR-Flow — Workforce Management Platform

> A comprehensive Java-based Human Resources management platform built with JavaFX, featuring modular architecture for managing employees, recruitment, training, compensation, leave, and AI-powered reporting.

---

## Overview

HR-Flow is a multi-module desktop application developed to digitalize and streamline core HR operations within an organization. It provides role-based dashboards for **Admins**, **HR Managers**, and **Employees**, each with tailored views and workflows. The platform integrates an AI-powered report generation engine capable of producing structured analytics reports from natural language prompts.

---

## Features

### User Management
- Multi-role authentication: Admin, HR Manager, Employee
- Role-based access control and session management
- Profile management and secure login

### Recruitment
- Job posting and candidate application tracking
- Interview scheduling and evaluation
- AI-powered recruitment analytics reports

### Training & Development
- Course creation and enrollment management
- Employee training tracking and certification
- Progress monitoring dashboards

### Compensation & Payroll
- Salary structure management
- Benefits administration and payroll records
- Compensation analytics

### Leave Management
- Leave request and approval workflow
- Leave balance tracking with policy enforcement
- Real-time status notifications

### Employee Relations
- Performance reviews and feedback system
- Engagement surveys and conflict tracking
- Project and task assignment

### AI Report Generator
- Natural language report generation via LLM
- Intelligent SQL query planning and execution
- Dynamic charts and tables (Bar, Pie, Line, Area)
- Streaming animations and real-time progress feedback

---

## Tech Stack

### Frontend

| Technology | Version | Purpose |
|---|---|---|
| JavaFX | 21.0.1 | Rich desktop UI framework |
| FXML | — | Declarative UI markup |
| MaterialFX | 11.17.0 | Material Design components |
| AtlantaFX | — | Modern JavaFX theming |
| CSS | — | Custom styling and animations |

### Backend

| Technology | Version | Purpose |
|---|---|---|
| Java | 17 | Core application language |
| Maven | 3.6+ | Build automation and dependency management |
| MySQL | 8.0 | Relational database |
| Gemini / Groq API | — | LLM integration for AI features |
| Llama 3.3 70B | — | Natural language processing model |

---

## Architecture

The platform follows a **multi-module Maven architecture** with clean separation of concerns and an MVC pattern for all UI components.

```
Workforce-Platform/
├── AppUi/                    # Main JavaFX UI Application
├── GestionUtilisateur/       # User & Authentication Module
├── GestionRecrutement/       # Recruitment + AI Reports Module
├── Gestionformation/         # Training & Development Module
├── GestionRemuneration/      # Compensation & Payroll Module
├── GestionDesConges/         # Leave Management Module
└── GestionRelationEmployees/ # Employee Relations Module
```

### Design Principles
- **Modular design** — each HR function is an independent Maven module
- **MVC pattern** — clear separation between views (FXML), controllers (Java), and models
- **Service layer** — business logic encapsulated in dedicated service classes
- **Repository pattern** — data access abstraction for all database operations

### Key AI Components

| Component | Role |
|---|---|
| ReportAgent.java | Orchestrates end-to-end AI report generation |
| SqlPlannerLlm.java | Generates optimized SQL queries via AI |
| ReportLlm.java | Converts raw data into narrative reports |
| DataAnalysisAgent.java | Produces charts and tables from query results |

---

## Contributors

| Name | Branch | Role |
|---|---|---|
| Bechir Lahoueg | `bechir` | UI/UX — Dashboards, Employee & RH modules |
| Ameen | `ameen` | Backend services |
| Farah | `farah` | Recruitment & AI integration |
| Maissa | `maissa` | Training & formation module |
| Sarra | `sarra` | Leave management module |

---

## Academic Context

This project was developed as part of the **PIDEV 3A** academic module at **ESPRIT School of Engineering** (2025–2026). It serves as the final integration project for 3rd-year engineering students, applying skills in Java, software architecture, database design, and AI integration in a real-world HR platform scenario.

- **Institution**: ESPRIT — École Supérieure Privée d'Ingénierie et de Technologies
- **Program**: Software Engineering — 3rd Year
- **Project**: PIDEV 3A4 — HR-Flow

---

## Getting Started

### Prerequisites
- **JDK 17** or higher
- **Maven 3.6+**
- **MySQL 8.0+**
- **4 GB+ RAM** recommended

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/Bechir-Lahoueg/Esprit-PIDEV-3A4-HrFlow.git
cd Esprit-PIDEV-3A4-HrFlow
```

**2. Set up the database**
```sql
CREATE DATABASE workforce_platform;
```

**3. Configure API key (for AI features)**
```bash
set GEMINI_API_KEY=your_api_key_here
```

**4. Build the project**
```bash
mvn clean compile
```

**5. Run the application**
```bash
cd AppUi
mvn javafx:run
# or on Windows:
run.bat
```

### Default Login Credentials

| Role | Username | Password |
|---|---|---|
| Admin | admin | admin123 |
| HR Manager | hr | hr123 |
| Employee | employee | emp123 |

---

## Acknowledgments

- **JavaFX** — Desktop UI framework (https://openjfx.io)
- **MaterialFX** — Material Design components for JavaFX
- **AtlantaFX** — Modern JavaFX themes
- **Groq / Google Gemini** — LLM APIs powering the AI report engine
- **MySQL Connector/J** — Java database connectivity
- **ESPRIT School of Engineering** — Academic framework and guidance

---

*Built with love by the HR-Flow team · ESPRIT PIDEV 3A4 · 2025–2026*
