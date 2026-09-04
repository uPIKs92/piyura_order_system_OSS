/**
 * PWA service worker (v3).
 *
 * Strategies:
 *  - install:    precache shell (/, offline.html, manifest, favicon, icons)
 *  - activate:   evict caches from prior VERSIONs, claim clients
 *  - navigate:   network-first, fallback to cached shell / offline.html
 *  - /build/*:   cache-first (Vite content-hashed filenames)
 *  - fonts:      stale-while-revalidate (Google Fonts CSS + woff2)
 *  - read API:   network-first (mutations must be reflected immediately by
 *                refetches); cached copy is only served when offline. Cached
 *                copies carry an `X-Kilo-Cached-At` header and are swept
 *                after 24h (on activate, plus an hourly opportunistic sweep)
 *                so stale PII cannot persist indefinitely while logged in.
 *  - other GET:  network, fallback to cache
 *  - update:     `skipWaiting` on install; on message {type:'SKIP_WAITING'}
 *                or {type:'CLIENTS_CLAIM'} we apply; clients listen via
 *                controllerchange and surface an update toast. On message
 *                {type:'CLEAR_API_CACHE'} the API cache is dropped (logout).
 */

const VERSION = 'v3';
const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;
const FONTS_CACHE = `fonts-${VERSION}`;
const API_CACHE = `api-${VERSION}`;
const CACHE_NAMES = [SHELL_CACHE, ASSET_CACHE, FONTS_CACHE, API_CACHE];

const ICON_FILES = [
    '/icons/icon-16.png', '/icons/icon-32.png', '/icons/icon-48.png',
    '/icons/icon-96.png', '/icons/icon-144.png', '/icons/icon-192.png',
    '/icons/icon-512.png', '/icons/icon-512-maskable.png',
    '/icons/icon-512-monochrome.png',
    '/icons/apple-touch-icon-152.png', '/icons/apple-touch-icon-167.png',
    '/icons/apple-touch-icon-180.png', '/icons/apple-touch-icon.png',
    '/icons/icon-1024.png',
];

const SHELL_URLS = ['/', '/offline.html', '/manifest.json', '/favicon.ico', ...ICON_FILES];

// Read-only API endpoints cached network-first for offline fallback.
const CACHED_API_PREFIXES = ['/api/products', '/api/categories', '/api/orders'];

// Cached API responses are stamped with `X-Kilo-Cached-At` and deleted once
// older than the TTL so authenticated PII does not persist indefinitely.
const API_CACHE_TTL_MS = 24 * 60 * 60 * 1000;
// The sweep itself is throttled: at most once per hour per SW lifetime.
const API_SWEEP_INTERVAL_MS = 60 * 60 * 1000;
let lastApiSweepAt = 0;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_URLS).catch(() => {
                // addAll is atomic — fall back to individual puts if any URL
                // 404s (e.g. an icon file is missing on this deploy).
                return Promise.all(SHELL_URLS.map((u) =>
                    cache.add(u).catch(() => null),
                ));
            }))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => !CACHE_NAMES.includes(key)).map((key) => caches.delete(key)),
            ))
            .then(() => sweepStaleApiEntries())
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data.type === 'SKIP_WAITING') self.skipWaiting();
    if (data.type === 'CLIENTS_CLAIM') self.clients.claim();
    // Logout / session end: drop cached API responses (orders etc.) so the
    // next session on this device never serves the previous user's data.
    if (data.type === 'CLEAR_API_CACHE') {
        event.waitUntil(caches.delete(API_CACHE));
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    const sameOrigin = url.origin === self.location.origin;

    // Google Fonts: stale-while-revalidate into FONTS_CACHE.
    if (url.host === 'fonts.googleapis.com' || url.host === 'fonts.gstatic.com') {
        event.respondWith(staleWhileRevalidate(request, FONTS_CACHE));
        return;
    }

    if (!sameOrigin) return;

    // Read-only API: network-first so post-mutation refetches always see
    // fresh data; the cached copy is only a fallback for offline use.
    if (CACHED_API_PREFIXES.some((p) => url.pathname.startsWith(p))) {
        // Opportunistic TTL sweep — cheap timestamp guard, runs the actual
        // sweep at most once per hour (waitUntil keeps the worker alive).
        if (Date.now() - lastApiSweepAt >= API_SWEEP_INTERVAL_MS) {
            lastApiSweepAt = Date.now();
            event.waitUntil(sweepStaleApiEntries());
        }
        event.respondWith(networkFirst(request, API_CACHE));
        return;
    }

    // Mutations / other API: passthrough, do not cache.
    if (url.pathname.startsWith('/api/')) return;

    // Navigations: network-first with cached shell + offline fallback.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(SHELL_CACHE).then((cache) => cache.put('/', copy));
                    return response;
                })
                .catch(() =>
                    caches.match('/').then((cached) => cached || caches.match('/offline.html')),
                ),
        );
        return;
    }

    // Vite build assets: cache-first.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
                return response;
            })),
        );
        return;
    }

    // Everything else: network, fallback to cache.
    event.respondWith(fetch(request).catch(() => caches.match(request)));
});

/**
 * Stale-while-revalidate: serve cache immediately if present, refresh in
 * background. Only caches successful (2xx) opaque/cors responses.
 */
function staleWhileRevalidate(request, cacheName) {
    return caches.open(cacheName).then((cache) =>
        cache.match(request).then((cached) => {
            const networkFetch = fetch(request)
                .then((response) => {
                    if (response && (response.ok || response.type === 'opaque')) {
                        cache.put(request, response.clone());
                    }
                    return response;
                })
                .catch(() => cached);
            return cached || networkFetch;
        }),
    );
}

/**
 * Network-first: hit the network so the page always gets fresh data when
 * online; fall back to (and refresh) the cache for offline use.
 */
function networkFirst(request, cacheName) {
    return caches.open(cacheName).then((cache) =>
        fetch(request)
            .then((response) => {
                if (response && response.ok) {
                    cache.put(request, withCachedAtHeader(response.clone()));
                }
                return response;
            })
            .catch(() =>
                cache.match(request).then((cached) => cached || Response.error()),
            ),
    );
}

/**
 * Copy a response into a fresh Response carrying an `X-Kilo-Cached-At`
 * timestamp header. The caller passes a clone (responses are immutable and
 * their body single-use); the original stays usable by the page.
 */
function withCachedAtHeader(response) {
    const headers = new Headers(response.headers);
    headers.set('X-Kilo-Cached-At', String(Date.now()));
    return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers,
    });
}

/**
 * TTL sweep: delete cached API entries stamped with `X-Kilo-Cached-At` that
 * are older than 24 hours. Entries without the header (legacy, pre-TTL) are
 * left alone — they age out via the existing logout/401 purge.
 */
function sweepStaleApiEntries() {
    const now = Date.now();
    return caches.open(API_CACHE).then((cache) =>
        cache.keys().then((requests) => Promise.all(requests.map((request) =>
            cache.match(request).then((cached) => {
                if (!cached) return;
                const raw = cached.headers.get('X-Kilo-Cached-At');
                if (raw === null || raw === '') return;
                const cachedAt = Number(raw);
                if (!Number.isFinite(cachedAt) || cachedAt <= 0) return;
                if (now - cachedAt > API_CACHE_TTL_MS) cache.delete(request);
            }),
        ))),
    );
}

