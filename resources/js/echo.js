import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Vem do documento, não do bundle: ver resources/views/app.blade.php. Com
// import.meta.env, estes quatro valores eram assados no build, e o container
// precisava reconstruir o frontend no arranque para acertá-los.
const reverb = window.__reverb ?? {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port,
    wssPort: reverb.port,
    forceTLS: reverb.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
