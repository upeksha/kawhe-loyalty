self.addEventListener('install', event => {
    event.waitUntil(
        caches.open('kawhe-v1').then(cache => {
            return cache.addAll([
                '/',
                '/scanner',
                '/css/app.css',
                '/js/app.js',
                '/manifest.webmanifest'
            ]);
        })
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request);
        })
    );
});

