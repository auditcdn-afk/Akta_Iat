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
    if (tab === "bank") {
        document.getElementById("bankPlanAuditId").value = activePlanId || "";
        loadBankForm().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "smh") {
        loadSmhForm().catch((e) => showAlert(e.message, "error"));
    }
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

// ── Form Pemeriksaan SMH ──────────────────────────────────────────────────────

let smhPmxId = null;
let smhItems = [];

function smhStatusBadge(status) {
    if (status === 'ada')       return '<span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-xs font-bold text-emerald-600">Ada ✓</span>';
    if (status === 'tidak_ada') return '<span class="inline-flex rounded-full border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-xs font-bold text-red-600">Tidak Ada</span>';
    return '<span class="inline-flex rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500">Belum</span>';
}

function smhStatusRowClass(status) {
    if (status === 'ada')       return 'bg-emerald-50';
    if (status === 'tidak_ada') return 'bg-red-50';
    return '';
}

function renderSmhTable(filter = '') {
    const tbody = document.getElementById('smhTableBody');
    if (!tbody) return;
    const filtered = filter
        ? smhItems.filter(it => filter === 'belum' ? !it.statusFisik : it.statusFisik === filter)
        : smhItems;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="11" class="px-4 py-6 text-center text-sm text-slate-400">Tidak ada unit.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map((it, idx) => `
        <tr class="border-b border-slate-100 hover:bg-slate-50 ${smhStatusRowClass(it.statusFisik)}" data-item-id="${it.id}">
            <td class="px-3 py-2 text-center text-xs text-slate-500">${idx + 1}</td>
            <td class="px-3 py-2 font-mono text-xs">${escapeHtml(it.noMesin || '-')}</td>
            <td class="px-3 py-2 font-mono text-xs">${escapeHtml(it.noRangka || '-')}</td>
            <td class="px-3 py-2 text-xs">${escapeHtml(it.noSpb || '-')}</td>
            <td class="px-3 py-2 text-xs">${escapeHtml(it.tglSpb || '-')}</td>
            <td class="px-3 py-2 text-center text-xs">${it.umur ?? '-'} hr</td>
            <td class="px-3 py-2 text-xs font-semibold">${escapeHtml(it.kodeModel || '-')}</td>
            <td class="px-3 py-2 text-xs">${escapeHtml(it.warna || '-')}</td>
            <td class="px-3 py-2 text-xs">${escapeHtml(it.gudang || '-')}</td>
            <td class="px-3 py-2 text-center">
                <select class="smh-status-select rounded border border-slate-300 px-1 py-0.5 text-xs" data-id="${it.id}">
                    <option value="">— Pilih —</option>
                    <option value="ada" ${it.statusFisik === 'ada' ? 'selected' : ''}>Ada ✓</option>
                    <option value="tidak_ada" ${it.statusFisik === 'tidak_ada' ? 'selected' : ''}>Tidak Ada</option>
                </select>
            </td>
            <td class="px-3 py-2">
                <input type="text" class="smh-ket-input w-full rounded border border-slate-300 px-1 py-0.5 text-xs" placeholder="ket..." data-id="${it.id}" value="${escapeHtml(it.keteranganFisik || '')}">
            </td>
        </tr>`).join('');
}

