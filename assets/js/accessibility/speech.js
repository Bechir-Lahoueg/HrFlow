/**
 * Wrapper minimal autour de la Web Speech API (SpeechSynthesis).
 *
 * - Aucune erreur si le navigateur ne supporte pas l'API.
 * - Coupe automatiquement la voix avant de prononcer un nouveau message
 *   (évite l'empilement quand l'utilisateur enchaîne les actions).
 * - Sélectionne la meilleure voix disponible pour la langue demandée.
 */
class SpeechFeedback {
    constructor() {
        this.supported = typeof window !== 'undefined' && 'speechSynthesis' in window;
        this.lang = 'fr-FR';
        this.enabled = false;
        this.rate = 1.0;
        this._cachedVoice = null;
        this._cachedVoiceForLang = null;

        if (this.supported) {
            // Les voix sont chargées de manière asynchrone par certains navigateurs.
            window.speechSynthesis.onvoiceschanged = () => {
                this._cachedVoice = null;
            };
        }
    }

    /** Active ou désactive globalement le retour vocal. */
    setEnabled(value) {
        this.enabled = !!value;
        if (!this.enabled) {
            this.cancel();
        }
    }

    /** Change la langue (fr-FR / en-US / ar-TN). */
    setLang(lang) {
        if (typeof lang === 'string' && lang.length > 0) {
            this.lang = lang;
            this._cachedVoice = null;
        }
    }

    /** Définit la vitesse de lecture (0.5 – 2.0). */
    setRate(rate) {
        const r = Number(rate);
        if (!Number.isNaN(r) && r >= 0.5 && r <= 2.0) {
            this.rate = r;
        }
    }

    /** Coupe immédiatement toute lecture en cours. */
    cancel() {
        if (!this.supported) return;
        try { window.speechSynthesis.cancel(); } catch (_) { /* silencieux */ }
    }

    /**
     * Prononce un texte. Ne fait rien si désactivé ou non supporté.
     * @param {string} text
     * @param {{rate?: number, pitch?: number, volume?: number}} [options]
     */
    speak(text, options = {}) {
        if (!this.enabled || !this.supported) return;
        const trimmed = (text || '').toString().trim();
        if (trimmed.length === 0) return;

        try {
            this.cancel();
            const utterance = new SpeechSynthesisUtterance(trimmed);
            utterance.lang   = this.lang;
            utterance.rate   = options.rate   ?? this.rate  ?? 1.0;
            utterance.pitch  = options.pitch  ?? 1.0;
            utterance.volume = options.volume ?? 1.0;

            const voice = this._pickVoice(this.lang);
            if (voice) utterance.voice = voice;

            window.speechSynthesis.speak(utterance);
        } catch (_) {
            /* Échec silencieux : ne jamais bloquer l'app à cause de l'a11y. */
        }
    }

    /** Sélectionne la meilleure voix pour une langue donnée. */
    _pickVoice(lang) {
        if (this._cachedVoice && this._cachedVoiceForLang === lang) {
            return this._cachedVoice;
        }
        const voices = window.speechSynthesis.getVoices();
        if (!voices || voices.length === 0) return null;

        const exact = voices.find((v) => v.lang === lang);
        const partial = voices.find((v) => v.lang && v.lang.startsWith(lang.slice(0, 2)));
        this._cachedVoice = exact || partial || voices[0];
        this._cachedVoiceForLang = lang;
        return this._cachedVoice;
    }
}

export const speech = new SpeechFeedback();
