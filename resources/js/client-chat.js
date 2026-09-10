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

        handleEnter(event) {
            if (event.shiftKey) return;
            event.preventDefault();
            this.send();
        },

        async send() {
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
                    created_at: data.created_at,
                });
                this.scrollToBottom();
            } finally {
                this.sending = false;
            }
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
