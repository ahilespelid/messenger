<x-layout :title="'Чат — '.$conversation->client_name">
<div
    x-data="clientChat({
        token: @js($conversation->token),
        initialMessages: @js($messages->map(fn ($m) => [
            'id' => $m->id,
            'sender_type' => $m->sender_type,
            'body' => $m->body,
            'read_at' => $m->read_at?->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ])),
    })"
    class="h-screen flex flex-col bg-gray-100"
>
    <header class="bg-white border-b px-4 py-3 flex items-center justify-between shrink-0">
        <div>
            <h1 class="font-semibold text-gray-900">Поддержка</h1>
            <p class="text-xs" :class="online ? 'text-green-600' : 'text-gray-400'" x-text="online ? 'В сети' : 'Не в сети'"></p>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto px-3 py-4 space-y-2" x-ref="scroll">
        <template x-for="m in messages" :key="m.id">
            <div class="flex" :class="m.sender_type === 'client' ? 'justify-end' : 'justify-start'">
                <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                     :class="m.sender_type === 'client' ? 'bg-gray-900 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm'">
                    <p class="whitespace-pre-wrap break-words" x-text="m.body"></p>
                    <p class="mt-1 text-[10px] flex items-center gap-1 justify-end"
                       :class="m.sender_type === 'client' ? 'text-gray-300' : 'text-gray-400'">
                        <span x-text="formatTime(m.created_at)"></span>
                        <template x-if="m.sender_type === 'client'">
                            <span x-text="m.read_at ? '✓✓' : '✓'"></span>
                        </template>
                    </p>
                </div>
            </div>
        </template>
        <p x-show="messages.length === 0" class="text-center text-sm text-gray-400 mt-10">
            Напишите нам, и мы ответим как можно скорее.
        </p>
    </main>

    <form @submit.prevent="send" class="bg-white border-t px-3 py-3 flex items-end gap-2 shrink-0">
        <textarea
            x-model="body"
            @keydown.enter="handleEnter($event)"
            rows="1"
            placeholder="Написать сообщение..."
            class="flex-1 resize-none rounded-2xl border border-gray-300 px-4 py-2 focus:border-gray-900 focus:ring-gray-900 text-sm"
        ></textarea>
        <button type="submit" :disabled="sending || !body.trim()"
                class="shrink-0 bg-gray-900 text-white rounded-full w-10 h-10 flex items-center justify-center disabled:opacity-40">
            &#10148;
        </button>
    </form>
</div>
</x-layout>
