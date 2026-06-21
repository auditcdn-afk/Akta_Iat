const SESSION_KEY = "akta_session";

let skItems = [];
let plans = [];
let currentUser = null;

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

function normalizeListPayload(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    return payload.data || [];
}

function canManageSk() {
    return ["admin", "manajer", "auditor"].includes(currentUser?.role);
}

function canApproveManajer() {
    return ["admin", "manajer"].includes(currentUser?.role);
}

function canApproveAfd() {
    return currentUser?.role === "admin";
}

function canEditSk(item) {
    if (!canManageSk()) {
        return false;
    }

    if (item.status === "selesai") {
        return currentUser?.role === "admin";
    }

    return true;
}

function canDeleteSk(item) {
    if (!canManageSk()) {
        return false;
    }

    if (item.status === "selesai") {
        return currentUser?.role === "admin";
    }

    return true;
}

function showAlert(message, type = "success") {
    const alert = document.getElementById("skAlert");

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
        pending_manajer: "bg-blue-500/10 text-blue-300 border-blue-500/20",
        pending_afd: "bg-amber-500/10 text-amber-300 border-amber-500/20",
        selesai: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
    };

    return map[status] || map.pending_manajer;
}

function statusLabel(status) {
    const map = {
        pending_manajer: "Pending Manajer",
        pending_afd: "Pending AFD",
        selesai: "Selesai",
    };

    return map[status] || status || "-";
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

async function loadPlans() {
    const payload = await fetchJson("/api/plans");

    plans = normalizeListPayload(payload);

    fillPlanSelect("planAuditId", "Tanpa Plan Audit");
    fillPlanSelect("skPlanFilter", "Semua Plan Audit");
}

async function loadSkItems() {
    const q = document.getElementById("skSearch")?.value || "";
    const status = document.getElementById("skStatusFilter")?.value || "";
    const planAuditId = document.getElementById("skPlanFilter")?.value || "";

    const params = new URLSearchParams();

    if (q) {
        params.set("q", q);
    }

    if (status) {
        params.set("status", status);
    }

    if (planAuditId) {
        params.set("plan_audit_id", planAuditId);
    }

    const url = params.toString() ? `/api/sk?${params.toString()}` : "/api/sk";

    const payload = await fetchJson(url);

    skItems = normalizeListPayload(payload);

    renderStats();
    renderSkItems();
}

function fillPlanSelect(elementId, firstLabel) {
    const select = document.getElementById(elementId);

    if (!select) {
        return;
    }

    select.innerHTML = `<option value="">${firstLabel}</option>`;

    plans.forEach((plan) => {
        const option = document.createElement("option");

        option.value = plan.id;
        option.textContent = planLabel(plan);

        select.appendChild(option);
    });
}

function planLabel(plan) {
    const noSpt = plan.noSpt || plan.no_spt || "-";
    const cabang = plan.cabang || plan.unitUsaha || plan.unit_usaha || "-";
    const status = plan.status || "-";

    return `#${plan.id} • ${noSpt} • ${cabang} • ${status}`;
}

function renderStats() {
    document.getElementById("skTotalStat").textContent = skItems.length;
    document.getElementById("skPendingManajerStat").textContent =
        skItems.filter((item) => item.status === "pending_manajer").length;
    document.getElementById("skPendingAfdStat").textContent = skItems.filter(
        (item) => item.status === "pending_afd",
    ).length;
    document.getElementById("skSelesaiStat").textContent = skItems.filter(
        (item) => item.status === "selesai",
    ).length;
}

function renderSkItems() {
    const tbody = document.getElementById("skTableBody");

    if (!tbody) {
        return;
    }

    if (!skItems.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada SK.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = skItems
        .map((item) => {
            const plan = item.plan_audit || item.planAudit || {};
            const file = item.file_sk || item.fileSk || {};
            const steps = item.steps || {};

            const approveManajerButton =
                canApproveManajer() && item.status === "pending_manajer"
                    ? `
                        <button type="button" class="approve-manajer-sk ml-2 rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10" data-id="${item.id}">
                            Approve Manajer
                        </button>
                    `
                    : "";

            const approveAfdButton =
                canApproveAfd() && item.status === "pending_afd"
                    ? `
                        <button type="button" class="approve-afd-sk ml-2 rounded-lg border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/10" data-id="${item.id}">
                            Approve AFD
                        </button>
                    `
                    : "";

            const editButton = canEditSk(item)
                ? `
                    <button type="button" class="edit-sk rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                        Edit
                    </button>
                `
                : "";

            const deleteButton = canDeleteSk(item)
                ? `
                    <button type="button" class="delete-sk ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">
                        Hapus
                    </button>
                `
                : "";

            const actions = [
                editButton,
                deleteButton,
                approveManajerButton,
                approveAfdButton,
            ]
                .filter(Boolean)
                .join("");

            const finalActions =
                actions ||
                '<span class="text-xs text-slate-500">Read only</span>';

            return `
                <tr class="hover:bg-slate-950/50">
                    <td class="px-4 py-4">
                        <div class="font-semibold text-slate-100">${escapeHtml(item.no_sk || item.noSk || "-")}</div>
                        <div class="text-xs text-slate-500">
                            No SPT: ${escapeHtml(item.no_spt || item.noSpt || "-")}
                        </div>
                        <div class="text-xs text-slate-500">
                            Jenis Audit: ${escapeHtml(item.jenis_audit || item.jenisAudit || "-")}
                        </div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>${escapeHtml(item.unit_usaha || item.unitUsaha || plan.cabang || "-")}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(plan.id ? planLabel(plan) : "-")}</div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>${escapeHtml(file.name || "-")}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(file.type || "-")}</div>
                        ${
                            file.url
                                ? `<a href="${escapeHtml(file.url)}" target="_blank" class="mt-1 inline-flex text-xs font-semibold text-blue-300 hover:text-blue-200">Buka File</a>`
                                : '<div class="mt-1 text-xs text-slate-600">Tidak ada URL</div>'
                        }
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>${escapeHtml(item.uploaded_by_name || item.uploadedByName || "-")}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(formatDateTime(item.uploaded_at || item.uploadedAt))}</div>
                    </td>

                    <td class="px-4 py-4">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${statusBadge(item.status)}">
                            ${escapeHtml(statusLabel(item.status))}
                        </span>

                        <div class="mt-2 text-xs text-slate-500">
                            <div>Manajer: ${escapeHtml(steps.manajer?.byName || "-")}</div>
                            <div>AFD: ${escapeHtml(steps.afd?.byName || "-")}</div>
                        </div>
                    </td>

                    <td class="px-4 py-4 text-right">
                        ${finalActions}
                    </td>
                </tr>
            `;
        })
        .join("");
}

function openModal(item = null) {
    const modal = document.getElementById("skModal");
    const title = document.getElementById("skModalTitle");

    document.getElementById("skForm").reset();

    if (item) {
        const file = item.file_sk || item.fileSk || {};

        title.textContent = "Edit SK";

        document.getElementById("skId").value = item.id;
        document.getElementById("planAuditId").value =
            item.plan_audit_id || item.planAuditId || "";
        document.getElementById("noSk").value = item.no_sk || item.noSk || "";
        document.getElementById("noSpt").value =
            item.no_spt || item.noSpt || "";
        document.getElementById("unitUsaha").value =
            item.unit_usaha || item.unitUsaha || "";
        document.getElementById("jenisAudit").value =
            item.jenis_audit || item.jenisAudit || "";

        document.getElementById("fileName").value = file.name || "";
        document.getElementById("fileType").value = file.type || "";
        document.getElementById("fileUrl").value = file.url || "";
    } else {
        title.textContent = "Tambah SK";

        document.getElementById("skId").value = "";
        document.getElementById("fileType").value = "application/pdf";
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeModal() {
    const modal = document.getElementById("skModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function getFormPayload() {
    const planAuditId = document.getElementById("planAuditId").value;

    const fileName = emptyToNull(document.getElementById("fileName").value);
    const fileType = emptyToNull(document.getElementById("fileType").value);
    const fileUrl = emptyToNull(document.getElementById("fileUrl").value);

    const fileSk =
        fileName || fileType || fileUrl
            ? {
                  name: fileName,
                  type: fileType,
                  url: fileUrl,
              }
            : null;

    return {
        plan_audit_id: planAuditId ? Number(planAuditId) : null,
        no_sk: document.getElementById("noSk").value.trim(),
        no_spt: emptyToNull(document.getElementById("noSpt").value),
        unit_usaha: emptyToNull(document.getElementById("unitUsaha").value),
        jenis_audit: emptyToNull(document.getElementById("jenisAudit").value),
        file_sk: fileSk,
    };
}

async function saveSk(event) {
    event.preventDefault();

    if (!canManageSk()) {
        showAlert("Role kamu hanya boleh melihat data.", "error");
        return;
    }

    const id = document.getElementById("skId").value;
    const isEdit = Boolean(id);

    const payload = await fetchJson(isEdit ? `/api/sk/${id}` : "/api/sk", {
        method: isEdit ? "PUT" : "POST",
        body: JSON.stringify(getFormPayload()),
    });

    closeModal();
    showAlert(payload.message || "SK berhasil disimpan.");
    await loadSkItems();
}

async function deleteSk(id) {
    const item = skItems.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(
        `Hapus SK "${item.no_sk || item.noSk || item.id}"?`,
    );

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/sk/${id}`, {
        method: "DELETE",
    });

    showAlert(payload.message || "SK berhasil dihapus.");
    await loadSkItems();
}

async function approveManajer(id) {
    const item = skItems.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(
        `Approve tahap manajer untuk SK "${item.no_sk || item.noSk || item.id}"?`,
    );

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/sk/${id}/approve-manajer`, {
        method: "POST",
    });

    showAlert(payload.message || "SK berhasil disetujui manajer.");
    await loadSkItems();
}

async function approveAfd(id) {
    const item = skItems.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(
        `Approve tahap AFD untuk SK "${item.no_sk || item.noSk || item.id}"?`,
    );

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/sk/${id}/approve-afd`, {
        method: "POST",
    });

    showAlert(payload.message || "SK berhasil disetujui AFD.");
    await loadSkItems();
}

function setupFilters() {
    let timer = null;

    document.getElementById("skSearch")?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(
            () =>
                loadSkItems().catch((error) =>
                    showAlert(error.message, "error"),
                ),
            300,
        );
    });

    document
        .getElementById("skStatusFilter")
        ?.addEventListener("change", () => {
            loadSkItems().catch((error) => showAlert(error.message, "error"));
        });

    document.getElementById("skPlanFilter")?.addEventListener("change", () => {
        loadSkItems().catch((error) => showAlert(error.message, "error"));
    });
}

function emptyToNull(value) {
    const clean = String(value || "").trim();

    return clean === "" ? null : clean;
}

function formatDateTime(value) {
    if (!value) {
        return "-";
    }

    return String(value).replace("T", " ").slice(0, 19);
}

document.addEventListener("DOMContentLoaded", async () => {
    document
        .getElementById("openCreateSkButton")
        ?.addEventListener("click", () => openModal());

    document
        .getElementById("closeSkModalButton")
        ?.addEventListener("click", closeModal);

    document
        .getElementById("cancelSkFormButton")
        ?.addEventListener("click", closeModal);

    document
        .getElementById("skForm")
        ?.addEventListener("submit", async (event) => {
            try {
                await saveSk(event);
            } catch (error) {
                showAlert(error.message || "Gagal menyimpan SK.", "error");
            }
        });

    document
        .getElementById("skTableBody")
        ?.addEventListener("click", async (event) => {
            const editButton = event.target.closest(".edit-sk");
            const deleteButton = event.target.closest(".delete-sk");
            const approveManajerButton = event.target.closest(
                ".approve-manajer-sk",
            );
            const approveAfdButton = event.target.closest(".approve-afd-sk");

            if (editButton) {
                const item = skItems.find(
                    (row) => String(row.id) === String(editButton.dataset.id),
                );

                openModal(item);
                return;
            }

            if (deleteButton) {
                try {
                    await deleteSk(deleteButton.dataset.id);
                } catch (error) {
                    showAlert(error.message || "Gagal menghapus SK.", "error");
                }

                return;
            }

            if (approveManajerButton) {
                try {
                    await approveManajer(approveManajerButton.dataset.id);
                } catch (error) {
                    showAlert(
                        error.message || "Gagal approve manajer.",
                        "error",
                    );
                }

                return;
            }

            if (approveAfdButton) {
                try {
                    await approveAfd(approveAfdButton.dataset.id);
                } catch (error) {
                    showAlert(error.message || "Gagal approve AFD.", "error");
                }
            }
        });

    setupFilters();

    try {
        await loadCurrentUser();

        if (!canManageSk()) {
            document
                .getElementById("openCreateSkButton")
                ?.classList.add("hidden");
        }

        await loadPlans();
        await loadSkItems();
    } catch (error) {
        showAlert(error.message || "Gagal memuat SK.", "error");
    }
});
