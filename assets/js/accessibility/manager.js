/**
 * Manager global d'accessibilité.
 *
 * - Lit les préférences initiales (depuis window.__A11Y_PREFS__ injecté par
 *   base.html.twig, ou depuis localStorage si non connecté).
 * - Applique les classes CSS sur <html> (l'application "anti-flash" est
 *   également faite par un script inline dans base.html.twig pour éviter
 *   tout flash de contenu non accessible).
 * - Sync : sauvegarde côté serveur (debounced) + miroir local pour
 *   conserver l'état hors-ligne.
 * - Émet l'événement DOM `a11y:prefs-changed` à chaque mise à jour pour
 *   que les autres modules (panneau, popover, speech...) se synchronisent.
 */
import { speech } from './speech.js';

const STORAGE_KEY = 'hrflow-a11y-prefs';
const ALLOWED_FONT_SCALES = [90, 100, 115, 130, 150];
const ALLOWED_VOICE_LANGS = ['fr-FR', 'en-US', 'ar-TN'];

const DEFAULT_PREFS = {
    high_contrast: false,
    font_scale: 100,
    voice_feedback: false,
    simplified_ui: false,
    reduce_motion: false,
    voice_lang: 'fr-FR',
};

class AccessibilityManager {
    constructor() {
        this.prefs = { ...DEFAULT_PREFS };
        this._saveTimer = null;
        this._csrf = null;
        this._authenticated = false;
    }

    /** Démarre le manager au chargement du document. */
    init() {
        // 1. Lire les préférences initiales injectées par Twig (anti-flash).
        const seed = window.__A11Y_PREFS__;
        if (seed && typeof seed === 'object') {
            this.prefs = this._sanitize({ ...DEFAULT_PREFS, ...seed });
            this._authenticated = true;
        } else {
            // Utilisateur non connecté → on lit le localStorage uniquement.
            const cached = this._readLocal();
            if (cached) this.prefs = this._sanitize({ ...DEFAULT_PREFS, ...cached });
        }

        // Détection système : prefers-reduced-motion → activer par défaut
        // si l'utilisateur n'a pas d'autre préférence enregistrée.
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            if (!this._authenticated && !this._readLocal()) {
                this.prefs.reduce_motion = true;
            }
        }

        // 2. Récupérer le token CSRF (déposé par Twig dans une balise <meta>).
        const meta = document.querySelector('meta[name="a11y-csrf"]');
        this._csrf = meta ? meta.getAttribute('content') : null;

        // 3. Configurer le moteur vocal.
        speech.setLang(this.prefs.voice_lang);
        speech.setEnabled(this.prefs.voice_feedback);

        // 4. Appliquer les classes (idempotent : déjà fait par le script inline).
        this.apply();

