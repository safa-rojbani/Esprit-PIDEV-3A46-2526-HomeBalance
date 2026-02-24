import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        endpoint: String,
        pollInterval: { type: Number, default: 20000 },
    };

    connect() {
        this.lastSeenId = this.readLastSeenId();
        this.poll = this.poll.bind(this);
        this.poll();
        this.timer = window.setInterval(this.poll, this.pollIntervalValue);
    }

    disconnect() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }

    async poll() {
        if (!this.hasEndpointValue || !this.endpointValue) {
            return;
        }

        try {
            const url = new URL(this.endpointValue, window.location.origin);
            url.searchParams.set('since', String(this.lastSeenId));

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const notifications = Array.isArray(data.notifications) ? data.notifications : [];

            notifications.forEach((item) => this.renderNotification(item));

            if (typeof data.maxId === 'number' && Number.isFinite(data.maxId)) {
                this.lastSeenId = Math.max(this.lastSeenId, data.maxId);
                this.persistLastSeenId(this.lastSeenId);
            }
        } catch (error) {
            // network/transient failures should not break the page
        }
    }

    renderNotification(item) {
        if (!item || typeof item !== 'object') {
            return;
        }

        const channels = item.channels && typeof item.channels === 'object' ? item.channels : {};
        const title = typeof item.title === 'string' && item.title !== '' ? item.title : 'Notification HomeBalance';
        const body = typeof item.body === 'string' ? item.body : '';

        if (channels.app) {
            this.showInAppToast(title, body);
        }

        if (channels.browser) {
            this.showBrowserNotification(title, body);
        }

        if (typeof item.id === 'number' && Number.isFinite(item.id)) {
            this.lastSeenId = Math.max(this.lastSeenId, item.id);
            this.persistLastSeenId(this.lastSeenId);
        }
    }

    showInAppToast(title, body) {
        const host = this.toastHost();
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center border-0 text-bg-primary';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${this.escapeHtml(title)}</strong><br />
                    <small>${this.escapeHtml(body)}</small>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        host.appendChild(toast);

        if (window.bootstrap && window.bootstrap.Toast) {
            const instance = window.bootstrap.Toast.getOrCreateInstance(toast, { delay: 6000 });
            toast.addEventListener('hidden.bs.toast', () => toast.remove(), { once: true });
            instance.show();
            return;
        }

        window.setTimeout(() => toast.remove(), 6000);
    }

    async showBrowserNotification(title, body) {
        if (!('Notification' in window)) {
            return;
        }

        let permission = Notification.permission;
        if (permission === 'default') {
            permission = await Notification.requestPermission();
        }

        if (permission !== 'granted') {
            return;
        }

        // Avoid spamming system notifications while the tab is in focus.
        if (document.visibilityState === 'visible') {
            return;
        }

        new Notification(title, { body });
    }

    toastHost() {
        let host = document.getElementById('hb-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'hb-toast-host';
            host.className = 'toast-container position-fixed top-0 end-0 p-3';
            host.style.zIndex = '1100';
            document.body.appendChild(host);
        }

        return host;
    }

    readLastSeenId() {
        const raw = window.localStorage.getItem(this.storageKey());
        const parsed = Number.parseInt(raw || '0', 10);

        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    }

    persistLastSeenId(id) {
        window.localStorage.setItem(this.storageKey(), String(id));
    }

    storageKey() {
        return 'hb.browser_notifications.last_seen_id';
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}
