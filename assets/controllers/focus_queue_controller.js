import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['list'];

    connect() {
        if (!this.hasListTarget) {
            return;
        }
        this.decorateItems();
    }

    decorateItems() {
        const items = this.listTarget.querySelectorAll('li');
        items.forEach((item, index) => {
            item.style.setProperty('--queue-index', index + 1);
            item.animate(
                [
                    { opacity: 0, transform: 'translateY(10px)' },
                    { opacity: 1, transform: 'translateY(0)' },
                ],
                {
                    duration: 400,
                    delay: index * 80,
                    fill: 'forwards',
                    easing: 'ease-out',
                },
            );
        });
    }
}
