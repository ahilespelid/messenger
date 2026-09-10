import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const cfg = window.APP_CONFIG || {};

const echo = new Echo({
    broadcaster: 'reverb',
    key: cfg.reverbKey,
    wsHost: cfg.reverbHost,
    wsPort: cfg.reverbPort,
    wssPort: cfg.reverbPort,
    forceTLS: cfg.reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

export default echo;
