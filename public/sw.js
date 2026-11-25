/*
 * Garage ERP service worker.
 * - Static assets (css/js/vendor/icons): cache-first.
 * - Pages: always from the network (never cached); the /offline page is shown when offline.
 * - Non-GET requests are never cached (forms, API writes).
 */
const VERSION = 'erp-v1';
const STATIC_CACHE = `${VERSION}-static`;
const PRECACHE = [
    '/offline',
    '/css/app.css',
    '/js/app.js',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const isStatic = (url) => /^\/(css|js|vendor|icons|build)\//.test(url.pathname) || url.pathname === '/favicon.ico';

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    // never cache auth, exports, files, livewire or API calls
    if (/^\/(api|livewire|broadcasting|logout|login|storage|attachments)/.test(url.pathname) || url.searchParams.has('export')) return;

    if (isStatic(url)) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) { const copy = res.clone(); caches.open(STATIC_CACHE).then((c) => c.put(req, copy)); }
                return res;
            }))
        );
        return;
    }

    if (req.mode === 'navigate') {
        // pages contain private business data: never cached, offline fallback only
        event.respondWith(fetch(req).catch(() => caches.match('/offline')));
    }
});

