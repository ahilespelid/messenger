<x-layout title="Активные сессии">
<div
    x-data="{
        sessions: @js($sessions),
        loading: false,
        async refresh() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('operator.sessions.index') }}', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.sessions = data.sessions;
            } finally {
                this.loading = false;
            }
        },
        async kill(id) {
            if (!confirm('Завершить эту сессию?')) return;
            const res = await fetch(`/operator/sessions/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': @js(csrf_token()) },
            });
            if (res.ok) {
                this.sessions = this.sessions.filter((s) => s.session_id !== id);
            } else {
                alert('Не удалось завершить сессию.');
            }
        },
        formatTime(iso) {
            return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        },
    }"
    x-init="setInterval(() => refresh(), 15000)"
    class="min-h-screen bg-gray-100"
>
    <header class="bg-white border-b px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('operator.dashboard') }}" class="text-gray-400 hover:text-gray-700">&larr;</a>
            <h1 class="font-semibold text-gray-900">Активные сессии</h1>
        </div>
        <button type="button" @click="refresh()" class="text-xs text-gray-400 hover:text-gray-700" :class="loading ? 'opacity-40' : ''">Обновить</button>
    </header>

    <main class="p-4">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <template x-if="sessions.length === 0">
                <p class="p-6 text-sm text-gray-400 text-center">Нет активных сессий.</p>
            </template>

            <template x-for="s in sessions" :key="s.session_id">
                <div class="px-4 py-3 border-b last:border-b-0 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 truncate">
                            <span x-text="s.name"></span>
                            <span class="ml-1 text-[10px] font-normal px-1.5 py-0.5 rounded-full"
                                  :class="s.kind === 'operator' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-500'"
                                  x-text="s.kind === 'operator' ? 'оператор' : 'клиент'"></span>
                            <span x-show="s.is_current" class="ml-1 text-[10px] text-gray-400">(вы)</span>
                        </p>
                        <p class="text-xs text-gray-400 truncate">
                            <span x-text="s.ip_address || '—'"></span>
                            ·
                            <span x-text="formatTime(s.last_seen_at)"></span>
                            ·
                            <span class="truncate" x-text="s.user_agent || ''"></span>
                        </p>
                    </div>
                    <button type="button" x-show="!s.is_current" @click="kill(s.session_id)"
                            class="shrink-0 text-xs text-red-600 hover:text-red-700 underline">Завершить</button>
                </div>
            </template>
        </div>
    </main>
</div>
</x-layout>
