/**
 * Câblage du panneau d'accessibilité complet (page Settings).
 * Le HTML est rendu par templates/Settings/_accessibility_panel.html.twig.
 * Réutilise les mêmes hooks (data-a11y-*) que le mini-popover, plus
 * un sélecteur de langue de voix et un bouton "Tester la voix".
 */
import { a11y, A11Y_FONT_SCALES } from './manager.js';

const ROOT_SELECTOR = '#a11y-panel-root';

class AccessibilityPanel {
    init() {
        this.root = document.querySelector(ROOT_SELECTOR);
        if (!this.root) return;

        // Toggles — délégation au niveau du root pour qu'aucun re-render
        // (re-rendu partiel, restauration depuis bfcache, contenu réinjecté)
        // ne fasse perdre les listeners. Un seul handler global suffit.
        this.root.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('[data-a11y-toggle]');
            if (toggleBtn && this.root.contains(toggleBtn)) {
                e.preventDefault();
                const key = toggleBtn.dataset.a11yToggle;
                const current = a11y.get();
                a11y.update({ [key]: !current[key] });
                this._refresh();
                return;
            }

            const stepBtn = e.target.closest('[data-a11y-font-step]');
            if (stepBtn && this.root.contains(stepBtn)) {
                e.preventDefault();
                const delta = Number(stepBtn.dataset.a11yFontStep) || 0;
                this._step(delta);
                this._refresh();
                return;
            }

            const resetBtn = e.target.closest('[data-a11y-font-reset]');
            if (resetBtn && this.root.contains(resetBtn)) {
                e.preventDefault();
                a11y.update({ font_scale: 100 });
                this._refresh();
                return;
            }

            const resetAll = e.target.closest('[data-a11y-reset-all]');
            if (resetAll && this.root.contains(resetAll)) {
                e.preventDefault();
                a11y.reset();
                this._refresh();
            }
        });

        // Bouton "Tester la voix" (déléggué après le bloc click générique)
        this.root.addEventListener('click', (e) => {
            const testBtn = e.target.closest('[data-a11y-voice-test]');
            if (!testBtn || !this.root.contains(testBtn)) return;
            e.preventDefault();
            const prefs = a11y.get();
            if (!prefs.voice_feedback) {
                a11y.update({ voice_feedback: true }, { silent: true });
                this._refresh();
            }
            a11y.speak('Bonjour, le retour vocal est activé. Vous entendrez les confirmations importantes.');
        });

        // Sélecteur de langue (délégation change)
        this.root.addEventListener('change', (e) => {
            const langSelect = e.target.closest('[data-a11y-voice-lang]');
            if (!langSelect || !this.root.contains(langSelect)) return;
            a11y.update({ voice_lang: langSelect.value }, { silent: true });
            this._refresh();
        });

        document.addEventListener('a11y:prefs-changed', () => this._refresh());
        this._refresh();
    }

    _step(delta) {
        const current = a11y.get().font_scale;
        const idx = A11Y_FONT_SCALES.indexOf(current);
        const next = Math.max(0, Math.min(A11Y_FONT_SCALES.length - 1, idx + delta));
        if (A11Y_FONT_SCALES[next] !== current) {
            a11y.update({ font_scale: A11Y_FONT_SCALES[next] });
        }
    }

    _refresh() {
        const prefs = a11y.get();

        // Toggles + état textuel
        this.root.querySelectorAll('[data-a11y-toggle]').forEach((btn) => {
            const key = btn.dataset.a11yToggle;
            btn.setAttribute('aria-pressed', prefs[key] ? 'true' : 'false');
            const state = btn.parentElement.querySelector('.a11y-toggle-state');
            if (state) state.textContent = prefs[key] ? 'Activé' : 'Désactivé';
        });

        // Affichage de la taille
        const valueEl = this.root.querySelector('[data-a11y-font-value]');
        if (valueEl) valueEl.textContent = prefs.font_scale + ' %';
        const labelEl = this.root.querySelector('[data-a11y-font-label]');
        if (labelEl) labelEl.textContent = this._fontLabel(prefs.font_scale);

        const dec = this.root.querySelector('[data-a11y-font-step="-1"]');
        const inc = this.root.querySelector('[data-a11y-font-step="1"]');
        if (dec) dec.disabled = prefs.font_scale <= A11Y_FONT_SCALES[0];
        if (inc) inc.disabled = prefs.font_scale >= A11Y_FONT_SCALES[A11Y_FONT_SCALES.length - 1];

        // Langue de voix
        const langSelect = this.root.querySelector('[data-a11y-voice-lang]');
        if (langSelect && langSelect.value !== prefs.voice_lang) {
            langSelect.value = prefs.voice_lang;
        }
    }

    _fontLabel(scale) {
        switch (scale) {
            case 90:  return 'Très petite';
            case 100: return 'Normale';
            case 115: return 'Grande';
            case 130: return 'Très grande';
            case 150: return 'Maximum';
            default:  return scale + ' %';
        }
    }
}

const panel = new AccessibilityPanel();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => panel.init());
} else {
    panel.init();
}
