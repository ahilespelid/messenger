import echo from './echo';
import { enablePushNotifications } from './push';
import { createRecorder } from './voice-recorder';
import { formatDuration, formatFileSize } from './format';

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export default function operatorApp({ conversations, colleagues, meId, meName, isAdmin, activeToken, initialMessages }) {
    return {
        conversations: conversations || [],
        colleagues: colleagues || [],
        meId,
        meName,
        isAdmin,
        inviteOpen: false,
        inviteName: '',
        inviteLink: '',
        inviteSending: false,
        inviteCopied: false,
        activeKind: 'client', // 'client' | 'team'
        activeToken: activeToken || null,
        activeColleagueId: null,
        messages: initialMessages || [],
        teamMessages: [],
        body: '',
        sending: false,
        onlineClient: false,
        conversationChannel: null,
        teamChannelPair: null,
        editingId: null,
        recording: false,
        recordSeconds: 0,
        recordTimer: null,
        recorder: null,
        playingId: null,
        audioProgress: 0,
        onlineOperatorIds: new Set(),

        init() {
            enablePushNotifications();

            echo.private('operator-inbox').listen('.conversation.updated', (e) => {
                this.upsertConversation(e);
            });

            echo.private(`operator.${this.meId}`).listen('.team-message.created', (e) => {
                this.handlePersonalTeamMessage(e);
            });

            const idFrom = (memberId) => parseInt(String(memberId).replace('operator-', ''), 10);

            echo.join('operators-online')
                .here((users) => {
                    this.onlineOperatorIds = new Set(users.map((u) => idFrom(u.id)));
                })
                .joining((user) => {
                    this.onlineOperatorIds.add(idFrom(user.id));
                })
                .leaving((user) => {
                    this.onlineOperatorIds.delete(idFrom(user.id));
                });

            if (this.activeToken) {
                this.joinConversationChannel(this.activeToken);
            }
        },

        isColleagueOnline(id) {
            return this.onlineOperatorIds.has(id);
        },

        formatTime,
        formatDuration,
        formatFileSize,

        hasActivePane() {
            return !!(this.activeToken || this.activeColleagueId);
        },

        closeActivePane() {
            this.activeToken = null;
            this.activeColleagueId = null;
        },

        activeClientName() {
            const c = this.conversations.find((c) => c.token === this.activeToken);
            return c ? c.client_name : '';
        },

        activeColleagueName() {
            const c = this.colleagues.find((c) => c.id === this.activeColleagueId);
            return c ? c.name : '';
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

        handlePersonalTeamMessage(e) {
            // e.recipient_id is always meId here (channel auth guarantees it);
            // e.sender_id is whichever colleague sent it.
            const col = this.colleagues.find((c) => c.id === e.sender_id);
            if (col) {
                col.last_message = e.body;
                col.last_message_at = e.created_at;
                if (this.activeColleagueId !== e.sender_id) {
                    col.unread_count = (col.unread_count || 0) + 1;
                }
            }
            this.colleagues.sort((a, b) => new Date(b.last_message_at || 0) - new Date(a.last_message_at || 0));
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
            this.cancelRecording();
            this.stopPlayback();
            this.activeKind = 'client';
            this.activeColleagueId = null;
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

        joinTeamChannel(colleagueId) {
            if (this.teamChannelPair) {
                echo.leave(`team.${this.teamChannelPair}`);
            }
            const pair = [this.meId, colleagueId].sort((a, b) => a - b).join('-');
            this.teamChannelPair = pair;

            // Online status for the header comes from the shared
            // 'operators-online' channel (isColleagueOnline); this presence
            // channel is joined purely to receive message events for the pair.
            echo.join(`team.${pair}`)
                .listen('.team-message.created', (e) => {
                    if (this.activeKind === 'team' && this.activeColleagueId === colleagueId) {
                        this.teamMessages.push(e);
                        this.scrollToBottom();
                        this.markTeamRead();
                    }
                })
                .listen('.team-message.updated', (e) => {
                    if (this.activeKind === 'team' && this.activeColleagueId === colleagueId) this.applyTeamUpdate(e);
                })
                .listen('.team-message.read', (e) => {
                    this.teamMessages.forEach((m) => {
                        if (m.sender_id === this.meId) m.read_at = e.at;
                    });
                });
        },

        async selectColleague(id) {
            this.cancelRecording();
            this.stopPlayback();
            this.activeKind = 'team';
            this.activeToken = null;
            this.activeColleagueId = id;
            this.teamMessages = [];
            this.editingId = null;
            this.body = '';

            const col = this.colleagues.find((c) => c.id === id);
            if (col) col.unread_count = 0;

            history.replaceState(null, '', '/operator');

            this.joinTeamChannel(id);

            const res = await fetch(`/operator/team/${id}/messages`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            this.teamMessages = data.messages;
            this.scrollToBottom();
        },

        handleEnter(event) {
            if (event.shiftKey) return;
            event.preventDefault();
            this.send();
        },

        send() {
            if (this.activeKind === 'team') {
                return this.editingId ? this.saveTeamEdit() : this.sendTeamNew();
            }
            return this.editingId ? this.saveEdit() : this.sendNew();
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
                    type: 'text',
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
            if (this.playingId === m.id) this.stopPlayback();

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

        async revealMessage(m) {
            if (!this.activeToken || m.revealing) return;
            m.revealing = true;

            try {
                const res = await fetch(`/operator/conversations/${this.activeToken}/messages/${m.id}/reveal`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;

                const data = await res.json();
                m.body = data.body;
                m.original_body = data.original_body;
                m.voice_duration = data.voice_duration;
                m.file_name = data.file_name;
                m.file_mime = data.file_mime;
                m.file_size = data.file_size;
                m.revealed = true;
            } finally {
                m.revealing = false;
            }
        },

        // --- Internal team DMs (text-only) ---

        async sendTeamNew() {
            const text = this.body.trim();
            if (!text || this.sending || !this.activeColleagueId) return;

            this.sending = true;
            this.body = '';

            try {
                const res = await fetch(`/operator/team/${this.activeColleagueId}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                        'X-Socket-ID': echo.socketId() || '',
                    },
                    body: JSON.stringify({ body: text }),
                });
                const data = await res.json();
                this.teamMessages.push({
                    id: data.id,
                    type: 'text',
                    sender_id: this.meId,
                    recipient_id: this.activeColleagueId,
                    body: text,
                    original_body: null,
                    read_at: null,
                    edited_at: null,
                    deleted_at: null,
                    created_at: data.created_at,
                });
                this.scrollToBottom();
            } finally {
                this.sending = false;
            }
        },

        startTeamEdit(m) {
            this.editingId = m.id;
            this.body = m.body;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        async saveTeamEdit() {
            const text = this.body.trim();
            if (!text || this.sending || !this.activeColleagueId) return;

            const id = this.editingId;
            this.sending = true;

            try {
                const res = await fetch(`/operator/team/${this.activeColleagueId}/messages/${id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                        'X-Socket-ID': echo.socketId() || '',
                    },
                    body: JSON.stringify({ body: text }),
                });
                const data = await res.json();
                const message = this.teamMessages.find((m) => m.id === id);
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

        async deleteTeamMessage(m) {
            if (!confirm('Удалить сообщение?')) return;
            if (!this.activeColleagueId) return;

            if (this.editingId === m.id) this.cancelEdit();

            await fetch(`/operator/team/${this.activeColleagueId}/messages/${m.id}`, {
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

        async revealTeamMessage(m) {
            if (!this.activeColleagueId || m.revealing) return;
            m.revealing = true;

            try {
                const res = await fetch(`/operator/team/${this.activeColleagueId}/messages/${m.id}/reveal`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;

                const data = await res.json();
                m.body = data.body;
                m.original_body = data.original_body;
                m.voice_duration = data.voice_duration;
                m.revealed = true;
            } finally {
                m.revealing = false;
            }
        },

        applyTeamUpdate(e) {
            const message = this.teamMessages.find((m) => m.id === e.id);
            if (!message) return;
            message.body = e.body;
            message.original_body = e.original_body;
            message.voice_duration = e.voice_duration;
            message.edited_at = e.edited_at;
            message.deleted_at = e.deleted_at;
        },

        markTeamRead() {
            if (!this.activeColleagueId) return;
            fetch(`/operator/team/${this.activeColleagueId}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                    'X-Socket-ID': echo.socketId() || '',
                },
            });
        },

        // --- Voice ---

        async startRecording() {
            if (this.recording || this.editingId) return;
            if (this.activeKind === 'client' && !this.activeToken) return;
            if (this.activeKind === 'team' && !this.activeColleagueId) return;

            this.recorder = createRecorder();
            try {
                await this.recorder.start();
            } catch (e) {
                alert('Не удалось получить доступ к микрофону.');
                return;
            }

            this.recording = true;
            this.recordSeconds = 0;
            this.recordTimer = setInterval(() => {
                this.recordSeconds++;
            }, 1000);
        },

        cancelRecording() {
            if (!this.recording) return;
            clearInterval(this.recordTimer);
            this.recording = false;
            this.recorder?.cancel();
            this.recorder = null;
        },

        async stopAndSendRecording() {
            if (!this.recording) return;
            clearInterval(this.recordTimer);
            this.recording = false;

            const duration = this.recordSeconds;
            const blob = await this.recorder.stop();
            this.recorder = null;

            if (!blob || duration < 1) return;

            if (this.activeKind === 'team') {
                return this.sendTeamVoice(blob, duration);
            }
            return this.sendClientVoice(blob, duration);
        },

        async sendClientVoice(blob, duration) {
            if (!this.activeToken) return;

            const form = new FormData();
            form.append('audio', blob, 'voice.webm');
            form.append('duration', duration);

            const res = await fetch(`/operator/conversations/${this.activeToken}/messages/voice`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                    'X-Socket-ID': echo.socketId() || '',
                },
                body: form,
            });
            const data = await res.json();

            this.messages.push({
                id: data.id,
                type: 'voice',
                body: null,
                sender_type: 'operator',
                read_at: null,
                edited_at: null,
                original_body: null,
                deleted_at: null,
                voice_duration: data.voice_duration,
                created_at: data.created_at,
            });
            this.scrollToBottom();
        },

        async sendTeamVoice(blob, duration) {
            if (!this.activeColleagueId) return;

            const form = new FormData();
            form.append('audio', blob, 'voice.webm');
            form.append('duration', duration);

            const res = await fetch(`/operator/team/${this.activeColleagueId}/messages/voice`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                    'X-Socket-ID': echo.socketId() || '',
                },
                body: form,
            });
            const data = await res.json();

            this.teamMessages.push({
                id: data.id,
                type: 'voice',
                body: null,
                sender_id: this.meId,
                recipient_id: this.activeColleagueId,
                read_at: null,
                edited_at: null,
                original_body: null,
                deleted_at: null,
                voice_duration: data.voice_duration,
                created_at: data.created_at,
            });
            this.scrollToBottom();
        },

        voiceUrl(m) {
            if (this.activeKind === 'team') {
                return `/operator/team/${this.activeColleagueId}/voice/${m.id}`;
            }
            return `/operator/conversations/${this.activeToken}/voice/${m.id}`;
        },

        toggleVoicePlay(m) {
            const audio = this.$refs.audioPlayer;
            if (!audio) return;

            if (this.playingId === m.id) {
                audio.pause();
                this.playingId = null;
                return;
            }

            audio.src = this.voiceUrl(m);
            audio.play();
            this.playingId = m.id;
        },

        stopPlayback() {
            const audio = this.$refs.audioPlayer;
            audio?.pause();
            this.playingId = null;
            this.audioProgress = 0;
        },

        onAudioTimeUpdate() {
            const audio = this.$refs.audioPlayer;
            if (!audio || !audio.duration) return;
            this.audioProgress = (audio.currentTime / audio.duration) * 100;
        },

        pickFile() {
            this.$refs.fileInput?.click();
        },

        async onFileSelected(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file || !this.activeToken) return;

            const form = new FormData();
            form.append('file', file);

            const res = await fetch(`/operator/conversations/${this.activeToken}/messages/file`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                    'X-Socket-ID': echo.socketId() || '',
                },
                body: form,
            });

            if (!res.ok) {
                alert('Не удалось отправить файл (возможно, слишком большой — максимум 25 МБ).');
                return;
            }

            const data = await res.json();
            this.messages.push({
                id: data.id,
                type: 'file',
                body: null,
                sender_type: 'operator',
                read_at: null,
                edited_at: null,
                original_body: null,
                deleted_at: null,
                file_name: data.file_name,
                file_mime: data.file_mime,
                file_size: data.file_size,
                created_at: data.created_at,
            });
            this.scrollToBottom();
        },

        fileUrl(m) {
            return `/operator/conversations/${this.activeToken}/file/${m.id}`;
        },

        isImageMessage(m) {
            return m.type === 'file' && (m.file_mime || '').startsWith('image/');
        },

        isVideoMessage(m) {
            return m.type === 'file' && (m.file_mime || '').startsWith('video/');
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

        openInvite() {
            this.inviteOpen = true;
            this.inviteName = '';
            this.inviteLink = '';
            this.inviteCopied = false;
        },

        closeInvite() {
            this.inviteOpen = false;
        },

        async submitInvite() {
            const name = this.inviteName.trim();
            if (!name || this.inviteSending) return;

            this.inviteSending = true;
            try {
                const res = await fetch('/operator/invites', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.APP_CONFIG.csrfToken,
                    },
                    body: JSON.stringify({ name }),
                });
                if (!res.ok) {
                    alert('Не удалось создать ссылку.');
                    return;
                }
                const data = await res.json();
                this.inviteName = data.name;
                this.inviteLink = data.link;
            } finally {
                this.inviteSending = false;
            }
        },

        async copyInviteLink() {
            try {
                await navigator.clipboard.writeText(this.inviteLink);
            } catch (e) {
                this.$refs.inviteLinkInput?.select();
            }
            this.inviteCopied = true;
            setTimeout(() => (this.inviteCopied = false), 2000);
        },
    };
}