function showSmhSuggestions(q) {
    const ul = document.getElementById('smhSuggestions');
    if (!ul) return;
    if (!q || q.length < 2) { ul.classList.add('hidden'); ul.innerHTML = ''; return; }

    const lower = q.toLowerCase();
    const matches = smhItems.filter(it =>
        (it.noMesin || '').toLowerCase().includes(lower) ||
        (it.noRangka || '').toLowerCase().includes(lower)
    ).slice(0, 20);

    if (!matches.length) { ul.classList.add('hidden'); ul.innerHTML = ''; return; }

    ul.innerHTML = matches.map(it => {
        const statusDot = it.statusFisik === 'ada'
            ? '<span class="text-emerald-500 font-bold">✓</span>'
            : it.statusFisik === 'tidak_ada'
            ? '<span class="text-red-500 font-bold">✗</span>'
            : '<span class="text-slate-300">○</span>';
        const bg = it.statusFisik === 'ada' ? 'hover:bg-emerald-50' : it.statusFisik === 'tidak_ada' ? 'hover:bg-red-50' : 'hover:bg-slate-50';
        return `<li class="smh-suggestion cursor-pointer px-3 py-2 text-xs border-b border-slate-100 ${bg} flex items-center gap-2"
                    data-mesin="${escapeHtml(it.noMesin || '')}" data-rangka="${escapeHtml(it.noRangka || '')}">
                    ${statusDot}
                    <div>
                        <div class="font-semibold text-slate-800">${escapeHtml(it.noMesin || '-')}</div>
                        <div class="text-slate-500">${escapeHtml(it.noRangka || '-')} &nbsp;|&nbsp; ${escapeHtml(it.kodeModel || '')} ${escapeHtml(it.warna || '')} &nbsp;|&nbsp; ${escapeHtml(it.gudang || '')}</div>
                    </div>
                </li>`;
    }).join('');
    ul.classList.remove('hidden');
}

function hideSmhSuggestions() {
    const ul = document.getElementById('smhSuggestions');
    if (ul) { ul.classList.add('hidden'); }
}

function populateSmhDropdown() { /* no-op — diganti autocomplete */ }

function updateSmhSummary(data) {
    document.getElementById('smhTotalUnit').textContent   = data.totalUnit ?? smhItems.length;
    document.getElementById('smhTotalAda').textContent    = data.totalDitemukan ?? smhItems.filter(i => i.statusFisik === 'ada').length;
    document.getElementById('smhTotalTidakAda').textContent = data.totalTidakDitemukan ?? smhItems.filter(i => i.statusFisik === 'tidak_ada').length;
    document.getElementById('smhTotalBelum').textContent  = data.totalBelumDiperiksa ?? smhItems.filter(i => !i.statusFisik).length;
    document.getElementById('smhSummary').classList.remove('hidden');
    document.getElementById('smhSummary').classList.add('grid');
    document.getElementById('smhTableWrap').classList.remove('hidden');
    document.getElementById('smhScanBox').classList.remove('hidden');
    document.getElementById('smhSyncBtn').classList.remove('hidden');
}

