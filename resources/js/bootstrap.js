import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { realtimeConfig } from './realtime-config';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.Pusher = Pusher;

// Only the public connection key is rendered by the current environment.
// The browser connects through that origin's WebSocket proxy, never an internal host.
const meta = document.querySelector('meta[name="kawhe-reverb-key"]');
const runtime = meta ? { key: meta.content } : null;
const options = realtimeConfig(runtime, import.meta.env, window.location);
window.Echo = options ? new Echo(options) : null;
