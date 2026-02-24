import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'homebalance:last-username';

export default class extends Controller {
    static targets = ['username'];
    static values = {
        last: String,
    };

    connect() {
        this.primeUsername();
        if (this.hasUsernameTarget) {
            this.persistHandler = this.persist.bind(this);
            this.usernameTarget.addEventListener('blur', this.persistHandler);
        }
    }

    disconnect() {
        if (this.hasUsernameTarget && this.persistHandler) {
            this.usernameTarget.removeEventListener('blur', this.persistHandler);
        }
    }

    primeUsername() {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (this.hasUsernameTarget) {
            if (!this.usernameTarget.value && (this.lastValue || stored)) {
                this.usernameTarget.value = this.lastValue || stored;
            }
        }
    }

    persist() {
        if (!this.hasUsernameTarget || !this.usernameTarget.value) {
            return;
        }

        try {
            window.localStorage.setItem(STORAGE_KEY, this.usernameTarget.value);
        } catch (error) {
            console.warn('Unable to persist username', error);
        }
    }
}