async function loadSmhForm() {
    smhPmxId = null;
    smhItems = [];
    ['smhSummary', 'smhScanBox', 'smhTableWrap', 'smhSyncResult'].forEach(id => document.getElementById(id)?.classList.add('hidden'));
    document.getElementById('smhTglOnhand').textContent = '';
    if (!activePlanId) return;

    const payload = await fetchJson(`/api/audit-detail/smh?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    const items = payload.data || [];
    if (!items.length) return;

    const rec = items[0];
    smhPmxId = rec.id;
    smhItems = rec.items || [];
    document.getElementById('smhTglOnhand').textContent = rec.tglOnhand ? `Tgl Onhand: ${rec.tglOnhand}` : '';
    updateSmhSummary(rec);
    renderSmhTable();
    populateSmhDropdown();
}

async function smhCheckItem(itemId, statusFisik, keteranganFisik = '') {
    const payload = await fetchJson(`/api/audit-detail/smh/items/${itemId}`, {
        method: 'PUT',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ status_fisik: statusFisik, keterangan_fisik: keteranganFisik }),
    });
    // Update local data
    const idx = smhItems.findIndex(i => i.id === itemId);
    if (idx >= 0) {
        smhItems[idx].statusFisik = statusFisik;
        smhItems[idx].keteranganFisik = keteranganFisik;
    }
    updateSmhSummary({
        totalUnit: smhItems.length,
        totalDitemukan: smhItems.filter(i => i.statusFisik === 'ada').length,
        totalTidakDitemukan: smhItems.filter(i => i.statusFisik === 'tidak_ada').length,
        totalBelumDiperiksa: smhItems.filter(i => !i.statusFisik).length,
    });
    return payload;
}

async function smhScanUnit(q) {
    const res = document.getElementById('smhScanResult');
    if (!q || q.length < 3) { res.classList.add('hidden'); return; }
    const payload = await fetchJson(`/api/audit-detail/smh/scan?q=${encodeURIComponent(q)}&plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    const it = payload.data;
    if (!it) {
        res.className = 'rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700';
        res.innerHTML = `<strong>Tidak ditemukan</strong> — unit dengan No. Mesin / Rangka "<em>${escapeHtml(q)}</em>" tidak ada dalam daftar onhand.`;
        res.classList.remove('hidden');
        return;
    }
    res.className = 'rounded-xl border border-emerald-400 bg-emerald-50 p-4 text-sm space-y-3';
    res.innerHTML = `
        <div class="font-bold text-emerald-700">Unit ditemukan dalam daftar onhand</div>
        <div class="grid gap-2 sm:grid-cols-3 text-slate-700">
            <div><span class="text-xs text-slate-500 block">No. Mesin</span><strong>${escapeHtml(it.noMesin || '-')}</strong></div>
            <div><span class="text-xs text-slate-500 block">No. Rangka</span><strong>${escapeHtml(it.noRangka || '-')}</strong></div>
            <div><span class="text-xs text-slate-500 block">Model</span>${escapeHtml(it.kodeModel || '-')} / ${escapeHtml(it.warna || '-')}</div>
            <div><span class="text-xs text-slate-500 block">No. SPB</span>${escapeHtml(it.noSpb || '-')}</div>
            <div><span class="text-xs text-slate-500 block">Gudang</span>${escapeHtml(it.gudang || '-')}</div>
            <div><span class="text-xs text-slate-500 block">Umur</span>${it.umur ?? '-'} hari</div>
        </div>
        <div class="flex gap-3 items-center pt-1">
            <span class="text-xs text-slate-600 font-semibold">Status fisik:</span>
            <button type="button" data-scan-check="${it.id}" data-val="ada"
                class="rounded-lg border border-emerald-400 bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-200">
                ✓ Ada / Ditemukan
            </button>
            <button type="button" data-scan-check="${it.id}" data-val="tidak_ada"
                class="rounded-lg border border-red-400 bg-red-100 px-3 py-1 text-xs font-bold text-red-700 hover:bg-red-200">
                ✗ Tidak Ditemukan
            </button>
        </div>`;
    res.classList.remove('hidden');

    // Scroll ke baris di tabel
    const row = document.querySelector(`#smhTableBody tr[data-item-id="${it.id}"]`);
    if (row) { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); row.classList.add('ring-2', 'ring-blue-400'); setTimeout(() => row.classList.remove('ring-2', 'ring-blue-400'), 2000); }
}

// ── Form Pemeriksaan Bank ──────────────────────────────────────────────────────

let bankLoadedIds = [];

function cekRow(item = {}) {
    const tr = document.createElement("tr");
    tr.innerHTML = `
        <td class="px-1 py-1.5"><input type="text" class="cek-nama w-full rounded border border-slate-300 px-2 py-1 text-sm" placeholder="Nama cek / no giro / range..." value="${escapeHtml(item.nomor || "")}"></td>
        <td class="px-1 text-center w-10"><button type="button" class="remove-row text-red-500 hover:text-red-700">✕</button></td>`;
    return tr;
}

