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

// Hitung progres keseluruhan siklus SK: Selesai -> [Pembebanan Final] -> Distribusi -> Semua Tanggapan.
// Tahap Pembebanan dilewati (tidak ikut menentukan bobot) jika SK ditandai
// tidak memerlukan Pembebanan, agar progres tetap bisa mencapai 100%.
function hitungProgressSk(item) {
    if (item.status !== "selesai") {
        return { persen: 0, tahap: "Menunggu SK disetujui" };
    }

    const perluPembebanan = item.perlu_pembebanan !== false;
    const totalTahap = perluPembebanan ? 4 : 3;
    const bobot = 100 / totalTahap;

    let persen = bobot;
    const tahapan = ["SK Disetujui"];

    if (perluPembebanan) {
        const pembebanan = item.pembebanan;
        if (pembebanan?.status === "final") {
            persen += bobot;
            tahapan.push("Pembebanan Final");
        }
    } else {
        tahapan.push("Pembebanan Tidak Diperlukan");
    }

    const distribusi = item.distribusi || [];
    if (distribusi.length) {
        persen += bobot;
        tahapan.push("Terdistribusi");

        const semuaTanggap = distribusi.every((d) => d.status === "ditanggapi");
        if (semuaTanggap) {
            persen += bobot;
            tahapan.push("Semua Tanggapan Selesai");
        }
    }

    return { persen: Math.round(persen), tahap: tahapan.join(" • ") };
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
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
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

            const perluPembebanan = item.perlu_pembebanan !== false;

            const pembebananButton =
                item.status === "selesai" && perluPembebanan && ["admin", "auditor"].includes(currentUser?.role)
                    ? `
                        <button type="button" class="pembebanan-sk ml-2 rounded-lg border border-amber-500/40 px-3 py-1.5 text-xs font-semibold text-amber-300 hover:bg-amber-500/10" data-id="${item.id}">
                            Pembebanan SK
                        </button>
                    `
                    : "";

            const togglePembebananButton =
                item.status === "selesai" && ["admin", "auditor"].includes(currentUser?.role) && !item.pembebanan
                    ? `
                        <button type="button" class="toggle-pembebanan-sk ml-2 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}" data-perlu="${perluPembebanan ? "1" : "0"}">
                            ${perluPembebanan ? "Tandai Tidak Perlu Pembebanan" : "Tandai Perlu Pembebanan"}
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
                pembebananButton,
                togglePembebananButton,
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
                        ${item.memutuskan ? `<details class="mt-1"><summary class="cursor-pointer text-xs font-semibold text-violet-300">Memutuskan</summary><p class="mt-1 whitespace-pre-wrap text-xs text-slate-400">${escapeHtml(item.memutuskan)}</p></details>` : ""}
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

                    <td class="px-4 py-4" style="min-width:160px">
                        ${(() => {
                            const progress = hitungProgressSk(item);
                            const barColor = progress.persen >= 100 ? "#34d399" : progress.persen >= 50 ? "#60a5fa" : "#fbbf24";
                            return `
                                <div class="flex items-center gap-2">
                                    <div style="flex:1;height:8px;border-radius:9999px;background:#1e293b;overflow:hidden">
                                        <div style="width:${progress.persen}%;height:100%;background:${barColor}"></div>
                                    </div>
                                    <span class="text-xs font-bold text-slate-300">${progress.persen}%</span>
                                </div>
                                <p class="mt-1 text-[10px] text-slate-500" title="${escapeAttr(progress.tahap)}">${escapeHtml(progress.tahap)}</p>
                            `;
                        })()}
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

async function openDistributeModal(id) {
    const modal = document.getElementById("distributeSkModal");
    if (!modal) return;
    document.getElementById("distributeSkId").value = id;

    const item = skItems.find((row) => String(row.id) === String(id));
    const memutuskanEl = document.getElementById("distributeSkMemutuskan");
    memutuskanEl.value = item?.memutuskan || "";

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

    // Kolom memutuskan masih kosong tapi filenya ada: coba ekstrak ulang dari PDF
    // secara otomatis (mis. untuk SK plan Audit Mandiri yang ekstraksi awalnya gagal).
    if (!item?.memutuskan && (item?.file_sk || item?.fileSk)) {
        memutuskanEl.placeholder = "⏳ Mengekstrak poin Memutuskan dari file SK...";
        try {
            const result = await fetchJson(`/api/sk/${id}/re-extract-memutuskan`, {
                method: "POST",
                headers: authHeaders(),
            });
            const updated = result.data?.memutuskan || "";
            if (updated) {
                memutuskanEl.value = updated;
                const idx = skItems.findIndex((row) => String(row.id) === String(id));
                if (idx !== -1) skItems[idx].memutuskan = updated;
            }
        } catch {
            // Diam-diam gagal; auditor tetap bisa mengisi manual.
        } finally {
            memutuskanEl.placeholder = "Salin poin-poin \"Memutuskan\" dari dokumen SK di sini...";
        }
    }
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
    const memutuskan = document.getElementById("distributeSkMemutuskan").value.trim();
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
            body: JSON.stringify({ usernames: checked, memutuskan: memutuskan || null }),
        });
        closeDistributeModal();
        showAlert(payload.message || "SK berhasil didistribusikan.");
        await loadSkItems();
    } catch (e) {
        showAlert(e.message || "Gagal mendistribusikan SK.", "error");
    } finally {
        btn.textContent = "Distribusikan";
        btn.disabled = false;
    }
}

let myDistribusiItems = [];

// Pecah teks "Memutuskan" menjadi poin-poin bernomor (1. ... 2. ... dst)
function splitMemutuskanPoints(text) {
    if (!text) return [];
    const lines = String(text).split(/\n+/);
    const points = [];
    let current = null;
    for (const raw of lines) {
        const line = raw.trim();
        if (!line) continue;
        if (/^\d+\.\s*/.test(line)) {
            if (current) points.push(current);
            current = { text: line };
        } else if (current) {
            current.text += "\n" + line;
        }
    }
    if (current) points.push(current);
    return points;
}

function progressOfDistribusi(item) {
    const points = splitMemutuskanPoints((item.surat_keputusan || item.suratKeputusan || {}).memutuskan);
    if (!points.length) return null;
    const saved = item.tanggapan_poin || item.tanggapanPoin || [];
    const checkedCount = saved.filter((p) => p.checked).length;
    return { total: points.length, checked: checkedCount };
}

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
        const progress = progressOfDistribusi(item);
        const badge = done
            ? "bg-emerald-500/10 text-emerald-300 border-emerald-500/20"
            : item.status === "sebagian"
                ? "bg-blue-500/10 text-blue-300 border-blue-500/20"
                : "bg-amber-500/10 text-amber-300 border-amber-500/20";
        const label = done
            ? "Selesai"
            : progress
                ? `${progress.checked}/${progress.total} Poin Selesai`
                : "Menunggu Tanggapan";
        const btn = done
            ? ""
            : `<button type="button" class="tanggapi-sk rounded-lg bg-blue-600 hover:bg-blue-500 px-3 py-1.5 text-xs font-semibold text-white transition" data-id="${item.id}">${progress ? "Isi Tanggapan" : "Tanggapan"}</button>`;

        const fileTanggapan = item.file_tanggapan || item.fileTanggapan || null;
        const tanggapanHtml = item.tanggapan
            ? escapeHtml(item.tanggapan) + (fileTanggapan?.url ? `<br><a href="${escapeAttr(fileTanggapan.url)}" target="_blank" class="text-blue-400 hover:underline text-xs">${escapeHtml(fileTanggapan.name || "Lampiran")}</a>` : "")
            : (fileTanggapan?.url ? `<a href="${escapeAttr(fileTanggapan.url)}" target="_blank" class="text-blue-400 hover:underline text-xs">${escapeHtml(fileTanggapan.name || "Lampiran")}</a>` : "-");

        return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-200">${escapeHtml(sk.no_sk || sk.noSk || "-")}</td>
                <td class="px-4 py-3 text-sm text-slate-300">${escapeHtml(sk.unit_usaha || sk.unitUsaha || "-")}</td>
                <td class="px-4 py-3 text-sm">${file.url ? `<a href="${escapeAttr(file.url)}" target="_blank" class="text-blue-400 hover:underline">${escapeHtml(file.name || "Buka File")}</a>` : "-"}</td>
                <td class="px-4 py-3 text-sm text-slate-300 max-w-xs whitespace-pre-wrap">${escapeHtml(sk.memutuskan || "-")}</td>
                <td class="px-4 py-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${badge}">${label}</span></td>
                <td class="px-4 py-3 text-sm text-slate-400">${tanggapanHtml}</td>
                <td class="px-4 py-3 text-right">${btn}</td>
            </tr>
        `;
    }).join("");
}

