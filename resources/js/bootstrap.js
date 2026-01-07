import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const isSecure = (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https';
const wsHost = import.meta.env.VITE_REVERB_HOST ?? 'localhost';
const wsPort = parseInt(import.meta.env.VITE_REVERB_PORT ?? (isSecure ? '443' : '8080'), 10);

// Configure Echo for Reverb
// Reverb uses Pusher-compatible protocol
const echoConfig = {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: wsHost,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
};

// Set port and TLS based on security
if (isSecure) {
    echoConfig.wssPort = wsPort;
    echoConfig.forceTLS = true;
    echoConfig.encrypted = true;
} else {
    // For non-secure connections, explicitly set wsPort and disable encryption
    echoConfig.wsPort = wsPort;
    echoConfig.forceTLS = false;
    echoConfig.encrypted = false;
    // Try using httpScheme to force ws://
    echoConfig.httpPath = '/app';
}

window.Echo = new Echo(echoConfig);

// Debug: Log the actual Pusher configuration after initialization
setTimeout(() => {
    if (window.Echo.connector && window.Echo.connector.pusher) {
        const pusher = window.Echo.connector.pusher;
        console.log('Pusher config:', {
            key: pusher.key,
            config: pusher.config,
            options: pusher.options
        });
        
        // Try to fix the WebSocket URL if it's wrong
        if (!isSecure && pusher.config) {
            // Force ws:// protocol and correct port
            const originalBuildURL = pusher.config.buildURL;
            pusher.config.buildURL = function(scheme, domain, port, path) {
                if (!isSecure) {
                    scheme = 'ws';
                    port = wsPort;
                }
                return originalBuildURL.call(this, scheme, domain, port, path);
            };
        }
    }
}, 100);

console.log('Echo configured:', {
    host: wsHost,
    port: wsPort,
    secure: isSecure,
    wsPort: echoConfig.wsPort,
    wssPort: echoConfig.wssPort,
    key: import.meta.env.VITE_REVERB_APP_KEY ? 'set' : 'missing'
});
