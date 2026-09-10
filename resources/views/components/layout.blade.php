<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <meta name="theme-color" content="#111827">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 antialiased">
    <script>
        window.APP_CONFIG = {
            reverbKey: @json(config('broadcasting.connections.reverb.key')),
            reverbHost: @json(config('services.reverb_client.host')),
            reverbPort: @json(config('services.reverb_client.port')),
            reverbScheme: @json(config('services.reverb_client.scheme')),
            vapidPublicKey: @json(config('services.webpush.public_key')),
            csrfToken: @json(csrf_token()),
        };
    </script>
    {{ $slot }}
</body>
</html>
