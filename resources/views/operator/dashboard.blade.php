<x-layout title="Оператор">
<div
    x-data="operatorApp({
        conversations: @js($conversations->map(fn ($c) => [
            'token' => $c->token,
            'client_name' => $c->client_name,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => $c->unread_count,
        ])),
        activeToken: @js($activeConversation?->token),
        initialMessages: @js(collect($messages)->map(fn ($m) => $m->toBroadcastArray())),
    })"
    class="h-screen flex bg-gray-100"
>
    <aside class="w-full sm:w-80 shrink-0 bg-white border-r flex-col" :class="activeToken ? 'hidden sm:flex' : 'flex'">
        <div class="px-4 py-3 border-b flex items-center justify-between shrink-0">
            <h1 class="font-semibold text-gray-900">Диалоги</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-xs text-gray-400 hover:text-gray-700">Выйти</button>
            </form>
        </div>
        <div class="flex-1 overflow-y-auto">
            <template x-for="c in conversations" :key="c.token">
                <button type="button" @click="selectConversation(c.token)"
                        class="w-full text-left px-4 py-3 border-b hover:bg-gray-50 flex items-start justify-between gap-2"
                        :class="activeToken === c.token ? 'bg-gray-50' : ''">
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

    <section class="flex-1 flex-col min-h-0" :class="activeToken ? 'flex' : 'hidden sm:flex'">
        <template x-if="!activeToken">
            <div class="flex-1 flex items-center justify-center text-gray-400 text-sm">Выберите диалог слева</div>
        </template>

        <template x-if="activeToken">
            <div class="flex-1 flex flex-col min-h-0">
                <header class="bg-white border-b px-4 py-3 flex items-center gap-3 shrink-0">
                    <button type="button" class="sm:hidden text-gray-400" @click="activeToken = null">&larr;</button>
                    <div>
                        <h2 class="font-semibold text-gray-900" x-text="activeClientName()"></h2>
                        <p class="text-xs" :class="onlineClient ? 'text-green-600' : 'text-gray-400'" x-text="onlineClient ? 'В сети' : 'Не в сети'"></p>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto px-3 py-4 space-y-2" x-ref="scroll">
                    <template x-for="m in messages" :key="m.id">
                        <div class="flex" :class="m.sender_type === 'operator' ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                                :class="m.sender_type === 'operator' ? 'bg-gray-900 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm'"
                                x-data="{ showOriginal: false }"
                            >
                                <template x-if="m.deleted_at">
                                    <p class="italic opacity-60">Сообщение удалено</p>
                                </template>

                                <template x-if="!m.deleted_at">
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
                                    <template x-if="m.sender_type === 'operator' && !m.deleted_at">
                                        <span class="flex gap-1.5">
                                            <button type="button" class="underline" @click="startEdit(m)">ред.</button>
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

                <form @submit.prevent="send" class="bg-white border-t px-3 py-3 flex items-end gap-2 shrink-0">
                    <textarea
                        x-model="body"
                        x-ref="input"
                        @keydown.enter="handleEnter($event)"
                        rows="1"
                        placeholder="Ответить..."
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
</div>
</x-layout>
