import { SESSION_KEY, readSession, cachedUser, updateCachedUser } from "./akta-session.js";
import { initNotificationBell } from "./akta-notifications.js";

const SIDEBAR_SCROLL_KEY = "akta_sidebar_scroll_top";

function getSession() {
    return readSession();
}

function clearSession() {
    sessionStorage.removeItem(SESSION_KEY);
}

function redirectToLogin() {
    window.location.href = "/akta/login";
}

function authHeaders(session) {
    return {
        Accept: "application/json",
        Authorization: `${session.tokenType || "Bearer"} ${session.token}`,
    };
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value;
    }
}

function renderUser(user) {
    const displayName =
        user.displayName || user.name || user.username || "User";
    const roleText = `${user.role || "-"} • ${user.unitUsaha || "-"}`;

    setText("shellUserName", displayName);
    setText("shellUserRole", roleText);
    setText("topbarUserName", displayName);
    setText("topbarUserRole", roleText);
    setText("dashboardAuthStatus", "Aktif");

    renderAvatar(user, displayName);
}

function renderAvatar(user, displayName) {
    const initial = (displayName || "U").trim().charAt(0).toUpperCase();
    const avatar = document.getElementById("topbarAvatar");
    const initialEl = document.getElementById("topbarAvatarInitial");

    if (initialEl) {
        initialEl.textContent = initial;
    }

    if (avatar && user.photoUrl) {
        avatar.innerHTML = `<img src="${user.photoUrl}" alt="Foto" class="h-full w-full object-cover">`;
    }
}

function applyRoleVisibility(user) {
    const role = user.role || "";

    // data-roles="admin,manajer" — tampilkan jika role user ada di daftar
    // Security note: ini hanya UX; proteksi sesungguhnya ada di API middleware
    document.querySelectorAll("[data-roles]").forEach((element) => {
        const allowed = element.dataset.roles.split(",").filter(Boolean);
        if (allowed.length === 0 || allowed.includes(role)) {
            element.classList.remove("hidden");
        } else {
            element.classList.add("hidden");
        }
    });

    // Backward-compat: data-admin-only masih dipakai oleh komponen lain
    const isAdmin = role === "admin";
    document.querySelectorAll('[data-admin-only="true"]').forEach((element) => {
        if (isAdmin) {
            element.classList.remove("hidden");
        } else {
            element.classList.add("hidden");
        }
    });
}

async function validateSession(session) {
    const response = await fetch("/api/auth/me", {
        method: "GET",
        headers: authHeaders(session),
    });

    if (!response.ok) {
        // Status ikut dibawa supaya pemanggil bisa membedakan token yang
        // BENAR-BENAR tidak valid (401 dari Sanctum) dari kegagalan lain
        // (jaringan/sinyal lemah, server 500/502/503 sesaat, timeout) — lihat
        // catatan di pemanggil kenapa ini penting.
        const error = new Error("Session tidak valid.");
        error.status = response.status;
        throw error;
    }

    return response.json();
}

async function logout(session) {
    try {
        await fetch("/api/auth/logout", {
            method: "POST",
            headers: authHeaders(session),
        });
    } finally {
        clearSession();
        redirectToLogin();
    }
}

function setupMobileMenu() {
    const button = document.getElementById("mobileMenuButton");
    const panel = document.getElementById("mobileMenuPanel");

    if (!button || !panel) {
        return;
    }

    button.addEventListener("click", () => {
        panel.classList.toggle("hidden");
    });
}

function setupSidebarScrollMemory() {
    const sidebarNav = document.getElementById("desktopSidebarNav");

    if (!sidebarNav) {
        return;
    }

    const saveScroll = () => {
        const maxSafeScroll = Math.max(
            0,
            sidebarNav.scrollHeight - sidebarNav.clientHeight,
        );
        const currentScroll = Math.min(sidebarNav.scrollTop, maxSafeScroll);

        sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(currentScroll));
    };

    sidebarNav.addEventListener("scroll", saveScroll, { passive: true });

    sidebarNav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("pointerdown", saveScroll);
        link.addEventListener("mousedown", saveScroll);
        link.addEventListener("touchstart", saveScroll, { passive: true });
        link.addEventListener("click", saveScroll);
    });

    window.addEventListener("beforeunload", saveScroll);
}

function guardAdminOnlyPage(user) {
    const adminOnlyPaths = [
        "/akta/pengguna",
        "/akta/monitoring",
        "/akta/manajemen-menu",
    ];

    const currentPath = window.location.pathname;
    const isAdminOnlyPage = adminOnlyPaths.includes(currentPath);

    if (isAdminOnlyPage && user.role !== "admin") {
        window.location.href = "/akta/dashboard";
    }
}

