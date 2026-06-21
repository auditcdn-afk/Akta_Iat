const SESSION_KEY = "akta_session";

let menuItems = [];

function getSession() {
    try {
        const rawSession = sessionStorage.getItem(SESSION_KEY);
        return rawSession ? JSON.parse(rawSession) : null;
    } catch {
        return null;
    }
}

function authHeaders() {
    const session = getSession();

    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
    };
}

function showAlert(message, type = "success") {
    const alert = document.getElementById("menuAlert");

    if (!alert) {
        return;
    }

    alert.textContent = message;
    alert.classList.remove(
        "hidden",
        "border-emerald-500/30",
        "bg-emerald-500/10",
        "text-emerald-200",
        "border-red-500/30",
        "bg-red-500/10",
        "text-red-200",
    );

    if (type === "error") {
        alert.classList.add(
            "border-red-500/30",
            "bg-red-500/10",
            "text-red-200",
        );
    } else {
        alert.classList.add(
            "border-emerald-500/30",
            "bg-emerald-500/10",
            "text-emerald-200",
        );
    }
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function renderMenuTable() {
    const tbody = document.getElementById("menuTableBody");

    if (!tbody) {
        return;
    }

    if (!menuItems.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                    Tidak ada menu.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = menuItems
        .map(
            (item, index) => `
        <tr class="menu-row hover:bg-slate-950/50" data-index="${index}">
            <td class="px-3 py-3">
                <input
                    type="number"
                    min="1"
                    max="999"
                    value="${Number(item.order || index + 1)}"
                    class="menu-order w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"
                >
            </td>

            <td class="px-3 py-3">
                <input
                    type="text"
                    value="${escapeHtml(item.label)}"
                    class="menu-label w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-slate-100 outline-none focus:border-blue-500"
                >
            </td>

            <td class="px-3 py-3">
                <input
                    type="text"
                    maxlength="3"
                    value="${escapeHtml(item.code)}"
                    class="menu-code w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm uppercase text-slate-100 outline-none focus:border-blue-500"
                >
            </td>

            <td class="px-3 py-3">
                <div class="truncate rounded-lg bg-slate-950 px-2 py-2 text-xs text-slate-400">
                    ${escapeHtml(item.route)}
                </div>
            </td>

            <td class="px-3 py-3 text-center">
                <input
                    type="checkbox"
                    class="menu-visible rounded border-slate-700 bg-slate-900"
                    ${item.visible ? "checked" : ""}
                >
            </td>

            <td class="px-3 py-3 text-center">
                <input
                    type="checkbox"
                    class="menu-admin-only rounded border-slate-700 bg-slate-900"
                    ${item.admin_only ? "checked" : ""}
                >
            </td>

            <td class="px-3 py-3">
                <div class="truncate text-xs text-slate-500">
                    ${escapeHtml(item.path)}
                </div>
            </td>
        </tr>
    `,
        )
        .join("");
}

function collectMenuItems() {
    const rows = document.querySelectorAll(".menu-row");

    return Array.from(rows).map((row) => {
        const index = Number(row.dataset.index);
        const original = menuItems[index];

        return {
            route: original.route,
            label:
                row.querySelector(".menu-label")?.value.trim() ||
                original.label,
            code:
                row.querySelector(".menu-code")?.value.trim().toUpperCase() ||
                original.code,
            admin_only: Boolean(row.querySelector(".menu-admin-only")?.checked),
            visible: Boolean(row.querySelector(".menu-visible")?.checked),
            order: Number(
                row.querySelector(".menu-order")?.value ||
                    original.order ||
                    index + 1,
            ),
        };
    });
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            ...authHeaders(),
            ...(options.headers || {}),
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : null;

        throw new Error(firstError || payload.message || "Request gagal.");
    }

    return payload;
}

async function loadMenus() {
    const payload = await fetchJson("/api/admin/menus");

    menuItems = payload.data || [];
    renderMenuTable();
}

async function saveMenus() {
    const payload = await fetchJson("/api/admin/menus", {
        method: "PUT",
        body: JSON.stringify({
            items: collectMenuItems(),
        }),
    });

    menuItems = payload.data || [];
    renderMenuTable();

    showAlert(
        payload.message ||
            "Menu berhasil disimpan. Refresh halaman untuk melihat sidebar terbaru.",
    );
}

async function resetMenus() {
    const confirmed = confirm("Reset menu ke default config Laravel?");

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson("/api/admin/menus/reset", {
        method: "POST",
    });

    menuItems = payload.data || [];
    renderMenuTable();

    showAlert(payload.message || "Menu berhasil direset.");
}

document.addEventListener("DOMContentLoaded", async () => {
    document
        .getElementById("saveMenuButton")
        ?.addEventListener("click", async () => {
            try {
                await saveMenus();
            } catch (error) {
                showAlert(error.message || "Gagal menyimpan menu.", "error");
            }
        });

    document
        .getElementById("resetMenuButton")
        ?.addEventListener("click", async () => {
            try {
                await resetMenus();
            } catch (error) {
                showAlert(error.message || "Gagal reset menu.", "error");
            }
        });

    try {
        await loadMenus();
    } catch (error) {
        showAlert(error.message || "Gagal memuat menu.", "error");
    }
});
