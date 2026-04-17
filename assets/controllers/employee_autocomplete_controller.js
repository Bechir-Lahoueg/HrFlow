import { Controller } from '@hotwired/stimulus';

/**
 * Employee autocomplete controller.
 * Targets: input, dropdown, hiddenInput
 * Values: url (string)
 */
export default class extends Controller {
    static targets = ['input', 'dropdown', 'hiddenInput'];
    static values  = { url: String };

    _timer = null;

    connect() {
        this._onClickOutside = this._onClickOutside.bind(this);
        document.addEventListener('click', this._onClickOutside);
    }

    disconnect() {
        document.removeEventListener('click', this._onClickOutside);
        clearTimeout(this._timer);
    }

    onInput() {
        clearTimeout(this._timer);
        const q = this.inputTarget.value.trim();

        if (q.length < 2) {
            this._hideDropdown();
            return;
        }

        this._timer = setTimeout(() => this._fetch(q), 300);
    }

    async _fetch(query) {
        try {
            const url = `${this.urlValue}?q=${encodeURIComponent(query)}`;
            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            this._renderDropdown(data);
        } catch (e) {
            // silently ignore network errors
        }
    }

    _renderDropdown(items) {
        const dd = this.dropdownTarget;
        dd.innerHTML = '';

        if (items.length === 0) {
            dd.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">Aucun résultat</div>';
            dd.classList.remove('hidden');
            return;
        }

        items.forEach(item => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'w-full text-left px-3 py-2 hover:bg-indigo-50 text-sm transition-colors cursor-pointer';
            el.innerHTML = `<span class="font-medium text-gray-800">${this._esc(item.name)}</span>
                            <span class="text-xs text-gray-400 ml-2">${this._esc(item.jobTitle || '')}</span>`;
            el.addEventListener('click', () => this._select(item));
            dd.appendChild(el);
        });

        dd.classList.remove('hidden');
    }

    _select(item) {
        this.inputTarget.value = item.name;
        if (this.hasHiddenInputTarget) {
            this.hiddenInputTarget.value = item.id;
        }
        this._hideDropdown();
        // Dispatch custom event for other controllers
        this.element.dispatchEvent(new CustomEvent('employee:selected', { detail: item, bubbles: true }));
    }

    _hideDropdown() {
        this.dropdownTarget.classList.add('hidden');
        this.dropdownTarget.innerHTML = '';
    }

    _onClickOutside(e) {
        if (!this.element.contains(e.target)) {
            this._hideDropdown();
        }
    }

    _esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
}
