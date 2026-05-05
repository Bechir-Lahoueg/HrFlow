/**
 * Lecteur d'écran intelligent – HrFlow Accessibility
 *
 * Annonce l'élément sous le curseur (après 400 ms de pause) et l'élément
 * qui reçoit le focus clavier. Actif uniquement quand voice_feedback ET
 * hover_reading sont tous les deux activés dans les préférences utilisateur.
 *
 * Extraction du nom accessible (par ordre de priorité) :
 *  1. aria-label
 *  2. aria-labelledby → texte des éléments référencés
 *  3. <label for="…"> associé
 *  4. Contenu spécifique : valeur/placeholder (input), option sélectionnée (select), alt (img)
 *  5. Contenu textuel visible (tronqué à 100 caractères)
 *  6. title
 *
 * Rôle implicite / explicite et état (appuyé, développé, coché, désactivé)
 * sont ajoutés après le texte pour un retour complet.
 */
import { a11y } from './manager.js';
import { speech } from './speech.js';

// Tags à ignorer totalement (pas de texte utile)
const SKIP_TAGS = new Set(['HTML', 'BODY', 'SCRIPT', 'STYLE', 'NOSCRIPT', 'META', 'LINK', 'HEAD']);

// Tags SVG : transparents, on remonte directement au parent
const TRANSPARENT_TAGS = new Set(['SVG', 'PATH', 'G', 'USE', 'SYMBOL', 'DEFS', 'CIRCLE', 'RECT',
    'LINE', 'POLYLINE', 'POLYGON', 'ELLIPSE', 'TEXT', 'TSPAN', 'CLIPPATH', 'MASK', 'ANIMATE']);

// Tags qui portent toujours du sens sémantique
const MEANINGFUL_TAGS = new Set([
    'BUTTON', 'A', 'INPUT', 'SELECT', 'TEXTAREA', 'LABEL',
    'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'LI', 'TD', 'TH', 'CAPTION', 'SUMMARY', 'DETAILS', 'OPTION',
    'FIGURE', 'FIGCAPTION', 'BLOCKQUOTE', 'ABBR',
]);

// Traduction des rôles ARIA en français
const ROLE_LABELS = {
    button: 'bouton',
    link: 'lien',
    checkbox: 'case à cocher',
    radio: 'bouton radio',
    combobox: 'liste déroulante',
    listbox: 'liste',
    switch: 'interrupteur',
    tab: 'onglet',
    tabpanel: 'panneau',
    menuitem: 'élément de menu',
    menuitemcheckbox: 'case de menu',
    navigation: 'navigation',
    main: 'contenu principal',
    banner: 'en-tête',
    contentinfo: 'pied de page',
    dialog: 'boîte de dialogue',
    alertdialog: 'alerte',
    alert: 'alerte',
    status: 'état',
    heading: 'titre',
    region: 'région',
    article: 'article',
    img: 'image',
    search: 'recherche',
    form: 'formulaire',
    progressbar: 'barre de progression',
    slider: 'curseur',
    spinbutton: 'compteur',
    tooltip: 'info-bulle',
};

class ScreenReader {
    constructor() {
        this._lastEl   = null;
        this._lastText = '';
        this._hoverTimer = null;
        this._active = false;
        this._attached = false;

        // Lier les handlers une seule fois pour pouvoir les retirer si besoin
        this._onHover = this._onHover.bind(this);
        this._onLeave = this._onLeave.bind(this);
        this._onFocus = this._onFocus.bind(this);
    }

    /** Initialise le module. Sûr à appeler plusieurs fois (idempotent). */
    init() {
        if (!this._attached) {
            this._attached = true;

            document.addEventListener('mouseover', this._onHover, { passive: true });
            document.addEventListener('mouseout',  this._onLeave, { passive: true });
            document.addEventListener('focusin',   this._onFocus, { passive: true });

            // Réagit aux changements de préférences en temps réel
            document.addEventListener('a11y:prefs-changed', (e) => {
                const prefs = e.detail?.prefs ?? a11y.get();
                this._active = !!(prefs.voice_feedback && prefs.hover_reading);
                if (!this._active) {
                    clearTimeout(this._hoverTimer);
                    this._lastEl   = null;
                    this._lastText = '';
                }
            });
        }

        // Sync l'état initial
        const prefs = a11y.get();
        this._active = !!(prefs.voice_feedback && prefs.hover_reading);
    }

