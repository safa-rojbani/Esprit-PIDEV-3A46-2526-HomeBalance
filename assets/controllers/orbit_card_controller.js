import { Controller } from '@hotwired/stimulus';

const TILT_LIMIT = 8;

export default class extends Controller {
    connect() {
        this.handleMouseMove = this.tilt.bind(this);
        this.element.addEventListener('pointermove', this.handleMouseMove, { passive: true });
        this.element.addEventListener('pointerleave', () => this.resetTilt(), { passive: true });
    }

    disconnect() {
        this.element.removeEventListener('pointermove', this.handleMouseMove);
    }

    tilt(event) {
        const bounds = this.element.getBoundingClientRect();
        const x = event.clientX - bounds.left;
        const y = event.clientY - bounds.top;
        const xPercent = (x / bounds.width) * 2 - 1;
        const yPercent = (y / bounds.height) * 2 - 1;
        const rotateX = Math.max(Math.min(-yPercent * TILT_LIMIT, TILT_LIMIT), -TILT_LIMIT);
        const rotateY = Math.max(Math.min(xPercent * TILT_LIMIT, TILT_LIMIT), -TILT_LIMIT);

        this.element.style.setProperty('--tilt-x', `${rotateX}deg`);
        this.element.style.setProperty('--tilt-y', `${rotateY}deg`);
        this.element.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    }

    resetTilt() {
        this.element.style.transform = '';
    }
}
