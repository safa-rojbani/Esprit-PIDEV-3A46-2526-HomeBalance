import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['waitlist'];
    static values = {
        open: Number,
        waitlist: Number,
    };

    connect() {
        if (this.hasWaitlistTarget) {
            this.animateCount();
        }
    }

    animateCount() {
        const endValue = this.waitlistValue || Number(this.waitlistTarget.textContent) || 0;
        const startTime = performance.now();
        const duration = 1600;

        const step = (timestamp) => {
            const progress = Math.min((timestamp - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = Math.round(eased * endValue);
            this.waitlistTarget.textContent = value.toString();
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    }
}
