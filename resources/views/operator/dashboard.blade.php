<x-layout title="Оператор">
<div
    x-data="operatorApp({
        conversations: @js($conversations->map(fn ($c) => [
            'token' => $c->token,
            'client_name' => $c->client_name,
            'last_message' => $c->messages->first()?->previewText(80),
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => $c->unread_count,
        ])),
        colleagues: @js($colleagues),
        meId: @js(auth()->id()),
        meName: @js(auth()->user()->name),
        activeToken: @js($activeConversation?->token),
        initialMessages: @js(collect($messages)->map(fn ($m) => $m->toBroadcastArray())),
    })"
    class="h-screen flex bg-gray-100"
>
    <aside class="w-full sm:w-80 shrink-0 bg-white border-r flex-col" :class="hasActivePane() ? 'hidden sm:flex' : 'flex'">
        <div class="px-4 py-3 border-b flex items-center justify-between shrink-0">
            <h1 class="font-semibold text-gray-900">Диалоги</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-xs text-gray-400 hover:text-gray-700">Выйти</button>
            </form>
        </div>
        <div class="flex-1 overflow-y-auto">
            <div class="border-b border-gray-100">
                <p class="px-4 pt-3 pb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Команда</p>
                <div class="w-full px-4 py-2.5 flex items-center gap-2 bg-gray-50/70">
                    <span class="w-2 h-2 rounded-full shrink-0 bg-green-500"></span>
                    <p class="font-medium text-gray-900 truncate text-sm flex-1" x-text="meName"></p>
                    <span class="shrink-0 text-[10px] text-gray-400">вы</span>
                </div>
                <template x-if="colleagues.length > 0">
                    <template x-for="op in colleagues" :key="op.id">
                        <button type="button" @click="selectColleague(op.id)"
                                class="w-full text-left px-4 py-2.5 hover:bg-gray-50 flex items-center gap-2"
                                :class="activeKind === 'team' && activeColleagueId === op.id ? 'bg-gray-50' : ''">
                            <span class="w-2 h-2 rounded-full shrink-0" :class="isColleagueOnline(op.id) ? 'bg-green-500' : 'bg-gray-300'"></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 truncate text-sm" x-text="op.name"></p>
                                <p class="text-xs text-gray-400 truncate" x-text="op.last_message || 'Нет сообщений'"></p>
                            </div>
                            <span x-show="op.unread_count > 0"
                                  class="shrink-0 bg-gray-900 text-white text-[10px] rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center"
                                  x-text="op.unread_count"></span>
                        </button>
                    </template>
                </template>
            </div>

            <template x-for="c in conversations" :key="c.token">
                <button type="button" @click="selectConversation(c.token)"
                        class="w-full text-left px-4 py-3 border-b hover:bg-gray-50 flex items-start justify-between gap-2"
                        :class="activeKind === 'client' && activeToken === c.token ? 'bg-gray-50' : ''">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 truncate" x-text="c.client_name"></p>
                        <p class="text-xs text-gray-400 truncate" x-text="c.last_message || '—'"></p>
                    </div>
                    <span x-show="c.unread_count > 0"
                          class="shrink-0 bg-gray-900 text-white text-[10px] rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center"
                          x-text="c.unread_count"></span>
                </button>
            </template>
            <p x-show="conversations.length === 0" class="p-4 text-sm text-gray-400">Пока нет диалогов</p>
        </div>
    </aside>

    <section class="flex-1 flex-col min-h-0" :class="hasActivePane() ? 'flex' : 'hidden sm:flex'">
        <template x-if="!hasActivePane()">
            <div class="flex-1 flex items-center justify-center text-gray-400 text-sm">Выберите диалог слева</div>
        </template>

        <template x-if="activeKind === 'client' && activeToken">
            <div class="flex-1 flex flex-col min-h-0">
                <header class="bg-white border-b px-4 py-3 flex items-center gap-3 shrink-0">
                    <button type="button" class="sm:hidden text-gray-400" @click="closeActivePane()">&larr;</button>
                    <div>
                        <h2 class="font-semibold text-gray-900" x-text="activeClientName()"></h2>
                        <p class="text-xs" :class="onlineClient ? 'text-green-600' : 'text-gray-400'" x-text="onlineClient ? 'В сети' : 'Не в сети'"></p>
                    </div>
                </header>

                <main class="chat-pattern flex-1 overflow-y-auto px-3 py-4 space-y-2" x-ref="scroll">
                    <template x-for="m in messages" :key="m.id">
                        <div class="flex" :class="m.sender_type === 'operator' ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                                :class="m.sender_type === 'operator' ? 'bg-gray-900 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm'"
                                x-data="{ showOriginal: false }"
                            >
                                <template x-if="m.deleted_at && !m.revealed">
                                    <p class="italic opacity-60 cursor-pointer underline decoration-dotted"
                                       @click="revealMessage(m)">
                                        Сообщение удалено — <span x-text="m.revealing ? 'открываю…' : 'показать'"></span>
                                    </p>
                                </template>

                                <template x-if="(!m.deleted_at || m.revealed) && m.type === 'voice'">
                                    <div class="flex items-center gap-2 min-w-[170px] py-0.5">
                                        <button type="button" @click="toggleVoicePlay(m)"
                                                class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                                                :class="m.sender_type === 'operator' ? 'bg-white/15' : 'bg-gray-900/10'">
                                            <span x-text="playingId === m.id ? '❚❚' : '▶'"></span>
                                        </button>
                                        <div class="flex-1 h-1 rounded-full overflow-hidden" :class="m.sender_type === 'operator' ? 'bg-white/20' : 'bg-gray-200'">
                                            <div class="h-full bg-current" :style="`width: ${playingId === m.id ? audioProgress : 0}%`"></div>
                                        </div>
                                        <span class="text-[10px] opacity-80 shrink-0" x-text="formatDuration(m.voice_duration)"></span>
                                    </div>
                                </template>

                                <template x-if="(!m.deleted_at || m.revealed) && isImageMessage(m)">
                                    <a :href="fileUrl(m)" target="_blank" rel="noopener" class="block -mx-1 -mt-0.5">
                                        <img :src="fileUrl(m)" class="max-w-full max-h-64 rounded-lg" loading="lazy">
                                    </a>
                                </template>

                                <template x-if="(!m.deleted_at || m.revealed) && isVideoMessage(m)">
                                    <video :src="fileUrl(m)" controls preload="metadata" class="max-w-full max-h-64 rounded-lg -mx-1 -mt-0.5"></video>
                                </template>

                                <template x-if="(!m.deleted_at || m.revealed) && m.type === 'file' && !isImageMessage(m) && !isVideoMessage(m)">
                                    <a :href="fileUrl(m)" class="flex items-center gap-2 min-w-[170px] py-0.5">
                                        <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-base"
                                              :class="m.sender_type === 'operator' ? 'bg-white/15' : 'bg-gray-900/10'">📎</span>
                                        <span class="min-w-0">
                                            <span class="block truncate underline" x-text="m.file_name"></span>
                                            <span class="block text-[10px] opacity-70" x-text="formatFileSize(m.file_size)"></span>
                                        </span>
                                    </a>
                                </template>

                                <template x-if="(!m.deleted_at || m.revealed) && m.type !== 'voice' && m.type !== 'file'">
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
                                   :class="m.sender_type === 'operator' ? 'text-gray-300' : 'text-gray-400'">
                                    <template x-if="m.deleted_at && m.revealed">
                                        <span class="italic">удалено</span>
                                    </template>
                                    <template x-if="m.sender_type === 'operator' && !m.deleted_at">
                                        <span class="flex gap-1.5">
                                            <template x-if="m.type === 'text'">
                                                <button type="button" class="underline" @click="startEdit(m)">ред.</button>
                                            </template>
                                            <button type="button" class="underline" @click="deleteMessage(m)">удалить</button>
                                        </span>
                                    </template>
                                    <span x-text="formatTime(m.created_at)"></span>
                                    <template x-if="m.sender_type === 'operator'">
                                        <span x-text="m.read_at ? '✓✓' : '✓'"></span>
                                    </template>
                                </p>
                            </div>
                        </div>
                    </template>
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
                        placeholder="Ответить..."
                        class="flex-1 resize-none rounded-2xl border border-gray-300 px-4 py-2 focus:border-gray-900 focus:ring-gray-900 text-sm"
                    ></textarea>
                    <input type="file" x-ref="fileInput" @change="onFileSelected($event)" class="hidden">
                    <button type="button" x-show="!body.trim() && !editingId" @click="pickFile()"
                            class="shrink-0 bg-gray-100 text-gray-700 rounded-full w-10 h-10 flex items-center justify-center">
                        &#128206;
                    </button>
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
            </div>
        </template>

        <template x-if="activeKind === 'team' && activeColleagueId">
            <div class="flex-1 flex flex-col min-h-0">
                <header class="bg-white border-b px-4 py-3 flex items-center gap-3 shrink-0">
                    <button type="button" class="sm:hidden text-gray-400" @click="closeActivePane()">&larr;</button>
                    <div>
                        <h2 class="font-semibold text-gray-900" x-text="activeColleagueName()"></h2>
                        <p class="text-xs" :class="isColleagueOnline(activeColleagueId) ? 'text-green-600' : 'text-gray-400'" x-text="isColleagueOnline(activeColleagueId) ? 'В сети' : 'Не в сети'"></p>
                    </div>
                </header>

                <main class="chat-pattern flex-1 overflow-y-auto px-3 py-4 space-y-2" x-ref="scroll">
                    <template x-for="m in teamMessages" :key="m.id">
                        <div class="flex" :class="m.sender_id === meId ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                                :class="m.sender_id === meId ? 'bg-gray-900 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm'"
                                x-data="{ showOriginal: false }"
                            >
                                <template x-if="m.deleted_at && !m.revealed">
                                    <p class="italic opacity-60 cursor-pointer underline decoration-dotted"
                                       @click="revealTeamMessage(m)">
                                        Сообщение удалено — <span x-text="m.revealing ? 'открываю…' : 'показать'"></span>
                                    </p>
                                </template>

                                <template x-if="!m.deleted_at || m.revealed">
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
                                   :class="m.sender_id === meId ? 'text-gray-300' : 'text-gray-400'">
                                    <template x-if="m.deleted_at && m.revealed">
                                        <span class="italic">удалено</span>
                                    </template>
                                    <template x-if="m.sender_id === meId && !m.deleted_at">
                                        <span class="flex gap-1.5">
                                            <button type="button" class="underline" @click="startTeamEdit(m)">ред.</button>
                                            <button type="button" class="underline" @click="deleteTeamMessage(m)">удалить</button>
                                        </span>
                                    </template>
                                    <span x-text="formatTime(m.created_at)"></span>
                                    <template x-if="m.sender_id === meId">
                                        <span x-text="m.read_at ? '✓✓' : '✓'"></span>
                                    </template>
                                </p>
                            </div>
                        </div>
                    </template>
                    <p x-show="teamMessages.length === 0" class="text-center text-sm text-gray-400 mt-10">
                        Внутренний чат — виден только вам двоим.
                    </p>
                </main>

                <div x-show="editingId" x-cloak class="bg-amber-50 border-t border-amber-200 px-4 py-1.5 flex items-center justify-between text-xs text-amber-700">
                    <span>Редактирование сообщения</span>
                    <button type="button" class="underline" @click="cancelEdit()">Отмена</button>
                </div>

                <form @submit.prevent="send" class="bg-white border-t px-3 py-3 flex items-end gap-2 shrink-0">
                    <textarea
                        x-model="body"
                        x-ref="input"
                        @keydown.enter="handleEnter($event)"
                        rows="1"
                        placeholder="Написать коллеге..."
                        class="flex-1 resize-none rounded-2xl border border-gray-300 px-4 py-2 focus:border-gray-900 focus:ring-gray-900 text-sm"
                    ></textarea>
                    <button type="submit" :disabled="sending || !body.trim()"
                            class="shrink-0 bg-gray-900 text-white rounded-full w-10 h-10 flex items-center justify-center disabled:opacity-40">
                        <span x-text="editingId ? '✓' : '➤'"></span>
                    </button>
                </form>
            </div>
        </template>
    </section>

    <audio x-ref="audioPlayer" @timeupdate="onAudioTimeUpdate()" @ended="stopPlayback()" class="hidden"></audio>
</div>
</x-layout>
