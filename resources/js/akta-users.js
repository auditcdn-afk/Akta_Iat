const SESSION_KEY = "akta_session";

let users = [];

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
        document.getElementById("role").value = user.role || "auditor";
        document.getElementById("unitUsaha").value = user.unitUsaha || "";
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
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">
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
        await loadUsers();
    } catch (error) {
        showAlert(error.message || "Gagal memuat user.", "error");
    }
});
