// ── Plan list (Audit) ──────────────────────────────────────────────────────────
// Ini adalah bundle "core": daftar plan + modal detail + filter, dimuat langsung
// saat halaman Audit dibuka. Semua logika tab pemeriksaan (Kas, SMH, Plafon, dst.)
// berada di ./audit-editor.js dan baru di-load (dynamic import) saat user benar-benar
// membuka pemeriksaan (klik "Mulai Audit" / "Buka Pemeriksaan", atau via deep link),
// supaya halaman ini tidak perlu mengunduh & menjalankan ribuan baris JS tab yang
// belum tentu dipakai.

const SESSION_KEY = "akta_session";

// Sama seperti di akta-plan-audit.js: dengarkan notifikasi cross-tab supaya
// baris plan yang dihapus/berubah status di menu Plan Audit langsung hilang
// dari sini, meski halaman ini dibuka di tab lain dan tidak di-reload.
const PLAN_SYNC_CHANNEL = "akta_plan_sync";
const planSyncChannel = "BroadcastChannel" in window ? new BroadcastChannel(PLAN_SYNC_CHANNEL) : null;
planSyncChannel?.addEventListener("message", () => {
    loadPlans().catch((e) => showAlert(e.message, "error"));
});

let plans = [];
export let currentUser = null;

function getSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch { return null; }
}

export function authHeaders(extra = {}) {
    const session = getSession();
    return {
        Accept: "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
        ...extra,
    };
}

export function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;").replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;").replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

export function showAlert(message, type = "success") {
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
    setTimeout(() => el.classList.add("hidden"), 4000);
}

export async function fetchJson(url, options = {}) {
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

// ── Status labels ────────────────────────────────────────────────────────────

const STATUS_META = {
    scheduled:     { label: "Terjadwal",      badge: "bg-blue-500/10 text-blue-300 border-blue-500/20" },
    running:       { label: "Sedang Berjalan", badge: "bg-amber-500/10 text-amber-300 border-amber-500/20" },
    cabang_active: { label: "Cabang Aktif",    badge: "bg-purple-500/10 text-purple-300 border-purple-500/20" },
    revisi:        { label: "Perlu Perbaikan", badge: "bg-red-500/10 text-red-300 border-red-500/20" },
    done:          { label: "Selesai",         badge: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20" },
};

const PLAN_STATUS_LABEL = {
    draft: "Draft", pending_koordinator: "Menunggu Koordinator",
    pending_manajer: "Menunggu Manajer Audit", pending_coo: "Menunggu COO",
    scheduled: "Terjadwal", running: "Audit Berjalan",
    cabang_active: "Cabang Aktif", revisi: "Perlu Perbaikan", done: "Selesai", cancelled: "Dibatalkan",
};

const ACTION_META = {
    created: { label: "Plan dibuat", dot: "bg-blue-500" },
    advance: { label: "Disetujui",   dot: "bg-emerald-500" },
    reject:  { label: "Ditolak",     dot: "bg-red-500" },
    execute: { label: "Pelaksanaan", dot: "bg-amber-500" },
};

const ROLE_LABEL = {
    admin: "Admin", manajer: "Manajer Audit",
    auditor: "Auditor", koordinator: "Koordinator", coo: "COO",
};

// ── Plan table ────────────────────────────────────────────────────────────────

export async function loadPlans() {
    const q      = document.getElementById("auditSearch")?.value || "";
    const status = document.getElementById("auditStatusFilter")?.value || "";
    const params = new URLSearchParams();
    if (q)      params.set("q", q);
    if (status) params.set("status", status);
    const payload = await fetchJson(`/api/plans?${params}`, { headers: authHeaders() });
    plans = payload.data || [];
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById("auditTableBody");
    if (!tbody) return;

    const relevant = plans.filter(p => !p.isMandiri && ["scheduled", "running", "cabang_active", "revisi"].includes(p.status));

    if (!relevant.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Belum ada plan audit yang siap dikerjakan.</td></tr>`;
        return;
    }

    tbody.innerHTML = relevant.map((plan) => {
        const meta    = STATUS_META[plan.status] || STATUS_META.scheduled;
        const tim     = [plan.kepalaTim, ...(plan.tim || [])].filter(Boolean);
        const isActive = plan.status === "running" || plan.status === "cabang_active" || plan.status === "revisi";
        const btnLabel = isActive ? "Buka Pemeriksaan" : "Mulai Audit";
        const btnColor = isActive
            ? "border-amber-500/40 text-amber-300 hover:bg-amber-500/10"
            : "border-emerald-500/40 text-emerald-300 hover:bg-emerald-500/10";

        return `
        <tr class="hover:bg-slate-950/50">
            <td class="px-4 py-4">
                <div class="font-semibold text-slate-100">${escapeHtml(plan.cabang || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(plan.noSpt || "-")}</div>
            </td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(plan.jenisAudit || "-")}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(plan.tglPlan || "-")}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(tim.join(", ") || "-")}</td>
            <td class="px-4 py-4">
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${meta.badge}">${escapeHtml(meta.label)}</span>
            </td>
            <td class="px-4 py-4 text-right">
                <button type="button" class="open-audit-btn rounded-lg border px-3 py-1.5 text-xs font-semibold ${btnColor}" data-id="${plan.id}">
                    ${btnLabel}
                </button>
            </td>
        </tr>`;
    }).join("");
}

// ── Audit modal (detail plan + mulai audit) ───────────────────────────────────

function detailRow(label, value) {
    return `<div><dt class="text-xs uppercase tracking-wide text-slate-500">${escapeHtml(label)}</dt><dd class="mt-0.5 font-medium text-slate-200">${escapeHtml(value || "-")}</dd></div>`;
}

function renderTimeline(logs) {
    const el = document.getElementById("auditTimeline");
    if (!el) return;
    if (!logs?.length) { el.innerHTML = `<li class="text-xs text-slate-500">Belum ada riwayat status.</li>`; return; }
    el.innerHTML = logs.map((log) => {
        const meta = ACTION_META[log.action] || { label: log.action, dot: "bg-slate-500" };
        const role = ROLE_LABEL[log.actorRole] || log.actorRole || "";
        const who  = [log.actor, role ? `(${role})` : ""].filter(Boolean).join(" ");
        const transisi = (log.fromStatus && log.toStatus && log.fromStatus !== log.toStatus)
            ? `<span class="text-slate-400">${escapeHtml(PLAN_STATUS_LABEL[log.fromStatus] || log.fromStatus)} → </span><span class="text-slate-200">${escapeHtml(PLAN_STATUS_LABEL[log.toStatus] || log.toStatus)}</span>`
            : `<span class="text-slate-200">${escapeHtml(PLAN_STATUS_LABEL[log.toStatus] || log.toStatus || "")}</span>`;
        return `<li class="flex gap-3"><span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${meta.dot}"></span><div class="flex-1"><div class="flex flex-wrap items-center gap-x-2"><span class="font-semibold text-slate-100">${escapeHtml(meta.label)}</span>${transisi}</div>${log.note ? `<div class="text-xs text-slate-400">${escapeHtml(log.note)}</div>` : ""}<div class="text-xs text-slate-500">${escapeHtml(who)} • ${escapeHtml(log.createdAt || "")}</div></div></li>`;
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
        startBtn.addEventListener("click", () => startAudit(plan));
        actions.appendChild(startBtn);
    } else if (plan.status === "running" || plan.status === "cabang_active" || plan.status === "revisi") {
        const openBtn = document.createElement("button");
        openBtn.type = "button";
        openBtn.className = "rounded-xl bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-500";
        openBtn.textContent = "Buka Pemeriksaan";
        openBtn.addEventListener("click", async () => {
            closeAuditModal();
            const editor = await import("./audit-editor.js");
            editor.openPemeriksaan(plan);
        });
        actions.appendChild(openBtn);
    }

    const modal = document.getElementById("auditModal");
    modal?.classList.remove("hidden");
    modal?.classList.add("flex");
}

