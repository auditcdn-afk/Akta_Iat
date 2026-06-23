const SESSION_KEY = "akta_session";

let plans = [];
let currentUser = null;
let usersList = [];
let unitUsahaList = [];

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

function canManagePlans() {
    // Tambah/perbaiki plan hanya untuk manajer audit & admin (UX di tangan admin)
    return ["admin", "manajer"].includes(currentUser?.role);
}

function showAlert(message, type = "success") {
    const alert = document.getElementById("planAlert");

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

function statusBadge(status) {
    const map = {
        draft: "bg-slate-500/10 text-slate-300 border-slate-500/20",
        scheduled: "bg-blue-500/10 text-blue-300 border-blue-500/20",
        running: "bg-amber-500/10 text-amber-300 border-amber-500/20",
        done: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
        cancelled: "bg-red-500/10 text-red-300 border-red-500/20",
    };

    return map[status] || map.draft;
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

async function loadCurrentUser() {
    const payload = await fetchJson("/api/auth/me");
    currentUser = payload.user;
}

async function loadPlanUsers() {
    const payload = await fetchJson("/api/plan-users");
    usersList = payload.data || [];
}

async function loadUnitUsahaOptions() {
    const payload = await fetchJson("/api/database/unit-usaha-options");
    unitUsahaList = payload.data || [];
}

async function loadPlans() {
    const q = document.getElementById("planSearch")?.value || "";
    const status = document.getElementById("planStatusFilter")?.value || "";

    const params = new URLSearchParams();

    if (q) {
        params.set("q", q);
    }

    if (status) {
        params.set("status", status);
    }

    const url = params.toString()
        ? `/api/plans?${params.toString()}`
        : "/api/plans";
    const payload = await fetchJson(url);

    plans = payload.data || [];
    renderPlans();
}

function renderPlans() {
    const tbody = document.getElementById("plansTableBody");

    if (!tbody) {
        return;
    }

    if (!plans.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada plan audit.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = plans
        .map((plan) => {
            const actions = canManagePlans()
                ? `
                <button type="button" class="edit-plan rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${plan.id}">
                    Edit
                </button>

                <button type="button" class="delete-plan ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${plan.id}">
                    Hapus
                </button>
            `
                : '<span class="text-xs text-slate-500">Read only</span>';

            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-4">
                    <div class="font-semibold text-slate-100">${escapeHtml(plan.noSpt || "-")}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(plan.jenisAudit || "-")}</div>
                </td>

                <td class="px-4 py-4">
                    <div class="text-sm font-semibold text-slate-200">${escapeHtml(plan.cabang || "-")}</div>
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    ${escapeHtml(plan.tglPlan || "-")}
                </td>

                <td class="px-4 py-4">
                    <div class="text-sm font-semibold text-slate-200">${escapeHtml(plan.kepalaTim || "-")}</div>
                    <div class="text-xs text-slate-500">${escapeHtml((plan.tim || []).join(", ") || "-")}</div>
                </td>

                <td class="px-4 py-4">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusBadge(plan.status)}">
                        ${escapeHtml(plan.status || "draft")}
                    </span>
                </td>

                <td class="px-4 py-4 text-right">
                    ${actions}
                </td>
            </tr>
        `;
        })
        .join("");
}

function renderCabangSelect(selected = "") {
    const el = document.getElementById("cabang");
    if (!el) return;

    const options = unitUsahaList
        .map(
            (u) =>
                `<option value="${escapeHtml(u.unitUsaha)}" ${u.unitUsaha === selected ? "selected" : ""}>${escapeHtml(u.unitUsaha)}</option>`,
        )
        .join("");

    el.innerHTML = `<option value="">-- Pilih Cabang --</option>${options}`;
}

function renderKepalaTimSelect(selected = "") {
    const el = document.getElementById("kepalaTim");
    if (!el) return;

    let lastWilayah = null;
    let options = "";

    for (const u of usersList) {
        if (u.wilayah !== lastWilayah) {
            if (lastWilayah !== null) options += "</optgroup>";
            options += `<optgroup label="${escapeHtml(u.wilayah || "Lainnya")}">`;
            lastWilayah = u.wilayah;
        }
        const label = escapeHtml(
            (u.displayName || u.name || u.username) +
                (u.unitUsaha ? ` — ${u.unitUsaha}` : ""),
        );
        const val = escapeHtml(u.displayName || u.name || u.username);
        options += `<option value="${val}" ${val === selected ? "selected" : ""}>${label}</option>`;
    }

    if (lastWilayah !== null) options += "</optgroup>";

    el.innerHTML = `<option value="">-- Pilih Kepala Tim --</option>${options}`;
}

function renderTimCheckboxes(selectedTim = []) {
    const container = document.getElementById("timContainer");
    if (!container) return;

    if (!usersList.length) {
        container.innerHTML =
            '<p class="py-2 text-center text-slate-500">Tidak ada pengguna tersedia.</p>';
        return;
    }

    let lastWilayah = null;
    let html = "";

    for (const u of usersList) {
        const label = (u.displayName || u.name || u.username) + (u.unitUsaha ? ` — ${u.unitUsaha}` : "");
        const isChecked = selectedTim.includes(u.displayName || u.name || u.username) ||
            selectedTim.includes(u.name) ||
            selectedTim.includes(u.username);

        if (u.wilayah !== lastWilayah) {
            if (lastWilayah !== null) html += "</div>";
            html += `<div class="mb-2"><p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(u.wilayah || "Lainnya")}</p><div class="space-y-1">`;
            lastWilayah = u.wilayah;
        }

        html += `
            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-800/60">
                <input type="checkbox" class="tim-checkbox h-4 w-4 rounded border-slate-600 bg-slate-900 accent-blue-500"
                    value="${escapeHtml(u.displayName || u.name || u.username)}"
                    ${isChecked ? "checked" : ""}>
                <span class="text-sm text-slate-300">${escapeHtml(label)}</span>
            </label>`;
    }

    if (lastWilayah !== null) html += "</div></div>";
    container.innerHTML = html;
}

function openModal(plan = null) {
    const modal = document.getElementById("planModal");
    const title = document.getElementById("planModalTitle");

    document.getElementById("planForm").reset();
    renderCabangSelect();
    renderKepalaTimSelect();
    renderTimCheckboxes([]);

    if (plan) {
        title.textContent = "Edit Plan Audit";

        document.getElementById("planId").value = plan.id;
        document.getElementById("noSpt").value = plan.noSpt || "";
        document.getElementById("jenisAudit").value = plan.jenisAudit || "Audit";
        document.getElementById("tglPlan").value = plan.tglPlan || "";
        document.getElementById("keterangan").value = plan.keterangan || "";

        renderCabangSelect(plan.cabang || "");
        renderKepalaTimSelect(plan.kepalaTim || "");
        renderTimCheckboxes(plan.tim || []);
    } else {
        title.textContent = "Tambah Plan Audit";

        document.getElementById("planId").value = "";
        document.getElementById("jenisAudit").value = "Audit";
        document.getElementById("tglPlan").value = "Otomatis saat disimpan";
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeModal() {
    const modal = document.getElementById("planModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function getFormPayload() {
    const tim = Array.from(
        document.querySelectorAll(".tim-checkbox:checked"),
    ).map((cb) => cb.value);

    return {
        no_spt: document.getElementById("noSpt").value.trim(),
        jenis_audit: document.getElementById("jenisAudit").value,
        cabang: document.getElementById("cabang").value,
        kepala_tim: document.getElementById("kepalaTim").value,
        tim,
        keterangan: document.getElementById("keterangan").value.trim(),
    };
}

async function savePlan(event) {
    event.preventDefault();

    if (!canManagePlans()) {
        showAlert("Role kamu hanya boleh melihat data.", "error");
        return;
    }

    const id = document.getElementById("planId").value;
    const isEdit = Boolean(id);

    const payload = await fetchJson(
        isEdit ? `/api/plans/${id}` : "/api/plans",
        {
            method: isEdit ? "PUT" : "POST",
            body: JSON.stringify(getFormPayload()),
        },
    );

    closeModal();
    showAlert(payload.message || "Plan audit berhasil disimpan.");
    await loadPlans();
}

async function deletePlan(id) {
    if (!canManagePlans()) {
        showAlert("Role kamu hanya boleh melihat data.", "error");
        return;
    }

    const plan = plans.find((item) => String(item.id) === String(id));

    if (!plan) {
        return;
    }

    const confirmed = confirm(`Hapus plan audit ${plan.noSpt || plan.cabang}?`);

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/plans/${id}`, {
        method: "DELETE",
    });

    showAlert(payload.message || "Plan audit berhasil dihapus.");
    await loadPlans();
}

function setupFilters() {
    let timer = null;

    document.getElementById("planSearch")?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(
            () =>
                loadPlans().catch((error) => showAlert(error.message, "error")),
            300,
        );
    });

    document
        .getElementById("planStatusFilter")
        ?.addEventListener("change", () => {
            loadPlans().catch((error) => showAlert(error.message, "error"));
        });
}