function openTanggapiModal(id) {
    const modal = document.getElementById("tanggapiSkModal");
    if (!modal) return;
    const item = myDistribusiItems.find((row) => String(row.id) === String(id));
    const sk = item?.surat_keputusan || item?.suratKeputusan || {};
    const points = splitMemutuskanPoints(sk.memutuskan);
    const saved = item?.tanggapan_poin || item?.tanggapanPoin || [];

    document.getElementById("tanggapiSkId").value = id;
    document.getElementById("tanggapiSkText").value = item?.tanggapan || "";
    const fileEl = document.getElementById("tanggapiSkFile");
    if (fileEl) fileEl.value = "";

    const pointsWrap = document.getElementById("tanggapiSkPoinList");
    const overallWrap = document.getElementById("tanggapiSkOverallWrap");
    if (pointsWrap) {
        if (points.length) {
            pointsWrap.classList.remove("hidden");
            if (overallWrap) overallWrap.classList.add("hidden");
            pointsWrap.innerHTML = points.map((p, idx) => {
                const prev = saved.find((s) => s.index === idx) || {};
                return `
                    <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3" data-poin-index="${idx}">
                        <label class="flex items-start gap-2 text-sm text-slate-200 cursor-pointer">
                            <input type="checkbox" class="poin-checkbox mt-1" ${prev.checked ? "checked" : ""}>
                            <span class="whitespace-pre-wrap">${escapeHtml(p.text)}</span>
                        </label>
                        <textarea rows="2" placeholder="Catatan / penjelasan untuk poin ini (opsional)..."
                            class="poin-note mt-2 w-full resize-y rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-xs text-slate-100 outline-none focus:border-blue-500">${escapeHtml(prev.note || "")}</textarea>
                    </div>
                `;
            }).join("");
        } else {
            pointsWrap.classList.add("hidden");
            pointsWrap.innerHTML = "";
            if (overallWrap) overallWrap.classList.remove("hidden");
        }
    }

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
    const file = document.getElementById("tanggapiSkFile")?.files?.[0];
    const btn = document.getElementById("saveTanggapiSkBtn");

    const pointsWrap = document.getElementById("tanggapiSkPoinList");
    const hasPoints = pointsWrap && !pointsWrap.classList.contains("hidden") && pointsWrap.children.length;

    const formData = new FormData();

    if (hasPoints) {
        const poin = Array.from(pointsWrap.querySelectorAll("[data-poin-index]")).map((el) => ({
            index: Number(el.dataset.poinIndex),
            text: el.querySelector(".poin-checkbox")?.closest("label")?.querySelector("span")?.textContent || "",
            checked: el.querySelector(".poin-checkbox")?.checked || false,
            note: el.querySelector(".poin-note")?.value.trim() || "",
        }));
        formData.append("poin", JSON.stringify(poin));
    } else {
        const tanggapan = document.getElementById("tanggapiSkText").value.trim();
        if (!tanggapan) {
            showAlert("Tanggapan wajib diisi.", "error");
            return;
        }
        formData.append("tanggapan", tanggapan);
    }

    if (file) formData.append("file", file);

    btn.textContent = "Menyimpan...";
    btn.disabled = true;
    try {
        const session = getSession();
        const response = await fetch(`/api/sk-distribusi/${id}/tanggapi`, {
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

let pembebananKategoriList = [];
let pembebananCurrentSkId = null;
let pembebananRecordId = null;
let pembebananIsFinal = false;

function formatRupiah(value) {
    const n = Number(value) || 0;
    return "Rp " + n.toLocaleString("id-ID");
}

function renderSudahDisimpan(pembebanan) {
    const wrap = document.getElementById("pembebananSudahDisimpanWrap");
    const list = document.getElementById("pembebananSudahDisimpanList");
    const totalEl = document.getElementById("pembebananTotalDisplay");
    if (!wrap || !list) return;

    pembebananRecordId = pembebanan?.id ?? null;
    pembebananIsFinal = pembebanan?.status === "final";

    const personil = pembebanan?.personil || [];
    if (!personil.length) {
        wrap.classList.add("hidden");
        return;
    }
    wrap.classList.remove("hidden");

    list.innerHTML = personil.map((p) => `
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm">
            <div class="flex items-center justify-between">
                <span class="font-semibold text-slate-200">${escapeHtml(p.nama)}${p.jabatan ? ` <span class="text-xs text-slate-500">(${escapeHtml(p.jabatan)})</span>` : ""}</span>
                <span class="font-semibold text-emerald-300">${formatRupiah(p.subtotal)}</span>
            </div>
            <div class="mt-1 text-xs text-slate-400">${(p.rincian || []).map((r) => `${escapeHtml(r.kategori)}: ${formatRupiah(r.nilai)}`).join(" • ")}</div>
        </div>
    `).join("");

    if (totalEl) totalEl.textContent = formatRupiah(pembebanan.total_pembebanan);

    const actionWrap = document.getElementById("pembebananFinalizeWrap");
    if (actionWrap) {
        if (pembebananIsFinal) {
            actionWrap.innerHTML = `
                <div class="flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                    <span>✓ Pembebanan sudah final dan terkunci${pembebanan.finalized_by_name ? ` oleh ${escapeHtml(pembebanan.finalized_by_name)}` : ""}.</span>
                </div>
            `;
        } else {
            actionWrap.innerHTML = `
                <button type="button" id="finalisasiPembebananBtn" class="w-full rounded-xl border border-emerald-500/40 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-500/10 transition">
                    Selesai Pengisian — Kunci Pembebanan
                </button>
            `;
            document.getElementById("finalisasiPembebananBtn")?.addEventListener("click", finalisasiPembebanan);
        }
    }

    // Sembunyikan form tambah personil bila sudah final
    const entrySection = document.getElementById("personilEntrySection");
    if (entrySection) entrySection.classList.toggle("hidden", pembebananIsFinal);
}

async function finalisasiPembebanan() {
    if (!pembebananRecordId) return;
    const confirmed = confirm("Selesaikan pengisian pembebanan SK? Setelah ini tidak bisa ditambah/diubah lagi.");
    if (!confirmed) return;

    const btn = document.getElementById("finalisasiPembebananBtn");
    if (btn) {
        btn.textContent = "Memproses...";
        btn.disabled = true;
    }
    try {
        const result = await fetchJson(`/api/sk-pembebanan/${pembebananRecordId}/finalize`, {
            method: "POST",
        });
        showAlert(result.message || "Pembebanan SK berhasil diselesaikan.");
        renderSudahDisimpan(result.data);
    } catch (e) {
        showAlert(e.message || "Gagal menyelesaikan pembebanan SK.", "error");
    }
}

function recalcPersonilSubtotal() {
    const block = document.querySelector("#personilList .personil-block");
    if (!block) return;
    let subtotal = 0;
    block.querySelectorAll(".rincian-nilai").forEach((input) => {
        const checkbox = input.closest(".rincian-row")?.querySelector(".rincian-checkbox");
        if (checkbox?.checked) subtotal += Number(input.value) || 0;
    });
    const subtotalEl = block.querySelector(".personil-subtotal");
    if (subtotalEl) subtotalEl.textContent = formatRupiah(subtotal);
}

function buildRincianRowsHtml() {
    return pembebananKategoriList.map((kategori) => `
        <div class="rincian-row flex items-center gap-2">
            <label class="flex flex-1 items-center gap-2 text-xs text-slate-300">
                <input type="checkbox" class="rincian-checkbox" data-kategori="${escapeAttr(kategori)}">
                ${escapeHtml(kategori)}
            </label>
            <input type="number" min="0" step="0.01" placeholder="Nilai" class="rincian-nilai w-32 rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-slate-100 outline-none focus:border-blue-500">
        </div>
    `).join("");
}

function renderPersonilEntryBlock() {
    const list = document.getElementById("personilList");
    if (!list) return;

    const block = document.createElement("div");
    block.className = "personil-block rounded-xl border border-slate-800 bg-slate-950/60 p-3 space-y-3";
    block.innerHTML = `
        <div class="grid gap-2 sm:grid-cols-2">
            <input type="text" placeholder="Nama Personil" class="personil-nama rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
            <input type="text" placeholder="Jabatan" class="personil-jabatan rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500">
        </div>
        <div class="rincian-rows grid gap-1.5 sm:grid-cols-2">${buildRincianRowsHtml()}</div>
        <div class="flex items-center justify-between">
            <span class="text-xs text-slate-400">Subtotal: <span class="personil-subtotal font-semibold text-slate-200">Rp 0</span></span>
            <button type="button" id="simpanPersonilBtn" class="rounded-lg bg-blue-600 hover:bg-blue-500 px-4 py-2 text-sm font-semibold text-white transition">
                Simpan Personil
            </button>
        </div>
    `;
    list.innerHTML = "";
    list.appendChild(block);

    block.querySelectorAll(".rincian-nilai, .rincian-checkbox").forEach((el) => {
        el.addEventListener("input", recalcPersonilSubtotal);
        el.addEventListener("change", recalcPersonilSubtotal);
    });
    block.querySelector("#simpanPersonilBtn")?.addEventListener("click", saveOnePersonil);
}

async function openPembebananModal(id) {
    const modal = document.getElementById("pembebananSkModal");
    if (!modal) return;
    const item = skItems.find((row) => String(row.id) === String(id));
    if (!item) return;

    const unitUsaha = item.unit_usaha || item.unitUsaha || "";
    const planId = item.plan_audit_id || item.planAuditId || "";
    pembebananCurrentSkId = id;
    document.getElementById("pembebananSkId").value = id;
    document.getElementById("pembebananPlanId").value = planId;
    document.getElementById("pembebananNoSk").value = item.no_sk || item.noSk || "";
    document.getElementById("pembebananUnitUsaha").value = unitUsaha;
    document.getElementById("pembebananPimpinanSo").value = "";
    document.getElementById("pembebananPimpinanCsc").value = "";
    document.getElementById("pembebananTglAudit").value = "";
    document.getElementById("pembebananSudahDisimpanWrap")?.classList.add("hidden");
    document.getElementById("personilEntrySection")?.classList.remove("hidden");
    pembebananRecordId = null;
    pembebananIsFinal = false;

    try {
        const qs = new URLSearchParams({ unit_usaha: unitUsaha });
        if (planId) qs.set("plan_audit_id", planId);
        const res = await fetchJson(`/api/sk-pembebanan/kategori?${qs.toString()}`);
        pembebananKategoriList = res.kategori || [];
        if (res.tgl_audit_suggestion) {
            document.getElementById("pembebananTglAudit").value = res.tgl_audit_suggestion;
        }
    } catch {
        pembebananKategoriList = [];
    }

    // Muat data pembebanan yang sudah tersimpan untuk SK ini (jika ada) agar bisa dilihat/dilanjutkan
    try {
        const existing = await fetchJson(`/api/sk-pembebanan?surat_keputusan_id=${id}`);
        const record = (existing.data || [])[0];
        if (record) {
            document.getElementById("pembebananPimpinanSo").value = record.pimpinan_so || "";
            document.getElementById("pembebananPimpinanCsc").value = record.pimpinan_csc || "";
            if (record.tgl_audit) {
                document.getElementById("pembebananTglAudit").value = String(record.tgl_audit).substring(0, 10);
            }
            renderSudahDisimpan(record);
        }
    } catch {
        // belum ada pembebanan tersimpan, biarkan kosong
    }

    renderPersonilEntryBlock();

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closePembebananModal() {
    const modal = document.getElementById("pembebananSkModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function saveOnePersonil() {
    if (pembebananIsFinal) {
        showAlert("Pembebanan SK sudah final, tidak bisa ditambah personil lagi.", "error");
        return;
    }
    const btn = document.getElementById("simpanPersonilBtn");
    const block = document.querySelector("#personilList .personil-block");
    if (!block) return;

    const nama = block.querySelector(".personil-nama").value.trim();
    const jabatan = block.querySelector(".personil-jabatan").value.trim();
    const rincian = Array.from(block.querySelectorAll(".rincian-row"))
        .filter((row) => row.querySelector(".rincian-checkbox")?.checked)
        .map((row) => ({
            kategori: row.querySelector(".rincian-checkbox").dataset.kategori,
            nilai: Number(row.querySelector(".rincian-nilai").value) || 0,
        }));

    if (!nama || !rincian.length) {
        showAlert("Nama personil dan minimal satu rincian pembebanan wajib diisi.", "error");
        return;
    }

    const payload = {
        surat_keputusan_id: pembebananCurrentSkId,
        plan_audit_id: document.getElementById("pembebananPlanId").value || null,
        tgl_audit: document.getElementById("pembebananTglAudit").value || null,
        no_sk: document.getElementById("pembebananNoSk").value,
        unit_usaha: document.getElementById("pembebananUnitUsaha").value,
        pimpinan_so: document.getElementById("pembebananPimpinanSo").value.trim() || null,
        pimpinan_csc: document.getElementById("pembebananPimpinanCsc").value.trim() || null,
        personil: { nama, jabatan, rincian },
    };

    btn.textContent = "Menyimpan...";
    btn.disabled = true;
    try {
        const result = await fetchJson("/api/sk-pembebanan", {
            method: "POST",
            body: JSON.stringify(payload),
        });
        showAlert(result.message || "Personil berhasil disimpan.");
        renderSudahDisimpan(result.data);
        // Header (tgl audit, no sk, unit usaha, pimpinan) tetap; hanya blok personil yang direset
        renderPersonilEntryBlock();
    } catch (e) {
        showAlert(e.message || "Gagal menyimpan personil.", "error");
    } finally {
        const newBtn = document.getElementById("simpanPersonilBtn");
        if (newBtn) {
            newBtn.textContent = "Simpan Personil";
            newBtn.disabled = false;
        }
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
            const pembebananButton = event.target.closest(".pembebanan-sk");
            const togglePembebananButton = event.target.closest(".toggle-pembebanan-sk");

            if (resubmitButton) {
                openResubmitModal(resubmitButton.dataset.id);
                return;
            }

            if (distributeButton) {
                openDistributeModal(distributeButton.dataset.id);
                return;
            }

            if (pembebananButton) {
                await openPembebananModal(pembebananButton.dataset.id);
                return;
            }

            if (togglePembebananButton) {
                const perluSekarang = togglePembebananButton.dataset.perlu === "1";
                const perluBaru = !perluSekarang;
                const confirmMsg = perluBaru
                    ? "Tandai SK ini perlu Pembebanan kembali?"
                    : "Tandai SK ini tidak memerlukan Pembebanan? Progres akan dihitung tanpa tahap ini.";
                if (!confirm(confirmMsg)) return;
                try {
                    const result = await fetchJson(`/api/sk/${togglePembebananButton.dataset.id}/toggle-pembebanan`, {
                        method: "POST",
                        headers: { ...authHeaders(), "Content-Type": "application/json" },
                        body: JSON.stringify({ perlu_pembebanan: perluBaru }),
                    });
                    showAlert(result.message || "Berhasil diperbarui.");
                    await loadSkItems();
                } catch (error) {
                    showAlert(error.message, "error");
                }
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

    document
        .getElementById("closePembebananSkModalBtn")
        ?.addEventListener("click", closePembebananModal);
    document
        .getElementById("cancelPembebananSkBtn")
        ?.addEventListener("click", closePembebananModal);
    document
        .getElementById("pembebananSkForm")
        ?.addEventListener("submit", (event) => event.preventDefault());

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
