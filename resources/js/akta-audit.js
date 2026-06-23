const SESSION_KEY = "akta_session";

let plans = [];
let currentUser = null;

function getSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function authHeaders(extra = {}) {
    const session = getSession();
    return {
        Accept: "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
        ...extra,
    };
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;").replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;").replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function showAlert(message, type = "success") {
    const el = document.getElementById("auditAlert");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden", "border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200",
        "border-red-500/30", "bg-red-500/10", "text-red-200");
    if (type === "error") {
        el.classList.add("border-red-500/30", "bg-red-500/10", "text-red-200");
    } else {
        el.classList.add("border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200");
    }
    window.scrollTo({ top: 0, behavior: "smooth" });
    setTimeout(() => el.classList.add("hidden"), 6000);
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(firstError || payload.message || "Request gagal.");
    }
    return payload;
}

async function loadCurrentUser() {
    const payload = await fetchJson("/api/auth/me", { headers: authHeaders() });
    currentUser = payload.user;
}

// ── Status meta ──────────────────────────────────────────────────────────────

const STATUS_META = {
    scheduled:    { label: "Terjadwal",       badge: "bg-blue-500/10 text-blue-300 border-blue-500/20" },
    running:      { label: "Sedang Berjalan",  badge: "bg-amber-500/10 text-amber-300 border-amber-500/20" },
    cabang_active:{ label: "Cabang Aktif",     badge: "bg-purple-500/10 text-purple-300 border-purple-500/20" },
    done:         { label: "Selesai",          badge: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20" },
    cancelled:    { label: "Dibatalkan",       badge: "bg-red-500/10 text-red-300 border-red-500/20" },
};

const PLAN_STATUS_LABEL = {
    draft: "Draft",
    pending_koordinator: "Menunggu Koordinator",
    pending_manajer: "Menunggu Manajer Audit",
    pending_coo: "Menunggu COO",
    scheduled: "Terjadwal",
    running: "Audit Berjalan",
    cabang_active: "Cabang Aktif",
    done: "Selesai",
    cancelled: "Dibatalkan",
};

const ACTION_META = {
    created: { label: "Plan dibuat",  dot: "bg-blue-500" },
    advance: { label: "Disetujui",    dot: "bg-emerald-500" },
    reject:  { label: "Ditolak",      dot: "bg-red-500" },
    execute: { label: "Pelaksanaan",  dot: "bg-amber-500" },
};

const ROLE_LABEL = {
    admin: "Admin", manajer: "Manajer Audit",
    auditor: "Auditor", koordinator: "Koordinator", coo: "COO",
};

// ── Load & render table ───────────────────────────────────────────────────────

async function loadPlans() {
    const q      = document.getElementById("auditSearch")?.value || "";
    const status = document.getElementById("auditStatusFilter")?.value || "";
    const params = new URLSearchParams();
    if (q)      params.set("q", q);
    if (status) params.set("status", status);
    const url = `/api/plans?${params}`;
    const payload = await fetchJson(url, { headers: authHeaders() });
    plans = payload.data || [];
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById("auditTableBody");
    if (!tbody) return;

    const relevant = plans.filter(p =>
        ["scheduled", "running", "cabang_active", "done"].includes(p.status)
    );

    if (!relevant.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Belum ada plan audit yang siap dikerjakan.</td></tr>`;
        return;
    }

    tbody.innerHTML = relevant.map((plan) => {
        const meta    = STATUS_META[plan.status] || STATUS_META.scheduled;
        const tim     = [plan.kepalaTim, ...(plan.tim || [])].filter(Boolean);
        const timHtml = tim.length ? escapeHtml(tim.join(", ")) : "-";
        const btnLabel = plan.status === "scheduled" ? "Mulai Audit"
                       : plan.status === "running"   ? "Lanjutkan"
                       : "Lihat Detail";
        const btnColor = plan.status === "scheduled"
            ? "border-emerald-500/40 text-emerald-300 hover:bg-emerald-500/10"
            : plan.status === "running"
            ? "border-amber-500/40 text-amber-300 hover:bg-amber-500/10"
            : "border-slate-600 text-slate-300 hover:bg-slate-800";

        return `
        <tr class="hover:bg-slate-950/50">
            <td class="px-4 py-4">
                <div class="font-semibold text-slate-100">${escapeHtml(plan.cabang || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(plan.noSpt || "-")}</div>
            </td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(plan.jenisAudit || "-")}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(plan.tglPlan || "-")}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${timHtml}</td>
            <td class="px-4 py-4">
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${meta.badge}">${escapeHtml(meta.label)}</span>
            </td>
            <td class="px-4 py-4 text-right">
                <button type="button" class="open-audit-btn rounded-lg border px-3 py-1.5 text-xs font-semibold ${btnColor}"
                    data-id="${plan.id}">${btnLabel}</button>
            </td>
        </tr>`;
    }).join("");
}

// ── Modal ────────────────────────────────────────────────────────────────────

function detailRow(label, value) {
    return `
    <div>
        <dt class="text-xs uppercase tracking-wide text-slate-500">${escapeHtml(label)}</dt>
        <dd class="mt-0.5 font-medium text-slate-200">${escapeHtml(value || "-")}</dd>
    </div>`;
}

function renderTimeline(logs) {
    const el = document.getElementById("auditTimeline");
    if (!el) return;
    if (!logs?.length) {
        el.innerHTML = `<li class="text-xs text-slate-500">Belum ada riwayat status.</li>`;
        return;
    }
    el.innerHTML = logs.map((log) => {
        const meta = ACTION_META[log.action] || { label: log.action, dot: "bg-slate-500" };
        const role = ROLE_LABEL[log.actorRole] || log.actorRole || "";
        const who  = [log.actor, role ? `(${role})` : ""].filter(Boolean).join(" ");
        const transisi = (log.fromStatus && log.toStatus && log.fromStatus !== log.toStatus)
            ? `<span class="text-slate-400">${escapeHtml(PLAN_STATUS_LABEL[log.fromStatus] || log.fromStatus)} → </span><span class="text-slate-200">${escapeHtml(PLAN_STATUS_LABEL[log.toStatus] || log.toStatus)}</span>`
            : `<span class="text-slate-200">${escapeHtml(PLAN_STATUS_LABEL[log.toStatus] || log.toStatus || "")}</span>`;
        return `
        <li class="flex gap-3">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${meta.dot}"></span>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-x-2">
                    <span class="font-semibold text-slate-100">${escapeHtml(meta.label)}</span>
                    ${transisi}
                </div>
                ${log.note ? `<div class="text-xs text-slate-400">${escapeHtml(log.note)}</div>` : ""}
                <div class="text-xs text-slate-500">${escapeHtml(who)} • ${escapeHtml(log.createdAt || "")}</div>
            </div>
        </li>`;
    }).join("");
}

function openAuditModal(plan) {
    document.getElementById("auditPlanId").value = plan.id;

    document.getElementById("auditPlanDetail").innerHTML = [
        detailRow("No SPT", plan.noSpt),
        detailRow("Jenis Audit", plan.jenisAudit),
        detailRow("Cabang", plan.cabang),
        detailRow("Wilayah", plan.cabangArea),
        detailRow("Tanggal Plan", plan.tglPlan),
        detailRow("Kepala Tim", plan.kepalaTim),
        detailRow("Tim Audit", (plan.tim || []).join(", ")),
        detailRow("Status", PLAN_STATUS_LABEL[plan.status] || plan.status),
    ].join("");

    renderTimeline(plan.logs || []);

    // Tombol aksi sesuai status
    const actions = document.getElementById("auditActions");
    actions.innerHTML = "";

    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800";
    cancelBtn.textContent = "Tutup";
    cancelBtn.addEventListener("click", closeAuditModal);
    actions.appendChild(cancelBtn);

    if (plan.status === "scheduled") {
        const startBtn = document.createElement("button");
        startBtn.type = "button";
        startBtn.className = "rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500";
        startBtn.textContent = "Mulai Audit";
        startBtn.addEventListener("click", () => advancePlan(plan.id, "Mulai pelaksanaan audit"));
        actions.appendChild(startBtn);
    } else if (plan.status === "running") {
        const doneBtn = document.createElement("button");
        doneBtn.type = "button";
        doneBtn.className = "rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500";
        doneBtn.textContent = "Selesaikan Audit";
        doneBtn.addEventListener("click", () => advancePlan(plan.id, "Audit selesai, menunggu konfirmasi cabang"));
        actions.appendChild(doneBtn);
    }

    const modal = document.getElementById("auditModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeAuditModal() {
    const modal = document.getElementById("auditModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function advancePlan(planId, note = "") {
    if (!confirm(`Konfirmasi: ${note}?`)) return;
    try {
        const payload = await fetchJson(`/api/plans/${planId}/advance`, {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ note }),
        });
        closeAuditModal();
        showAlert(payload.message || "Status plan berhasil diperbarui.");
        await loadPlans();
    } catch (err) {
        showAlert(err.message || "Gagal memperbarui status.", "error");
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────

function setupFilters() {
    let timer = null;
    document.getElementById("auditSearch")?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadPlans().catch((e) => showAlert(e.message, "error")), 300);
    });
    document.getElementById("auditStatusFilter")?.addEventListener("change", () => {
        loadPlans().catch((e) => showAlert(e.message, "error"));
    });
}

// ── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", async () => {
    document.getElementById("closeAuditModal")?.addEventListener("click", closeAuditModal);

    document.getElementById("auditTableBody")?.addEventListener("click", (e) => {
        const btn = e.target.closest(".open-audit-btn");
        if (!btn) return;
        const plan = plans.find((p) => String(p.id) === String(btn.dataset.id));
        if (plan) openAuditModal(plan);
    });

    setupFilters();

    try {
        await loadCurrentUser();
        await loadPlans();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data audit.", "error");
    }
});
