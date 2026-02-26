import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['reply', 'aiReplies', 'priorityHint', 'categoryHint'];

    static values = {
        aiRepliesUrl: String,
        classifyUrl: String,
    };

    connect() {
        console.log('support-ticket#connect', {
            hasAiRepliesUrlValue: this.hasAiRepliesUrlValue,
            aiRepliesUrl: this.hasAiRepliesUrlValue ? this.aiRepliesUrlValue : null,
            hasClassifyUrlValue: this.hasClassifyUrlValue,
            classifyUrl: this.hasClassifyUrlValue ? this.classifyUrlValue : null,
        });

        if (this.hasClassifyUrlValue) {
            this.requestClassification();
        }
    }

    async requestClassification() {
        if (!this.hasClassifyUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.classifyUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (this.hasPriorityHintTarget && data.priority) {
                this.priorityHintTarget.textContent = data.priority.toUpperCase();
                this.priorityHintTarget.classList.remove('d-none');
            }
            if (this.hasCategoryHintTarget && data.category) {
                this.categoryHintTarget.textContent = data.category;
                this.categoryHintTarget.classList.remove('d-none');
            }
        } catch (e) {
            // ignore
        }
    }

    async requestAiReplies() {
        if (!this.hasAiRepliesUrlValue) {
            console.warn('support-ticket#requestAiReplies: no aiRepliesUrlValue found');
            return;
        }

        console.log('support-ticket#requestAiReplies: sending request', this.aiRepliesUrlValue);

        if (this.hasAiRepliesTarget) {
            this.aiRepliesTarget.innerHTML = '<span class="text-muted small">Chargement…</span>';
            this.aiRepliesTarget.classList.remove('d-none');
        }

        try {
            const response = await fetch(this.aiRepliesUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            console.log('support-ticket#requestAiReplies: response status', response.status);

            if (!response.ok) {
                if (this.hasAiRepliesTarget) {
                    this.aiRepliesTarget.innerHTML = '<span class="text-danger small">Erreur ('.concat(response.status, '). Vérifiez GEMINI_API_KEY.</span>');
                }
                return;
            }

            const data = await response.json();
            console.log('support-ticket#requestAiReplies: response json', data);
            this.renderSuggestions(data.suggestions || []);
        } catch (e) {
            console.error('support-ticket#requestAiReplies: fetch error', e);
            if (this.hasAiRepliesTarget) {
                this.aiRepliesTarget.innerHTML = '<span class="text-danger small">Erreur réseau ou serveur.</span>';
            }
        }
    }

    useSuggestion(event) {
        const suggestion = event.currentTarget.dataset.suggestion || event.currentTarget.textContent || '';
        if (!suggestion.trim() || !this.hasReplyTarget) {
            return;
        }

        this.replyTarget.value = suggestion;
        this.replyTarget.focus();
    }

    renderSuggestions(suggestions) {
        if (!this.hasAiRepliesTarget) {
            return;
        }

        if (!suggestions.length) {
            this.aiRepliesTarget.innerHTML = '<span class="text-muted small">Aucune suggestion.</span>';
            this.aiRepliesTarget.classList.remove('d-none');
            return;
        }

        const container = document.createElement('div');
        container.className = 'd-flex flex-wrap gap-2';

        suggestions.forEach((text) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary rounded-pill';
            btn.dataset.action = 'click->support-ticket#useSuggestion';
            btn.dataset.suggestion = text;
            btn.textContent = text;
            container.appendChild(btn);
        });

        this.aiRepliesTarget.innerHTML = '';
        this.aiRepliesTarget.appendChild(container);
        this.aiRepliesTarget.classList.remove('d-none');
    }
}

