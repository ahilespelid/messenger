import echo from './echo';
import { enablePushNotifications } from './push';

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export default function operatorApp({ conversations, activeToken, initialMessages }) {
    return {
        conversations: conversations || [],
        activeToken: activeToken || null,
        messages: initialMessages || [],
        body: '',
        sending: false,
        onlineClient: false,
        conversationChannel: null,
        editingId: null,

        init() {
            enablePushNotifications();

            echo.private('operator-inbox').listen('.conversation.updated', (e) => {
                this.upsertConversation(e);
            });

            if (this.activeToken) {
                this.joinConversationChannel(this.activeToken);
            }
        },

        formatTime,

        activeClientName() {
            const c = this.conversations.find((c) => c.token === this.activeToken);
            return c ? c.client_name : '';
        },

        upsertConversation(data) {
            const existing = this.conversations.find((c) => c.token === data.token);

            if (existing) {
                existing.last_message = data.last_message;
                existing.last_message_at = data.last_message_at;
                existing.unread_count = this.activeToken === data.token ? 0 : data.unread_count;
            } else {
                this.conversations.push({
                    token: data.token,
                    client_name: data.client_name,
                    last_message: data.last_message,
                    last_message_at: data.last_message_at,
                    unread_count: this.activeToken === data.token ? 0 : data.unread_count,
                });
            }

            this.conversations.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));
        },

        applyUpdate(e) {
            const message = this.messages.find((m) => m.id === e.id);
            if (!message) return;
            message.body = e.body;
            message.original_body = e.original_body;
            message.edited_at = e.edited_at;
            message.deleted_at = e.deleted_at;
        },

        joinConversationChannel(token) {
            if (this.conversationChannel) {
                echo.leave(`conversation.${this.conversationChannel}`);
            }
            this.conversationChannel = token;
            this.onlineClient = false;

            echo.join(`conversation.${token}`)
                .here((users) => {
                    this.onlineClient = users.some((u) => u.role === 'client');
                })
                .joining((user) => {
                    if (user.role === 'client') this.onlineClient = true;
                })
                .leaving((user) => {
                    if (user.role === 'client') this.onlineClient = false;
                })
                .listen('.message.created', (e) => {
                    if (e.sender_type === 'client' && this.activeToken === token) {
                        this.messages.push(e);
                        this.scrollToBottom();
                        this.markRead();
                    }
                })
                .listen('.message.updated', (e) => {
                    if (this.activeToken === token) this.applyUpdate(e);
                })
                .listen('.message.read', (e) => {
                    if (e.reader === 'client') {
                        this.messages.forEach((m) => {
                            if (m.sender_type === 'operator') m.read_at = e.at;
                        });
                    }
                });
        },

        async selectConversation(token) {
            this.activeToken = token;
            this.messages = [];
            this.editingId = null;
            this.body = '';

            const conv = this.conversations.find((c) => c.token === token);
            if (conv) conv.unread_count = 0;

            history.replaceState(null, '', `/operator/conversations/${token}`);

            this.joinConversationChannel(token);

            const res = await fetch(`/operator/conversations/${token}/messages`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            this.messages = data.messages;
            this.scrollToBottom();
        },

        handleEnter(event) {
            if (event.shiftKey) return;
            event.preventDefault();
            this.send();
        },

        send() {
            if (this.editingId) {
                return this.saveEdit();
            }
            return this.sendNew();
        },

        async sendNew() {
            const text = this.body.trim();
            if (!text || this.sending || !this.activeToken) return;

            this.sending = true;
            this.body = '';

            try {
                const res = await fetch(`/operator/conversations/${this.activeToken}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                        'X-Socket-ID': echo.socketId() || '',
                    },
                    body: JSON.stringify({ body: text }),
                });
                const data = await res.json();
                this.messages.push({
                    id: data.id,
                    body: text,
                    sender_type: 'operator',
                    read_at: null,
                    edited_at: null,
                    original_body: null,
                    deleted_at: null,
                    created_at: data.created_at,
                });
                this.scrollToBottom();
            } finally {
                this.sending = false;
            }
        },

        startEdit(m) {
            this.editingId = m.id;
            this.body = m.body;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        cancelEdit() {
            this.editingId = null;
            this.body = '';
        },

        async saveEdit() {
            const text = this.body.trim();
            if (!text || this.sending || !this.activeToken) return;

            const id = this.editingId;
            this.sending = true;

            try {
                const res = await fetch(`/operator/conversations/${this.activeToken}/messages/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                        'X-Socket-ID': echo.socketId() || '',
                    },
                    body: JSON.stringify({ body: text }),
                });
                const data = await res.json();
                const message = this.messages.find((m) => m.id === id);
                if (message) {
                    if (!message.edited_at) message.original_body = message.body;
                    message.body = text;
                    message.edited_at = data.edited_at;
                }
                this.editingId = null;
                this.body = '';
            } finally {
                this.sending = false;
            }
        },

        async deleteMessage(m) {
            if (!confirm('Удалить сообщение?')) return;
            if (!this.activeToken) return;

            if (this.editingId === m.id) this.cancelEdit();

            await fetch(`/operator/conversations/${this.activeToken}/messages/${m.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                    'X-Socket-ID': echo.socketId() || '',
                },
            });

            m.deleted_at = new Date().toISOString();
            m.body = '';
            m.original_body = null;
        },

        markRead() {
            fetch(`/operator/conversations/${this.activeToken}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                    'X-Socket-ID': echo.socketId() || '',
                },
            });
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.scroll;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
    };
}
