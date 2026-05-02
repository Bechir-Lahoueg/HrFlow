import { speech } from './speech.js';

const SELECTOR = '[data-confirm]';

class ConfirmDialog {
    constructor() {
        this.el = null;
        this.titleEl = null;
        this.msgEl = null;
        this.confirmBtn = null;
        this.cancelBtn = null;
        this.previouslyFocused = null;
        this._pendingTrigger = null;
    }

    init() {
        this.el = document.getElementById('a11y-confirm-dialog');
        if (!this.el) return; // base.html.twig n'a pas (encore) inclus la modale

        this.titleEl    = this.el.querySelector('[data-role="title"]');
        this.msgEl      = this.el.querySelector('[data-role="message"]');
        this.confirmBtn = this.el.querySelector('[data-action="confirm"]');
        this.cancelBtn  = this.el.querySelector('[data-action="cancel"]');

        // Délégation : un seul listener pour TOUS les éléments data-confirm
        document.addEventListener('click', (e) => this._handleTriggerClick(e), true);

        // Délégation pour les soumissions de formulaire qui passent par data-confirm
        document.addEventListener('submit', (e) => this._handleFormSubmit(e), true);

        // Boutons de la modale
        this.cancelBtn.addEventListener('click', () => this._cancel());
        this.confirmBtn.addEventListener('click', () => this._confirm());

        // Backdrop : clic en dehors ferme
        this.el.addEventListener('click', (e) => {
            if (e.target === this.el) this._cancel();
        });

        // ESC
        document.addEventListener('keydown', (e) => {
            if (this.el.hidden) return;
            if (e.key === 'Escape') { e.preventDefault(); this._cancel(); }
            if (e.key === 'Tab')    { this._trapFocus(e); }
        });
    }

    _handleTriggerClick(e) {
        const trigger = e.target.closest(SELECTOR);
        if (!trigger) return;
        if (trigger.dataset.a11yConfirmed === '1') return; // déjà confirmé

        // On bloque le déclenchement initial
        e.preventDefault();
        e.stopPropagation();

        this._open(trigger);
    }

    _handleFormSubmit(e) {
        // Si un submitter porte data-confirm et n'est pas encore confirmé, on bloque.
        const submitter = e.submitter;
        if (submitter && submitter.matches(SELECTOR) && submitter.dataset.a11yConfirmed !== '1') {
            e.preventDefault();
            e.stopPropagation();
            this._open(submitter);
        }
    }

    _open(trigger) {
        if (!this.el) return;
        this._pendingTrigger = trigger;

        const message = trigger.dataset.confirm || 'Voulez-vous confirmer cette action ?';
        const title   = trigger.dataset.confirmTitle || 'Confirmer l\'action';
        const variant = trigger.dataset.confirmVariant || 'danger';
        const okLabel = trigger.dataset.confirmOk || 'Confirmer';
        const noLabel = trigger.dataset.confirmCancel || 'Annuler';

        this.titleEl.textContent    = title;
        this.msgEl.textContent      = message;
        this.confirmBtn.textContent = okLabel;
        this.cancelBtn.textContent  = noLabel;
        this.el.querySelector('.a11y-confirm-dialog').dataset.variant = variant;

        this.previouslyFocused = document.activeElement;
        this.el.hidden = false;
        // Focus sur le bouton "Annuler" par défaut (plus sûr)
        setTimeout(() => this.cancelBtn.focus(), 10);

        speech.speak(`${title}. ${message}`);
    }

    _close() {
        if (!this.el) return;
        this.el.hidden = true;
        if (this.previouslyFocused && typeof this.previouslyFocused.focus === 'function') {
            this.previouslyFocused.focus();
        }
        this.previouslyFocused = null;
    }

    _cancel() {
        this._pendingTrigger = null;
        this._close();
    }

    _confirm() {
        const trigger = this._pendingTrigger;
        this._pendingTrigger = null;
        this._close();
        if (!trigger) return;

        // Marquer comme confirmé pour que le re-déclenchement passe à travers
        trigger.dataset.a11yConfirmed = '1';

        // Cas 1 : le trigger est un bouton de soumission → submit le form
        if (trigger.tagName === 'BUTTON' && trigger.type === 'submit') {
            const form = trigger.form || trigger.closest('form');
            if (form) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(trigger);
                } else {
                    form.submit();
                }
                return;
            }
        }

        // Cas 2 : lien <a href> → naviguer
        if (trigger.tagName === 'A' && trigger.getAttribute('href')) {
            const href = trigger.getAttribute('href');
            const target = trigger.getAttribute('target') || '_self';
            if (target === '_blank') {
                window.open(href, '_blank', 'noopener,noreferrer');
            } else {
                window.location.assign(href);
            }
            return;
        }

        // Cas 3 : autre élément → re-déclenche un clic synthétique
        trigger.click();
    }

    _trapFocus(e) {
        if (this.el.hidden) return;
        const focusables = this.el.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
}

export const confirmDialog = new ConfirmDialog();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => confirmDialog.init());
} else {
    confirmDialog.init();
}