function closeAuditModal() {
    const modal = document.getElementById("auditModal");
    modal?.classList.add("hidden");
    modal?.classList.remove("flex");
}

async function startAudit(plan) {
    if (!confirm("Konfirmasi mulai pelaksanaan audit?")) return;
    try {
        await fetchJson(`/api/plans/${plan.id}/advance`, {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ note: "Mulai pelaksanaan audit" }),
        });
        closeAuditModal();
        showAlert("Audit dimulai. Silakan isi data pemeriksaan di bawah.");
        await loadPlans();
        const updated = plans.find((p) => String(p.id) === String(plan.id));
        if (updated) {
            const editor = await import("./audit-editor.js");
            editor.openPemeriksaan(updated);
        }
    } catch (err) {
        showAlert(err.message || "Gagal memulai audit.", "error");
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────

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

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", async () => {
    setupFilters();

    document.getElementById("closeAuditModal")?.addEventListener("click", closeAuditModal);

    document.getElementById("auditTableBody")?.addEventListener("click", async (e) => {
        const btn = e.target.closest(".open-audit-btn");
        if (!btn) return;
        const plan = plans.find((p) => String(p.id) === String(btn.dataset.id));
        if (!plan) return;
        if (plan.status === "scheduled") {
            openAuditModal(plan);
            return;
        }
        const editor = await import("./audit-editor.js");
        editor.openPemeriksaan(plan);
    });

    try {
        await loadCurrentUser();
        await loadPlans();

        // Deep link dari menu Audit Mandiri: ?planId=X&modul=audit_mandiri|sertijab
        const params = new URLSearchParams(window.location.search);
        const deepLinkPlanId = params.get("planId");
        if (deepLinkPlanId) {
            const deepLinkModul = params.get("modul") || "audit_mandiri";
            try {
                const res = await fetchJson(`/api/plans/${deepLinkPlanId}`, { headers: authHeaders() });
                if (res.data) {
                    const editor = await import("./audit-editor.js");
                    editor.openPemeriksaan(res.data, deepLinkModul);
                }
            } catch (e) {
                showAlert(e.message || "Gagal membuka pemeriksaan.", "error");
            }
        }
    } catch (err) {
        showAlert(err.message || "Gagal memuat data audit.", "error");
    }
});

// Halaman dipulihkan dari bfcache (tombol back/forward) tidak memicu ulang
// DOMContentLoaded, jadi tabel bisa menampilkan plan yang sudah dihapus di tab
// lain sebelumnya. Muat ulang saat itu terjadi.
window.addEventListener("pageshow", (e) => {
    if (e.persisted) loadPlans().catch((err) => showAlert(err.message, "error"));
});