function bankCardEl(item = {}) {
    const d = item.detail_json || {};
    const card = document.createElement("div");
    card.className = "bank-card overflow-hidden rounded-2xl border border-slate-800 bg-white text-slate-800 shadow";
    if (item.id) card.dataset.id = item.id;
    card.innerHTML = `
        <div class="flex items-center justify-between bg-[#1e3a5f] px-5 py-3 text-white">
            <span class="bank-title text-sm font-bold uppercase tracking-wide">🏦 Bank</span>
            <button type="button" class="bank-remove text-white/70 hover:text-white text-lg leading-none">✕</button>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Bank</label>
                <input type="text" class="bank-nama w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" placeholder="Nama Bank..." value="${escapeHtml(item.nama_bank || "")}">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Awal (Tanggal H-1 Pemeriksaan)</label>
                    <input type="date" class="bank-saldo-awal-tgl w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" value="${escapeHtml(d.saldo_awal_tgl || activePlan?.tglPlan || "")}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Awal (Rp)</label>
                    <input type="text" inputmode="numeric" class="bank-saldo-awal bank-calc w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" value="${formatThousands(d.saldo_awal)}">
                </div>
            </div>

            <div>
                <div class="mb-2 text-sm font-bold text-emerald-600">▲ Penerimaan</div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-3 py-2 text-left w-40">Tanggal</th><th class="px-3 py-2 text-left">Keterangan</th><th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th><th class="w-10"></th></tr>
                    </thead>
                    <tbody class="bank-penerimaan-body"></tbody>
                </table>
                <button type="button" data-add="bankPenerimaan" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">+ Tambah Penerimaan</button>
            </div>

            <div>
                <div class="mb-2 text-sm font-bold text-red-500">▼ Pengeluaran</div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-3 py-2 text-left w-40">Tanggal</th><th class="px-3 py-2 text-left">Keterangan</th><th class="px-3 py-2 text-right w-40">Jumlah (Rp)</th><th class="w-10"></th></tr>
                    </thead>
                    <tbody class="bank-pengeluaran-body"></tbody>
                </table>
                <button type="button" data-add="bankPengeluaran" class="add-row-btn mt-2 rounded-lg border border-dashed border-blue-400 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">+ Tambah Pengeluaran</button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <div class="flex justify-between py-1 text-slate-600"><span>Saldo Awal</span><span class="bank-sum-saldo-awal font-semibold text-blue-600">Rp 0</span></div>
                <div class="flex justify-between py-1 text-slate-600"><span>Total Penerimaan</span><span class="bank-sum-penerimaan font-semibold text-emerald-600">Rp 0</span></div>
                <div class="flex justify-between py-1 text-slate-600"><span>Total Pengeluaran</span><span class="bank-sum-pengeluaran font-semibold text-red-500">Rp 0</span></div>
                <div class="mt-1 flex justify-between border-t border-slate-300 py-2 font-bold text-slate-800"><span>Saldo Buku (Sistem)</span><span class="bank-saldo-buku">Rp 0</span></div>
                <div class="flex justify-between py-1 text-slate-600"><span>Saldo Rekening Koran</span><span class="bank-sum-rk font-semibold text-blue-600">Rp 0</span></div>
                <div class="flex justify-between py-1 font-bold"><span class="text-red-500">Selisih</span><span class="bank-selisih text-red-500">Rp 0</span></div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan Selisih</label>
                <input type="text" class="bank-keterangan w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" placeholder="contoh: Selisih biaya administrasi" value="${escapeHtml(d.keterangan_selisih || item.keterangan || "")}">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Rekening Koran (Tanggal)</label>
                    <input type="date" class="bank-rk-tgl w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" value="${escapeHtml(d.saldo_rk_tgl || "")}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Saldo Rekening Koran (Rp)</label>
                    <input type="text" inputmode="numeric" class="bank-rk bank-calc w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500" value="${formatThousands(item.saldo_bank ?? d.saldo_rk)}">
                </div>
            </div>
        </div>`;

    const fill = (sel, rows) => {
        const body = card.querySelector(sel);
        (rows || []).forEach((r) => body.appendChild(trxRow(r)));
    };
    fill(".bank-penerimaan-body", d.penerimaan);
    fill(".bank-pengeluaran-body", d.pengeluaran);
    return card;
}

function renumberBanks() {
    document.querySelectorAll("#bankList .bank-card").forEach((card, i) => {
        const t = card.querySelector(".bank-title");
        if (t) t.textContent = `🏦 Bank ${i + 1}`;
    });
}

function addBankCard(item = {}) {
    document.getElementById("bankList").appendChild(bankCardEl(item));
    renumberBanks();
}

