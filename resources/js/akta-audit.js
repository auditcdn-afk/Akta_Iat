const SESSION_KEY = "akta_session";

let plans = [];
let currentUser = null;
let activePlanId = null;
let activePlan = null;
let currentKasId = null;

const PECAHAN = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100];

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

function formatRupiah(value) {
    return "Rp " + new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value || 0));
}

function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? "-";
}

function num(value) {
    if (typeof value === "string") value = value.replace(/\./g, "");
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
}

function formatThousands(value) {
    const n = num(String(value).replace(/\./g, ""));
    return n === 0 ? "" : new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(n);
}

function applyThousandsFormat(input) {
    const raw = input.value.replace(/\./g, "");
    const n = Number(raw);
    if (!raw || isNaN(n)) return;
    const pos = input.selectionStart;
    const prevLen = input.value.length;
    input.value = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(n);
    const diff = input.value.length - prevLen;
    try { input.setSelectionRange(pos + diff, pos + diff); } catch (_) {}
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
    activePlan = plan;
    document.getElementById("kasPlanAuditId").value = plan.id;

    const section = document.getElementById("pemeriksaanSection");
    if (section) {
        section.classList.remove("hidden");
        setTimeout(() => section.scrollIntoView({ behavior: "smooth", block: "start" }), 50);
    }

    setText("pemeriksaanPlanLabel", `${plan.noSpt || "-"} • ${plan.cabang || "-"}`);
    switchTab("kas");
    loadKasForm().catch((e) => showAlert(e.message, "error"));
}

