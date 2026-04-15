# Design : Validation de formulaire en temps réel (CSS pur)

**Date :** 2026-04-13  
**Périmètre :** Page de login + Dashboard RH (formulaire Employés)  
**Approche :** CSS pur — pseudo-classes `:valid` / `:invalid` / `:placeholder-shown`

---

## Objectif

Ajouter une validation visuelle en temps réel sur les formulaires sans JavaScript et sans validation HTML native visible. L'utilisateur voit immédiatement un retour visuel (bordure colorée + message texte) pendant qu'il remplit les champs.

---

## Technique

### Principe CSS

On exploite trois pseudo-classes CSS combinées :

- `:invalid` — champ dont la valeur ne respecte pas les contraintes HTML (`required`, `type`, `pattern`, `min`, `max`)
- `:valid` — champ dont la valeur respecte toutes les contraintes
- `:not(:placeholder-shown)` — champ où le placeholder n'est pas visible (l'utilisateur a tapé quelque chose)

La combinaison `input:not(:placeholder-shown):invalid` permet d'afficher l'erreur **uniquement après que l'utilisateur a interagi** avec le champ — un champ vide non touché reste neutre.

### Astuce placeholder

Chaque champ doit avoir un `placeholder` non vide (même un espace `" "`) pour que `:placeholder-shown` fonctionne correctement :

```html
<input type="email" placeholder=" " required />
```

### Affichage des messages d'erreur

Les messages d'erreur sont des `<span>` placés **immédiatement après** chaque `<input>`. Le sélecteur CSS `+` (adjacent sibling) les rend visibles uniquement quand l'input est invalide :

```css
input:not(:placeholder-shown):invalid + span.field-error {
  display: block;
}
input:not(:placeholder-shown):valid + span.field-error {
  display: none;
}
```

---

## Comportement visuel

| État | Bordure | Message |
|------|---------|---------|
| Champ non touché (vide, placeholder visible) | Défaut (gris) | Caché |
| En cours de frappe, invalide | Rouge (`border-red-500`) | Visible en rouge |
| En cours de frappe, valide | Verte (`border-green-500`) | Caché |
| Blur, invalide | Rouge (`border-red-500`) | Visible en rouge |
| Blur, valide | Verte (`border-green-500`) | Caché |

**Transition :** `transition-colors duration-150` sur les inputs pour un changement de bordure fluide.

---

## Règles de validation par champ

### Page Login (`templates/Auth/login.html.twig`)

| Champ | Attributs HTML | Message d'erreur |
|-------|---------------|------------------|
| Email | `type="email"` `required` `placeholder=" "` | "Veuillez entrer une adresse email valide" |
| Mot de passe | `type="password"` `required` `placeholder=" "` | "Ce champ est obligatoire" |

### Formulaire Employé (`templates/DashboardHr/employees.html.twig`)

| Champ | Attributs HTML | Message d'erreur |
|-------|---------------|------------------|
| Prénom | `type="text"` `required` `placeholder=" "` | "Ce champ est obligatoire" |
| Nom | `type="text"` `required` `placeholder=" "` | "Ce champ est obligatoire" |
| Email | `type="email"` `required` `placeholder=" "` | "Veuillez entrer une adresse email valide" |
| Âge | `type="number"` `required` `min="18"` `max="100"` `placeholder=" "` | "L'âge doit être entre 18 et 100 ans" |
| Titre du poste | `type="text"` `required` `placeholder=" "` | "Ce champ est obligatoire" |
| Mot de passe | `type="password"` `required` `placeholder=" "` | "Ce champ est obligatoire" |

---

## Implémentation CSS

Les styles de validation sont ajoutés dans `assets/styles/app.css` dans un `@layer components` dédié :

```css
@layer components {
  /* Bordure rouge sur champ invalide après interaction */
  .validated-field:not(:placeholder-shown):invalid {
    @apply border-red-500 focus:ring-red-500/30;
  }

  /* Bordure verte sur champ valide après interaction */
  .validated-field:not(:placeholder-shown):valid {
    @apply border-green-500 focus:ring-green-500/30;
  }

  /* Message d'erreur caché par défaut */
  .field-error {
    @apply hidden text-red-400 text-xs mt-1;
  }

  /* Message visible quand l'input précédent est invalide et touché */
  .validated-field:not(:placeholder-shown):invalid + .field-error {
    @apply block;
  }

  /* Animation d'apparition */
  .field-error {
    animation: fadeIn 150ms ease-in-out;
  }
}
```

La classe `validated-field` est ajoutée sur chaque `<input>` concerné dans les templates Twig.

---

## Fichiers modifiés

| Fichier | Modification |
|---------|-------------|
| `assets/styles/app.css` | Ajout des classes `.validated-field` et `.field-error` dans `@layer components` |
| `templates/Auth/login.html.twig` | Ajout de `validated-field` sur les inputs + `<span class="field-error">` après chaque input |
| `templates/DashboardHr/employees.html.twig` | Idem + attributs `min`/`max` sur le champ âge |

**Aucun fichier JS créé ou modifié.**

---

## Contraintes

- Chaque `<input>` doit avoir un `placeholder` (même `" "`) pour que la technique fonctionne
- Le `<span class="field-error">` doit être **immédiatement adjacent** à son `<input>` (pas de div intermédiaire)
- La validation HTML native du navigateur (bulle tooltip) est supprimée via `novalidate` sur la `<form>` — le CSS prend le relais visuellement
