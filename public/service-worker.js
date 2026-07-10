// Service worker SIMPAS-IAT.
// Strategi:
// - Navigasi (buka halaman): network-first, fallback ke cache lalu ke offline.html.
// - Asset statis (build/JS/CSS/gambar/ikon): cache-first (jarang berubah, sudah versioned oleh Vite).
// - Permintaan API (/api/*): selalu ke jaringan, tidak pernah di-cache (data harus selalu live).
const CACHE_VERSION = "simpas-iat-v1";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const PAGE_CACHE = `${CACHE_VERSION}-pages`;
const OFFLINE_URL = "/offline.html";

const PRECACHE_URLS = [
    OFFLINE_URL,
    "/manifest.json",
];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key.startsWith("simpas-iat-") && key !== STATIC_CACHE && key !== PAGE_CACHE)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

function isApiRequest(url) {
    return url.pathname.startsWith("/api/");
}

function isStaticAsset(url) {
    return (
        url.pathname.startsWith("/build/") ||
        url.pathname.startsWith("/icons/") ||
        url.pathname === "/manifest.json" ||
        url.pathname === "/favicon.ico"
    );
}

self.addEventListener("fetch", (event) => {
    const { request } = event;
    if (request.method !== "GET") return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (isApiRequest(url)) return; // biarkan browser handle langsung, tidak di-cache

    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const clone = response.clone();
                    caches.open(PAGE_CACHE).then((cache) => cache.put(request, clone));
                    return response;
                })
                .catch(() =>
                    caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL))
                )
        );
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    const clone = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, clone));
                    return response;
                });
            })
        );
    }
});
