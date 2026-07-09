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
    return ["admin", "afd"].includes(currentUser?.role);
}

function canResubmitSk(item) {
    if (item.status !== "ditolak") return false;
    if (currentUser?.role === "admin") return true;
    return currentUser?.username && currentUser.username === (item.uploaded_by || item.uploadedBy);
}

function canEditSk(item) {
    return currentUser?.role === "admin";
}

function canDeleteSk(item) {
    if (currentUser?.role !== "admin") {
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
        ditolak: "bg-red-500/10 text-red-300 border-red-500/20",
    };

    return map[status] || map.pending_manajer;
}

function statusLabel(status) {
    const map = {
        pending_manajer: "Pending Manajer",
        pending_afd: "Pending AFD",
        selesai: "Selesai",
        ditolak: "Ditolak",
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

            const rejectManajerButton =
                canApproveManajer() && item.status === "pending_manajer"
                    ? `
                        <button type="button" class="reject-manajer-sk ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">
                            Reject
                        </button>
                    `
                    : "";

            const rejectAfdButton =
                canApproveAfd() && item.status === "pending_afd"
                    ? `
                        <button type="button" class="reject-afd-sk ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">
                            Reject
                        </button>
                    `
                    : "";

            const resubmitButton = canResubmitSk(item)
                ? `
                    <button type="button" class="resubmit-sk ml-2 rounded-lg bg-blue-600 hover:bg-blue-500 px-3 py-1.5 text-xs font-semibold text-white transition" data-id="${item.id}">
                        Upload Ulang
                    </button>
                `
                : "";

            const distributeButton =
                item.status === "selesai" && ["admin", "auditor"].includes(currentUser?.role)
                    ? `
                        <button type="button" class="distribute-sk ml-2 rounded-lg border border-violet-500/40 px-3 py-1.5 text-xs font-semibold text-violet-300 hover:bg-violet-500/10" data-id="${item.id}">
                            Distribusikan
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
                rejectManajerButton,
                approveAfdButton,
                rejectAfdButton,
                resubmitButton,
                distributeButton,
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

        const existingEl = document.getElementById("skFileExisting");
        if (existingEl) {
            existingEl.textContent = file.name
                ? `File saat ini: ${file.name}`
                : "";
        }
    } else {
        title.textContent = "Tambah SK";

        document.getElementById("skId").value = "";
        const existingEl = document.getElementById("skFileExisting");
        if (existingEl) existingEl.textContent = "";
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

    return {
        plan_audit_id: planAuditId ? Number(planAuditId) : null,
        no_sk: document.getElementById("noSk").value.trim(),
        no_spt: emptyToNull(document.getElementById("noSpt").value),
        unit_usaha: emptyToNull(document.getElementById("unitUsaha").value),
        jenis_audit: emptyToNull(document.getElementById("jenisAudit").value),
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
    const fileInput = document.getElementById("skFile");
    const fields = getFormPayload();

    const formData = new FormData();
    Object.entries(fields).forEach(([key, value]) => {
        if (value !== null && value !== undefined) formData.append(key, value);
    });
    if (fileInput?.files?.[0]) {
        formData.append("file", fileInput.files[0]);
    }
    if (isEdit) {
        formData.append("_method", "PUT");
    }

    const session = getSession();
    const response = await fetch(isEdit ? `/api/sk/${id}` : "/api/sk", {
        method: "POST",
        headers: {
            Accept: "application/json",
            Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
        },
        body: formData,
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : null;
        throw new Error(firstError || payload.message || "Request gagal.");
    }

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

async function rejectSk(id, stage) {
    const item = skItems.find((row) => String(row.id) === String(id));
    if (!item) return;

    const reason = prompt(
        `Alasan penolakan SK "${item.no_sk || item.noSk || item.id}" (opsional):`,
        "",
    );
    if (reason === null) return; // dibatalkan

    const payload = await fetchJson(`/api/sk/${id}/reject-${stage}`, {
        method: "POST",
        body: JSON.stringify({ reason }),
    });

    showAlert(payload.message || "SK ditolak.");
    await loadSkItems();
}

function openResubmitModal(id) {
    const modal = document.getElementById("resubmitSkModal");
    if (!modal) return;
    document.getElementById("resubmitSkId").value = id;
    document.getElementById("resubmitSkFile").value = "";
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeResubmitModal() {
    const modal = document.getElementById("resubmitSkModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function saveResubmitSk(event) {
    event.preventDefault();
    const id = document.getElementById("resubmitSkId").value;
    const file = document.getElementById("resubmitSkFile").files?.[0];
    const btn = document.getElementById("saveResubmitSkBtn");

    if (!file) {
        showAlert("File PDF wajib diisi.", "error");
        return;
    }

    const formData = new FormData();
    formData.append("file", file);

    btn.textContent = "Mengunggah...";
    btn.disabled = true;
    try {
        const session = getSession();
        const response = await fetch(`/api/sk/${id}/resubmit`, {
            method: "POST",
            headers: {
                Accept: "application/json",
                Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
            },
            body: formData,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(firstError || payload.message || "Request gagal.");
        }
        closeResubmitModal();
        showAlert(payload.message || "SK berhasil diunggah ulang.");
        await loadSkItems();
    } catch (e) {
        showAlert(e.message || "Gagal mengunggah ulang SK.", "error");
    } finally {
        btn.textContent = "Simpan";
        btn.disabled = false;
    }
}

let allUserOptions = [];

async function loadUserOptions() {
    const payload = await fetchJson("/api/users/options");
    allUserOptions = payload.data || [];
}

function openDistributeModal(id) {
    const modal = document.getElementById("distributeSkModal");
    if (!modal) return;
    document.getElementById("distributeSkId").value = id;

    const list = document.getElementById("distributeSkUserList");
    if (list) {
        list.innerHTML = allUserOptions.map((u) => `
            <label class="flex items-center gap-2 rounded-lg border border-slate-800 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800/60 cursor-pointer">
                <input type="checkbox" value="${escapeAttr(u.username)}" class="distribute-user-checkbox">
                ${escapeAttr(u.label)}
            </label>
        `).join("");
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeDistributeModal() {
    const modal = document.getElementById("distributeSkModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function escapeAttr(value) {
    return String(value ?? "").replaceAll('"', "&quot;");
}

async function saveDistributeSk(event) {
    event.preventDefault();
    const id = document.getElementById("distributeSkId").value;
    const checked = Array.from(document.querySelectorAll(".distribute-user-checkbox:checked")).map((el) => el.value);
    const btn = document.getElementById("saveDistributeSkBtn");

    if (!checked.length) {
        showAlert("Pilih minimal satu pengguna.", "error");
        return;
    }

    btn.textContent = "Mendistribusikan...";
    btn.disabled = true;
    try {
        const payload = await fetchJson(`/api/sk/${id}/distribute`, {
            method: "POST",
            body: JSON.stringify({ usernames: checked }),
        });
        closeDistributeModal();
        showAlert(payload.message || "SK berhasil didistribusikan.");
    } catch (e) {
        showAlert(e.message || "Gagal mendistribusikan SK.", "error");
    } finally {
        btn.textContent = "Distribusikan";
        btn.disabled = false;
    }
}

let myDistribusiItems = [];

async function loadMyDistribusi() {
    const section = document.getElementById("myDistribusiSection");
    const body = document.getElementById("myDistribusiTableBody");
    if (!section || !body) return;

    const payload = await fetchJson("/api/sk-distribusi/saya");
    myDistribusiItems = payload.data || [];

    if (!myDistribusiItems.length) {
        section.classList.add("hidden");
        return;
    }
    section.classList.remove("hidden");

    body.innerHTML = myDistribusiItems.map((item) => {
        const sk = item.surat_keputusan || item.suratKeputusan || {};
        const file = sk.file_sk || sk.fileSk || {};
        const done = item.status === "ditanggapi";
        const badge = done
            ? "bg-emerald-500/10 text-emerald-300 border-emerald-500/20"
            : "bg-amber-500/10 text-amber-300 border-amber-500/20";
        const label = done ? "Sudah Ditanggapi" : "Menunggu Tanggapan";
        const btn = done
            ? ""
            : `<button type="button" class="tanggapi-sk rounded-lg bg-blue-600 hover:bg-blue-500 px-3 py-1.5 text-xs font-semibold text-white transition" data-id="${item.id}">Tanggapan</button>`;

        return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-200">${escapeHtml(sk.no_sk || sk.noSk || "-")}</td>
                <td class="px-4 py-3 text-sm text-slate-300">${escapeHtml(sk.unit_usaha || sk.unitUsaha || "-")}</td>
                <td class="px-4 py-3 text-sm">${file.url ? `<a href="${escapeAttr(file.url)}" target="_blank" class="text-blue-400 hover:underline">${escapeHtml(file.name || "Buka File")}</a>` : "-"}</td>
                <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${badge}">${label}</span></td>
                <td class="px-4 py-3 text-sm text-slate-400">${escapeHtml(item.tanggapan || "-")}</td>
                <td class="px-4 py-3 text-right">${btn}</td>
            </tr>
        `;
    }).join("");
}

