const SESSION_KEY = "akta_session";

let plans = [];
let kasItems = [];
let currentUser = null;
let activePlanId = null;

function getSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch { return null; }
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

function canManageKas() {
    return ["admin", "manajer", "auditor"].includes(currentUser?.role);
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
    done:          { label: "Selesai",         badge: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20" },
};

const PLAN_STATUS_LABEL = {
    draft: "Draft", pending_koordinator: "Menunggu Koordinator",
    pending_manajer: "Menunggu Manajer Audit", pending_coo: "Menunggu COO",
    scheduled: "Terjadwal", running: "Audit Berjalan",
    cabang_active: "Cabang Aktif", done: "Selesai", cancelled: "Dibatalkan",
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

async function loadPlans() {
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

    const relevant = plans.filter(p => ["scheduled", "running", "cabang_active"].includes(p.status));

    if (!relevant.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Belum ada plan audit yang siap dikerjakan.</td></tr>`;
        return;
    }

    tbody.innerHTML = relevant.map((plan) => {
        const meta    = STATUS_META[plan.status] || STATUS_META.scheduled;
        const tim     = [plan.kepalaTim, ...(plan.tim || [])].filter(Boolean);
        const isActive = plan.status === "running" || plan.status === "cabang_active";
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

// ── Pemeriksaan section ───────────────────────────────────────────────────────

function openPemeriksaan(plan) {
    activePlanId = plan.id;

    // Tunjukkan section pemeriksaan
    const section = document.getElementById("pemeriksaanSection");
    if (section) {
        section.classList.remove("hidden");
        setTimeout(() => section.scrollIntoView({ behavior: "smooth", block: "start" }), 50);
    }

    // Label plan aktif
    const label = `${plan.noSpt || "-"} • ${plan.cabang || "-"}`;
    const planLabel = document.getElementById("pemeriksaanPlanLabel");
    if (planLabel) planLabel.textContent = label;

    // Pre-fill plan di dropdown kas form
    fillKasPlanSelect();

    // Reset ke tab Kas
    switchTab("kas");

    // Load data kas untuk plan ini
    loadKasData().catch((e) => showAlert(e.message, "error"));
}

function closePemeriksaan() {
    document.getElementById("pemeriksaanSection")?.classList.add("hidden");
    activePlanId = null;
}

function switchTab(tab) {
    document.querySelectorAll(".audit-tab-btn").forEach((btn) => {
        const active = btn.dataset.tab === tab;
        btn.classList.toggle("bg-blue-600", active);
        btn.classList.toggle("text-white", active);
        btn.classList.toggle("text-slate-300", !active);
        btn.classList.toggle("hover:bg-slate-800", !active);
    });
    document.querySelectorAll(".audit-tab-panel").forEach((panel) => {
        panel.classList.toggle("hidden", panel.id !== `tabPanel-${tab}`);
    });
}

// ── Pemeriksaan Kas ───────────────────────────────────────────────────────────

function formatRupiah(value) {
    return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(value || 0));
}

function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? "-";
}

function fillKasPlanSelect() {
    ["planAuditId"].forEach((elId) => {
        const sel = document.getElementById(elId);
        if (!sel) return;
        sel.innerHTML = `<option value="">Pilih Plan Audit</option>`;
        plans.forEach((p) => {
            const opt = document.createElement("option");
            opt.value = p.id;
            opt.textContent = `${p.noSpt || "-"} • ${p.cabang || "-"}`;
            if (String(p.id) === String(activePlanId)) opt.selected = true;
            sel.appendChild(opt);
        });
    });
}

async function loadKasSummary() {
    const params = new URLSearchParams();
    if (activePlanId) params.set("plan_audit_id", activePlanId);
    const payload = await fetchJson(`/api/audit-detail/kas/summary?${params}`, { headers: authHeaders() });
    const s = payload.data || {};
    setText("kasTotalPosStat", s.total_pos || 0);
    setText("kasSaldoFisikStat", formatRupiah(s.total_saldo_fisik || 0));
    setText("kasSaldoBukuStat", formatRupiah(s.total_saldo_buku || 0));
    setText("kasTotalSelisihStat", formatRupiah(s.total_selisih || 0));
    setText("kasPosSelisihStat", s.pos_selisih || 0);
}

async function loadKasItems() {
    const q         = document.getElementById("kasSearch")?.value || "";
    const hasSelisih = document.getElementById("kasSelisihFilter")?.value || "";
    const params    = new URLSearchParams();
    if (activePlanId)  params.set("plan_audit_id", activePlanId);
    if (q)             params.set("q", q);
    if (hasSelisih)    params.set("has_selisih", hasSelisih);
    const payload = await fetchJson(`/api/audit-detail/kas?${params}`, { headers: authHeaders() });
    kasItems = Array.isArray(payload) ? payload : (payload.data || []);
    renderKasItems();
}

async function loadKasData() {
    await Promise.all([loadKasSummary(), loadKasItems()]);
}

function renderKasItems() {
    const tbody = document.getElementById("kasTableBody");
    if (!tbody) return;
    if (!kasItems.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">Belum ada pemeriksaan kas untuk plan ini.</td></tr>`;
        return;
    }
    tbody.innerHTML = kasItems.map((item) => {
        const plan = item.plan_audit || item.planAudit || {};
        const selisih = Number(item.selisih || 0);
        const selisihClass = selisih === 0 ? "text-emerald-300" : "text-red-300";
        const actions = canManageKas()
            ? `<button class="view-kas rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">Detail</button>
               <button class="edit-kas ml-2 rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10" data-id="${item.id}">Edit</button>
               <button class="delete-kas ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">Hapus</button>`
            : `<button class="view-kas rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">Detail</button>`;
        return `
        <tr class="hover:bg-slate-950/50">
            <td class="px-4 py-4">
                <div class="font-semibold text-slate-100">${escapeHtml(item.nama_pos || "-")}</div>
                <div class="text-xs text-slate-500">ID: ${item.id}</div>
            </td>
            <td class="px-4 py-4 text-sm text-slate-300">
                <div>${escapeHtml(item.no_spt || plan.noSpt || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(item.cabang || plan.cabang || "-")}</div>
            </td>
            <td class="px-4 py-4 text-right text-sm font-semibold text-blue-300">${escapeHtml(formatRupiah(item.saldo_fisik || 0))}</td>
            <td class="px-4 py-4 text-right text-sm font-semibold text-amber-300">${escapeHtml(formatRupiah(item.saldo_buku || 0))}</td>
            <td class="px-4 py-4 text-right text-sm font-bold ${selisihClass}">${escapeHtml(formatRupiah(selisih))}</td>
            <td class="px-4 py-4 text-sm text-slate-300 max-w-xs line-clamp-2">${escapeHtml(item.keterangan || "-")}</td>
            <td class="px-4 py-4 text-right whitespace-nowrap">${actions}</td>
        </tr>`;
    }).join("");
}

// ── Modal Kas ─────────────────────────────────────────────────────────────────

function openKasModal(item = null) {
    document.getElementById("kasForm")?.reset();
    fillKasPlanSelect();
    const title = document.getElementById("kasModalTitle");
    if (item) {
        if (title) title.textContent = "Edit Pemeriksaan Kas";
        document.getElementById("kasId").value = item.id;
        document.getElementById("planAuditId").value = item.plan_audit_id || "";
        document.getElementById("namaPos").value = item.nama_pos || "";
        document.getElementById("saldoFisik").value = item.saldo_fisik || 0;
        document.getElementById("saldoBuku").value = item.saldo_buku || 0;
        document.getElementById("keterangan").value = item.keterangan || "";
        document.getElementById("detailJson").value = item.detail_json ? JSON.stringify(item.detail_json, null, 2) : "";
    } else {
        if (title) title.textContent = "Tambah Pemeriksaan Kas";
        document.getElementById("kasId").value = "";
        document.getElementById("saldoFisik").value = 0;
        document.getElementById("saldoBuku").value = 0;
        document.getElementById("detailJson").value = JSON.stringify({ penerimaan: [], pengeluaran: [], blanko: [] }, null, 2);
    }
    const modal = document.getElementById("kasModal");
    modal?.classList.remove("hidden");
    modal?.classList.add("flex");
}

function closeKasModal() {
    const modal = document.getElementById("kasModal");
    modal?.classList.add("hidden");
    modal?.classList.remove("flex");
}

function openKasDetail(item) {
    if (!item) return;
    setText("kasDetailTitle", item.nama_pos || "Detail Pemeriksaan Kas");
    setText("kasDetailSubtitle", `${item.no_spt || "-"} • ${item.cabang || "-"}`);
    setText("detailSaldoFisik", formatRupiah(item.saldo_fisik || 0));
    setText("detailSaldoBuku", formatRupiah(item.saldo_buku || 0));
    setText("detailSelisih", formatRupiah(item.selisih || 0));
    const info = document.getElementById("detailInfo");
    if (info) {
        const row = (l, v) => `<div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(l)}</div><div class="mt-1 break-words text-sm font-semibold text-slate-200">${escapeHtml(v || "-")}</div></div>`;
        info.innerHTML = row("No SPT", item.no_spt) + row("Cabang", item.cabang) + row("Jenis Audit", item.jenis_audit) + row("Keterangan", item.keterangan) + row("Dibuat oleh", item.created_by) + row("Diperbarui", item.updated_by);
    }
    const preview = document.getElementById("detailJsonPreview");
    if (preview) preview.textContent = item.detail_json ? JSON.stringify(item.detail_json, null, 2) : "{}";
    const modal = document.getElementById("kasDetailModal");
    modal?.classList.remove("hidden");
    modal?.classList.add("flex");
}

function closeKasDetail() {
    const modal = document.getElementById("kasDetailModal");
    modal?.classList.add("hidden");
    modal?.classList.remove("flex");
}

async function saveKas(e) {
    e.preventDefault();
    if (!canManageKas()) { showAlert("Role kamu hanya boleh melihat data.", "error"); return; }
    const id = document.getElementById("kasId").value;
    const isEdit = Boolean(id);
    const rawJson = document.getElementById("detailJson").value.trim();
    let detailJson = null;
    if (rawJson) {
        try { detailJson = JSON.parse(rawJson); } catch { throw new Error("Detail JSON tidak valid."); }
    }
    const body = {
        plan_audit_id: document.getElementById("planAuditId").value ? Number(document.getElementById("planAuditId").value) : null,
        nama_pos: document.getElementById("namaPos").value.trim(),
        saldo_fisik: Number(document.getElementById("saldoFisik").value || 0),
        saldo_buku: Number(document.getElementById("saldoBuku").value || 0),
        keterangan: document.getElementById("keterangan").value.trim() || null,
        detail_json: detailJson,
    };
    const payload = await fetchJson(isEdit ? `/api/audit-detail/kas/${id}` : "/api/audit-detail/kas", {
        method: isEdit ? "PUT" : "POST",
        headers: { ...authHeaders(), "Content-Type": "application/json" },
        body: JSON.stringify(body),
    });
    closeKasModal();
    showAlert(payload.message || "Pemeriksaan kas berhasil disimpan.");
    await loadKasData();
}

async function deleteKas(id) {
    const item = kasItems.find((r) => String(r.id) === String(id));
    if (!item || !confirm(`Hapus pemeriksaan kas "${item.nama_pos}"?`)) return;
    const payload = await fetchJson(`/api/audit-detail/kas/${id}`, {
        method: "DELETE",
        headers: authHeaders(),
    });
    showAlert(payload.message || "Pemeriksaan kas berhasil dihapus.");
    await loadKasData();
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
    } else if (plan.status === "running" || plan.status === "cabang_active") {
        const openBtn = document.createElement("button");
        openBtn.type = "button";
        openBtn.className = "rounded-xl bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-500";
        openBtn.textContent = "Buka Pemeriksaan";
        openBtn.addEventListener("click", () => { closeAuditModal(); openPemeriksaan(plan); });
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
        // Cari plan yang baru running dan buka pemeriksaan
        const updated = plans.find((p) => String(p.id) === String(plan.id));
        if (updated) openPemeriksaan(updated);
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

    let kasTimer = null;
    document.getElementById("kasSearch")?.addEventListener("input", () => {
        clearTimeout(kasTimer);
        kasTimer = setTimeout(() => loadKasItems().catch((e) => showAlert(e.message, "error")), 300);
    });
    document.getElementById("kasSelisihFilter")?.addEventListener("change", () => {
        loadKasItems().catch((e) => showAlert(e.message, "error"));
    });
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", async () => {
    // Tab switching
    document.querySelectorAll(".audit-tab-btn").forEach((btn) => {
        btn.addEventListener("click", () => switchTab(btn.dataset.tab));
    });

    // Tutup section pemeriksaan
    document.getElementById("closePemeriksaanBtn")?.addEventListener("click", closePemeriksaan);

    // Tombol aksi di baris tabel plan
    document.getElementById("auditTableBody")?.addEventListener("click", (e) => {
        const btn = e.target.closest(".open-audit-btn");
        if (!btn) return;
        const plan = plans.find((p) => String(p.id) === String(btn.dataset.id));
        if (!plan) return;
        if (plan.status === "scheduled") {
            openAuditModal(plan);
        } else {
            openPemeriksaan(plan);
        }
    });

    // Modal plan
    document.getElementById("closeAuditModal")?.addEventListener("click", closeAuditModal);

    // Modal kas
    document.getElementById("openCreateKasButton")?.addEventListener("click", () => openKasModal());
    document.getElementById("closeKasModalButton")?.addEventListener("click", closeKasModal);
    document.getElementById("cancelKasFormButton")?.addEventListener("click", closeKasModal);
    document.getElementById("closeKasDetailButton")?.addEventListener("click", closeKasDetail);

    document.getElementById("kasForm")?.addEventListener("submit", async (e) => {
        try { await saveKas(e); } catch (err) { showAlert(err.message || "Gagal menyimpan.", "error"); }
    });

    document.getElementById("kasTableBody")?.addEventListener("click", async (e) => {
        const viewBtn   = e.target.closest(".view-kas");
        const editBtn   = e.target.closest(".edit-kas");
        const deleteBtn = e.target.closest(".delete-kas");
        if (viewBtn)   { openKasDetail(kasItems.find((r) => String(r.id) === String(viewBtn.dataset.id))); return; }
        if (editBtn)   { openKasModal(kasItems.find((r) => String(r.id) === String(editBtn.dataset.id))); return; }
        if (deleteBtn) { try { await deleteKas(deleteBtn.dataset.id); } catch (err) { showAlert(err.message, "error"); } }
    });

    setupFilters();

    try {
        await loadCurrentUser();
        await loadPlans();
        if (!canManageKas()) document.getElementById("openCreateKasButton")?.classList.add("hidden");
    } catch (err) {
        showAlert(err.message || "Gagal memuat data audit.", "error");
    }
});
