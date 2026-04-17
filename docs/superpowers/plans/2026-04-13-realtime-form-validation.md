# Real-Time Form Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une validation visuelle en temps réel (CSS pur, sans JS) sur la page de login et le formulaire d'ajout d'employé du dashboard RH.

**Architecture:** Deux nouvelles classes CSS (`.validated-field` et `.field-error`) ajoutées dans `app.css` via `@layer components`. Chaque `<input>` dans les deux formulaires reçoit la classe `.validated-field` + un `<span class="field-error">` adjacent. Les pseudo-classes CSS `:not(:placeholder-shown):invalid` et `:not(:placeholder-shown):valid` pilotent le rendu en temps réel.

**Tech Stack:** Tailwind CSS (via `@layer components`), Twig, HTML5 attributes (`required`, `type`, `min`, `max`, `placeholder`)

---

## Fichiers modifiés

| Fichier | Rôle |
|---------|------|
| `assets/styles/app.css` | Ajout des classes `.validated-field` et `.field-error` |
| `templates/Auth/login.html.twig` | Ajout `validated-field` sur les inputs + `<span class="field-error">` |
| `templates/DashboardHr/employees.html.twig` | Idem + vérification `min`/`max` sur le champ âge |

---

## Task 1: Ajouter les classes CSS de validation dans app.css

**Files:**
- Modify: `assets/styles/app.css` (à la fin du fichier, après la section `/* ── Responsive calendar ──*/`)

- [ ] **Step 1: Ajouter le bloc CSS de validation**

Ouvrir `assets/styles/app.css` et ajouter le bloc suivant **à la fin du fichier** :

```css
/* ── Real-time form validation (CSS-only) ── */
.validated-field {
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

/* Champ invalide après interaction (placeholder caché = l'utilisateur a tapé) */
.validated-field:not(:placeholder-shown):invalid {
  border-color: #ef4444 !important;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

/* Champ valide après interaction */
.validated-field:not(:placeholder-shown):valid {
  border-color: #22c55e !important;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
}

/* Dark mode */
:is(.dark .validated-field:not(:placeholder-shown):invalid) {
  border-color: #f87171 !important;
  box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.12);
}

:is(.dark .validated-field:not(:placeholder-shown):valid) {
  border-color: #4ade80 !important;
  box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.12);
}

/* Message d'erreur : caché par défaut */
.field-error {
  display: none;
  font-size: 0.7rem;
  color: #ef4444;
  margin-top: 0.25rem;
  animation: fadeIn 0.15s ease-out;
}

/* Message visible quand l'input précédent est invalide et touché */
.validated-field:not(:placeholder-shown):invalid + .field-error {
  display: block;
}

/* Dark mode message */
:is(.dark) .field-error {
  color: #f87171;
}
```

- [ ] **Step 2: Vérifier que le fichier est bien sauvegardé**

Vérifier visuellement que le bloc est ajouté à la fin de `assets/styles/app.css` après la ligne `}` qui clôt `@media (max-width: 768px)`.

- [ ] **Step 3: Commit**

```bash
git add assets/styles/app.css
git commit -m "feat: add CSS-only real-time validation classes to app.css"
```

---

## Task 2: Appliquer la validation sur la page de login

**Files:**
- Modify: `templates/Auth/login.html.twig` (lignes 85-96)

**Contexte :** Le formulaire login est à la ligne 82. Il a déjà `novalidate`. Il contient deux champs : `identifier` (email/username) et `password`.

- [ ] **Step 1: Modifier le champ identifier (email)**

Remplacer le champ `identifier` existant (ligne 85-88) par :

```twig
<input id="identifier" name="identifier" type="email"
       placeholder=" "
       value="{{ last_username|default('') }}"
       required
       class="validated-field w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
<span class="field-error">Veuillez entrer une adresse email valide</span>
```

> Note: `type="text"` est remplacé par `type="email"` pour activer la validation CSS native. Le `placeholder=" "` (espace) est requis pour que `:not(:placeholder-shown)` fonctionne quand le champ est vide.

- [ ] **Step 2: Modifier le champ password**

Remplacer le champ `password` existant (lignes 93-96) par :

```twig
<input id="password" name="password" type="password"
       placeholder=" "
       required
       class="validated-field w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 outline-none transition-all focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20">
<span class="field-error">Ce champ est obligatoire</span>
```

- [ ] **Step 3: Vérifier que `novalidate` est bien présent sur la `<form>`**

La ligne 82 doit contenir `novalidate` :
```twig
<form method="post" action="{{ path('app_login') }}" class="space-y-5" novalidate>
```
C'est déjà le cas dans le fichier actuel — ne rien modifier.

- [ ] **Step 4: Commit**

```bash
git add templates/Auth/login.html.twig
git commit -m "feat: add real-time CSS validation to login form"
```

---

## Task 3: Appliquer la validation sur le formulaire employé (dashboard RH)

**Files:**
- Modify: `templates/DashboardHr/employees.html.twig` (lignes 88-143)

