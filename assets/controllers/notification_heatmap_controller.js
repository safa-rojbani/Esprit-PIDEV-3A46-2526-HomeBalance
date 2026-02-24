import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['cell'];
    static values = {
        matrix: Object,
        endpoint: String,
        csrf: String,
    };

    connect() {
        this.refreshFromMatrix();
    }

    refreshFromMatrix() {
        if (!this.hasCellTarget || !this.matrixValue) {
            return;
        }

        this.cellTargets.forEach((cell) => {
            const type = cell.dataset.type;
            const channel = cell.dataset.channel;
            if (!type || !channel || !this.matrixValue[type]) {
                return;
            }
            const enabled = Boolean(this.matrixValue[type][channel]);
            this.updateCellState(cell, enabled);
            cell.addEventListener('click', () => this.toggleCell(cell));
        });
    }

    toggleCell(cell) {
        const type = cell.dataset.type;
        const channel = cell.dataset.channel;
        if (!type || !channel) {
            return;
        }
        const enabled = cell.dataset.enabled !== 'true';
        this.updateCellState(cell, enabled);
        this.persistToggle(type, channel, enabled);
    }

    updateCellState(cell, enabled) {
        cell.dataset.enabled = enabled ? 'true' : 'false';
        cell.classList.toggle('is-active', enabled);
        cell.querySelector('span').textContent = enabled ? 'On' : 'Off';
    }

    async persistToggle(type, channel, enabled) {
        if (!this.endpointValue || !this.csrfValue) {
            return;
        }

        try {
            const response = await fetch(this.endpointValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfValue,
                },
                body: JSON.stringify({ type, channel, enabled }),
            });

            if (!response.ok) {
                throw new Error('Failed to update notification preference.');
            }
        } catch (error) {
            this.updateCellState(
                this.cellTargets.find(
                    (cell) => cell.dataset.type === type && cell.dataset.channel === channel
                ),
                !enabled
            );
        }
    }
}
