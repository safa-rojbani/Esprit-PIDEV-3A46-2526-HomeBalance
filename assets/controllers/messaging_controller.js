import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'messages', 'input', 'form', 'attachment', 'attachmentPreview',
        'typingIndicator', 'typingName', 'statusLine', 'replyPreview',
        'smartReplies', 'summaryPanel',
    ];

    static values = {
        mercureUrl:     String,
        mercureToken:   String,
        conversationId: String,
    };

    connect() {
        this.scrollToBottom();
        this._typingTimer     = null;
        this._isTyping        = false;
        this._eventSource     = null;
        this._typingExpiry    = {};
        this._parentMessageId = null;
        if (this.mercureUrlValue && this.mercureTokenValue) {
            this._connectMercure();
        }
        this._postPresence(true);
        this._boundOffline = () => this._postPresence(false);
        window.addEventListener('beforeunload', this._boundOffline);
    }

    disconnect() {
        if (this._eventSource) {
            this._eventSource.close();
            this._eventSource = null;
        }
        window.removeEventListener('beforeunload', this._boundOffline);
        this._postPresence(false);
    }

    _connectMercure() {
        const url = new URL(this.mercureUrlValue);
        if (this.conversationIdValue) {
            url.searchParams.append('topic', `messaging/conversation/${this.conversationIdValue}`);
        }
        const userId = this._jwtSub(this.mercureTokenValue);
        if (userId) {
            url.searchParams.append('topic', `messaging/user/${userId}`);
            url.searchParams.append('topic', `messaging/user/${userId}/presence`);
        }
        const authUrl = new URL(url.toString());
        authUrl.searchParams.set('authorization', this.mercureTokenValue);
        this._eventSource = new EventSource(authUrl.toString());
        this._eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this._handleMercureEvent(data);
            } catch (e) {}
        };
        this._eventSource.onerror = () => {
            setTimeout(() => {
                if (this._eventSource) {
                    this._eventSource.close();
                    this._connectMercure();
                }
            }, 5000);
        };
    }

    _handleMercureEvent(data) {
        switch (data.type) {
            case 'new_message':    this._onNewMessage(data); break;
            case 'typing':         this._onTyping(data); break;
            case 'read_receipt':   this._onReadReceipt(data); break;
            case 'presence':       this._onPresence(data); break;
            case 'reaction_update': this._onReactionUpdate(data); break;
            case 'smart_replies':  this._onSmartReplies(data); break;
            case 'conversation_summary': this._onConversationSummary(data); break;
        }
    }

    _onNewMessage(data) {
        if (String(data.conversationId) !== String(this.conversationIdValue)) {
            this._incrementSidebarBadge(data.conversationId);
            return;
        }
        const placeholder = document.getElementById('empty-chat-placeholder');
        if (placeholder) placeholder.remove();
        const list = document.getElementById('chat-messages-list');
        if (!list) return;
        const bubble = this._buildMessageBubble(data);
        list.appendChild(bubble);
        this.scrollToBottom();
        this._markRead([data.messageId]);
    }

    _buildMessageBubble(data) {
        const isMe = data.senderId === this._currentUserId();
        const wrapper = document.createElement('div');
        wrapper.id = `message-${data.messageId}`;
        wrapper.className = `d-flex ${isMe ? 'justify-content-end' : 'justify-content-start'} mb-3`;
        const time = data.sentAt ? new Date(data.sentAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        let replyHtml = '';
        if (data.parent) {
            replyHtml = `
                <div class="reply-quote mb-2">
                    <div class="reply-quote-author">${this._escapeHtml(data.parent.senderName)}</div>
                    <div class="reply-quote-content text-truncate">${this._escapeHtml(data.parent.content)}</div>
                </div>`;
        }
        const attachmentHtml = data.attachmentURL ? `<div class="mt-2 text-center"><img src="/${data.attachmentURL}" class="img-fluid rounded" style="max-height:240px;" alt="Attachment"></div>` : '';
        if (isMe) {
            wrapper.innerHTML = `
                <div style="max-width:72%;">
                    <div class="chat-bubble-me">
                        ${replyHtml}
                        <p class="mb-0" style="white-space:pre-wrap;">${this._escapeHtml(data.content || '')}</p>
                        ${attachmentHtml}
                    </div>
                    <div class="d-flex align-items-center mt-1 gap-1 justify-content-end">
                        <small class="text-muted" style="font-size:.68rem;">${time}</small>
                        <i class="bx bx-check-double text-muted" style="font-size:.85rem;" id="tick-${data.messageId}"></i>
                    </div>
                </div>`;
        } else {
            const initials = (data.senderName || '?').slice(0, 1).toUpperCase();
            const avatar = data.senderAvatar
                ? `<img src="${data.senderAvatar}" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;" alt="">`
                : `<span class="avatar-initial rounded-circle bg-label-secondary d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:.8rem;">${initials}</span>`;
            wrapper.innerHTML = `
                <div class="me-2 flex-shrink-0" style="margin-top:2px;">${avatar}</div>
                <div style="max-width:72%;">
                    <div class="chat-bubble-other">
                        ${replyHtml}
                        <p class="mb-0" style="white-space:pre-wrap;">${this._escapeHtml(data.content || '')}</p>
                        ${attachmentHtml}
                    </div>
                    <div class="d-flex align-items-center mt-1 gap-1">
                        <small class="text-muted" style="font-size:.68rem;">${time}</small>
                    </div>
                </div>`;
        }
        return wrapper;
    }

    _onTyping(data) {
        if (!this.hasTypingIndicatorTarget) return;
        const key = String(data.userId);
        if (data.isTyping) {
            if (this.hasTypingNameTarget) this.typingNameTarget.textContent = `${data.userName} is typing`;
            this.typingIndicatorTarget.classList.remove('d-none');
            clearTimeout(this._typingExpiry[key]);
            this._typingExpiry[key] = setTimeout(() => { this.typingIndicatorTarget.classList.add('d-none'); }, 3000);
        } else {
            clearTimeout(this._typingExpiry[key]);
            this.typingIndicatorTarget.classList.add('d-none');
        }
    }

    _onReadReceipt(data) {
        const tick = document.getElementById(`tick-${data.messageId}`);
        if (tick) { tick.classList.remove('text-muted'); tick.classList.add('text-primary'); }
    }

    _onPresence(data) {
        const dot = document.getElementById(`presence-dot-${data.userId}`);
        if (!dot) return;
        if (data.online) { dot.textContent = 'Online'; dot.className = 'badge rounded-pill bg-success'; }
        else { dot.textContent = 'Offline'; dot.className = 'badge rounded-pill bg-secondary'; }
    }

    _onReactionUpdate(data) { this._renderReactionBar(data.messageId, data.reactions); }

    react(event) {
        const btn = event.currentTarget;
        const messageId = btn.dataset.messageId;
        const emoji = btn.dataset.emoji;
        if (!messageId || !emoji) return;
        fetch(`/portal/messaging/message/${messageId}/react`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ emoji }),
        }).then(r => r.json()).then(data => { if (data.ok) this._renderReactionBar(data.messageId, data.reactions); });
    }

    toggleReactionPicker(event) {
        const btn = event.currentTarget;
        const messageId = btn.dataset.messageId;
        if (!messageId) return;
        const existing = document.getElementById('reaction-picker');
        if (existing) {
            if (existing.dataset.for === String(messageId)) { existing.remove(); return; }
            existing.remove();
        }
        const allowed = ['', '', '', '', ''];
        const picker = document.createElement('div');
        picker.id = 'reaction-picker';
        picker.dataset.for = String(messageId);
        picker.style.cssText = 'position:absolute;z-index:200;background:#fff;border:1px solid #e4e6f0;border-radius:2rem;padding:6px 10px;display:flex;gap:6px;box-shadow:0 4px 16px rgba(0,0,0,.12);';
        allowed.forEach(emoji => {
            const span = document.createElement('button');
            span.type = 'button'; span.textContent = emoji;
            span.style.cssText = 'font-size:1.3rem;background:none;border:none;cursor:pointer;padding:2px;border-radius:50%;transition:transform .1s;';
            span.addEventListener('click', () => {
                picker.remove();
                fetch(`/portal/messaging/message/${messageId}/react`, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ emoji }),
                }).then(r => r.json()).then(data => { if (data.ok) this._renderReactionBar(data.messageId, data.reactions); });
            });
            picker.appendChild(span);
        });
        const rect = btn.getBoundingClientRect();
        const chatBody = document.getElementById('chat-messages');
        if (chatBody) {
            const bodyRect = chatBody.getBoundingClientRect();
            picker.style.top = `${rect.top - bodyRect.top - 50}px`;
            picker.style.left = `${rect.left - bodyRect.left}px`;
            chatBody.style.position = 'relative';
            chatBody.appendChild(picker);
        }
        setTimeout(() => document.addEventListener('click', (e) => { if (!picker.contains(e.target)) picker.remove(); }, {once: true}), 0);
    }

    _renderReactionBar(messageId, reactions) {
        const bar = document.getElementById(`reactions-${messageId}`);
        if (!bar) return;
        const currentUserId = String(this._currentUserId() ?? '');
        bar.innerHTML = '';
        Object.entries(reactions).forEach(([emoji, data]) => {
            const userIds = Object.keys(data.users);
            const iReacted = userIds.includes(currentUserId);
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = `reaction-pill btn btn-sm ${iReacted ? 'btn-primary' : 'btn-outline-secondary'}`;
            btn.dataset.messageId = String(messageId); btn.dataset.emoji = emoji;
            btn.innerHTML = `${emoji} <span class="reaction-count">${data.count}</span>`;
            btn.addEventListener('click', (e) => this.react(e));
            bar.appendChild(btn);
        });
    }

    reply(event) {
        const btn = event.currentTarget;
        const messageId = btn.dataset.messageId;
        const sender = btn.dataset.senderName || 'Unknown';
        const content = btn.dataset.content || '';
        if (!messageId) return;
        this._parentMessageId = messageId;
        let parentField = this.formTarget.querySelector('input[name="message[parentMessageId]"]');
        if (!parentField) {
            parentField = document.createElement('input');
            parentField.type = 'hidden'; parentField.name = 'message[parentMessageId]';
            this.formTarget.appendChild(parentField);
        }
        parentField.value = messageId;
        this._showReplyPreview(messageId, sender, content);
    }

    cancelReply() {
        this._parentMessageId = null;
        const parentField = this.formTarget.querySelector('input[name="message[parentMessageId]"]');
        if (parentField) parentField.remove();
        this._hideReplyPreview();
    }

    _showReplyPreview(messageId, sender, content) {
        if (!this.hasReplyPreviewTarget) return;
        const truncated = content.length > 80 ? content.slice(0, 80) + '' : content;
        this.replyPreviewTarget.innerHTML = `
            <div class="reply-preview-bar">
                <div class="reply-preview-label">Replying to ${this._escapeHtml(sender)}</div>
                <div class="reply-preview-content">${this._escapeHtml(truncated)}</div>
            </div>
            <button type="button" class="reply-preview-cancel" data-action="messaging#cancelReply"></button>`;
        this.replyPreviewTarget.classList.remove('d-none');
        this.inputTarget.focus();
    }

    _hideReplyPreview() { if (this.hasReplyPreviewTarget) this.replyPreviewTarget.classList.add('d-none'); }

    handleTyping() {
        if (!this.conversationIdValue) return;
        if (!this._isTyping) { this._isTyping = true; this._sendTyping(true); }
        clearTimeout(this._typingTimer);
        this._typingTimer = setTimeout(() => { this._isTyping = false; this._sendTyping(false); }, 2000);
    }

    _sendTyping(isTyping) {
        fetch(`/portal/messaging/${this.conversationIdValue}/typing`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ isTyping }), keepalive: true,
        }).catch(() => {});
    }

    _markRead(messageIds) {
        if (!this.conversationIdValue || messageIds.length === 0) return;
        fetch(`/portal/messaging/${this.conversationIdValue}/read`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ messageIds }), keepalive: true,
        }).catch(() => {});
    }

    _postPresence(online) {
        fetch('/portal/messaging/presence', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ online }), keepalive: true,
        }).catch(() => {});
    }

    _incrementSidebarBadge(conversationId) {
        const link = document.querySelector(`[href*="/portal/messaging/${conversationId}"]`);
        if (!link) return;
        let badge = link.querySelector('.badge.bg-danger');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge badge-center rounded-pill bg-danger';
            badge.style.cssText = 'width:1.2rem;height:1.2rem;font-size:.65rem;';
            badge.textContent = '1';
            const row = link.querySelector('.d-flex.justify-content-between.align-items-center:last-child');
            if (row) row.appendChild(badge);
        } else { badge.textContent = String(parseInt(badge.textContent || '0', 10) + 1); }
    }

    scrollToBottom() { if (this.hasMessagesTarget) this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight; }

    triggerUpload(event) { if (event) event.preventDefault(); if (this.hasAttachmentTarget) this.attachmentTarget.click(); }

    handleSelection() {
        if (this.hasAttachmentTarget && this.attachmentTarget.files.length > 0) {
            const fileName = this.attachmentTarget.files[0].name;
            if (this.hasAttachmentPreviewTarget) {
                this.attachmentPreviewTarget.innerText = ` ${fileName}`;
                this.attachmentPreviewTarget.classList.remove('d-none');
            }
        }
    }

    clearInput(event) {
        if (event.detail.success) {
            this.formTarget.reset();
            if (this.hasAttachmentPreviewTarget) this.attachmentPreviewTarget.classList.add('d-none');
            this.cancelReply();
            this.inputTarget.focus();
            setTimeout(() => this.scrollToBottom(), 100);
        }
    }

    handleKeydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault(); this._isTyping = false; clearTimeout(this._typingTimer); this._sendTyping(false); this.formTarget.requestSubmit();
        }
    }

    _escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    _jwtSub(token) {
        try { const payload = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))); return payload.sub ?? null; } catch { return null; }
    }
    _currentUserId() { return document.querySelector('meta[name="user-id"]')?.content ?? null; }

    // ========== AI Methods ==========

    requestSmartReplies() {
        if (!this.conversationIdValue) return;
        fetch(`/portal/messaging/${this.conversationIdValue}/ai/suggest-replies`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
        }).catch(() => {});
    }

    requestSummarize(limit = 50) {
        if (!this.conversationIdValue) return;
        fetch(`/portal/messaging/${this.conversationIdValue}/ai/summarize`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ limit }),
        }).catch(() => {});
    }

    useSmartReply(event) {
        const btn = event.currentTarget;
        const suggestion = btn.dataset.suggestion;
        if (!suggestion) return;
        
        // Fill the input with the suggestion
        if (this.hasInputTarget) {
            this.inputTarget.value = suggestion;
            this.inputTarget.focus();
        }
        
        // Mark as used
        fetch(`/portal/messaging/${this.conversationIdValue}/ai/use-reply`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ suggestion }),
        }).catch(() => {});
    }

    closeSummary() {
        if (this.hasSummaryPanelTarget) {
            this.summaryPanelTarget.classList.add('d-none');
        }
    }

    _onSmartReplies(data) {
        if (String(data.conversationId) !== String(this.conversationIdValue)) return;
        if (!data.suggestions || data.suggestions.length === 0) return;
        
        // Find or create smart replies container
        let container = document.getElementById('smart-replies-container');
        if (!container) {
            const footer = this.formTarget;
            if (!footer) return;
            container = document.createElement('div');
            container.id = 'smart-replies-container';
            container.className = 'smart-replies-container px-3 py-2 border-top';
            footer.parentNode.insertBefore(container, footer);
        }
        
        // Build suggestion pills
        container.innerHTML = `
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span class="text-muted small">
                    <i class="bx bx-lightbulb me-1"></i>Suggestions:
                </span>
                ${data.suggestions.map(s => `
                    <button type="button" 
                            class="btn btn-sm btn-outline-primary rounded-pill"
                            data-action="messaging#useSmartReply"
                            data-suggestion="${this._escapeHtml(s)}">
                        ${this._escapeHtml(s)}
                    </button>
                `).join('')}
            </div>
        `;
    }

    _onConversationSummary(data) {
        if (String(data.conversationId) !== String(this.conversationIdValue)) return;
        if (!data.summary) return;
        
        // Find or create summary panel
        let panel = document.getElementById('summary-panel-container');
        if (!panel) {
            const messages = document.getElementById('chat-messages-list');
            if (!messages) return;
            panel = document.createElement('div');
            panel.id = 'summary-panel-container';
            messages.parentNode.insertBefore(panel, messages);
        }
        
        const generatedAt = data.generatedAt ? new Date(data.generatedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        panel.innerHTML = `
            <div class="card bg-light border-0 mb-3">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-file-find text-primary"></i>
                            <span class="fw-semibold small">Summary</span>
                            <span class="text-muted" style="font-size: 0.7rem;">
                                — ${data.messageCount || 0} messages • ${generatedAt}
                            </span>
                        </div>
                        <button type="button" 
                                class="btn btn-icon btn-sm btn-text-secondary"
                                data-action="messaging#closeSummary"
                                title="Close summary">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                    <p class="mb-0 mt-2 small text-dark">
                        ${this._escapeHtml(data.summary)}
                    </p>
                </div>
            </div>
        `;
    }
}
