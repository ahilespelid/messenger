<x-layout title="Диалог не найден">
<script>
    try {
        localStorage.removeItem('conversation_token');
    } catch (e) {}
</script>

<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-6 text-center">
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Диалог не найден</h1>
        <p class="text-sm text-gray-500 mb-6">Похоже, ссылка устарела. Начните новый разговор — мы ответим как можно скорее.</p>
        <a href="{{ route('home') }}" class="inline-block w-full bg-gray-900 text-white rounded-lg py-2.5 font-medium hover:bg-gray-800">
            Написать нам
        </a>
    </div>
</div>
</x-layout>
