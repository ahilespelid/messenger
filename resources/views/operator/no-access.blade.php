<x-layout title="Нет доступа">
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-6 text-center">
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Нет доступа</h1>
        <p class="text-sm text-gray-500">
            Панель оператора открывается только по персональной ссылке.
            Если вы её потеряли — попросите коллегу или администратора выдать новую:
        </p>
        <p class="mt-3 text-xs text-gray-400 font-mono bg-gray-50 rounded-lg px-3 py-2">
            docker compose exec app php artisan operator:create &lt;email&gt; &lt;имя&gt;
        </p>
    </div>
</div>
</x-layout>
