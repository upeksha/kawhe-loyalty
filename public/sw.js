const CACHE_NAME = 'kawhe-v3'; // Updated version to force refresh

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            // Only cache static assets, not Vite-built assets (Vite handles those)
            return cache.addAll([
                '/',
                '/manifest.webmanifest'
            ]).catch(err => {
                console.log('Service worker cache: Some assets may not be available', err);
            });
        })
    );
    // Force activation of new service worker
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

self.addEventListener('fetch', event => {
    // Don't cache external resources or API calls
    const url = new URL(event.request.url);
    const isExternal = url.origin !== location.origin;
    const isApi = url.pathname.startsWith('/api/');
    
    // Skip caching for external resources and API calls
    if (isExternal || isApi) {
        return fetch(event.request).catch(() => {
            // Silently fail for external resources
            return new Response('', { status: 404 });
        });
    }
    
    // Cache local resources
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request);
        })
    );
});

