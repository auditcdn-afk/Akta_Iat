const SESSION_KEY = "akta_session";

let users = [];
let roles = [];

// ── Color map untuk badge role ────────────────────────────────
const COLOR_MAP = {
    red:    "bg-red-500/10 text-red-300 border-red-500/20",
    amber:  "bg-amber-500/10 text-amber-300 border-amber-500/20",
    blue:   "bg-blue-500/10 text-blue-300 border-blue-500/20",
    green:  "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
    purple: "bg-purple-500/10 text-purple-300 border-purple-500/20",
    slate:  "bg-slate-500/10 text-slate-300 border-slate-500/20",
};

function colorClass(color) {
    return COLOR_MAP[color] || COLOR_MAP.slate;
}

// ── Role API ──────────────────────────────────────────────────
async function loadRoles() {
    const response = await fetch("/api/admin/roles", { headers: authHeaders() });
    const payload  = await response.json().catch(() => ({}));
    if (!response.ok) return;

    roles = payload.data || [];
    renderRoleSelect();
    renderRoleList();
}

function renderRoleSelect() {
    const select = document.getElementById("role");
    if (!select || !roles.length) return;

    const current = select.value;
    select.innerHTML = roles
        .map((r) => `<option value="${r.name}">${r.label}</option>`)
        .join("");

    if (current) select.value = current;
}

function renderRoleList() {
    const el = document.getElementById("roleList");
    if (!el) return;

    if (!roles.length) {
        el.innerHTML = `<p class="text-sm text-slate-500">Belum ada role.</p>`;
        return;
    }

    el.innerHTML = roles.map((r) => `
        <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950 px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold ${colorClass(r.color)}">
                    ${r.label}
                </span>
                <span class="text-xs text-slate-500 font-mono">${r.name}</span>
                ${r.isSystem ? '<span class="text-xs text-amber-400/70">sistem</span>' : ''}
            </div>
            <div class="flex gap-2">
                <button type="button" class="edit-role rounded-lg border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${r.id}">Edit</button>
                ${!r.isSystem ? `<button type="button" class="delete-role rounded-lg border border-red-500/30 px-3 py-1 text-xs font-semibold text-red-400 hover:bg-red-500/10" data-id="${r.id}">Hapus</button>` : ''}
            </div>
        </div>
    `).join("");
}

function showRoleAlert(msg, type = "success") {
    const el = document.getElementById("roleAlert");
    if (!el) return;
    el.className = `mb-3 rounded-xl border px-4 py-3 text-sm ${type === "error"
        ? "border-red-500/30 bg-red-500/10 text-red-200"
        : "border-emerald-500/30 bg-emerald-500/10 text-emerald-200"}`;
    el.textContent = msg;
    el.classList.remove("hidden");
    setTimeout(() => el.classList.add("hidden"), 4000);
}

function openRoleEdit(role) {
    document.getElementById("roleId").value = role.id;
    document.getElementById("roleName").value = role.name;
    document.getElementById("roleName").disabled = true; // slug tidak bisa diubah
    document.getElementById("roleLabel").value = role.label;
    document.getElementById("roleColor").value = role.color || "slate";
    document.getElementById("roleDescription").value = role.description || "";
    document.getElementById("roleFormTitle").textContent = `Edit Role: ${role.label}`;
    document.getElementById("saveRoleBtn").textContent = "Update Role";
    document.getElementById("cancelRoleBtn").classList.remove("hidden");
}

function resetRoleForm() {
    document.getElementById("roleId").value = "";
    document.getElementById("roleName").value = "";
    document.getElementById("roleName").disabled = false;
    document.getElementById("roleLabel").value = "";
    document.getElementById("roleColor").value = "slate";
    document.getElementById("roleDescription").value = "";
    document.getElementById("roleFormTitle").textContent = "+ Tambah Role Baru";
    document.getElementById("saveRoleBtn").textContent = "Simpan Role";
    document.getElementById("cancelRoleBtn").classList.add("hidden");
}