function recalcBank() {
    document.querySelectorAll("#bankList .bank-card").forEach((card) => {
        const saldoAwal = num(card.querySelector(".bank-saldo-awal")?.value);
        let pen = 0, peng = 0;
        card.querySelectorAll(".bank-penerimaan-body .trx-jumlah").forEach((i) => pen += num(i.value));
        card.querySelectorAll(".bank-pengeluaran-body .trx-jumlah").forEach((i) => peng += num(i.value));
        const buku = saldoAwal + pen - peng;
        const rk = num(card.querySelector(".bank-rk")?.value);
        const selisih = rk - buku;
        card.querySelector(".bank-sum-saldo-awal").textContent = formatRupiah(saldoAwal);
        card.querySelector(".bank-sum-penerimaan").textContent = formatRupiah(pen);
        card.querySelector(".bank-sum-pengeluaran").textContent = formatRupiah(peng);
        card.querySelector(".bank-saldo-buku").textContent = formatRupiah(buku);
        card.querySelector(".bank-sum-rk").textContent = formatRupiah(rk);
        const selEl = card.querySelector(".bank-selisih");
        selEl.textContent = formatRupiah(selisih) + (selisih === 0 ? " ✓" : "");
        selEl.classList.toggle("text-emerald-600", selisih === 0);
        selEl.classList.toggle("text-red-500", selisih !== 0);
    });
}

function collectCardTrx(card, sel) {
    return [...card.querySelectorAll(`${sel} tr`)].map((tr) => ({
        tanggal: tr.querySelector(".trx-tanggal")?.value || "",
        keterangan: tr.querySelector(".trx-ket")?.value || "",
        jumlah: num(tr.querySelector(".trx-jumlah")?.value),
    })).filter((r) => r.keterangan || r.jumlah);
}

function collectRegisterCek() {
    return [...document.querySelectorAll("#registerCekBody tr")].map((tr) => ({
        nomor: tr.querySelector(".cek-nama")?.value || "",
    })).filter((r) => r.nomor);
}

function buildBankPayload(card, registerCek) {
    const saldoAwal = num(card.querySelector(".bank-saldo-awal")?.value);
    const penerimaan = collectCardTrx(card, ".bank-penerimaan-body");
    const pengeluaran = collectCardTrx(card, ".bank-pengeluaran-body");
    const totalPen = penerimaan.reduce((s, r) => s + r.jumlah, 0);
    const totalPeng = pengeluaran.reduce((s, r) => s + r.jumlah, 0);
    const saldoBuku = saldoAwal + totalPen - totalPeng;
    const saldoBank = num(card.querySelector(".bank-rk")?.value);
    const keterangan = card.querySelector(".bank-keterangan")?.value || "";
    return {
        plan_audit_id: Number(activePlanId),
        nama_bank: card.querySelector(".bank-nama")?.value || "-",
        saldo_buku: saldoBuku,
        saldo_bank: saldoBank,
        keterangan: keterangan || null,
        detail_json: {
            saldo_awal_tgl: card.querySelector(".bank-saldo-awal-tgl")?.value || "",
            saldo_awal: saldoAwal,
            penerimaan,
            pengeluaran,
            saldo_rk_tgl: card.querySelector(".bank-rk-tgl")?.value || "",
            saldo_rk: saldoBank,
            keterangan_selisih: keterangan,
            register_cek: registerCek,
        },
    };
}