**Contexte :** Le formulaire est à la ligne 88. Il a déjà `novalidate`. Il contient 6 champs : `first_name`, `last_name`, `job_title`, `age`, `email`, `password`.

- [ ] **Step 1: Ajouter `novalidate` sur la form si absent**

Vérifier la ligne 88. Elle doit être :
```twig
<form method="post" action="{{ path('app_rh_employees') }}" class="space-y-4" novalidate>
```
C'est déjà le cas — ne rien modifier.

- [ ] **Step 2: Modifier le champ first_name (ligne 94-97)**

Remplacer :
```twig
<input id="first_name" name="first_name" type="text" required
       class="input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600"
       placeholder="Sara" />
```
Par :
```twig
<input id="first_name" name="first_name" type="text" required
       placeholder="Sara"
       class="validated-field input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600" />
<span class="field-error">Ce champ est obligatoire</span>
```

- [ ] **Step 3: Modifier le champ last_name (ligne 100-103)**

Remplacer :
```twig
<input id="last_name" name="last_name" type="text" required
       class="input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600"
       placeholder="Ben Ali" />
```
Par :
```twig
<input id="last_name" name="last_name" type="text" required
       placeholder="Ben Ali"
       class="validated-field input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600" />
<span class="field-error">Ce champ est obligatoire</span>
```

- [ ] **Step 4: Modifier le champ job_title (ligne 109-112)**

Remplacer :
```twig
<input id="job_title" name="job_title" type="text" required
       class="input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600"
       placeholder="Developpeur Frontend" />
```
Par :
```twig
<input id="job_title" name="job_title" type="text" required
       placeholder="Developpeur Frontend"
       class="validated-field input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600" />
<span class="field-error">Ce champ est obligatoire</span>
```

- [ ] **Step 5: Modifier le champ age (ligne 115-118)**

Remplacer :
```twig
<input id="age" name="age" type="number" min="18" max="100" required
       class="input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600 font-mono-num"
       placeholder="29" />
```
Par :
```twig
<input id="age" name="age" type="number" min="18" max="100" required
       placeholder="29"
       class="validated-field input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600 font-mono-num" />
<span class="field-error">L'age doit etre entre 18 et 100 ans</span>
```

- [ ] **Step 6: Modifier le champ email (ligne 123-126)**

Remplacer :
```twig
<input id="email" name="email" type="email" required
       class="input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600"
       placeholder="employe@entreprise.com" />
```
Par :
```twig
<input id="email" name="email" type="email" required
       placeholder="employe@entreprise.com"
       class="validated-field input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600" />
<span class="field-error">Veuillez entrer une adresse email valide</span>
```

- [ ] **Step 7: Modifier le champ password (ligne 130-133)**

Remplacer :
```twig
<input id="password" name="password" type="password" required
       class="input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600"
       placeholder="Mot de passe securise" />
```
Par :
```twig
<input id="password" name="password" type="password" required
       placeholder="Mot de passe securise"
       class="validated-field input-refined w-full rounded-xl bg-zinc-50 dark:bg-zinc-800 px-3.5 py-2.5 text-sm text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-600" />
<span class="field-error">Ce champ est obligatoire</span>
```

- [ ] **Step 8: Commit**

```bash
git add templates/DashboardHr/employees.html.twig
git commit -m "feat: add real-time CSS validation to HR employee form"
```

---

## Task 4: Vérification visuelle finale

- [ ] **Step 1: Lancer le serveur Symfony**

```bash
symfony serve
```
ou
```bash
php -S localhost:8000 -t public/
```

- [ ] **Step 2: Tester la page de login**

Ouvrir `http://localhost:8000/login` (ou l'URL configurée).

Checklist :
- [ ] Taper une valeur invalide dans le champ email (ex: `abc`) → bordure rouge + message "Veuillez entrer une adresse email valide"
- [ ] Compléter avec un email valide (ex: `abc@test.com`) → bordure verte, message disparaît
- [ ] Laisser le champ password vide et cliquer dedans puis sortir → champ reste neutre (pas de rouge sur un champ non touché)
- [ ] Taper quelque chose dans password → bordure verte immédiatement
- [ ] Effacer le password → bordure rouge + message "Ce champ est obligatoire"

- [ ] **Step 3: Tester le formulaire employé dashboard RH**

Ouvrir la page employés RH.

Checklist :
- [ ] Taper dans prénom → valide dès qu'il y a du texte (bordure verte)
- [ ] Effacer le prénom → bordure rouge + message
- [ ] Taper `15` dans âge → bordure rouge (< 18)
- [ ] Taper `25` dans âge → bordure verte
- [ ] Taper `150` dans âge → bordure rouge (> 100)
- [ ] Email invalide → rouge, email valide → vert
- [ ] Tester en dark mode (toggle) → couleurs adaptées (rouge/vert plus clairs)

- [ ] **Step 4: Commit final si ajustements nécessaires**

```bash
git add -p
git commit -m "fix: adjust real-time validation styles after visual review"
```
