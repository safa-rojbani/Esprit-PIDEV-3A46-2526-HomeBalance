import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'messages',
        'typingIndicator',
        'typingName',
        'form',
        'attachment',
        'attachmentPreview',
        'input',
        'replyPreview',
        'parentMessageId',
        'smartReplies',
        'summaryPanel',
    ];

    static values = {
        mercureUrl: String,
        mercureToken: String,
        conversationId: Number,
    };

    connect() {
        const url = this.mercureUrlValue;
        const token = this.mercureTokenValue;
        const conversationId = this.conversationIdValue || null;
        const currentUserId = document.body.dataset.currentUserId || null;

        if (!url || !token || !currentUserId) {
            return;
        }

        try {
            const hubUrl = new URL(url);
            hubUrl.searchParams.append('topic', `messaging/user/${currentUserId}`);
            if (conversationId) {
                hubUrl.searchParams.append('topic', `messaging/conversation/${conversationId}`);
            }
            hubUrl.searchParams.append('access_token', token);

            this.eventSource = new EventSource(hubUrl.toString());

            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this._handleMercureEvent(data);
                } catch (e) {
                    // ignore malformed payloads
                }
            };
        } catch (e) {
            // ignore connection errors silently
        }
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    clearInput(event) {
        // Clear textarea
        if (this.hasInputTarget) {
            this.inputTarget.value = '';
        }

        // Clear parent message (reply)
        if (this.hasParentMessageIdTarget) {
            this.parentMessageIdTarget.value = '';
        }
        if (this.hasReplyPreviewTarget) {
            this.replyPreviewTarget.classList.add('d-none');
            this.replyPreviewTarget.innerHTML = '';
        }

        // Clear attachment preview
        if (this.hasAttachmentPreviewTarget) {
            this.attachmentPreviewTarget.classList.add('d-none');
            this.attachmentPreviewTarget.innerHTML = '';
        }
    }

    triggerUpload() {
        if (this.hasAttachmentTarget) {
            this.attachmentTarget.click();
        }
    }

    handleSelection(event) {
        const input = event.currentTarget;
        if (!this.hasAttachmentPreviewTarget || !input.files || input.files.length === 0) {
            return;
        }

        const file = input.files[0];
        const span = this.attachmentPreviewTarget.querySelector('span') || this.attachmentPreviewTarget;
        span.textContent = file.name;
        this.attachmentPreviewTarget.classList.remove('d-none');
    }

    handleKeydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (this.hasFormTarget) {
                this.formTarget.requestSubmit();
            }
        }
    }

    handleTyping() {
        // Optional: could send typing events via Mercure later.
    }

    reply(event) {
        const button = event.currentTarget;
        const messageId = button.dataset.messageId;
        const senderName = button.dataset.senderName || '';
        const content = button.dataset.content || '';

        if (!this.hasParentMessageIdTarget || !this.hasReplyPreviewTarget || !messageId) {
            return;
        }

        this.parentMessageIdTarget.value = messageId;

        this.replyPreviewTarget.innerHTML = `
            <div class="d-flex align-items-start gap-2">
                <div class="small text-muted">Replying to <strong>${this._escapeHtml(senderName)}</strong></div>
                <button type="button" class="btn btn-sm btn-link p-0 ms-auto" data-action="click->messaging#cancelReply">
                    &times;
                </button>
            </div>
            <div class="small text-truncate text-muted mt-1">${this._escapeHtml(content)}</div>
        `;
        this.replyPreviewTarget.classList.remove('d-none');
    }

    cancelReply() {
        if (this.hasParentMessageIdTarget) {
            this.parentMessageIdTarget.value = '';
        }
        if (this.hasReplyPreviewTarget) {
            this.replyPreviewTarget.classList.add('d-none');
            this.replyPreviewTarget.innerHTML = '';
        }
    }

    async react(event) {
        const button = event.currentTarget;
        const messageId = button.dataset.messageId;
        const emoji = button.dataset.emoji;

        if (!messageId || !emoji) {
            return;
        }

        const url = `/portal/messaging/message/${encodeURIComponent(messageId)}/react`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ emoji }),
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (!data || !data.reactions || !data.messageId) {
                return;
            }

            this._renderReactions(data.messageId, data.reactions);
        } catch (e) {
            // Fail silently to avoid breaking the UI
        }
    }

    toggleReactionPicker(event) {
        const trigger = event.currentTarget;
        const messageId = trigger.dataset.messageId;

        if (!messageId) {
            return;
        }

        // Close existing picker if it is already open for this message
        if (this._reactionPicker && this._reactionPicker.dataset.messageId === String(messageId)) {
            this._reactionPicker.remove();
            this._reactionPicker = null;
            return;
        }

        // Close any existing picker
        if (this._reactionPicker) {
            this._reactionPicker.remove();
            this._reactionPicker = null;
        }

        const picker = document.createElement('div');
        picker.className = 'reaction-picker';
        picker.dataset.messageId = String(messageId);

        const emojis = ['👍', '❤️', '😂', '😮', '😢', '🎉'];
        emojis.forEach((emoji) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = emoji;
            btn.setAttribute('title', emoji);
            btn.dataset.action = 'click->messaging#react';
            btn.dataset.messageId = String(messageId);
            btn.dataset.emoji = emoji;
            picker.appendChild(btn);
        });

        // Position picker relative to the bubble group
        const group = trigger.closest('.message-bubble-group') || trigger.parentElement;
        group.appendChild(picker);
        this._reactionPicker = picker;
    }

    toggleEmojiPicker(event) {
        const trigger = event.currentTarget;

        // Close existing input picker if it is already open
        if (this._inputEmojiPicker) {
            this._inputEmojiPicker.remove();
            this._inputEmojiPicker = null;
            return;
        }

        const picker = document.createElement('div');
        picker.className = 'reaction-picker';
        picker.style.bottom = 'calc(100% + 15px)';
        picker.style.left = '0';

        const emojis = ['😊', '😂', '❤️', '👍', '🙏', '🔥', '✨', '😢', '😮', '🙌'];
        emojis.forEach((emoji) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = emoji;
            btn.dataset.action = 'click->messaging#insertEmoji';
            btn.dataset.emoji = emoji;
            picker.appendChild(btn);
        });

        const footerRow = trigger.closest('.footer-form-row') || trigger.parentElement;
        footerRow.appendChild(picker);
        this._inputEmojiPicker = picker;

        // Close when clicking outside
        const closePicker = (e) => {
            if (!picker.contains(e.target) && e.target !== trigger && !trigger.contains(e.target)) {
                picker.remove();
                this._inputEmojiPicker = null;
                document.removeEventListener('click', closePicker);
            }
        };
        setTimeout(() => document.addEventListener('click', closePicker), 10);
    }

    insertEmoji(event) {
        const emoji = event.currentTarget.dataset.emoji;
        if (this.hasInputTarget && emoji) {
            const start = this.inputTarget.selectionStart;
            const end = this.inputTarget.selectionEnd;
            const text = this.inputTarget.value;
            this.inputTarget.value = text.substring(0, start) + emoji + text.substring(end);
            this.inputTarget.focus();
            this.inputTarget.selectionStart = this.inputTarget.selectionEnd = start + emoji.length;
        }
    }

    requestSmartReplies() {
        const conversationId = this.conversationIdValue;
        if (!conversationId) {
            return;
        }

        fetch(`/portal/messaging/${conversationId}/ai/suggest-replies`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        }).catch(() => { });
    }

    requestSummary() {
        const conversationId = this.conversationIdValue;
        if (!conversationId) {
            return;
        }

        const payload = { limit: 50 };

        fetch(`/portal/messaging/${conversationId}/ai/summarize`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        }).catch(() => { });
    }

    closeSummary() {
        if (this.hasSummaryPanelTarget) {
            this.summaryPanelTarget.innerHTML = '';
        }
    }

    useSmartReply(event) {
        const button = event.currentTarget;
        const suggestion = button.dataset.suggestion || button.textContent || '';

        if (!suggestion.trim()) {
            return;
        }

        if (this.hasInputTarget) {
            this.inputTarget.value = suggestion;
            this.inputTarget.focus();
        }

        const conversationId = this.conversationIdValue;
        if (!conversationId) {
            return;
        }

        fetch(`/portal/messaging/${conversationId}/ai/use-reply`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ suggestion }),
        }).catch(() => { });
    }

    _renderReactions(messageId, reactions) {
        const container = document.getElementById(`reactions-${messageId}`);
        if (!container) return;

        container.innerHTML = '';

        if (!reactions || Object.keys(reactions).length === 0) {
            container.classList.add('d-none');
            return;
        }

        container.classList.remove('d-none');
        const currentUserId = document.body.dataset.currentUserId || null;

        Object.entries(reactions).forEach(([emoji, data]) => {
            const users = data.users || {};
            const count = data.count || 0;
            const userIds = Object.keys(users);
            const iReacted = currentUserId && userIds.includes(String(currentUserId));

            const pill = document.createElement('div');
            pill.className = `reaction-pill-modern${iReacted ? ' active' : ''}`;
            pill.dataset.action = 'click->messaging#react';
            pill.dataset.messageId = String(messageId);
            pill.dataset.emoji = emoji;
            pill.title = Object.values(users).join(', ');
            pill.innerHTML = `${emoji}<span>${count}</span>`;

            container.appendChild(pill);
        });
    }

    _handleMercureEvent(data) {
        if (!data || !data.type) {
            return;
        }

        const conversationId = this.conversationIdValue || null;

        if (data.type === 'smart_replies') {
            if (conversationId && data.conversationId && Number(data.conversationId) !== Number(conversationId)) {
                return;
            }
            if (Array.isArray(data.suggestions)) {
                this._renderSmartReplies(data.suggestions);
            }
            return;
        }

        if (data.type === 'conversation_summary') {
            if (conversationId && data.conversationId && Number(data.conversationId) !== Number(conversationId)) {
                return;
            }
            this._renderSummary(data);
        }
    }

    _renderSmartReplies(suggestions) {
        if (!this.hasSmartRepliesTarget) {
            return;
        }

        if (!suggestions || suggestions.length === 0) {
            this.smartRepliesTarget.innerHTML = '';
            this.smartRepliesTarget.classList.add('d-none');
            return;
        }

        const container = document.createElement('div');
        container.className = 'd-flex gap-2 align-items-center mb-2';

        const label = document.createElement('span');
        label.className = 'text-muted small';
        label.innerHTML = '<i class="bx bx-lightbulb me-1 text-warning"></i> Suggestions:';
        container.appendChild(label);

        const list = document.createElement('div');
        list.className = 'd-flex gap-2 flex-wrap';

        suggestions.forEach((text) => {
            const pill = document.createElement('div');
            pill.className = 'reaction-pill-modern py-1 px-3';
            pill.style.cursor = 'pointer';
            pill.style.background = '#eef2ff';
            pill.style.color = '#6366f1';
            pill.style.borderColor = '#c7d2fe';
            pill.dataset.action = 'click->messaging#useSmartReply';
            pill.dataset.suggestion = text;
            pill.textContent = text;
            list.appendChild(pill);
        });

        container.appendChild(list);

        this.smartRepliesTarget.innerHTML = '';
        this.smartRepliesTarget.appendChild(container);
        this.smartRepliesTarget.classList.remove('d-none');
    }

    _renderSummary(data) {
        if (!this.hasSummaryPanelTarget) {
            return;
        }

        const messageCount = data.messageCount || 0;
        const summary = data.summary || '';

        if (!summary) {
            this.summaryPanelTarget.innerHTML = '';
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'summary-panel card bg-light border-0 mb-2';

        const body = document.createElement('div');
        body.className = 'card-body py-2 px-3';

        const header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-start';

        const left = document.createElement('div');
        left.className = 'd-flex align-items-center gap-2';
        left.innerHTML = `<i class="bx bx-file-find text-primary"></i>
            <span class="fw-semibold small">Summary</span>
            <span class="text-muted" style="font-size: 0.7rem;">— ${messageCount} messages</span>`;

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn btn-icon btn-sm btn-text-secondary';
        closeBtn.dataset.action = 'click->messaging#closeSummary';
        closeBtn.innerHTML = '<i class="bx bx-x"></i>';

        header.appendChild(left);
        header.appendChild(closeBtn);

        const p = document.createElement('p');
        p.className = 'mb-0 mt-2 small text-dark';
        p.textContent = summary;

        body.appendChild(header);
        body.appendChild(p);
        wrapper.appendChild(body);

        this.summaryPanelTarget.innerHTML = '';
        this.summaryPanelTarget.appendChild(wrapper);
    }

    _escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