function openTanggapiModal(id) {
    const modal = document.getElementById("tanggapiSkModal");
    if (!modal) return;
    document.getElementById("tanggapiSkId").value = id;
    document.getElementById("tanggapiSkText").value = "";
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeTanggapiModal() {
    const modal = document.getElementById("tanggapiSkModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function saveTanggapiSk(event) {
    event.preventDefault();
    const id = document.getElementById("tanggapiSkId").value;
    const tanggapan = document.getElementById("tanggapiSkText").value.trim();
    const btn = document.getElementById("saveTanggapiSkBtn");

    if (!tanggapan) {
        showAlert("Tanggapan wajib diisi.", "error");
        return;
    }

    btn.textContent = "Menyimpan...";
    btn.disabled = true;
    try {
        const payload = await fetchJson(`/api/sk-distribusi/${id}/tanggapi`, {
            method: "POST",
            body: JSON.stringify({ tanggapan }),
        });
        closeTanggapiModal();
        showAlert(payload.message || "Tanggapan berhasil disimpan.");
        await loadMyDistribusi();
    } catch (e) {
        showAlert(e.message || "Gagal menyimpan tanggapan.", "error");
    } finally {
        btn.textContent = "Simpan";
        btn.disabled = false;
    }
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
        .getElementById("closeResubmitSkModalBtn")
        ?.addEventListener("click", closeResubmitModal);
    document
        .getElementById("cancelResubmitSkBtn")
        ?.addEventListener("click", closeResubmitModal);
    document
        .getElementById("resubmitSkForm")
        ?.addEventListener("submit", saveResubmitSk);

    document
        .getElementById("skTableBody")
        ?.addEventListener("click", async (event) => {
            const editButton = event.target.closest(".edit-sk");
            const deleteButton = event.target.closest(".delete-sk");
            const approveManajerButton = event.target.closest(
                ".approve-manajer-sk",
            );
            const approveAfdButton = event.target.closest(".approve-afd-sk");
            const rejectManajerButton = event.target.closest(".reject-manajer-sk");
            const rejectAfdButton = event.target.closest(".reject-afd-sk");
            const resubmitButton = event.target.closest(".resubmit-sk");
            const distributeButton = event.target.closest(".distribute-sk");

            if (resubmitButton) {
                openResubmitModal(resubmitButton.dataset.id);
                return;
            }

            if (distributeButton) {
                openDistributeModal(distributeButton.dataset.id);
                return;
            }

            if (rejectManajerButton) {
                try {
                    await rejectSk(rejectManajerButton.dataset.id, "manajer");
                } catch (error) {
                    showAlert(error.message || "Gagal menolak SK.", "error");
                }
                return;
            }

            if (rejectAfdButton) {
                try {
                    await rejectSk(rejectAfdButton.dataset.id, "afd");
                } catch (error) {
                    showAlert(error.message || "Gagal menolak SK.", "error");
                }
                return;
            }

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

    document
        .getElementById("closeDistributeSkModalBtn")
        ?.addEventListener("click", closeDistributeModal);
    document
        .getElementById("cancelDistributeSkBtn")
        ?.addEventListener("click", closeDistributeModal);
    document
        .getElementById("distributeSkForm")
        ?.addEventListener("submit", async (event) => {
            try {
                await saveDistributeSk(event);
            } catch (error) {
                showAlert(error.message || "Gagal mendistribusikan SK.", "error");
            }
        });

    document
        .getElementById("closeTanggapiSkModalBtn")
        ?.addEventListener("click", closeTanggapiModal);
    document
        .getElementById("cancelTanggapiSkBtn")
        ?.addEventListener("click", closeTanggapiModal);
    document
        .getElementById("tanggapiSkForm")
        ?.addEventListener("submit", async (event) => {
            try {
                await saveTanggapiSk(event);
            } catch (error) {
                showAlert(error.message || "Gagal menyimpan tanggapan.", "error");
            }
        });

    document
        .getElementById("myDistribusiTableBody")
        ?.addEventListener("click", (event) => {
            const btn = event.target.closest(".tanggapi-sk");
            if (btn) openTanggapiModal(btn.dataset.id);
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
        await loadUserOptions();
        await loadSkItems();
        await loadMyDistribusi();
    } catch (error) {
        showAlert(error.message || "Gagal memuat SK.", "error");
    }
});
