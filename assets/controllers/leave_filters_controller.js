import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'search'];

    connect() {
        this._debounceTimer = null;
    }

    disconnect() {
        clearTimeout(this._debounceTimer);
    }

    search() {
        clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
            this._submitForm();
        }, 350);
    }

    filter() {
        this._submitForm();
    }

    _submitForm() {
        const form = this.formTarget;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }
}
