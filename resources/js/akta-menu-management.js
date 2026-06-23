const SESSION_KEY = "akta_session";

let menuItems = [];
let rolesList = []; // daftar nama role aktif dari backend

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
    if (!alert) return;

    alert.textContent = message;
    alert.classList.remove(
        "hidden",
        "border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200",
        "border-red-500/30", "bg-red-500/10", "text-red-200",
    );

    if (type === "error") {
        alert.classList.add("border-red-500/30", "bg-red-500/10", "text-red-200");
    } else {
        alert.classList.add("border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200");
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

function roleCheckboxes(item, index) {
    if (!rolesList.length) {
        return '<span class="text-xs text-slate-500">Belum ada role.</span>';
    }
    const allowed = new Set(item.roles || []);
    return `<div class="flex flex-wrap gap-2">${rolesList
        .map(
            (role) => `
        <label class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-slate-300 cursor-pointer hover:border-blue-500">
            <input type="checkbox" class="menu-role rounded border-slate-700 bg-slate-900"
                data-role="${escapeHtml(role)}" ${allowed.has(role) ? "checked" : ""}>
            <span class="uppercase">${escapeHtml(role)}</span>
        </label>`,
        )
        .join("")}</div>`;
}

function renderMenuTable() {
    const tbody = document.getElementById("menuTableBody");
    if (!tbody) return;

    if (!menuItems.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">
                    Tidak ada menu. Klik "Reset Default" untuk memuat dari konfigurasi.
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = menuItems
        .map(
            (item, index) => `
        <tr class="menu-row hover:bg-slate-950/50" data-index="${index}">
            <td class="px-3 py-3 align-top">
                <input type="number" min="1" max="999" value="${Number(item.order || index + 1)}"
                    class="menu-order w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            </td>

            <td class="px-3 py-3 align-top">
                <div class="truncate rounded-lg bg-slate-950 px-2 py-2 text-xs text-slate-400">
                    ${escapeHtml(item.routeName)}
                </div>
            </td>

            <td class="px-3 py-3 text-center align-top">
                <input type="checkbox" class="menu-visible mt-2 rounded border-slate-700 bg-slate-900"
                    ${item.isActive ? "checked" : ""}>
            </td>

            <td class="px-3 py-3 align-top">
                ${roleCheckboxes(item, index)}
            </td>

            <td class="px-3 py-3 align-top">
                <div class="truncate text-xs text-slate-500">${escapeHtml(item.path)}</div>
            </td>
        </tr>`,
        )
        .join("");
}

function collectRowPayload(row, original) {
    const roles = Array.from(row.querySelectorAll(".menu-role"))
        .filter((cb) => cb.checked)
        .map((cb) => cb.dataset.role);

    return {
        label: original.label,
        code: original.code || (original.label || "M").slice(0, 3).toUpperCase(),
        route_name: original.routeName,
        path: original.path,
        icon: original.icon,
        parent_id: original.parentId,
        order: Number(row.querySelector(".menu-order")?.value || original.order || 1),
        is_active: Boolean(row.querySelector(".menu-visible")?.checked),
        roles,
    };
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { ...authHeaders(), ...(options.headers || {}) },
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
    rolesList = payload.roles || [];
    renderMenuTable();
}

async function saveMenus() {
    const rows = Array.from(document.querySelectorAll(".menu-row"));
    let saved = 0;

    for (const row of rows) {
        const original = menuItems[Number(row.dataset.index)];
        const body = collectRowPayload(row, original);

        if (!body.roles.length) {
            throw new Error(`Menu "${body.label}" harus punya minimal satu role.`);
        }

        await fetchJson(`/api/admin/menus/${original.id}`, {
            method: "PUT",
            body: JSON.stringify(body),
        });
        saved++;
    }

    await loadMenus();
    showAlert(`${saved} menu berhasil disimpan. Refresh halaman untuk melihat sidebar terbaru.`);
}

async function resetMenus() {
    if (!confirm("Muat ulang menu dari konfigurasi default? Menu yang hilang akan ditambahkan kembali.")) {
        return;
    }

    const payload = await fetchJson("/api/admin/menus/seed", { method: "POST" });
    menuItems = payload.data || [];
    renderMenuTable();
    showAlert(payload.message || "Menu berhasil dimuat ulang dari konfigurasi.");
}

document.addEventListener("DOMContentLoaded", async () => {
    document.getElementById("saveMenuButton")?.addEventListener("click", async () => {
        try {
            await saveMenus();
        } catch (error) {
            showAlert(error.message || "Gagal menyimpan menu.", "error");
        }
    });

    document.getElementById("resetMenuButton")?.addEventListener("click", async () => {
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