function closePemeriksaan() {
    document.getElementById("pemeriksaanSection")?.classList.add("hidden");
    activePlanId = null;
    activePlan = null;
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

// ── Form Pemeriksaan Kas (Kas Besar, Kas Kecil, Pecahan, Blanko) ───────────────

function trxRow(item = {}) {
    const tr = document.createElement("tr");
    tr.innerHTML = `
        <td class="px-3 py-1.5"><input type="date" class="trx-tanggal w-full rounded border border-slate-300 px-2 py-1 text-sm" value="${escapeHtml(item.tanggal || "")}"></td>
        <td class="px-3 py-1.5"><input type="text" class="trx-ket w-full rounded border border-slate-300 px-2 py-1 text-sm" placeholder="Keterangan..." value="${escapeHtml(item.keterangan || "")}"></td>
        <td class="px-3 py-1.5 text-right"><input type="text" inputmode="numeric" class="trx-jumlah calc-trigger w-full rounded border border-slate-300 px-2 py-1 text-sm text-right" value="${formatThousands(item.jumlah)}"></td>
        <td class="px-1 text-center"><button type="button" class="remove-row text-red-500 hover:text-red-700">✕</button></td>`;
    return tr;
}

function blankoRow(item = {}) {
    const tr = document.createElement("tr");
    tr.innerHTML = `
        <td class="px-1 py-1.5 w-28"><input type="text" class="blk-jenis w-full rounded border border-slate-300 px-2 py-1 text-sm" placeholder="Jenis" value="${escapeHtml(item.jenis || "")}"></td>
        <td class="px-1 py-1.5"><input type="text" class="blk-nomor w-full rounded border border-slate-300 px-2 py-1 text-sm" placeholder="Nomor range blanko..." value="${escapeHtml(item.nomor || "")}"></td>
        <td class="px-1 text-center w-10"><button type="button" class="remove-row text-red-500 hover:text-red-700">✕</button></td>`;
    return tr;
}

function renderPecahan(data = []) {
    const tbody = document.getElementById("pecahanBody");
    if (!tbody) return;
    const byNominal = {};
    (data || []).forEach((p) => { byNominal[p.nominal] = p; });
    tbody.innerHTML = "";
    PECAHAN.forEach((nominal) => {
        const item = byNominal[nominal] || {};
        const tr = document.createElement("tr");
        tr.className = "border-b border-slate-100";
        tr.dataset.nominal = nominal;
        tr.innerHTML = `
            <td class="px-3 py-1.5 text-right font-semibold">${new Intl.NumberFormat("id-ID").format(nominal)}</td>
            <td class="px-3 py-1.5 text-center"><input type="number" min="0" class="pcn-besar calc-trigger w-24 rounded border border-slate-300 px-2 py-1 text-sm text-center" value="${num(item.lembar_besar)}"></td>
            <td class="px-3 py-1.5 text-right pcn-total-besar">Rp 0</td>
            <td class="px-3 py-1.5 text-center"><input type="number" min="0" class="pcn-kecil calc-trigger w-24 rounded border border-slate-300 px-2 py-1 text-sm text-center" value="${num(item.lembar_kecil)}"></td>
            <td class="px-3 py-1.5 text-right pcn-total-kecil">Rp 0</td>`;
        tbody.appendChild(tr);
    });
}

function recalcKas() {
    // Pecahan → saldo fisik
    let fisikBesar = 0, fisikKecil = 0, lembarBesar = 0, lembarKecil = 0;
    document.querySelectorAll("#pecahanBody tr").forEach((tr) => {
        const nominal = num(tr.dataset.nominal);
        const lb = num(tr.querySelector(".pcn-besar")?.value);
        const lk = num(tr.querySelector(".pcn-kecil")?.value);
        const tb = nominal * lb;
        const tk = nominal * lk;
        lembarBesar += lb; lembarKecil += lk;
        fisikBesar += tb; fisikKecil += tk;
        tr.querySelector(".pcn-total-besar").textContent = formatRupiah(tb);
        tr.querySelector(".pcn-total-kecil").textContent = formatRupiah(tk);
    });
    setText("pecahanTotalLembarBesar", lembarBesar);
    setText("pecahanTotalLembarKecil", lembarKecil);
    setText("pecahanTotalBesar", formatRupiah(fisikBesar));
    setText("pecahanTotalKecil", formatRupiah(fisikKecil));

    // Kas Besar
    const saldoAwal = num(document.getElementById("kbSaldoAwal")?.value);
    let totalPenerimaan = 0, totalPengeluaran = 0;
    document.querySelectorAll("#kbPenerimaanBody .trx-jumlah").forEach((i) => totalPenerimaan += num(i.value));
    document.querySelectorAll("#kbPengeluaranBody .trx-jumlah").forEach((i) => totalPengeluaran += num(i.value));
    const kbBuku = saldoAwal + totalPenerimaan - totalPengeluaran;
    const kbSelisih = fisikBesar - kbBuku;
    setText("kbSumSaldoAwal", formatRupiah(saldoAwal));
    setText("kbSumPenerimaan", formatRupiah(totalPenerimaan));
    setText("kbSumPengeluaran", formatRupiah(totalPengeluaran));
    setText("kbSaldoBuku", formatRupiah(kbBuku));
    setText("kbSaldoFisik", formatRupiah(fisikBesar));
    setText("kbSelisih", formatRupiah(kbSelisih));

    // Kas Kecil
    const cadangan = num(document.getElementById("kkCadangan")?.value);
    let totalBon = 0;
    document.querySelectorAll("#kkBonBody .trx-jumlah").forEach((i) => totalBon += num(i.value));
    const kkBuku = cadangan - totalBon;
    const kkSelisih = fisikKecil - kkBuku;
    setText("kkSumCadangan", formatRupiah(cadangan));
    setText("kkSumBon", formatRupiah(totalBon));
    setText("kkSaldoBuku", formatRupiah(kkBuku));
    setText("kkSaldoFisik", formatRupiah(fisikKecil));
    setText("kkSelisih", formatRupiah(kkSelisih));
}

function collectTrx(bodyId) {
    return [...document.querySelectorAll(`#${bodyId} tr`)].map((tr) => ({
        tanggal: tr.querySelector(".trx-tanggal")?.value || "",
        keterangan: tr.querySelector(".trx-ket")?.value || "",
        jumlah: num(tr.querySelector(".trx-jumlah")?.value),
    })).filter((r) => r.keterangan || r.jumlah);
}

function collectBlanko(bodyId) {
    return [...document.querySelectorAll(`#${bodyId} tr`)].map((tr) => ({
        jenis: tr.querySelector(".blk-jenis")?.value || "",
        nomor: tr.querySelector(".blk-nomor")?.value || "",
    })).filter((r) => r.jenis || r.nomor);
}

function collectPecahan() {
    return [...document.querySelectorAll("#pecahanBody tr")].map((tr) => ({
        nominal: num(tr.dataset.nominal),
        lembar_besar: num(tr.querySelector(".pcn-besar")?.value),
        lembar_kecil: num(tr.querySelector(".pcn-kecil")?.value),
    })).filter((r) => r.lembar_besar || r.lembar_kecil);
}

function resetKasForm() {
    currentKasId = null;
    document.getElementById("kasId").value = "";
    document.getElementById("kbSaldoAwalTgl").value = activePlan?.tglPlan || activePlan?.tglMulai || "";
    document.getElementById("kbSaldoAwal").value = "";
    document.getElementById("kbKeterangan").value = "";
    document.getElementById("kkCadangan").value = "";
    document.getElementById("kkKeterangan").value = "";
    document.getElementById("kbPenerimaanBody").innerHTML = "";
    document.getElementById("kbPengeluaranBody").innerHTML = "";
    document.getElementById("kkBonBody").innerHTML = "";
    document.getElementById("blankoH1Body").innerHTML = "";
    document.getElementById("blankoH2Body").innerHTML = "";
    renderPecahan([]);
}

function populateKasForm(d = {}) {
    const kb = d.kas_besar || {};
    const kk = d.kas_kecil || {};
    document.getElementById("kbSaldoAwalTgl").value = kb.saldo_awal_tgl || activePlan?.tglPlan || activePlan?.tglMulai || "";
    document.getElementById("kbSaldoAwal").value = formatThousands(kb.saldo_awal);
    document.getElementById("kbKeterangan").value = kb.keterangan || "";
    document.getElementById("kkCadangan").value = formatThousands(kk.cadangan);
    document.getElementById("kkKeterangan").value = kk.keterangan || "";

    const fill = (bodyId, rows, builder) => {
        const body = document.getElementById(bodyId);
        body.innerHTML = "";
        (rows || []).forEach((r) => body.appendChild(builder(r)));
    };
    fill("kbPenerimaanBody", kb.penerimaan, trxRow);
    fill("kbPengeluaranBody", kb.pengeluaran, trxRow);
    fill("kkBonBody", kk.bon, trxRow);
    fill("blankoH1Body", d.blanko_h1, blankoRow);
    fill("blankoH2Body", d.blanko_h2, blankoRow);
    renderPecahan(d.pecahan || []);
    recalcKas();
}

async function loadKasForm() {
    resetKasForm();
    if (!activePlanId) { recalcKas(); return; }
    const payload = await fetchJson(`/api/audit-detail/kas?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    const items = Array.isArray(payload) ? payload : (payload.data || []);
    const record = items[0];
    if (record) {
        currentKasId = record.id;
        document.getElementById("kasId").value = record.id;
        populateKasForm(record.detail_json || {});
    } else {
        recalcKas();
    }

    // Auditor/admin/manajer boleh edit; lainnya read-only
    const editable = canManageKas();
    document.querySelectorAll("#tabPanel-kas input").forEach((i) => { i.disabled = !editable; });
    document.querySelectorAll("#tabPanel-kas .add-row-btn, #tabPanel-kas .remove-row").forEach((b) => { b.style.display = editable ? "" : "none"; });
    const saveBtn = document.getElementById("saveKasFormBtn");
    if (saveBtn) saveBtn.style.display = editable ? "" : "none";
}

function buildDetailJson() {
    return {
        kas_besar: {
            saldo_awal_tgl: document.getElementById("kbSaldoAwalTgl").value || "",
            saldo_awal: num(document.getElementById("kbSaldoAwal").value),
            penerimaan: collectTrx("kbPenerimaanBody"),
            pengeluaran: collectTrx("kbPengeluaranBody"),
            keterangan: document.getElementById("kbKeterangan").value || "",
        },
        kas_kecil: {
            cadangan: num(document.getElementById("kkCadangan").value),
            bon: collectTrx("kkBonBody"),
            keterangan: document.getElementById("kkKeterangan").value || "",
        },
        pecahan: collectPecahan(),
        blanko_h1: collectBlanko("blankoH1Body"),
        blanko_h2: collectBlanko("blankoH2Body"),
    };
}

async function saveKasForm() {
    if (!canManageKas()) { showAlert("Role kamu hanya boleh melihat data.", "error"); return; }
    if (!activePlanId) { showAlert("Plan audit tidak valid.", "error"); return; }

    const detail = buildDetailJson();

    // Hitung total untuk kolom ringkasan di DB
    const fisikBesar = detail.pecahan.reduce((s, p) => s + p.nominal * p.lembar_besar, 0);
    const fisikKecil = detail.pecahan.reduce((s, p) => s + p.nominal * p.lembar_kecil, 0);
    const totalPenerimaan = detail.kas_besar.penerimaan.reduce((s, r) => s + r.jumlah, 0);
    const totalPengeluaran = detail.kas_besar.pengeluaran.reduce((s, r) => s + r.jumlah, 0);
    const totalBon = detail.kas_kecil.bon.reduce((s, r) => s + r.jumlah, 0);
    const kbBuku = detail.kas_besar.saldo_awal + totalPenerimaan - totalPengeluaran;
    const kkBuku = detail.kas_kecil.cadangan - totalBon;

    const body = {
        plan_audit_id: Number(activePlanId),
        nama_pos: "Pemeriksaan Kas",
        saldo_fisik: fisikBesar + fisikKecil,
        saldo_buku: kbBuku + kkBuku,
        keterangan: [detail.kas_besar.keterangan, detail.kas_kecil.keterangan].filter(Boolean).join(" | ") || null,
        detail_json: detail,
    };

    const isEdit = Boolean(currentKasId);
    const payload = await fetchJson(isEdit ? `/api/audit-detail/kas/${currentKasId}` : "/api/audit-detail/kas", {
        method: isEdit ? "PUT" : "POST",
        headers: { ...authHeaders(), "Content-Type": "application/json" },
        body: JSON.stringify(body),
    });

    if (payload.data?.id) {
        currentKasId = payload.data.id;
        document.getElementById("kasId").value = payload.data.id;
    }
    showAlert(payload.message || "Pemeriksaan kas berhasil disimpan.");
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
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", async () => {
    document.querySelectorAll(".audit-tab-btn").forEach((btn) => {
        btn.addEventListener("click", () => switchTab(btn.dataset.tab));
    });

    document.getElementById("closePemeriksaanBtn")?.addEventListener("click", closePemeriksaan);

    document.getElementById("auditTableBody")?.addEventListener("click", (e) => {
        const btn = e.target.closest(".open-audit-btn");
        if (!btn) return;
        const plan = plans.find((p) => String(p.id) === String(btn.dataset.id));
        if (!plan) return;
        if (plan.status === "scheduled") openAuditModal(plan);
        else openPemeriksaan(plan);
    });

    document.getElementById("closeAuditModal")?.addEventListener("click", closeAuditModal);

    // Tombol tambah baris (penerimaan, pengeluaran, bon, blanko)
    const kasPanel = document.getElementById("tabPanel-kas");
    kasPanel?.addEventListener("click", (e) => {
        const addBtn = e.target.closest(".add-row-btn");
        if (addBtn) {
            const which = addBtn.dataset.add;
            const map = {
                kbPenerimaan: ["kbPenerimaanBody", trxRow],
                kbPengeluaran: ["kbPengeluaranBody", trxRow],
                kkBon: ["kkBonBody", trxRow],
                blankoH1: ["blankoH1Body", blankoRow],
                blankoH2: ["blankoH2Body", blankoRow],
            };
            const [bodyId, builder] = map[which] || [];
            if (bodyId) document.getElementById(bodyId).appendChild(builder());
            return;
        }
        const removeBtn = e.target.closest(".remove-row");
        if (removeBtn) {
            removeBtn.closest("tr")?.remove();
            recalcKas();
        }
    });

    // Recalc & format otomatis saat input berubah
    kasPanel?.addEventListener("input", (e) => {
        const t = e.target;
        const isRupiah = t.classList.contains("calc-trigger") || t.classList.contains("trx-jumlah") || t.id === "kbSaldoAwal" || t.id === "kkCadangan";
        if (isRupiah && t.type === "text") applyThousandsFormat(t);
        if (t.classList.contains("calc-trigger") || t.id === "kbSaldoAwal" || t.id === "kkCadangan") {
            recalcKas();
        }
    });

    document.getElementById("saveKasFormBtn")?.addEventListener("click", () => {
        saveKasForm().catch((err) => showAlert(err.message || "Gagal menyimpan.", "error"));
    });

    setupFilters();

    try {
        await loadCurrentUser();
        await loadPlans();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data audit.", "error");
    }
});