function setupThemeToggle() {
    const THEME_KEY = "akta_theme";
    const btn = document.getElementById("themeToggleBtn");
    if (!btn) return;

    btn.addEventListener("click", () => {
        const current = document.documentElement.getAttribute("data-theme") || "dark";
        const next = current === "dark" ? "light" : "dark";
        document.documentElement.setAttribute("data-theme", next);
        try {
            localStorage.setItem(THEME_KEY, next);
        } catch {
            /* localStorage tidak tersedia, tema tidak tersimpan antar sesi */
        }
    });
}

// Setiap klik menu memicu full page reload (aplikasi ini MPA, bukan SPA).
// Dengan mengambil HTML halaman tujuan sejak kursor menyentuh menunya, dokumen
// biasanya sudah ada di cache browser saat user benar-benar mengklik — sehingga
// bagian "menunggu server" dari navigasi hilang tanpa mengubah arsitektur.
function setupNavPrefetch() {
    const prefetched = new Set();

    const prefetch = (event) => {
        // Listener dipasang di `document` dengan capture, jadi event juga lewat
        // saat sasarannya bukan elemen — node `document` sendiri, misalnya, yang
        // tidak punya closest(). Tanpa penjagaan ini, tiap kursor melintasi batas
        // dokumen akan melempar "target.closest is not a function" ke konsol.
        const asal = event.target;
        const link = asal instanceof Element ? asal.closest("a.akta-menu-item") : null;

        if (!link) {
            return;
        }

        const href = link.getAttribute("href");

        if (!href || href === "#") {
            return;
        }

        // route() menghasilkan URL absolut, jadi href harus di-resolve dulu —
        // bukan dicek dengan startsWith("/") — lalu dibatasi ke origin sendiri
        // supaya link eksternal tidak ikut di-prefetch.
        let target;

        try {
            target = new URL(href, window.location.href);
        } catch {
            return;
        }

        if (target.origin !== window.location.origin) {
            return;
        }

        // Halaman yang sedang dibuka tidak perlu di-prefetch.
        if (target.pathname === window.location.pathname) {
            return;
        }

        if (prefetched.has(target.href)) {
            return;
        }

        prefetched.add(target.href);

        const hint = document.createElement("link");
        hint.rel = "prefetch";
        hint.as = "document";
        hint.href = target.href;
        document.head.appendChild(hint);
    };

    document.addEventListener("pointerenter", prefetch, { capture: true, passive: true });
    document.addEventListener("touchstart", prefetch, { capture: true, passive: true });
}

function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return;
    window.addEventListener("load", () => {
        navigator.serviceWorker.register("/service-worker.js").catch(() => {
            /* PWA opsional; abaikan kalau browser/lingkungan tidak mendukung */
        });
    });
}

function applyUser(user) {
    renderUser(user);
    applyRoleVisibility(user);
    guardAdminOnlyPage(user);
}

document.addEventListener("DOMContentLoaded", () => {
    setupMobileMenu();
    setupSidebarScrollMemory();
    setupThemeToggle();
    registerServiceWorker();
    setupNavPrefetch();

    const session = getSession();

    if (!session || !session.token) {
        clearSession();
        redirectToLogin();
        return;
    }

    const logoutButton = document.getElementById("shellLogoutButton");

    if (logoutButton) {
        logoutButton.addEventListener("click", () => logout(session));
    }

    initNotificationBell(() => authHeaders(session));

    // Objek user sudah tersimpan sejak login, jadi nama/role/menu bisa langsung
    // digambar tanpa menunggu jaringan sama sekali.
    const known = cachedUser();

    if (known) {
        applyUser(known);
    }

    // Validasi sesi tetap dilakukan, tapi di latar belakang — halaman tidak lagi
    // menunggu satu round-trip penuh sebelum menampilkan apa pun. Kalau server
    // menolak token, user tetap dilempar ke login; kalau data user di server
    // berbeda (mis. role diubah admin), tampilan disegarkan di sini.
    validateSession(session)
        .then((payload) => {
            const user = payload.user;

            updateCachedUser(user);

            if (!known || JSON.stringify(user) !== JSON.stringify(known)) {
                applyUser(user);
            }
        })
        .catch((error) => {
            // Sebelumnya SETIAP kegagalan di sini (termasuk sinyal/koneksi
            // putus sesaat, atau server sempat 500/502/503/timeout) langsung
            // menghapus sesi dan melempar ke halaman login — padahal token-nya
            // sendiri sebenarnya masih sah. Auditor yang koneksinya sekadar
            // lemot sesaat jadi ikut ter-logout tanpa alasan. Sekarang hanya
            // 401 (Sanctum memang menolak token-nya — kadaluarsa/dihapus/tidak
            // valid) yang memaksa logout; kegagalan lain dibiarkan saja, sesi
            // yang sudah ada di layar tetap dipakai sampai request berikutnya
            // berhasil atau memang benar-benar ditolak.
            if (error?.status === 401) {
                clearSession();
                redirectToLogin();
            }
        });
});