async function saveRole(event) {
    event.preventDefault();
    const id     = document.getElementById("roleId").value;
    const isEdit = Boolean(id);

    const body = {
        label:       document.getElementById("roleLabel").value.trim(),
        color:       document.getElementById("roleColor").value,
        description: document.getElementById("roleDescription").value.trim(),
    };
    if (!isEdit) {
        body.name = document.getElementById("roleName").value.trim();
    }

    const url    = isEdit ? `/api/admin/roles/${id}` : "/api/admin/roles";
    const method = isEdit ? "PUT" : "POST";

    const response = await fetch(url, {
        method,
        headers: authHeaders(),
        body: JSON.stringify(body),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstErr = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(firstErr || payload.message || "Gagal menyimpan role.");
    }

    // Update local roles array langsung — tidak re-fetch agar tidak ada race condition
    const saved = payload.data;
    if (isEdit) {
        const idx = roles.findIndex((r) => String(r.id) === String(id));
        if (idx !== -1) roles[idx] = saved;
        else roles.push(saved);
    } else {
        roles.push(saved);
    }

    renderRoleSelect();
    renderRoleList();
    showRoleAlert(payload.message || "Role berhasil disimpan.");
    resetRoleForm();
}

async function deleteRole(id) {
    const role = roles.find((r) => String(r.id) === String(id));
    if (!role) return;
    if (!confirm(`Hapus role "${role.label}"? Pastikan tidak ada user yang menggunakan role ini.`)) return;

    const response = await fetch(`/api/admin/roles/${id}`, {
        method: "DELETE",
        headers: authHeaders(),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || "Gagal menghapus role.");

    // Hapus dari local array langsung
    roles = roles.filter((r) => String(r.id) !== String(id));
    renderRoleSelect();
    renderRoleList();
    showRoleAlert(payload.message || "Role berhasil dihapus.");
}

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
    const alert = document.getElementById("userAlert");

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

function openModal(user = null) {
    const modal = document.getElementById("userModal");
    const title = document.getElementById("userModalTitle");
    const password = document.getElementById("password");

    document.getElementById("userForm").reset();

    if (user) {
        title.textContent = "Edit User";

        document.getElementById("userId").value = user.id;
        document.getElementById("username").value = user.username || "";
        document.getElementById("name").value = user.name || "";
        document.getElementById("displayName").value = user.displayName || "";
        document.getElementById("email").value = user.email || "";
        renderRoleSelect();
        document.getElementById("role").value = user.role || "auditor";
        document.getElementById("unitUsaha").value = user.unitUsaha || "";
        document.getElementById("wilayah").value = user.wilayah || "";
        document.getElementById("isDisabled").checked = Boolean(
            user.isDisabled,
        );
        password.required = false;
    } else {
        title.textContent = "Tambah User";

        document.getElementById("userId").value = "";
        document.getElementById("role").value = "auditor";
        password.required = true;
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeModal() {
    const modal = document.getElementById("userModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function roleBadge(role) {
    const map = {
        admin: "bg-red-500/10 text-red-300 border-red-500/20",
        manajer: "bg-amber-500/10 text-amber-300 border-amber-500/20",
        auditor: "bg-blue-500/10 text-blue-300 border-blue-500/20",
        viewer: "bg-slate-500/10 text-slate-300 border-slate-500/20",
    };

    return map[role] || map.viewer;
}

function renderUsers() {
    const tbody = document.getElementById("usersTableBody");

    if (!tbody) {
        return;
    }

    if (!users.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada pengguna.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = users
        .map(
            (user) => `
        <tr class="hover:bg-slate-950/50">
            <td class="px-4 py-4">
                <div class="font-semibold text-slate-100">${user.displayName || user.name || user.username}</div>
                <div class="text-xs text-slate-500">${user.username} • ${user.email || "-"}</div>
            </td>

            <td class="px-4 py-4">
<span class="inline-flex min-w-20 justify-center rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${roleBadge(user.role)}">
    ${user.role}
</span>
            </td>

            <td class="px-4 py-4 text-sm text-slate-300">
                ${user.unitUsaha || "-"}
            </td>

            <td class="px-4 py-4 text-sm text-slate-300">
                ${user.wilayah || "-"}
            </td>

            <td class="px-4 py-4">
                ${
                    user.isDisabled
                        ? '<span class="rounded-full bg-red-500/10 px-2.5 py-1 text-xs font-bold text-red-300">Nonaktif</span>'
                        : '<span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-300">Aktif</span>'
                }
            </td>

            <td class="px-4 py-4 text-right">
                <button
                    type="button"
                    class="edit-user rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                    data-id="${user.id}"
                >
                    Edit
                </button>

                <button
                    type="button"
                    class="delete-user ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10"
                    data-id="${user.id}"
                >
                    Hapus
                </button>
            </td>
        </tr>
    `,
        )
        .join("");
}

async function loadUsers() {
    const response = await fetch("/api/admin/users", {
        headers: authHeaders(),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.message || "Gagal memuat user.");
    }

    users = payload.data || [];
    renderUsers();
}

function getFormPayload() {
    const password = document.getElementById("password").value;

    const payload = {
        username: document.getElementById("username").value.trim(),
        name: document.getElementById("name").value.trim(),
        display_name: document.getElementById("displayName").value.trim(),
        email: document.getElementById("email").value.trim(),
        role: document.getElementById("role").value,
        unit_usaha: document.getElementById("unitUsaha").value.trim(),
        wilayah: document.getElementById("wilayah").value,
        is_disabled: document.getElementById("isDisabled").checked,
    };

    if (password) {
        payload.password = password;
    }

    return payload;
}

async function saveUser(event) {
    event.preventDefault();

    const id = document.getElementById("userId").value;
    const isEdit = Boolean(id);
    const url = isEdit ? `/api/admin/users/${id}` : "/api/admin/users";
    const method = isEdit ? "PUT" : "POST";

    const response = await fetch(url, {
        method,
        headers: authHeaders(),
        body: JSON.stringify(getFormPayload()),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : null;

        throw new Error(
            firstError || payload.message || "Gagal menyimpan user.",
        );
    }

    closeModal();
    showAlert(payload.message || "User berhasil disimpan.");
    await loadUsers();
}

async function deleteUser(id) {
    const user = users.find((item) => String(item.id) === String(id));

    if (!user) {
        return;
    }

    const confirmed = confirm(`Hapus user ${user.username}?`);

    if (!confirmed) {
        return;
    }

    const response = await fetch(`/api/admin/users/${id}`, {
        method: "DELETE",
        headers: authHeaders(),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.message || "Gagal menghapus user.");
    }

    showAlert(payload.message || "User berhasil dihapus.");
    await loadUsers();
}

document.addEventListener("DOMContentLoaded", async () => {
    // Toggle panel Kelola Role
    document.getElementById("toggleRolePanel")?.addEventListener("click", () => {
        const body    = document.getElementById("rolePanelBody");
        const chevron = document.getElementById("roleChevron");
        body.classList.toggle("hidden");
        chevron.classList.toggle("rotate-180");
    });

    // Form simpan role (tambah / edit)
    document.getElementById("roleForm")?.addEventListener("submit", async (e) => {
        try {
            await saveRole(e);
        } catch (err) {
            showRoleAlert(err.message || "Gagal menyimpan role.", "error");
        }
    });

    // Batal edit role
    document.getElementById("cancelRoleBtn")?.addEventListener("click", resetRoleForm);

    // Delegasi: edit & hapus role dari daftar
    document.getElementById("roleList")?.addEventListener("click", async (e) => {
        const editBtn   = e.target.closest(".edit-role");
        const deleteBtn = e.target.closest(".delete-role");

        if (editBtn) {
            const role = roles.find((r) => String(r.id) === String(editBtn.dataset.id));
            if (role) openRoleEdit(role);
        }
        if (deleteBtn) {
            try {
                await deleteRole(deleteBtn.dataset.id);
            } catch (err) {
                showRoleAlert(err.message || "Gagal menghapus role.", "error");
            }
        }
    });

    // Auto-sanitize username: lowercase, ganti spasi/karakter tidak valid dengan underscore
    document.getElementById("username")?.addEventListener("input", (e) => {
        const start = e.target.selectionStart;
        const sanitized = e.target.value
            .toLowerCase()
            .replace(/[^a-z0-9_-]/g, "_");
        if (sanitized !== e.target.value) {
            e.target.value = sanitized;
            e.target.setSelectionRange(start, start);
        }
    });

    document
        .getElementById("openCreateUserButton")
        ?.addEventListener("click", () => openModal());
    document
        .getElementById("closeUserModalButton")
        ?.addEventListener("click", closeModal);
    document
        .getElementById("cancelUserFormButton")
        ?.addEventListener("click", closeModal);

    document
        .getElementById("userForm")
        ?.addEventListener("submit", async (event) => {
            try {
                await saveUser(event);
            } catch (error) {
                showAlert(error.message || "Gagal menyimpan user.", "error");
            }
        });

    document
        .getElementById("usersTableBody")
        ?.addEventListener("click", async (event) => {
            const editButton = event.target.closest(".edit-user");
            const deleteButton = event.target.closest(".delete-user");

            if (editButton) {
                const user = users.find(
                    (item) => String(item.id) === String(editButton.dataset.id),
                );
                openModal(user);
                return;
            }

            if (deleteButton) {
                try {
                    await deleteUser(deleteButton.dataset.id);
                } catch (error) {
                    showAlert(
                        error.message || "Gagal menghapus user.",
                        "error",
                    );
                }
            }
        });

    try {
        await Promise.all([loadUsers(), loadRoles()]);
    } catch (error) {
        showAlert(error.message || "Gagal memuat data.", "error");
    }
});