    // ──────────────────────────── Handlers ────────────────────────────

    _onHover(e) {
        if (!this._active) return;
        clearTimeout(this._hoverTimer);
        const el = e.target;

        // Délai : on attend que la souris se stabilise avant d'annoncer
        this._hoverTimer = setTimeout(() => {
            const target = this._findMeaningful(el);
            if (!target) return;

            const text = this._getAccessibleText(target);
            if (!text) return;
            // Ne répète pas le même texte sur le même élément
            if (target === this._lastEl && text === this._lastText) return;

            this._lastEl   = target;
            this._lastText = text;

            const prefs = a11y.get();
            speech.speak(text, { rate: prefs.voice_speed ?? 1.0 });
        }, 400);
    }

    _onLeave() {
        clearTimeout(this._hoverTimer);
    }

    _onFocus(e) {
        if (!this._active) return;
        clearTimeout(this._hoverTimer);

        const el = e.target;
        if (!el?.tagName) return;
        if (SKIP_TAGS.has(el.tagName) || TRANSPARENT_TAGS.has(el.tagName)) return;
        if (el.getAttribute('aria-hidden') === 'true') return;

        const text = this._getAccessibleText(el);
        if (!text) return;

        this._lastEl   = el;
        this._lastText = text;

        const prefs = a11y.get();
        // Le focus clavier est annoncé immédiatement (sans délai)
        speech.speak(text, { rate: prefs.voice_speed ?? 1.0 });
    }

    // ──────────────────────────── Logique DOM ────────────────────────────

    /**
     * Remonte l'arbre DOM pour trouver l'élément le plus porteur de sens.
     * Ignore les SVG et les éléments aria-hidden.
     */
    _findMeaningful(el) {
        let current = el;
        let depth = 0;

        while (current && current !== document.body && depth < 7) {
            if (!current.tagName) break;

            // SVG interne : toujours remonter
            if (TRANSPARENT_TAGS.has(current.tagName)) {
                current = current.parentElement;
                depth++;
                continue;
            }

            // Stopper sur aria-hidden
            if (current.getAttribute('aria-hidden') === 'true') return null;

            // aria-label ou aria-labelledby : cet élément EST le porteur de sens
            if (current.getAttribute('aria-label')?.trim()) return current;
            if (current.getAttribute('aria-labelledby')?.trim()) return current;

            // Balises sémantiques interactives
            if (MEANINGFUL_TAGS.has(current.tagName)) return current;

            // Rôle ARIA explicite (hors décoratifs)
            const role = current.getAttribute('role');
            if (role && role !== 'none' && role !== 'presentation' && role !== 'generic') {
                return current;
            }

            // Texte visible court : libellé probable
            const text = current.textContent?.trim().replace(/\s+/g, ' ') ?? '';
            if (text.length >= 2 && text.length <= 120) return current;

            current = current.parentElement;
            depth++;
        }

        return el; // fallback : élément original
    }

