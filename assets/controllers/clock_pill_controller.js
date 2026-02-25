import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['label'];

    connect() {
        this.tick();
        this.intervalId = window.setInterval(() => this.tick(), 60_000);
    }

    disconnect() {
        if (this.intervalId) {
            window.clearInterval(this.intervalId);
        }
    }

    tick() {
        const now = new Date();
        const formatted = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = formatted;
        }
    }
}
