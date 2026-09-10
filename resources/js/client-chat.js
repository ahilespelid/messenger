import echo from './echo';
import { enablePushNotifications } from './push';

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export default function clientChat({ token, initialMessages }) {
    return {
        token,
        messages: initialMessages || [],
        body: '',
        sending: false,
        online: false,
        editingId: null,

        init() {
            try {
                localStorage.setItem('conversation_token', this.token);
            } catch (e) {
                // ignore (private browsing, storage disabled, etc.)
            }

            enablePushNotifications(this.token);

            echo.join(`conversation.${this.token}`)
                .here((users) => {
                    this.online = users.some((u) => u.role === 'operator');
                })
                .joining((user) => {
                    if (user.role === 'operator') this.online = true;
                })
                .leaving((user) => {
                    if (user.role === 'operator') this.online = false;
                })
                .listen('.message.created', (e) => {
                    if (e.sender_type === 'operator') {
                        this.messages.push(e);
                        this.scrollToBottom();
                        this.markRead();
                    }
                })
                .listen('.message.updated', (e) => {
                    this.applyUpdate(e);
                })
                .listen('.message.read', (e) => {
                    if (e.reader === 'operator') {
                        this.messages.forEach((m) => {
                            if (m.sender_type === 'client') m.read_at = e.at;
                        });
                    }
                });

            this.scrollToBottom();
        },

        formatTime,

        applyUpdate(e) {
            const message = this.messages.find((m) => m.id === e.id);
            if (!message) return;
            message.body = e.body;
            message.original_body = e.original_body;
            message.edited_at = e.edited_at;
            message.deleted_at = e.deleted_at;
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
            if (!text || this.sending) return;

            this.sending = true;
            this.body = '';

            try {
                const res = await fetch(`/c/${this.token}/messages`, {
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
                    sender_type: 'client',
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
            if (!text || this.sending) return;

            const id = this.editingId;
            this.sending = true;

            try {
                const res = await fetch(`/c/${this.token}/messages/${id}`, {
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

            if (this.editingId === m.id) this.cancelEdit();

            await fetch(`/c/${this.token}/messages/${m.id}`, {
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
            fetch(`/c/${this.token}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
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