    /**
     * Construit la chaîne de texte à prononcer pour un élément.
     * @param {Element} el
     * @returns {string|null}
     */
    _getAccessibleText(el) {
        if (!el?.tagName) return null;
        if (SKIP_TAGS.has(el.tagName) || TRANSPARENT_TAGS.has(el.tagName)) return null;
        if (el.getAttribute('aria-hidden') === 'true') return null;

        const parts = [];

        // ── 1. Nom accessible (aria-label > aria-labelledby > label[for]) ──
        const ariaLabel = el.getAttribute('aria-label')?.trim();
        if (ariaLabel) {
            parts.push(ariaLabel);
        } else {
            const labelledBy = el.getAttribute('aria-labelledby')?.trim();
            if (labelledBy) {
                const names = labelledBy.split(/\s+/)
                    .map(id => document.getElementById(id)?.textContent?.trim())
                    .filter(Boolean);
                if (names.length) parts.push(names.join(' '));
            }
        }

        // Label HTML associé (via id)
        if (parts.length === 0 && el.id) {
            try {
                const lbl = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
                if (lbl) parts.push(lbl.textContent?.trim());
            } catch (_) { /* CSS.escape peut échouer sur certains IDs complexes */ }
        }

        // ── 2. Contenu spécifique au type d'élément ──
        if (parts.length === 0) {
            switch (el.tagName) {
                case 'IMG': {
                    const alt = el.alt?.trim();
                    if (alt) parts.push(alt);
                    break;
                }
                case 'INPUT': {
                    const typeLabels = {
                        text: 'texte', email: 'email', password: 'mot de passe',
                        search: 'recherche', number: 'nombre', date: 'date',
                        tel: 'téléphone', url: 'URL', file: 'fichier',
                        range: 'curseur', color: 'couleur',
                    };
                    const val = el.value?.trim();
                    const ph  = el.getAttribute('placeholder')?.trim();
                    if (val) parts.push(val);
                    else if (ph) parts.push(ph);
                    else parts.push(typeLabels[el.type] || el.type || 'champ');
                    break;
                }
                case 'SELECT': {
                    const opt = el.options[el.selectedIndex];
                    parts.push(opt ? opt.text.trim() : 'liste');
                    break;
                }
                case 'TEXTAREA': {
                    const val = el.value?.trim();
                    if (val) parts.push(val.length > 60 ? val.slice(0, 60) + '…' : val);
                    else parts.push(el.getAttribute('placeholder')?.trim() || 'zone de texte');
                    break;
                }
                default: {
                    const text = el.textContent?.trim().replace(/\s+/g, ' ') ?? '';
                    if (text) parts.push(text.length > 100 ? text.slice(0, 100) + '…' : text);
                    break;
                }
            }
        }

        // Title comme ultime secours
        if (!parts[0]) {
            const title = el.getAttribute('title')?.trim();
            if (title) parts.push(title);
        }

        if (!parts[0]) return null;

        // ── 3. Rôle ──
        const explicitRole = el.getAttribute('role');
        const role = explicitRole || this._implicitRole(el);
        if (role && ROLE_LABELS[role]) parts.push(ROLE_LABELS[role]);

        // ── 4. État ──
        const state = this._getState(el);
        if (state) parts.push(state);

        return parts.filter(Boolean).join(', ');
    }

    _implicitRole(el) {
        switch (el.tagName) {
            case 'BUTTON':   return 'button';
            case 'A':        return el.getAttribute('href') != null ? 'link' : null;
            case 'SELECT':   return 'combobox';
            case 'IMG':      return 'img';
            case 'FORM':     return 'form';
            case 'NAV':      return 'navigation';
            case 'MAIN':     return 'main';
            case 'HEADER':   return 'banner';
            case 'FOOTER':   return 'contentinfo';
            case 'SECTION':  return el.getAttribute('aria-labelledby') ? 'region' : null;
            case 'ARTICLE':  return 'article';
            case 'H1': case 'H2': case 'H3':
            case 'H4': case 'H5': case 'H6': return 'heading';
            case 'INPUT':
                switch (el.type) {
                    case 'checkbox': return 'checkbox';
                    case 'radio':    return 'radio';
                    case 'submit':
                    case 'button':   return 'button';
                    case 'range':    return 'slider';
                    case 'search':   return 'search';
                    default:         return null;
                }
            default: return null;
        }
    }

    _getState(el) {
        const states = [];

        const pressed = el.getAttribute('aria-pressed');
        if (pressed === 'true')  states.push('activé');
        else if (pressed === 'false') states.push('désactivé');

        const expanded = el.getAttribute('aria-expanded');
        if (expanded === 'true')  states.push('développé');
        else if (expanded === 'false') states.push('réduit');

        const checked = el.getAttribute('aria-checked');
        if (checked === 'true')  states.push('coché');
        else if (checked === 'false') states.push('non coché');

        if (el.disabled || el.getAttribute('aria-disabled') === 'true') {
            states.push('désactivé');
        }

        const selected = el.getAttribute('aria-selected');
        if (selected === 'true') states.push('sélectionné');

        const required = el.required || el.getAttribute('aria-required') === 'true';
        if (required) states.push('obligatoire');

        return states.join(', ');
    }
}

export const screenReader = new ScreenReader();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => screenReader.init());
} else {
    screenReader.init();
}
document.addEventListener('turbo:load', () => screenReader.init());
