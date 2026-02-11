import { Controller } from '@hotwired/stimulus';

const PARALLAX_RANGE = 18;

export default class extends Controller {
    static values = {
        theme: { type: String, default: 'dawn' },
    };

    connect() {
        this.handlePointerMove = this.updateParallax.bind(this);
        window.addEventListener('pointermove', this.handlePointerMove, { passive: true });
        this.element.style.setProperty('--auth-shell-theme', this.themeValue);
        this.element.classList.add('auth-shell--ready');
    }

    disconnect() {
        window.removeEventListener('pointermove', this.handlePointerMove);
    }

    updateParallax(event) {
        const { innerWidth, innerHeight } = window;
        const offsetX = ((event.clientX / innerWidth) - 0.5) * PARALLAX_RANGE;
        const offsetY = ((event.clientY / innerHeight) - 0.5) * PARALLAX_RANGE;

        this.element.style.setProperty('--auth-parallax-x', `${offsetX.toFixed(2)}px`);
        this.element.style.setProperty('--auth-parallax-y', `${offsetY.toFixed(2)}px`);
    }
}
