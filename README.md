<p align="center">
  <img src="public/logo.png" alt="HR Flow Logo" width="200" />
</p>

<h1 align="center">HR Flow</h1>

<p align="center">
  Plateforme RH complète — recrutement, congés, paie, formations, projets & assistant IA
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/Symfony-6.4-000000?logo=symfony&logoColor=white" />
  <img src="https://img.shields.io/badge/TailwindCSS-3.4-38B2AC?logo=tailwindcss&logoColor=white" />
  <img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" />
  <img src="https://img.shields.io/badge/Déployé sur-Render-46E3B7?logo=render&logoColor=white" />
</p>

---

## À propos

**HR Flow** est une application web de gestion des ressources humaines développée dans le cadre du **PIDEV — ESPRIT 3A4 (2025/2026)**. Elle centralise l'ensemble des processus RH d'une entreprise au sein d'une seule plateforme.

🌐 **Production :** [https://www.hrflow.tech](https://www.hrflow.tech)

---

## Fonctionnalités

| Module | Description |
|---|---|
| 🔐 **Authentification** | Connexion, OAuth, 2FA TOTP, gestion des rôles (Admin / RH / Employé / Candidat) |
| 👥 **Recrutement** | Offres d'emploi, candidatures, scoring IA, planification d'entretiens |
| 📅 **Congés** | Soldes par type, workflow d'approbation, calendrier, notifications email |
| 🎓 **Formations** | Catalogue, sessions, présences, feedback, certificats PDF |
| 💰 **Paie** | Fiches de paie, primes, déductions, export PDF |
| 📋 **Projets** | Kanban, jalons, collaborateurs, alertes par email (cron) |
| 💬 **Chat** | Messagerie interne par projet via Matrix.org |
| 🤖 **Assistant IA** | Agent conversationnel multi-LLM avec tool-use (Gemini / Groq / NVIDIA / OpenRouter) |
| 📊 **Dashboards** | Tableaux de bord Admin / RH / Employé avec graphiques et exports |
| ♿ **Accessibilité** | Lecteur d'écran, zoom, contraste élevé, navigation clavier |

---

## Stack Technique

**Backend**
- PHP 8.2+, Symfony 6.4 LTS
- Doctrine ORM 3 + Doctrine Migrations
- PostgreSQL 16 (dev) / MySQL 8 (production Aiven)

**Frontend**
- Twig 3, Stimulus, Turbo
- TailwindCSS 3.4 via Symfony Asset Mapper
- Chart.js

**IA & APIs**
- Google Gemini, Groq, NVIDIA, OpenRouter, Hugging Face
- OpenWeather, NewsAPI, Matrix.org, QuickChart

**Infrastructure**
- Docker + Docker Compose
- Déployé sur Render (IaC via `render.yaml`)
- wkhtmltopdf + dompdf (génération PDF)

---

## Démarrage rapide

### Prérequis

- PHP 8.2+
- Composer 2
- Docker Desktop
- Symfony CLI *(recommandé)*

### Installation

```bash
# 1. Cloner le projet
git clone https://github.com/Bechir-Lahoueg/Esprit-PIDEV-WEB--3A4-HrFlow.git
cd Esprit-PIDEV-WEB--3A4-HrFlow

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Éditer .env.local : DATABASE_URL, MAILER_DSN, clés API...

# 4. Démarrer la base de données
docker compose up -d database

# 5. Exécuter les migrations
php bin/console doctrine:migrations:migrate

# 6. Compiler les assets
php bin/console tailwind:build
php bin/console asset-map:compile

# 7. Lancer le serveur
symfony serve -d
```

Application disponible sur **http://127.0.0.1:8000**

### Avec Docker (tout-en-un)

```bash
docker compose up -d --build
```

---

## Variables d'environnement

| Variable | Description |
|---|---|
| `APP_SECRET` | Clé secrète Symfony (`php -r "echo bin2hex(random_bytes(16));"`) |
| `DATABASE_URL` | DSN de connexion à la base de données |
| `MAILER_DSN` | Serveur SMTP pour les emails |
| `GEMINI_API_KEY` / `GROQ_API_KEY` | Fournisseur LLM pour l'assistant IA |
| `MATRIX_ACCESS_TOKEN` | Token bot pour le chat interne |
| `OPENWEATHER_API_KEY` | API météo (widgets dashboard) |
| `NEWS_API_KEY` | API actualités (widgets dashboard) |
| `CORS_ALLOW_ORIGINS` | Origines CORS autorisées (CSV) |

---

## Structure du projet

```
src/
├── AI/             Agent IA (Core, Domain, Infrastructure, Tools)
├── Controller/     Contrôleurs par domaine
├── Entity/         Entités Doctrine (Formation, Paie, Projet, Recrutement, RH...)
├── Form/           Formulaires Symfony
├── Repository/     Repositories Doctrine
├── Security/       Authenticators, Voters
└── Service/        Logique métier

templates/          Vues Twig
assets/             JS Stimulus + CSS Tailwind
migrations/         Migrations Doctrine
docker/             Dockerfiles, nginx, scripts de démarrage
```

---

## Commandes utiles

```bash
# Migrations
php bin/console doctrine:migrations:migrate

# Vider le cache
php bin/console cache:clear

# Alertes tâches projet (cron quotidien 08:00)
php bin/console app:projects:send-task-reminders
php bin/console app:projects:send-task-reminders --dry-run

# Build Tailwind en mode watch
php bin/console tailwind:build --watch
```

### Planifier les alertes tâches

**Linux/macOS :**
```cron
0 8 * * * cd /chemin/vers/projet && php bin/console app:projects:send-task-reminders >> var/log/cron.log 2>&1
```

**Windows (Task Scheduler) :**
```powershell
schtasks /Create /SC DAILY /ST 08:00 /TN "HrFlow Task Alerts" /TR "php C:\chemin\vers\projet\bin\console app:projects:send-task-reminders" /F
```

---

## Tests

```bash
# Lancer les tests
php bin/phpunit

# Analyse statique
vendor/bin/phpstan analyse

# Lint Twig / YAML
php bin/console lint:twig templates/
php bin/console lint:yaml config/
```

---

## Déploiement

Le projet est déployé automatiquement sur **Render** via `render.yaml` à chaque push sur `main`.

Toutes les variables sensibles sont configurées dans le dashboard Render (`sync: false`). Aucun secret n'est commité dans le dépôt.

```bash
# Build Docker manuel
docker build -t hrflow .
docker run -d -p 10000:10000 --env-file .env hrflow
```

---

## Équipe

Projet réalisé en équipe dans le cadre du **PIDEV — ESPRIT 3A4 (2025/2026)**.

---

<p align="center">
  <sub>ESPRIT — École Supérieure Privée d'Ingénierie et de Technologies · Tunis · 2025/2026</sub>
</p>
