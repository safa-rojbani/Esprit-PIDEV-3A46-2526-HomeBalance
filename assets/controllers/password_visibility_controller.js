import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'toggle'];

    toggle() {
        if (!this.hasInputTarget) {
            return;
        }

        const isHidden = this.inputTarget.type === 'password';
        this.inputTarget.type = isHidden ? 'text' : 'password';

        if (this.hasToggleTarget) {
            this.toggleTarget.classList.toggle('is-active', isHidden);
            this.toggleTarget.querySelector('i')?.classList.toggle('bx-show', isHidden);
        }
    }
}
