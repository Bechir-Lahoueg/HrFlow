/**
 * Bouton flottant d'accessibilité + mini-panneau (popover).
 *
 * Le HTML est rendu par le partial Twig _a11y_floating_button.html.twig
 * inclus dans base.html.twig. Ce module ne fait que câbler les interactions :
 *  - ouverture/fermeture (toggle, ESC, click outside)
 *  - synchronisation des contrôles avec le manager
 *  - retour vocal sur les actions importantes
 */
import { a11y, A11Y_FONT_SCALES } from './manager.js';

const ROOT_SELECTOR = '#a11y-fab-root';

class FloatingButton {
    constructor() {
        this.root = null;
        this.fab = null;
        this.popover = null;
        this.isOpen = false;
        this._docListenersAdded = false;
    }

    init() {
        this.root = document.querySelector(ROOT_SELECTOR);
        if (!this.root) return;

        this.fab = this.root.querySelector('.a11y-fab');
        this.popover = this.root.querySelector('.a11y-popover');

        this.fab.addEventListener('click', () => this.toggle());

        if (!this._docListenersAdded) {
            this._docListenersAdded = true;

            document.addEventListener('click', (e) => {
                if (!this.isOpen) return;
                if (this.root && this.root.contains(e.target)) return;
                this.close();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                    if (this.fab) this.fab.focus();
                }
            });

            // Resync UI quand les prefs changent (depuis le panneau settings ou ailleurs)
            document.addEventListener('a11y:prefs-changed', () => this._refreshUI());
        }

        this._wireControls();
        this._refreshUI();
    }

    toggle() { this.isOpen ? this.close() : this.open(); }

    open() {
        this.isOpen = true;
        this.popover.hidden = false;
        this.fab.setAttribute('aria-expanded', 'true');
        // Focus sur le premier élément interactif du popover
        const first = this.popover.querySelector('button, [tabindex]:not([tabindex="-1"])');
        if (first) setTimeout(() => first.focus(), 10);
    }

    close() {
        this.isOpen = false;
        this.popover.hidden = true;
        this.fab.setAttribute('aria-expanded', 'false');
    }

    _wireControls() {
        // Toggles booléens
        this.popover.querySelectorAll('[data-a11y-toggle]').forEach((btn) => {
            const key = btn.dataset.a11yToggle;
            btn.addEventListener('click', () => {
                const current = a11y.get();
                a11y.update({ [key]: !current[key] });
            });
        });

        // Stepper : taille de texte
        const decBtn = this.popover.querySelector('[data-a11y-font-step="-1"]');
        const incBtn = this.popover.querySelector('[data-a11y-font-step="1"]');
        const resetBtn = this.popover.querySelector('[data-a11y-font-reset]');

        if (decBtn) decBtn.addEventListener('click', () => this._stepFont(-1));
        if (incBtn) incBtn.addEventListener('click', () => this._stepFont(+1));
        if (resetBtn) resetBtn.addEventListener('click', () => a11y.update({ font_scale: 100 }));

        // Lien "Réinitialiser tout"
        const resetAll = this.popover.querySelector('[data-a11y-reset-all]');
        if (resetAll) resetAll.addEventListener('click', (e) => { e.preventDefault(); a11y.reset(); });
    }

    _stepFont(delta) {
        const current = a11y.get().font_scale;
        const idx = A11Y_FONT_SCALES.indexOf(current);
        const nextIdx = Math.max(0, Math.min(A11Y_FONT_SCALES.length - 1, idx + delta));
        if (A11Y_FONT_SCALES[nextIdx] !== current) {
            a11y.update({ font_scale: A11Y_FONT_SCALES[nextIdx] });
        }
    }

    _refreshUI() {
        if (!this.popover) return;
        const prefs = a11y.get();

        this.popover.querySelectorAll('[data-a11y-toggle]').forEach((btn) => {
            const key = btn.dataset.a11yToggle;
            btn.setAttribute('aria-pressed', prefs[key] ? 'true' : 'false');
            const stateEl = btn.parentElement.querySelector('.a11y-toggle-state');
            if (stateEl) stateEl.textContent = prefs[key] ? 'Activé' : 'Désactivé';
        });

        const valueEl = this.popover.querySelector('[data-a11y-font-value]');
        if (valueEl) valueEl.textContent = prefs.font_scale + ' %';

        const decBtn = this.popover.querySelector('[data-a11y-font-step="-1"]');
        const incBtn = this.popover.querySelector('[data-a11y-font-step="1"]');
        if (decBtn) decBtn.disabled = prefs.font_scale <= A11Y_FONT_SCALES[0];
        if (incBtn) incBtn.disabled = prefs.font_scale >= A11Y_FONT_SCALES[A11Y_FONT_SCALES.length - 1];

        // Sous-options vocales : grisées quand voice_feedback est désactivé
        this.popover.querySelectorAll('[data-a11y-voice-opts]').forEach((el) => {
            el.style.opacity = prefs.voice_feedback ? '1' : '0.4';
            el.style.pointerEvents = prefs.voice_feedback ? '' : 'none';
            el.setAttribute('aria-hidden', prefs.voice_feedback ? 'false' : 'true');
        });
    }
}

export const floatingButton = new FloatingButton();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => floatingButton.init());
} else {
    floatingButton.init();
}
// Re-init après chaque navigation Turbo Drive : le body est remplacé,
// le bouton flottant est un nouvel élément DOM → on doit le recâbler.
document.addEventListener('turbo:load', () => floatingButton.init());