async function loadBankForm() {
    const list = document.getElementById("bankList");
    list.innerHTML = "";
    bankLoadedIds = [];
    document.getElementById("registerCekBody").innerHTML = "";
    if (!activePlanId) { addBankCard(); return; }

    const payload = await fetchJson(`/api/audit-detail/bank?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    const items = Array.isArray(payload) ? payload : (payload.data || []);
    if (items.length) {
        items.forEach((it) => { addBankCard(it); if (it.id) bankLoadedIds.push(it.id); });
        const withCek = items.find((it) => (it.detail_json?.register_cek || []).length);
        (withCek?.detail_json?.register_cek || []).forEach((r) => document.getElementById("registerCekBody").appendChild(cekRow(r)));
    } else {
        addBankCard();
    }
    recalcBank();

    const editable = canManageKas();
    document.querySelectorAll("#tabPanel-bank input").forEach((i) => { i.disabled = !editable; });
    document.querySelectorAll("#tabPanel-bank .add-row-btn, #tabPanel-bank .remove-row, #tabPanel-bank .bank-remove, #addBankBtn").forEach((b) => { b.style.display = editable ? "" : "none"; });
    const saveBtn = document.getElementById("saveBankFormBtn");
    if (saveBtn) saveBtn.style.display = editable ? "" : "none";
}

async function saveBankForm() {
    if (!canManageKas()) { showAlert("Role kamu hanya boleh melihat data.", "error"); return; }
    if (!activePlanId) { showAlert("Plan audit tidak valid.", "error"); return; }

    const registerCek = collectRegisterCek();
    const cards = [...document.querySelectorAll("#bankList .bank-card")];
    const keptIds = [];

    for (const card of cards) {
        const body = buildBankPayload(card, registerCek);
        const id = card.dataset.id;
        if (id) {
            keptIds.push(Number(id));
            await fetchJson(`/api/audit-detail/bank/${id}`, {
                method: "PUT",
                headers: { ...authHeaders(), "Content-Type": "application/json" },
                body: JSON.stringify(body),
            });
        } else {
            const res = await fetchJson("/api/audit-detail/bank", {
                method: "POST",
                headers: { ...authHeaders(), "Content-Type": "application/json" },
                body: JSON.stringify(body),
            });
            if (res.data?.id) { card.dataset.id = res.data.id; keptIds.push(res.data.id); }
        }
    }

    // Hapus record bank yang dibuang dari form
    const removed = bankLoadedIds.filter((id) => !keptIds.includes(id));
    for (const id of removed) {
        await fetchJson(`/api/audit-detail/bank/${id}`, { method: "DELETE", headers: authHeaders() });
    }
    bankLoadedIds = keptIds;

    showAlert("Pemeriksaan bank berhasil disimpan.");
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

    // ── SMH panel ──
    document.getElementById('smhUploadBtn')?.addEventListener('click', async () => {
        const fileInput = document.getElementById('smhFileInput');
        if (!fileInput?.files[0]) { showAlert('Pilih file onhand terlebih dahulu.', 'error'); return; }
        if (!activePlanId) { showAlert('Plan audit tidak aktif.', 'error'); return; }
        const form = new FormData();
        form.append('file', fileInput.files[0]);
        form.append('plan_audit_id', activePlanId);
        try {
            const res = await fetchJson('/api/audit-detail/smh/upload', {
                method: 'POST',
                headers: authHeaders(),
                body: form,
            });
            showAlert(res.message || 'Upload berhasil.');
            smhPmxId = res.data.id;
            smhItems = res.data.items || [];
            document.getElementById('smhTglOnhand').textContent = res.data.tglOnhand ? `Tgl Onhand: ${res.data.tglOnhand}` : '';
            updateSmhSummary(res.data);
            renderSmhTable();
            populateSmhDropdown();
        } catch (e) { showAlert(e.message, 'error'); }
    });

    // Autocomplete suggestions
    document.getElementById('smhScanInput')?.addEventListener('input', (e) => {
        showSmhSuggestions(e.target.value.trim());
    });

    document.getElementById('smhScanInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideSmhSuggestions();
    });

    document.getElementById('smhSuggestions')?.addEventListener('mousedown', (e) => {
        const li = e.target.closest('.smh-suggestion');
        if (!li) return;
        e.preventDefault();
        const q = li.dataset.mesin || li.dataset.rangka;
        document.getElementById('smhScanInput').value = q;
        hideSmhSuggestions();
        smhScanUnit(q).catch((err) => showAlert(err.message, 'error'));
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#smhScanInput') && !e.target.closest('#smhSuggestions')) hideSmhSuggestions();
    });

    document.getElementById('smhScanBtn')?.addEventListener('click', () => {
        const q = document.getElementById('smhScanInput').value.trim();
        smhScanUnit(q).catch((e) => showAlert(e.message, 'error'));
    });

    document.getElementById('smhScanInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const q = e.target.value.trim();
            smhScanUnit(q).catch((err) => showAlert(err.message, 'error'));
        }
    });

    document.getElementById('smhScanResult')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-scan-check]');
        if (!btn) return;
        const itemId = Number(btn.dataset.scanCheck);
        const val    = btn.dataset.val;
        try {
            await smhCheckItem(itemId, val);
            renderSmhTable(document.getElementById('smhFilterStatus')?.value || '');
            const q = document.getElementById('smhScanInput').value.trim();
            await smhScanUnit(q);
        } catch (err) { showAlert(err.message, 'error'); }
    });

    document.getElementById('smhFilterStatus')?.addEventListener('change', (e) => {
        renderSmhTable(e.target.value);
    });

    document.getElementById('smhTableBody')?.addEventListener('change', async (e) => {
        const sel = e.target.closest('.smh-status-select');
        if (!sel || !sel.value) return;
        const itemId = Number(sel.dataset.id);
        const ket    = document.querySelector(`.smh-ket-input[data-id="${itemId}"]`)?.value || '';
        try {
            await smhCheckItem(itemId, sel.value, ket);
            const row = sel.closest('tr');
            if (row) { row.className = `border-b border-slate-100 hover:bg-slate-50 ${smhStatusRowClass(sel.value)}`; }
            populateSmhDropdown();
        } catch (err) { showAlert(err.message, 'error'); }
    });

    document.getElementById('smhTableBody')?.addEventListener('blur', async (e) => {
        const inp = e.target.closest('.smh-ket-input');
        if (!inp) return;
        const itemId = Number(inp.dataset.id);
        const item   = smhItems.find(i => i.id === itemId);
        if (!item?.statusFisik) return;
        try { await smhCheckItem(itemId, item.statusFisik, inp.value); } catch (_) {}
    }, true);

    document.getElementById('smhSyncBtn')?.addEventListener('click', async () => {
        if (!smhPmxId) return;
        try {
            const res = await fetchJson(`/api/audit-detail/smh/${smhPmxId}/sync-perlengkapan`, { headers: authHeaders() });
            const body = document.getElementById('smhSyncBody');
            const list = res.data || [];
            body.innerHTML = list.length
                ? list.map(r => `<div class="flex gap-3 items-center py-1 border-b border-slate-100">
                    <span class="text-xs ${r.matched ? 'text-emerald-600 font-bold' : 'text-red-500'}">${r.matched ? '✓' : '✗'}</span>
                    <span class="font-mono text-xs">${escapeHtml(r.kode_model_intern || '-')}</span>
                    <span class="text-xs text-slate-500">${r.matched ? escapeHtml(r.perlengkapan?.tipe || r.perlengkapan?.nosin || '-') : 'Tidak ditemukan di database perlengkapan'}</span>
                  </div>`).join('')
                : '<p class="text-sm text-slate-400">Tidak ada kode model untuk disinkronkan.</p>';
            document.getElementById('smhSyncResult').classList.remove('hidden');
        } catch (e) { showAlert(e.message, 'error'); }
    });

    // ── Bank panel ──
    const bankPanel = document.getElementById("tabPanel-bank");
    document.getElementById("addBankBtn")?.addEventListener("click", () => addBankCard());

    bankPanel?.addEventListener("click", (e) => {
        const addBtn = e.target.closest(".add-row-btn");
        if (addBtn) {
            const which = addBtn.dataset.add;
            if (which === "registerCek") {
                document.getElementById("registerCekBody").appendChild(cekRow());
                return;
            }
            const card = addBtn.closest(".bank-card");
            if (!card) return;
            const sel = which === "bankPenerimaan" ? ".bank-penerimaan-body" : ".bank-pengeluaran-body";
            card.querySelector(sel).appendChild(trxRow());
            return;
        }
        const removeBank = e.target.closest(".bank-remove");
        if (removeBank) {
            removeBank.closest(".bank-card")?.remove();
            renumberBanks();
            recalcBank();
            return;
        }
        const removeRow = e.target.closest(".remove-row");
        if (removeRow) {
            removeRow.closest("tr")?.remove();
            recalcBank();
        }
    });

    bankPanel?.addEventListener("input", (e) => {
        const t = e.target;
        const isRupiah = t.classList.contains("trx-jumlah") || t.classList.contains("bank-calc");
        if (isRupiah && t.type === "text") applyThousandsFormat(t);
        recalcBank();
    });

    document.getElementById("saveBankFormBtn")?.addEventListener("click", () => {
        saveBankForm().catch((err) => showAlert(err.message || "Gagal menyimpan.", "error"));
    });

    setupFilters();

    try {
        await loadCurrentUser();
        await loadPlans();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data audit.", "error");
    }
});
