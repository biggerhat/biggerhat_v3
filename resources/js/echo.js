import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;

if (reverbKey && reverbHost) {
    if (import.meta.env.DEV) {
        console.log(
            `[Echo] Connecting to Reverb: ${reverbHost}:${import.meta.env.VITE_REVERB_PORT} (TLS: ${(import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https'})`,
        );
    }
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
        },
    });

    // Log connection state changes
    if (import.meta.env.DEV) {
        window.Echo.connector.pusher.connection.bind('connected', () => console.log('[Echo] Connected'));
        window.Echo.connector.pusher.connection.bind('disconnected', () => console.warn('[Echo] Disconnected'));
        window.Echo.connector.pusher.connection.bind('error', (err) => console.error('[Echo] Error:', err));
    }
} else if (import.meta.env.DEV) {
    console.warn('[Echo] Reverb not configured — VITE_REVERB_APP_KEY or VITE_REVERB_HOST missing');
}
