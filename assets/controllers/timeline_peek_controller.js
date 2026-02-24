import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['timeline'];

    connect() {
        if (this.hasTimelineTarget) {
            this.timelineTarget.scrollTop = 0;
        }
    }
}
