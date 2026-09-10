<x-layout title="Написать нам">
<script>
    try {
        var existingToken = localStorage.getItem('conversation_token');
        if (existingToken) {
            window.location.replace('/c/' + existingToken);
        }
    } catch (e) {}
</script>

<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-6">
        <h1 class="text-xl font-semibold text-gray-900 mb-1">Свяжитесь с нами</h1>
        <p class="text-sm text-gray-500 mb-6">Ответим как можно скорее.</p>

        @if ($errors->any())
            <div class="mb-4 text-sm text-red-600">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('conversation.start') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Имя</label>
                <input type="text" name="client_name" required value="{{ old('client_name') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-gray-900 focus:ring-gray-900">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email (необязательно)</label>
                <input type="email" name="client_email" value="{{ old('client_email') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-gray-900 focus:ring-gray-900">
            </div>
            <button type="submit" class="w-full bg-gray-900 text-white rounded-lg py-2.5 font-medium hover:bg-gray-800">
                Начать чат
            </button>
        </form>
    </div>
</div>
</x-layout>
