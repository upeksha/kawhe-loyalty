export function realtimeConfig(runtime, env, location) {
    const key = runtime?.key ?? env.VITE_REVERB_APP_KEY;
    if (!key) return null;
    const secure = runtime ? location.protocol === 'https:' : (env.VITE_REVERB_SCHEME ?? 'https') === 'https';
    const host = runtime ? location.hostname : (env.VITE_REVERB_HOST || location.hostname);
    const port = runtime ? Number(location.port || (secure ? 443 : 80)) : Number(env.VITE_REVERB_PORT || (secure ? 443 : 8080));
    return { broadcaster: 'reverb', key, wsHost: host, wsPort: port, wssPort: port, forceTLS: secure, enabledTransports: ['ws', 'wss'], disableStats: true };
}
