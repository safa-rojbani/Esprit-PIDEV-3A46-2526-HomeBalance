import { Controller } from '@hotwired/stimulus';

const MAX_BADGES = 12;

export default class extends Controller {
    static values = {
        count: Number,
    };

    connect() {
        const normalized = Math.min(this.countValue || 0, MAX_BADGES);
        const progress = normalized / MAX_BADGES;
        this.element.style.setProperty('--streak-progress', progress.toString());
    }
}
