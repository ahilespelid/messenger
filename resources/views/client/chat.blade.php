<x-layout :title="'Чат — '.$conversation->client_name">
<div
    x-data="clientChat({
        token: @js($conversation->token),
        initialMessages: @js($messages->map(fn ($m) => $m->toBroadcastArray())),
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
                <div
                    class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                    :class="m.sender_type === 'client' ? 'bg-gray-900 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm'"
                    x-data="{ showOriginal: false }"
                >
                    <template x-if="m.deleted_at">
                        <p class="italic opacity-60">Сообщение удалено</p>
                    </template>

                    <template x-if="!m.deleted_at && m.type === 'voice'">
                        <div class="flex items-center gap-2 min-w-[170px] py-0.5">
                            <button type="button" @click="toggleVoicePlay(m)"
                                    class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                                    :class="m.sender_type === 'client' ? 'bg-white/15' : 'bg-gray-900/10'">
                                <span x-text="playingId === m.id ? '❚❚' : '▶'"></span>
                            </button>
                            <div class="flex-1 h-1 rounded-full overflow-hidden" :class="m.sender_type === 'client' ? 'bg-white/20' : 'bg-gray-200'">
                                <div class="h-full bg-current" :style="`width: ${playingId === m.id ? audioProgress : 0}%`"></div>
                            </div>
                            <span class="text-[10px] opacity-80 shrink-0" x-text="formatDuration(m.voice_duration)"></span>
                        </div>
                    </template>

                    <template x-if="!m.deleted_at && m.type !== 'voice'">
                        <div>
                            <p class="whitespace-pre-wrap break-words" x-text="m.body"></p>
                            <template x-if="m.edited_at">
                                <p class="mt-0.5 text-[10px] underline decoration-dotted cursor-pointer opacity-70"
                                   @click="showOriginal = !showOriginal"
                                   x-text="showOriginal ? ('было: ' + m.original_body) : 'изменено'"></p>
                            </template>
                        </div>
                    </template>

                    <p class="mt-1 text-[10px] flex items-center gap-2 justify-end"
                       :class="m.sender_type === 'client' ? 'text-gray-300' : 'text-gray-400'">
                        <template x-if="m.sender_type === 'client' && !m.deleted_at">
                            <span class="flex gap-1.5">
                                <template x-if="m.type !== 'voice'">
                                    <button type="button" class="underline" @click="startEdit(m)">ред.</button>
                                </template>
                                <button type="button" class="underline" @click="deleteMessage(m)">удалить</button>
                            </span>
                        </template>
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

    <div x-show="editingId" x-cloak class="bg-amber-50 border-t border-amber-200 px-4 py-1.5 flex items-center justify-between text-xs text-amber-700">
        <span>Редактирование сообщения</span>
        <button type="button" class="underline" @click="cancelEdit()">Отмена</button>
    </div>

    <form @submit.prevent="send" x-show="!recording" class="bg-white border-t px-3 py-3 flex items-end gap-2 shrink-0">
        <textarea
            x-model="body"
            x-ref="input"
            @keydown.enter="handleEnter($event)"
            rows="1"
            placeholder="Написать сообщение..."
            class="flex-1 resize-none rounded-2xl border border-gray-300 px-4 py-2 focus:border-gray-900 focus:ring-gray-900 text-sm"
        ></textarea>
        <button type="button" x-show="!body.trim() && !editingId" @click="startRecording()"
                class="shrink-0 bg-gray-100 text-gray-700 rounded-full w-10 h-10 flex items-center justify-center">
            &#127908;
        </button>
        <button type="submit" x-show="body.trim() || editingId" :disabled="sending || !body.trim()"
                class="shrink-0 bg-gray-900 text-white rounded-full w-10 h-10 flex items-center justify-center disabled:opacity-40">
            <span x-text="editingId ? '✓' : '➤'"></span>
        </button>
    </form>

    <div x-show="recording" x-cloak class="bg-white border-t px-4 py-3 flex items-center gap-3 shrink-0">
        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse shrink-0"></span>
        <span class="text-sm text-gray-700 tabular-nums" x-text="formatDuration(recordSeconds)"></span>
        <div class="flex-1"></div>
        <button type="button" @click="cancelRecording()" class="text-gray-400 text-sm underline">Отмена</button>
        <button type="button" @click="stopAndSendRecording()"
                class="shrink-0 bg-gray-900 text-white rounded-full w-10 h-10 flex items-center justify-center">✓</button>
    </div>

    <audio x-ref="audioPlayer" @timeupdate="onAudioTimeUpdate()" @ended="stopPlayback()" class="hidden"></audio>
</div>
</x-layout>
