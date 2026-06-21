const SESSION_KEY = "akta_session";

let kasItems = [];
let plans = [];
let kasSummary = {};
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

function canManageKas() {
    return ["admin", "manajer", "auditor"].includes(currentUser?.role);
}

function normalizeListPayload(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    return payload.data || [];
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

    fillPlanSelect("planAuditId", "Pilih Plan Audit");
    fillPlanSelect("kasPlanFilter", "Semua Plan Audit");
}

async function loadKasSummary() {
    const planAuditId = document.getElementById("kasPlanFilter")?.value || "";

    const params = new URLSearchParams();

    if (planAuditId) {
        params.set("plan_audit_id", planAuditId);
    }

    const url = params.toString()
        ? `/api/audit-detail/kas/summary?${params.toString()}`
        : "/api/audit-detail/kas/summary";

    const payload = await fetchJson(url);

    kasSummary = payload.data || {};

    renderSummary();
}

async function loadKasItems() {
    const q = document.getElementById("kasSearch")?.value || "";
    const hasSelisih = document.getElementById("kasSelisihFilter")?.value || "";
    const planAuditId = document.getElementById("kasPlanFilter")?.value || "";

    const params = new URLSearchParams();

    if (q) {
        params.set("q", q);
    }

    if (hasSelisih) {
        params.set("has_selisih", hasSelisih);
    }

    if (planAuditId) {
        params.set("plan_audit_id", planAuditId);
    }

    const url = params.toString()
        ? `/api/audit-detail/kas?${params.toString()}`
        : "/api/audit-detail/kas";

    const payload = await fetchJson(url);

    kasItems = normalizeListPayload(payload);

    renderKasItems();
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

function renderSummary() {
    setText("kasTotalPosStat", kasSummary.total_pos || 0);
    setText(
        "kasSaldoFisikStat",
        formatRupiah(kasSummary.total_saldo_fisik || 0),
    );
    setText("kasSaldoBukuStat", formatRupiah(kasSummary.total_saldo_buku || 0));
    setText("kasTotalSelisihStat", formatRupiah(kasSummary.total_selisih || 0));
    setText("kasPosSelisihStat", kasSummary.pos_selisih || 0);
}

function renderKasItems() {
    const tbody = document.getElementById("kasTableBody");

    if (!tbody) {
        return;
    }

    if (!kasItems.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada pemeriksaan kas.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = kasItems
        .map((item) => {
            const plan = item.plan_audit || item.planAudit || {};
            const selisihValue = Number(item.selisih || 0);
            const selisihClass =
                selisihValue === 0 ? "text-emerald-300" : "text-red-300";

            const actions = canManageKas()
                ? `
                    <button type="button" class="view-kas rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                        Detail
                    </button>

                    <button type="button" class="edit-kas ml-2 rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10" data-id="${item.id}">
                        Edit
                    </button>

                    <button type="button" class="delete-kas ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">
                        Hapus
                    </button>
                `
                : `
                    <button type="button" class="view-kas rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                        Detail
                    </button>
                `;

            return `
                <tr class="hover:bg-slate-950/50">
                    <td class="px-4 py-4">
                        <div class="font-semibold text-slate-100">${escapeHtml(item.nama_pos || "-")}</div>
                        <div class="text-xs text-slate-500">
                            ID: ${escapeHtml(item.id)}
                        </div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>${escapeHtml(item.no_spt || plan.noSpt || plan.no_spt || "-")}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(item.cabang || plan.cabang || "-")}</div>
                    </td>

                    <td class="px-4 py-4 text-right text-sm font-semibold text-blue-300">
                        ${escapeHtml(formatRupiah(item.saldo_fisik || 0))}
                    </td>

                    <td class="px-4 py-4 text-right text-sm font-semibold text-amber-300">
                        ${escapeHtml(formatRupiah(item.saldo_buku || 0))}
                    </td>

                    <td class="px-4 py-4 text-right text-sm font-bold ${selisihClass}">
                        ${escapeHtml(formatRupiah(item.selisih || 0))}
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div class="max-w-sm line-clamp-2">${escapeHtml(item.keterangan || "-")}</div>
                    </td>

                    <td class="px-4 py-4 text-right">
                        ${actions}
                    </td>
                </tr>
            `;
        })
        .join("");
}

function openModal(item = null) {
    const modal = document.getElementById("kasModal");
    const title = document.getElementById("kasModalTitle");

    document.getElementById("kasForm").reset();

    if (item) {
        title.textContent = "Edit Pemeriksaan Kas";

        document.getElementById("kasId").value = item.id;
        document.getElementById("planAuditId").value = item.plan_audit_id || "";
        document.getElementById("namaPos").value = item.nama_pos || "";
        document.getElementById("saldoFisik").value = item.saldo_fisik || 0;
        document.getElementById("saldoBuku").value = item.saldo_buku || 0;
        document.getElementById("keterangan").value = item.keterangan || "";
        document.getElementById("detailJson").value = item.detail_json
            ? JSON.stringify(item.detail_json, null, 2)
            : "";
    } else {
        title.textContent = "Tambah Pemeriksaan Kas";

        document.getElementById("kasId").value = "";
        document.getElementById("saldoFisik").value = 0;
        document.getElementById("saldoBuku").value = 0;
        document.getElementById("detailJson").value = JSON.stringify(
            {
                penerimaan: [],
                pengeluaran: [],
                blanko: [],
            },
            null,
            2,
        );
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeModal() {
    const modal = document.getElementById("kasModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function openDetail(item) {
    if (!item) {
        showAlert("Data pemeriksaan kas tidak ditemukan.", "error");
        return;
    }

    setText("kasDetailTitle", item.nama_pos || "Detail Pemeriksaan Kas");
    setText(
        "kasDetailSubtitle",
        `${item.no_spt || "-"} • ${item.cabang || "-"} • ${item.jenis_audit || "-"}`,
    );

    setText("detailSaldoFisik", formatRupiah(item.saldo_fisik || 0));
    setText("detailSaldoBuku", formatRupiah(item.saldo_buku || 0));
    setText("detailSelisih", formatRupiah(item.selisih || 0));

    const info = document.getElementById("detailInfo");

    if (info) {
        info.innerHTML = `
            ${infoItem("Plan Audit ID", item.plan_audit_id)}
            ${infoItem("No SPT", item.no_spt)}
            ${infoItem("Cabang", item.cabang)}
            ${infoItem("Jenis Audit", item.jenis_audit)}
            ${infoItem("Created By", item.created_by)}
            ${infoItem("Updated By", item.updated_by)}
            ${infoItem("Created At", formatDateTime(item.created_at))}
            ${infoItem("Updated At", formatDateTime(item.updated_at))}
            ${infoItem("Keterangan", item.keterangan)}
        `;
    }

    const preview = document.getElementById("detailJsonPreview");

    if (preview) {
        preview.textContent = item.detail_json
            ? JSON.stringify(item.detail_json, null, 2)
            : "{}";
    }

    const modal = document.getElementById("kasDetailModal");

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeDetail() {
    const modal = document.getElementById("kasDetailModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function getFormPayload() {
    const planAuditId = document.getElementById("planAuditId").value;
    const rawDetailJson = document.getElementById("detailJson").value.trim();

    let detailJson = null;

    if (rawDetailJson) {
        try {
            detailJson = JSON.parse(rawDetailJson);
        } catch {
            throw new Error("Detail JSON tidak valid.");
        }
    }

    return {
        plan_audit_id: planAuditId ? Number(planAuditId) : null,
        nama_pos: document.getElementById("namaPos").value.trim(),
        saldo_fisik: Number(document.getElementById("saldoFisik").value || 0),
        saldo_buku: Number(document.getElementById("saldoBuku").value || 0),
        keterangan: emptyToNull(document.getElementById("keterangan").value),
        detail_json: detailJson,
    };
}

async function saveKas(event) {
    event.preventDefault();

    if (!canManageKas()) {
        showAlert("Role kamu hanya boleh melihat data.", "error");
        return;
    }

    const id = document.getElementById("kasId").value;
    const isEdit = Boolean(id);

    const payload = await fetchJson(
        isEdit ? `/api/audit-detail/kas/${id}` : "/api/audit-detail/kas",
        {
            method: isEdit ? "PUT" : "POST",
            body: JSON.stringify(getFormPayload()),
        },
    );

    closeModal();
    showAlert(payload.message || "Pemeriksaan kas berhasil disimpan.");
    await reloadKasData();
}

async function deleteKas(id) {
    const item = kasItems.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(`Hapus pemeriksaan kas "${item.nama_pos}"?`);

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/audit-detail/kas/${id}`, {
        method: "DELETE",
    });

    showAlert(payload.message || "Pemeriksaan kas berhasil dihapus.");
    await reloadKasData();
}

async function reloadKasData() {
    await loadKasSummary();
    await loadKasItems();
}

function setupFilters() {
    let timer = null;

    document.getElementById("kasSearch")?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(
            () =>
                loadKasItems().catch((error) =>
                    showAlert(error.message, "error"),
                ),
            300,
        );
    });

    document
        .getElementById("kasSelisihFilter")
        ?.addEventListener("change", () => {
            loadKasItems().catch((error) => showAlert(error.message, "error"));
        });

    document.getElementById("kasPlanFilter")?.addEventListener("change", () => {
        reloadKasData().catch((error) => showAlert(error.message, "error"));
    });
}

function setupTableActions() {
    document
        .getElementById("kasTableBody")
        ?.addEventListener("click", async (event) => {
            const viewButton = event.target.closest(".view-kas");
            const editButton = event.target.closest(".edit-kas");
            const deleteButton = event.target.closest(".delete-kas");

            if (viewButton) {
                const item = kasItems.find(
                    (row) => String(row.id) === String(viewButton.dataset.id),
                );
                openDetail(item);
                return;
            }

            if (editButton) {
                const item = kasItems.find(
                    (row) => String(row.id) === String(editButton.dataset.id),
                );
                openModal(item);
                return;
            }

            if (deleteButton) {
                try {
                    await deleteKas(deleteButton.dataset.id);
                } catch (error) {
                    showAlert(
                        error.message || "Gagal menghapus pemeriksaan kas.",
                        "error",
                    );
                }
            }
        });
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value ?? "-";
    }
}

function infoItem(label, value) {
    return `
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(label)}</div>
            <div class="mt-1 break-words text-sm font-semibold text-slate-200">${escapeHtml(value || "-")}</div>
        </div>
    `;
}

function emptyToNull(value) {
    const clean = String(value || "").trim();

    return clean === "" ? null : clean;
}

function formatRupiah(value) {
    const number = Number(value || 0);

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(number);
}

function formatDateTime(value) {
    if (!value) {
        return "-";
    }

    return String(value).replace("T", " ").slice(0, 19);
}

function showAlert(message, type = "success") {
    const alert = document.getElementById("kasAlert");

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

document.addEventListener("DOMContentLoaded", async () => {
    document
        .getElementById("openCreateKasButton")
        ?.addEventListener("click", () => openModal());

    document
        .getElementById("closeKasModalButton")
        ?.addEventListener("click", closeModal);

    document
        .getElementById("cancelKasFormButton")
        ?.addEventListener("click", closeModal);

    document
        .getElementById("closeKasDetailButton")
        ?.addEventListener("click", closeDetail);

    document
        .getElementById("kasForm")
        ?.addEventListener("submit", async (event) => {
            try {
                await saveKas(event);
            } catch (error) {
                showAlert(
                    error.message || "Gagal menyimpan pemeriksaan kas.",
                    "error",
                );
            }
        });

    setupFilters();
    setupTableActions();

    try {
        await loadCurrentUser();

        if (!canManageKas()) {
            document
                .getElementById("openCreateKasButton")
                ?.classList.add("hidden");
        }

        await loadPlans();
        await reloadKasData();
    } catch (error) {
        showAlert(error.message || "Gagal memuat pemeriksaan kas.", "error");
    }
});
