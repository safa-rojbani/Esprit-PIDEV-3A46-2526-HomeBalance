import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        score: Number,
    };

    static targets = ['ring', 'value'];

    connect() {
        this.render();
    }

    scoreValueChanged() {
        this.render();
    }

    render() {
        const score = Math.max(0, Math.min(this.scoreValue ?? 0, 100));
        if (this.hasRingTarget) {
            this.ringTarget.style.setProperty('--pulse-score', `${score}`);
        }
        if (this.hasValueTarget) {
            this.valueTarget.textContent = `${score}%`;
        }
    }
}