        // 5. Annoncer aux autres modules que les prefs sont prêtes.
        this._dispatch();
    }

    /** Retourne une copie défensive des préférences courantes. */
    get() {
        return { ...this.prefs };
    }

    /**
     * Met à jour une ou plusieurs préférences puis applique + sauvegarde.
     * @param {Partial<typeof DEFAULT_PREFS>} patch
     * @param {{silent?: boolean}} [options] - silent: ne pas annoncer vocalement
     */
    update(patch, options = {}) {
        const next = this._sanitize({ ...this.prefs, ...patch });
        const changed = JSON.stringify(next) !== JSON.stringify(this.prefs);
        if (!changed) return;

        this.prefs = next;
        this.apply();
        this._writeLocal();
        this._scheduleServerSync();
        this._dispatch();

        // Retour vocal sur les changements importants
        if (!options.silent && this.prefs.voice_feedback) {
            this._announceChange(patch);
        }
    }

    /** Réinitialise les préférences aux valeurs par défaut. */
    reset() {
        this.update({ ...DEFAULT_PREFS });
    }

    /** Applique les préférences au document (classes CSS sur <html>). */
    apply() {
        const html = document.documentElement;

        html.classList.toggle('a11y-high-contrast', this.prefs.high_contrast);
        html.classList.toggle('a11y-simplified', this.prefs.simplified_ui);
        html.classList.toggle('a11y-reduce-motion', this.prefs.reduce_motion);

        // Échelle de police : on retire toutes les classes a11y-font-* puis on ajoute la bonne.
        ALLOWED_FONT_SCALES.forEach((s) => html.classList.remove(`a11y-font-${s}`));
        html.classList.add(`a11y-font-${this.prefs.font_scale}`);

        // Met à jour le moteur vocal.
        speech.setLang(this.prefs.voice_lang);
        speech.setEnabled(this.prefs.voice_feedback);
    }

    /**
     * Force une annonce vocale (utilisée par le panneau pour le bouton "Tester").
     * @param {string} text
     */
    speak(text) {
        speech.speak(text);
    }

    // ──────────────────────────── Internes ────────────────────────────

    _sanitize(prefs) {
        const out = { ...DEFAULT_PREFS };
        out.high_contrast  = !!prefs.high_contrast;
        out.voice_feedback = !!prefs.voice_feedback;
        out.simplified_ui  = !!prefs.simplified_ui;
        out.reduce_motion  = !!prefs.reduce_motion;

        const fs = Number(prefs.font_scale);
        out.font_scale = ALLOWED_FONT_SCALES.includes(fs) ? fs : DEFAULT_PREFS.font_scale;

        out.voice_lang = ALLOWED_VOICE_LANGS.includes(prefs.voice_lang)
            ? prefs.voice_lang
            : DEFAULT_PREFS.voice_lang;
        return out;
    }

    _readLocal() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (_) { return null; }
    }

    _writeLocal() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(this.prefs)); }
        catch (_) { /* silencieux (quota / mode privé) */ }
    }

    _scheduleServerSync() {
        // Pas de sync serveur si l'utilisateur n'est pas authentifié.
        if (!this._authenticated || !this._csrf) return;

        clearTimeout(this._saveTimer);
        this._saveTimer = setTimeout(() => this._serverSync(), 500);
    }

    async _serverSync() {
        try {
            await fetch('/account/accessibility/save', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this._csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(this.prefs),
            });
        } catch (_) {
            /* Échec silencieux : les prefs restent en localStorage et seront
               ressynchronisées au prochain changement réussi. */
        }
    }

    _announceChange(patch) {
        const messages = [];
        if ('high_contrast' in patch) {
            messages.push(patch.high_contrast ? 'Contraste élevé activé' : 'Contraste élevé désactivé');
        }
        if ('simplified_ui' in patch) {
            messages.push(patch.simplified_ui ? 'Interface simplifiée activée' : 'Interface simplifiée désactivée');
        }
        if ('reduce_motion' in patch) {
            messages.push(patch.reduce_motion ? 'Animations réduites' : 'Animations réactivées');
        }
        if ('voice_feedback' in patch) {
            // Cas spécial : si on vient de l'activer, annoncer ; si on vient de le désactiver,
            // ne rien dire (sinon on parle alors que l'utilisateur veut le silence).
            if (patch.voice_feedback) messages.push('Retour vocal activé');
        }
        if ('font_scale' in patch) {
            const label = this._fontScaleLabel(patch.font_scale);
            messages.push(`Taille du texte : ${label}`);
        }
        if (messages.length > 0) speech.speak(messages.join('. '));
    }

    _fontScaleLabel(scale) {
        switch (scale) {
            case 90:  return 'Très petite';
            case 100: return 'Normale';
            case 115: return 'Grande';
            case 130: return 'Très grande';
            case 150: return 'Maximum';
            default:  return scale + ' pour cent';
        }
    }

    _dispatch() {
        document.dispatchEvent(new CustomEvent('a11y:prefs-changed', {
            detail: { prefs: this.get() },
        }));
    }
}

export const a11y = new AccessibilityManager();
export const A11Y_FONT_SCALES = ALLOWED_FONT_SCALES;

// Démarrage automatique dès que le DOM est prêt.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => a11y.init());
} else {
    a11y.init();
}

// Expose en global pour le code Twig non-modulaire (boutons inline, etc.)
window.HrFlowA11y = a11y;