document.addEventListener("DOMContentLoaded", async () => {
    document
        .getElementById("openCreatePlanButton")
        ?.addEventListener("click", () => openModal());
    document
        .getElementById("closePlanModalButton")
        ?.addEventListener("click", closeModal);
    document
        .getElementById("cancelPlanFormButton")
        ?.addEventListener("click", closeModal);

    document
        .getElementById("planForm")
        ?.addEventListener("submit", async (event) => {
            try {
                await savePlan(event);
            } catch (error) {
                showAlert(
                    error.message || "Gagal menyimpan plan audit.",
                    "error",
                );
            }
        });

    document
        .getElementById("plansTableBody")
        ?.addEventListener("click", async (event) => {
            const editButton = event.target.closest(".edit-plan");
            const deleteButton = event.target.closest(".delete-plan");

            if (editButton) {
                const plan = plans.find(
                    (item) => String(item.id) === String(editButton.dataset.id),
                );
                openModal(plan);
                return;
            }

            if (deleteButton) {
                try {
                    await deletePlan(deleteButton.dataset.id);
                } catch (error) {
                    showAlert(
                        error.message || "Gagal menghapus plan audit.",
                        "error",
                    );
                }
            }
        });

    setupFilters();

    try {
        await loadCurrentUser();

        if (!canManagePlans()) {
            document
                .getElementById("openCreatePlanButton")
                ?.classList.add("hidden");
        }

        await Promise.all([loadPlanUsers(), loadUnitUsahaOptions()]);
        await loadPlans();
    } catch (error) {
        showAlert(error.message || "Gagal memuat plan audit.", "error");
    }
});
