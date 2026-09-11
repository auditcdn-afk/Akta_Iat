// Service worker SIMPAS-IAT.
// Strategi:
// - Navigasi (buka halaman): network-first, fallback ke cache lalu ke offline.html.
// - Asset statis (build/JS/CSS/gambar/ikon): cache-first (jarang berubah, sudah versioned oleh Vite).
// - Permintaan API (/api/*): selalu ke jaringan, tidak pernah di-cache (data harus selalu live).
const CACHE_VERSION = "simpas-iat-v2";
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

// ── Notifikasi push ──────────────────────────────────────────────────────────
//
// Isi notifikasi dikirim server dalam keadaan terenkripsi (RFC 8291); browser
// yang membukanya, lalu menyerahkan hasilnya ke sini. Service worker ini bisa
// bangun walau aplikasinya sedang tertutup — itulah yang membuat notifikasi
// muncul di layar HP seperti aplikasi biasa.

const NOTIF_BAWAAN = {
    title: "SIMPAS-IAT",
    body: "Ada pembaruan yang menunggu Anda.",
    url: "/akta/dashboard",
};

function bacaPayload(event) {
    if (!event.data) return { ...NOTIF_BAWAAN };

    try {
        return { ...NOTIF_BAWAAN, ...event.data.json() };
    } catch {
        // Payload bukan JSON (mis. kiriman uji dari alat lain) — tetap tampilkan
        // teksnya daripada menelan notifikasinya diam-diam.
        return { ...NOTIF_BAWAAN, body: event.data.text() };
    }
}

self.addEventListener("push", (event) => {
    const data = bacaPayload(event);

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: "/icons/icon-192.png",
            badge: "/icons/icon-192.png",
            lang: "id",
            // tag membuat kabar susulan tentang hal yang sama MENIMPA yang lama,
            // bukan menumpuk jadi sepuluh baris tentang plan yang itu-itu juga.
            tag: data.tag || "akta-notif",
            renotify: true,
            timestamp: Date.now(),
            data: { url: data.url || NOTIF_BAWAAN.url },
        })
    );
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();

    const tujuan = new URL(
        (event.notification.data && event.notification.data.url) || NOTIF_BAWAAN.url,
        self.location.origin
    );

    // Kalau aplikasinya sudah terbuka di suatu tab, pakai tab itu — jangan
    // menumpuk jendela baru tiap kali sebuah notifikasi diketuk.
    event.waitUntil(
        self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((daftar) => {
            for (const klien of daftar) {
                if (klien.url.startsWith(self.location.origin) && "focus" in klien) {
                    return Promise.resolve(klien.navigate ? klien.navigate(tujuan.href) : klien)
                        .catch(() => klien)
                        .then((target) => (target || klien).focus());
                }
            }
            return self.clients.openWindow(tujuan.href);
        })
    );
});
