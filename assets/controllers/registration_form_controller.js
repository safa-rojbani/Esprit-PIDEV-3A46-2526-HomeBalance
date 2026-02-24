import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['username', 'firstName', 'lastName'];

    connect() {
        this.usernameManuallyEdited = false;
        if (this.hasUsernameTarget) {
            this.usernameInputHandler = () => {
                this.usernameManuallyEdited = true;
            };
            this.usernameTarget.addEventListener('input', this.usernameInputHandler);
        }

        this.syncHandler = () => this.suggestUsername();
        if (this.hasFirstNameTarget) {
            this.firstNameTarget.addEventListener('input', this.syncHandler);
        }
        if (this.hasLastNameTarget) {
            this.lastNameTarget.addEventListener('input', this.syncHandler);
        }
    }

    disconnect() {
        if (this.hasUsernameTarget && this.usernameInputHandler) {
            this.usernameTarget.removeEventListener('input', this.usernameInputHandler);
        }
        if (this.hasFirstNameTarget && this.syncHandler) {
            this.firstNameTarget.removeEventListener('input', this.syncHandler);
        }
        if (this.hasLastNameTarget && this.syncHandler) {
            this.lastNameTarget.removeEventListener('input', this.syncHandler);
        }
    }

    suggestUsername() {
        if (this.usernameManuallyEdited || !this.hasUsernameTarget) {
            return;
        }

        const first = this.hasFirstNameTarget ? this.firstNameTarget.value : '';
        const last = this.hasLastNameTarget ? this.lastNameTarget.value : '';
        const suggestion = [first, last]
            .filter(Boolean)
            .map((chunk) => chunk.trim().toLowerCase().replace(/\s+/g, ''))
            .join('.');

        if (suggestion) {
            this.usernameTarget.value = suggestion;
        }
    }
}
