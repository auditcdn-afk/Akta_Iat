const POLL_INTERVAL_MS = 60_000;

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function timeAgo(isoLike) {
    if (!isoLike) return "";
    const then = new Date(isoLike.replace(" ", "T"));
    if (Number.isNaN(then.getTime())) return "";

    const diffMs = Date.now() - then.getTime();
    const minutes = Math.floor(diffMs / 60_000);
    if (minutes < 1) return "baru saja";
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    const days = Math.floor(hours / 24);
    return `${days} hari lalu`;
}

function renderBadge(count) {
    const badge = document.getElementById("notifBellBadge");
    if (!badge) return;

    if (count > 0) {
        badge.textContent = count > 99 ? "99+" : String(count);
        badge.classList.remove("hidden");
    } else {
        badge.classList.add("hidden");
    }
}

function renderList(notifications, onItemClick) {
    const list = document.getElementById("notifList");
    if (!list) return;

    if (notifications.length === 0) {
        list.innerHTML = '<p class="px-4 py-6 text-center text-sm text-slate-400">Belum ada notifikasi.</p>';
        return;
    }

    list.innerHTML = notifications
        .map((n) => {
            const unreadDot = n.read
                ? ""
                : '<span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-500"></span>';

            return `
                <button type="button" data-notif-id="${n.id}" data-notif-url="${escapeHtml(n.url || "")}"
                    class="notif-item flex w-full items-start gap-2 border-b border-slate-800 px-4 py-3 text-left transition hover:bg-slate-800/60 ${n.read ? "opacity-60" : ""}">
                    ${unreadDot}
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-100">${escapeHtml(n.title)}</span>
                        ${n.message ? `<span class="mt-0.5 block text-xs text-slate-400">${escapeHtml(n.message)}</span>` : ""}
                        <span class="mt-1 block text-[11px] text-slate-500">${timeAgo(n.createdAt)}</span>
                    </span>
                </button>
            `;
        })
        .join("");

    list.querySelectorAll(".notif-item").forEach((el) => {
        el.addEventListener("click", () => onItemClick(el.dataset.notifId, el.dataset.notifUrl));
    });
}

export function initNotificationBell(authHeaders) {
    const btn = document.getElementById("notifBellBtn");
    const dropdown = document.getElementById("notifDropdown");
    const markAllBtn = document.getElementById("notifMarkAllReadBtn");

    if (!btn || !dropdown) {
        return;
    }

    let isOpen = false;

    const fetchNotifications = () =>
        fetch("/api/notifications", { headers: authHeaders() })
            .then((res) => (res.ok ? res.json() : Promise.reject(new Error("gagal memuat notifikasi"))))
            .then((payload) => {
                renderBadge(payload.unreadCount || 0);
                if (isOpen) {
                    renderList(payload.data || [], handleItemClick);
                }
                return payload;
            })
            .catch(() => {
                /* Polling diam-diam gagal (mis. koneksi putus) -- badge lama tetap ditampilkan. */
            });

    const handleItemClick = (id, url) => {
        fetch(`/api/notifications/${id}/read`, { method: "POST", headers: authHeaders() })
            .catch(() => {
                /* Gagal tandai terbaca tidak menghalangi navigasi. */
            })
            .finally(() => {
                if (url) {
                    window.location.href = url;
                } else {
                    fetchNotifications();
                }
            });
    };

    const openDropdown = () => {
        isOpen = true;
        dropdown.classList.remove("hidden");
        fetchNotifications();
    };

    const closeDropdown = () => {
        isOpen = false;
        dropdown.classList.add("hidden");
    };

    btn.addEventListener("click", (event) => {
        event.stopPropagation();
        if (isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    });

    document.addEventListener("click", (event) => {
        if (isOpen && !dropdown.contains(event.target) && event.target !== btn) {
            closeDropdown();
        }
    });

    if (markAllBtn) {
        markAllBtn.addEventListener("click", (event) => {
            event.stopPropagation();
            fetch("/api/notifications/read-all", { method: "POST", headers: authHeaders() })
                .catch(() => {
                    /* Gagal tandai semua terbaca -- badge akan tetap seperti semula sampai polling berikutnya. */
                })
                .finally(fetchNotifications);
        });
    }

    fetchNotifications();
    setInterval(fetchNotifications, POLL_INTERVAL_MS);
}
