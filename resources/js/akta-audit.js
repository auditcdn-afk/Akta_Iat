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
    setTimeout(() => el.classList.add("hidden"), 4000);
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
    _gradingData = null;
    _gradingLoadedPlanId = null;
    _mtData = null;
    _mtActiveMekanik = null;
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
    if (tab === "plafon") {
        loadPlafonTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "materai") {
        loadMateraiTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "bpkb") {
        loadBpkbTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "bpkb-inproses") {
        loadBpkiTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "kwitansi") {
        loadKwTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "piutang-reguler") {
        loadPrTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "piutang-cdn") {
        loadPcdnTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "ttp-gantung") {
        loadTtpTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "cek-fisik") {
        loadCfTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "mt") {
        loadMtTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "hgp") {
        loadHgpTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "hga") {
        loadHgaTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "smh-tarikan") {
        loadSmhTarikanTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "lampiran") {
        loadLampiranTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "grading") {
        loadGradingTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "pica") {
        loadPicaTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "rekomendasi") {
        loadRekomendasiTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "bu-performance") {
        loadBupTab().catch((e) => showAlert(e.message, "error"));
    }
    if (tab === "perlengkapan") {
        loadPlForm().catch((e) => showAlert(e.message, "error"));
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

async function smhCheckItem(itemId, body) {
    const payload = await fetchJson(`/api/audit-detail/smh/items/${itemId}`, {
        method: 'PUT',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const updated = payload.data;
    const idx = smhItems.findIndex(i => i.id === itemId);
    if (idx >= 0 && updated) Object.assign(smhItems[idx], {
        statusFisik: updated.statusFisik,
        keteranganFisik: updated.keteranganFisik,
        tglPeriksa: updated.tglPeriksa,
        keteranganKondisi: updated.keteranganKondisi,
        perlengkapanJson: updated.perlengkapanJson,
    });
    updateSmhSummary({
        totalUnit: smhItems.length,
        totalDitemukan: smhItems.filter(i => i.statusFisik === 'ada').length,
        totalTidakDitemukan: smhItems.filter(i => i.statusFisik === 'tidak_ada').length,
        totalBelumDiperiksa: smhItems.filter(i => !i.statusFisik).length,
    });
    return payload;
}

function smhPerlengkapanChecklist(perlengkapan, saved = []) {
    if (!perlengkapan || !perlengkapan.length) return '<p class="text-xs text-slate-400 italic">Tidak ada data perlengkapan untuk kode motor ini.</p>';
    const savedMap = {};
    (saved || []).forEach(p => { savedMap[p.nama] = p.ada; });
    const total = perlengkapan.length;
    const ada   = (saved || []).filter(p => p.ada).length;
    return `
        <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-600">Perlengkapan SMH</span>
            <span class="text-xs font-bold" id="smhPlProgress">${ada}/${total} lengkap</span>
        </div>
        <div class="space-y-1" id="smhPlList">
            ${perlengkapan.map(nama => `
            <label class="flex items-center gap-2 cursor-pointer rounded px-2 py-1 hover:bg-slate-50">
                <input type="checkbox" class="smh-pl-cb h-4 w-4 rounded border-slate-300 text-emerald-600"
                    data-nama="${escapeHtml(nama)}" ${savedMap[nama] ? 'checked' : ''}>
                <span class="text-sm text-slate-700">${escapeHtml(nama)}</span>
            </label>`).join('')}
        </div>`;
}

async function smhScanUnit(q) {
    const res = document.getElementById('smhScanResult');
    if (!q || q.length < 2) { res.classList.add('hidden'); return; }
    const payload = await fetchJson(`/api/audit-detail/smh/scan?q=${encodeURIComponent(q)}&plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    const it = payload.data;
    const perlengkapan = payload.perlengkapan || [];

    if (!it) {
        res.className = 'rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700';
        res.innerHTML = `<strong>Tidak ditemukan</strong> — unit "<em>${escapeHtml(q)}</em>" tidak ada dalam daftar onhand.`;
        res.classList.remove('hidden');
        return;
    }

    const today = new Date().toISOString().slice(0, 10);
    const tglVal  = it.tglPeriksa || today;
    const kondisi = it.keteranganKondisi || 'ready_for_sale';
    const isAda   = it.statusFisik === 'ada';

    res.className = 'rounded-xl border border-emerald-400 bg-white p-5 text-sm space-y-4';
    res.innerHTML = `
        <div class="flex items-center gap-2">
            <span class="inline-flex rounded-full border border-emerald-400 bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">✓ Unit Ditemukan dalam Daftar Onhand</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tgl Pemeriksaan</label>
                <input type="date" id="smhFormTgl" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-500" value="${tglVal}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Fisik Scan</label>
                <div class="rounded-lg bg-blue-500 px-3 py-2 text-center text-sm font-bold text-white">${escapeHtml(it.noMesin || it.noRangka || q)}</div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">No. Rangka</label>
                <div class="text-sm font-semibold text-slate-700">${escapeHtml(it.noRangka || '-')}</div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis Motor</label>
                <div class="text-sm font-semibold text-slate-700">${escapeHtml(it.kodeModel || '-')} / ${escapeHtml(it.warna || '-')}</div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Lokasi</label>
                <div class="text-sm font-semibold text-slate-700">${escapeHtml(it.gudang || '-')} &nbsp;|&nbsp; Umur ${it.umur ?? '-'} hr</div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan Fisik</label>
                <input type="text" id="smhFormKetFisik" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-500"
                    value="${escapeHtml(it.keteranganFisik || 'Fisik Ada')}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Keterangan Kondisi</label>
                <input id="smhFormKondisi" list="smhKondisiList"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-500"
                    placeholder="Pilih atau ketik kondisi..."
                    value="${escapeHtml(it.keteranganKondisi || '')}">
                <datalist id="smhKondisiList">
                    <option value="Ready for Sale">
                    <option value="Perlu Perbaikan">
                    <option value="Rusak">
                    <option value="Hilang">
                </datalist>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            ${smhPerlengkapanChecklist(perlengkapan, it.perlengkapanJson)}
        </div>

        <div class="flex justify-between items-center gap-3 pt-1 border-t border-slate-200">
            <button type="button" data-scan-check="${it.id}" data-val="tidak_ada"
                class="rounded-xl border border-red-400 px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50">
                ✗ Tandai Tidak Ditemukan
            </button>
            <button type="button" id="smhFormSimpanBtn" data-id="${it.id}"
                class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-500">
                Simpan Pemeriksaan
            </button>
        </div>`;
    res.classList.remove('hidden');

    // Update progress saat checkbox berubah
    res.querySelectorAll('.smh-pl-cb').forEach(cb => {
        cb.addEventListener('change', () => {
            const total2 = res.querySelectorAll('.smh-pl-cb').length;
            const ada2   = res.querySelectorAll('.smh-pl-cb:checked').length;
            const prog = res.querySelector('#smhPlProgress');
            if (prog) prog.textContent = `${ada2}/${total2} lengkap`;
        });
    });

    // Scroll ke baris di tabel
    const row = document.querySelector(`#smhTableBody tr[data-item-id="${it.id}"]`);
    if (row) { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); row.classList.add('ring-2', 'ring-blue-400'); setTimeout(() => row.classList.remove('ring-2', 'ring-blue-400'), 2000); }
}

// ── Perlengkapan di Luar SMH ──────────────────────────────────────────────────

let plJenisAll  = [];   // semua jenis dari API
let plSmhMap    = {};   // { nama: { ada, total } } dari smh-summary
let plEditId    = null; // id record sedang diedit

async function loadPlForm() {
    if (!activePlanId) return;

    // Isi no plan, nama unit, pemeriksa dari activePlan & currentUser
    const plan = activePlan || {};
    document.getElementById('plNoPlan').value     = plan.noSpt || plan.noPlan || '';
    document.getElementById('plNamaUnit').value   = plan.cabang || plan.namaUnit || '';
    document.getElementById('plTglPeriksa').value = plan.tglPlan || plan.tglMulai || '';
    // Auto-fill pemeriksa dari user login (hidden field)
    const pemeriksa = currentUser?.name || currentUser?.username || currentUser?.email || '';
    document.getElementById('plNamaPemeriksaHidden').value = pemeriksa;
    document.getElementById('plNamaPemeriksaDisplay').textContent = pemeriksa || '-';

    // Load jenis perlengkapan dari onhand
    try {
        const [jenisRes, smhRes] = await Promise.all([
            fetchJson(`/api/audit-detail/perlengkapan/jenis?plan_audit_id=${activePlanId}`, { headers: authHeaders() }),
            fetchJson(`/api/audit-detail/perlengkapan/smh-summary?plan_audit_id=${activePlanId}`, { headers: authHeaders() }),
        ]);
        plJenisAll = jenisRes.data || [];
        plSmhMap   = {};
        (smhRes.data || []).forEach(r => { plSmhMap[r.nama] = r; });
    } catch (e) {
        plJenisAll = [];
    }

    plPopulateJenisSelect();

    // Load data yang sudah ada
    await loadPlTable();
}

async function loadPlTable() {
    if (!activePlanId) return;
    const res = await fetchJson(`/api/audit-detail/perlengkapan?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    const rows = res.data || [];
    renderPlTable(rows);
}

function renderPlTable(rows) {
    const tbody = document.getElementById('plTableBody');
    const wrap  = document.getElementById('plTableWrap');
    const count = document.getElementById('plCount');
    if (!tbody) return;

    if (!rows.length) { wrap?.classList.add('hidden'); return; }
    wrap?.classList.remove('hidden');
    count.textContent = `${rows.length} item`;

    let totSaldo = 0, totFisik = 0, totSelisih = 0;

    tbody.innerHTML = rows.map(r => {
        totSaldo   += r.saldo   || 0;
        totFisik   += r.fisik   || 0;
        totSelisih += r.selisih || 0;
        const selClass = r.selisih < 0 ? 'text-red-400' : r.selisih > 0 ? 'text-emerald-400' : 'text-slate-300';
        return `<tr class="hover:bg-slate-800/50" data-pl-id="${r.id}">
            <td class="px-4 py-3 text-sm text-slate-100">${escapeHtml(r.jenisPerlengkapan || '-')}</td>
            <td class="px-4 py-3 text-right text-sm text-slate-300">${r.saldo}</td>
            <td class="px-4 py-3 text-right text-sm text-slate-300">${r.fisik}</td>
            <td class="px-4 py-3 text-right text-sm font-bold ${selClass}">${r.selisih > 0 ? '+' : ''}${r.selisih}</td>
            <td class="px-4 py-3 text-xs text-slate-400 max-w-xs truncate">${escapeHtml(r.penjelasan || '-')}</td>
            <td class="px-4 py-3 text-center">
                <button type="button" data-pl-edit="${r.id}" class="rounded px-2 py-1 text-xs text-blue-400 hover:bg-blue-900/30">Edit</button>
                <button type="button" data-pl-del="${r.id}" class="rounded px-2 py-1 text-xs text-red-400 hover:bg-red-900/30">Hapus</button>
            </td>
        </tr>`;
    }).join('');

    document.getElementById('plTotalSaldo').textContent   = totSaldo;
    document.getElementById('plTotalFisik').textContent   = totFisik;
    const selEl = document.getElementById('plTotalSelisih');
    selEl.textContent = (totSelisih > 0 ? '+' : '') + totSelisih;
    selEl.style.color = totSelisih < 0 ? '#f87171' : totSelisih > 0 ? '#34d399' : '#94a3b8';
}

function plRecalcSelisih() {
    const saldo  = parseFloat(document.getElementById('plSaldo')?.value  || 0);
    const fisik  = parseInt(document.getElementById('plFisik')?.value    || 0, 10);
    const selisih = fisik - saldo;
    const el = document.getElementById('plSelisih');
    if (el) {
        el.value = selisih;
        el.style.color = selisih < 0 ? '#f87171' : selisih > 0 ? '#34d399' : '#94a3b8';
    }
}

function plPopulateJenisSelect() {
    const sel = document.getElementById('plJenisInput');
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">-- Pilih Jenis Perlengkapan --</option>' +
        plJenisAll.map(n => `<option value="${escapeHtml(n)}"${n === current ? ' selected' : ''}>${escapeHtml(n)}</option>`).join('');
}

function plResetForm() {
    plEditId = null;
    const sel = document.getElementById('plJenisInput');
    if (sel) sel.value = '';
    document.getElementById('plSaldo').value       = 0;
    document.getElementById('plFisik').value       = 0;
    document.getElementById('plSelisih').value     = 0;
    document.getElementById('plPenjelasan').value  = '';
    document.getElementById('plJenisSmhInfo')?.classList.add('hidden');
    document.getElementById('plSimpanBtn').textContent = 'Simpan';
    plRecalcSelisih();
}

function plSelectJenis(nama) {
    // Saldo = total onhand - unit SMH yang perlengkapan ini sudah ada (ada=true)
    const smh  = plSmhMap[nama];
    const info = document.getElementById('plJenisSmhInfo');
    if (smh) {
        const totalOnhand = smh.totalOnhand || 0;
        const saldoVal = Math.max(0, totalOnhand - smh.ada);
        document.getElementById('plSaldo').value = saldoVal;
        if (info) {
            info.textContent = `Onhand: ${totalOnhand} unit − SMH ada: ${smh.ada} unit = saldo ${saldoVal}`;
            info.classList.remove('hidden');
        }
    } else {
        document.getElementById('plSaldo').value = 0;
        if (info) info.classList.add('hidden');
    }
    plRecalcSelisih();
}

function initPlForm() {
    // Jenis perlengkapan dropdown
    document.getElementById('plJenisInput')?.addEventListener('change', e => plSelectJenis(e.target.value));

    // Fisik ± buttons
    document.getElementById('plFisikPlus')?.addEventListener('click', () => {
        const el = document.getElementById('plFisik');
        el.value = parseInt(el.value || 0, 10) + 1;
        plRecalcSelisih();
    });
    document.getElementById('plFisikMinus')?.addEventListener('click', () => {
        const el = document.getElementById('plFisik');
        el.value = Math.max(0, parseInt(el.value || 0, 10) - 1);
        plRecalcSelisih();
    });
    document.getElementById('plSaldo')?.addEventListener('input', plRecalcSelisih);
    document.getElementById('plFisik')?.addEventListener('input', plRecalcSelisih);

    // Reset
    document.getElementById('plResetBtn')?.addEventListener('click', plResetForm);

    // Simpan
    document.getElementById('plSimpanBtn')?.addEventListener('click', async () => {
        const jenis = document.getElementById('plJenisInput')?.value.trim();
        if (!jenis) { showAlert('Pilih jenis perlengkapan terlebih dahulu.', 'error'); return; }
        const btn = document.getElementById('plSimpanBtn');
        btn.disabled = true; btn.textContent = 'Menyimpan...';
        try {
            const body = {
                plan_audit_id:      activePlanId,
                no_plan:            document.getElementById('plNoPlan')?.value || null,
                nama_unit_usaha:    document.getElementById('plNamaUnit')?.value || null,
                nama_pemeriksa:     document.getElementById('plNamaPemeriksaHidden')?.value || null,
                tgl_periksa:        document.getElementById('plTglPeriksa')?.value || null,
                jenis_perlengkapan: jenis,
                saldo:              parseFloat(document.getElementById('plSaldo')?.value || 0),
                fisik:              parseInt(document.getElementById('plFisik')?.value || 0, 10),
                penjelasan:         document.getElementById('plPenjelasan')?.value || null,
            };
            if (plEditId) {
                await fetchJson(`/api/audit-detail/perlengkapan/${plEditId}`, { method: 'PUT', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            } else {
                await fetchJson('/api/audit-detail/perlengkapan', { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            }
            showAlert('Data perlengkapan berhasil disimpan.');
            plResetForm();
            await loadPlTable();
        } catch (err) {
            showAlert('Gagal: ' + err.message, 'error');
        } finally {
            btn.disabled = false; btn.textContent = plEditId ? 'Update' : 'Simpan';
        }
    });

    // Edit & Delete dari tabel
    document.getElementById('plTableBody')?.addEventListener('click', async e => {
        const editBtn = e.target.closest('[data-pl-edit]');
        if (editBtn) {
            const id = Number(editBtn.dataset.plEdit);
            const res = await fetchJson(`/api/audit-detail/perlengkapan?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const rec = (res.data || []).find(r => r.id === id);
            if (!rec) return;
            plEditId = id;
            const sel = document.getElementById('plJenisInput');
            if (sel) sel.value = rec.jenisPerlengkapan || '';
            document.getElementById('plSaldo').value        = rec.saldo  || 0;
            document.getElementById('plFisik').value        = rec.fisik  || 0;
            document.getElementById('plPenjelasan').value   = rec.penjelasan || '';
            document.getElementById('plSimpanBtn').textContent = 'Update';
            plRecalcSelisih();
            sel?.focus();
            return;
        }
        const delBtn = e.target.closest('[data-pl-del]');
        if (delBtn) {
            if (!confirm('Hapus data ini?')) return;
            try {
                await fetchJson(`/api/audit-detail/perlengkapan/${delBtn.dataset.plDel}`, { method: 'DELETE', headers: authHeaders() });
                showAlert('Data dihapus.');
                await loadPlTable();
            } catch (err) { showAlert(err.message, 'error'); }
        }
    });
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

// ── Pemeriksaan Plafon ────────────────────────────────────────────────────────

function fmtRupiah(val) {
    if (val === null || val === undefined) return '—';
    return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}

async function loadPlafonTab() {
    if (!activePlanId) return;

    document.getElementById('pfAnalisaWrap')?.classList.add('hidden');
    document.getElementById('pfRingkasanWrap')?.classList.add('hidden');

    try {
        const res = await fetchJson(
            `/api/audit-detail/plafon/analisa?plan_audit_id=${activePlanId}`,
            { headers: authHeaders() }
        );
        pfRenderHeader(res);
        pfRenderAnalisa(res);
        pfRenderRingkasan(res);
    } catch (e) {
        showAlert('Gagal memuat analisa plafon: ' + e.message, 'error');
    }
}

function pfRenderHeader(data) {
    document.getElementById('pfKodeUnit').textContent    = data.namaUnit || data.cabang || activePlan?.cabang || '—';
    document.getElementById('pfPlafonCover').textContent = data.plafonNilai != null ? fmtRupiah(data.plafonNilai) : '—';
    document.getElementById('pfDaerah').textContent      = data.wilayah || '—';
}

function pfRenderAnalisa(data) {
    const wrap = document.getElementById('pfAnalisaWrap');
    wrap?.classList.remove('hidden');

    document.getElementById('pfAnalisaSubtitle').textContent = `${data.totalUnit} unit diproses`;
    document.getElementById('pfStatTotal').textContent      = data.totalUnit;
    document.getElementById('pfStatDitemukan').textContent  = data.ditemukan;
    document.getElementById('pfStatTidak').textContent      = data.tidakDitemukan;
    document.getElementById('pfStatPlafon').textContent     = fmtRupiah(data.totalPlafon);
    document.getElementById('pfStatNilai').textContent      = fmtRupiah(data.totalNilaiSmh);

    // Progress bar total
    const progressWrap = document.getElementById('pfProgressWrap');
    if (data.totalPlafon && data.totalPlafon > 0) {
        progressWrap?.classList.remove('hidden');
        const pct = Math.min(100, data.persentaseTotal || 0);
        document.getElementById('pfProgressBar').style.width = pct + '%';
        document.getElementById('pfProgressBar').className = `h-3 rounded-full transition-all duration-500 ${pct > 80 ? 'bg-red-500' : pct > 50 ? 'bg-yellow-500' : 'bg-emerald-500'}`;
        document.getElementById('pfProgressLabel').textContent = `Total nilai SMH ${fmtRupiah(data.totalNilaiSmh)} dari plafon ${fmtRupiah(data.totalPlafon)}`;
        document.getElementById('pfSisaCoverLabel').textContent = `Sisa Cover: ${fmtRupiah(data.sisaTotal)}`;
        document.getElementById('pfProgressPct').textContent = `${pct}% dari plafon terpakai`;
    } else {
        progressWrap?.classList.add('hidden');
    }

    // Detail tabel semua unit
    const tbody = document.getElementById('pfDetailBody');
    if (tbody) {
        const allDetail = (data.perUnit || []).flatMap(u => u.detail || []);
        tbody.innerHTML = allDetail.map(r => {
            const statusCls = r.ditemukan ? 'text-emerald-400' : 'text-orange-400';
            const statusTxt = r.ditemukan ? '✓ Ada' : '✕ Tidak';
            return `<tr class="hover:bg-slate-800/40">
                <td class="px-3 py-2 text-xs text-slate-300">${escapeHtml(r.noMesin || r.noRangka || '-')}</td>
                <td class="px-3 py-2 text-xs font-mono text-slate-200">${escapeHtml(r.kodeModel || '-')}</td>
                <td class="px-3 py-2 text-xs text-slate-300">${escapeHtml(r.namaSmh || '-')}</td>
                <td class="px-3 py-2 text-right text-xs text-slate-200">${r.harga != null ? fmtRupiah(r.harga) : '—'}</td>
                <td class="px-3 py-2 text-xs text-slate-400">${escapeHtml(r.gudang || '-')}</td>
                <td class="px-3 py-2 text-center text-xs font-bold ${statusCls}">${statusTxt}</td>
            </tr>`;
        }).join('') || `<tr><td colspan="6" class="px-3 py-6 text-center text-xs text-slate-500">Belum ada data onhand untuk plan ini</td></tr>`;
    }
}

function pfRenderRingkasan(data) {
    const wrap = document.getElementById('pfRingkasanWrap');
    const rows = data.perUnit || [];
    if (!rows.length) { wrap?.classList.add('hidden'); return; }
    wrap?.classList.remove('hidden');

    const tbody = document.getElementById('pfRingkasanBody');
    if (tbody) {
        tbody.innerHTML = rows.map(r => {
            const pctBar = r.persentase != null
                ? `<div class="h-1.5 w-full rounded-full bg-slate-700 mt-1"><div class="h-1.5 rounded-full ${r.persentase > 80 ? 'bg-red-500' : 'bg-blue-500'}" style="width:${Math.min(100, r.persentase)}%"></div></div>` : '';
            return `<tr class="hover:bg-slate-800/40">
                <td class="px-3 py-2 text-xs font-semibold text-slate-200">${escapeHtml(r.namaUnit || r.gudang)}<div class="text-xs font-normal text-slate-400">${escapeHtml(r.wilayah || '')}</div></td>
                <td class="px-3 py-2 text-center text-xs text-slate-300">${r.totalUnit}</td>
                <td class="px-3 py-2 text-center text-xs text-emerald-400">${r.ditemukan}</td>
                <td class="px-3 py-2 text-right text-xs text-blue-300">${fmtRupiah(r.totalNilai)}</td>
                <td class="px-3 py-2 text-right text-xs text-slate-300">${fmtRupiah(r.plafonNilai)}</td>
                <td class="px-3 py-2 text-right text-xs text-emerald-400">${fmtRupiah(r.sisaCover)}</td>
                <td class="px-3 py-2 text-center text-xs">${r.persentase != null ? `<div>${r.persentase}%${pctBar}</div>` : '—'}</td>
            </tr>`;
        }).join('');
    }

    document.getElementById('pfRingkasanTotalNilai').textContent  = fmtRupiah(data.totalNilaiSmh);
    document.getElementById('pfRingkasanTotalPlafon').textContent = fmtRupiah(data.totalPlafon);
    document.getElementById('pfRingkasanTotalSisa').textContent   = fmtRupiah(data.sisaTotal);

    const progWrap = document.getElementById('pfRingkasanProgressWrap');
    if (data.totalPlafon > 0) {
        progWrap?.classList.remove('hidden');
        const pct = Math.min(100, data.persentaseTotal || 0);
        document.getElementById('pfRingkasanBar').style.width = pct + '%';
        document.getElementById('pfRingkasanProgressLabel').textContent = `Total nilai SMH ${fmtRupiah(data.totalNilaiSmh)} dari plafon ${fmtRupiah(data.totalPlafon)}`;
        document.getElementById('pfRingkasanSisaLabel').textContent = `Sisa Cover: ${fmtRupiah(data.sisaTotal)}`;
        document.getElementById('pfRingkasanPct').textContent = `${pct}% dari plafon terpakai`;
    } else {
        progWrap?.classList.add('hidden');
    }
}

function initPlafonForm() { /* event delegation sudah tidak diperlukan */ }

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

    // ── Perlengkapan di luar SMH panel ──
    initPlForm();

    // ── Plafon panel ──
    initPlafonForm();

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

    // ── Tambah Manual SMH ─────────────────────────────────────────────────────
    const smhManualModal  = document.getElementById('smhManualModal');
    const smhManualAlert  = document.getElementById('smhManualAlert');

    function openSmhManual() {
        document.getElementById('smhManualNoMesin').value  = '';
        document.getElementById('smhManualNoRangka').value = '';
        document.getElementById('smhManualGudang').value   = '';
        smhManualAlert.classList.add('hidden');
        smhManualModal.classList.remove('hidden');
        smhManualModal.classList.add('flex');
        document.getElementById('smhManualNoMesin').focus();
    }

    function closeSmhManual() {
        smhManualModal.classList.add('hidden');
        smhManualModal.classList.remove('flex');
    }

    document.getElementById('smhManualBtn')?.addEventListener('click', openSmhManual);
    document.getElementById('smhManualClose')?.addEventListener('click', closeSmhManual);
    document.getElementById('smhManualCancel')?.addEventListener('click', closeSmhManual);

    document.getElementById('smhManualSave')?.addEventListener('click', async () => {
        const noMesin  = document.getElementById('smhManualNoMesin').value.trim().toUpperCase();
        const noRangka = document.getElementById('smhManualNoRangka').value.trim().toUpperCase();
        const gudang   = document.getElementById('smhManualGudang').value.trim();

        if (!noMesin || !noRangka) {
            smhManualAlert.textContent = 'No. Mesin dan No. Rangka wajib diisi.';
            smhManualAlert.className = 'rounded-lg px-3 py-2 text-xs bg-red-900/40 text-red-300';
            return;
        }

        const saveBtn = document.getElementById('smhManualSave');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Menyimpan...';

        try {
            const res = await fetchJson('/api/audit-detail/smh/manual', {
                method: 'POST',
                headers: authHeaders(),
                body: JSON.stringify({
                    plan_audit_id: activePlanId,
                    no_mesin:  noMesin,
                    no_rangka: noRangka,
                    gudang:    gudang || null,
                }),
            });

            smhManualAlert.textContent = res.message || 'Berhasil ditambahkan.';
            smhManualAlert.className = 'rounded-lg px-3 py-2 text-xs bg-emerald-900/40 text-emerald-300';

            // Refresh daftar SMH
            await loadSmhForm();

            setTimeout(closeSmhManual, 800);
        } catch (err) {
            smhManualAlert.textContent = err.message || 'Gagal menyimpan.';
            smhManualAlert.className = 'rounded-lg px-3 py-2 text-xs bg-red-900/40 text-red-300';
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Simpan';
        }
    });

    document.getElementById('smhScanInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const q = e.target.value.trim();
            smhScanUnit(q).catch((err) => showAlert(err.message, 'error'));
        }
    });

    document.getElementById('smhScanResult')?.addEventListener('click', async (e) => {
        // Tombol Simpan Pemeriksaan
        const simpanBtn = e.target.closest('#smhFormSimpanBtn');
        if (simpanBtn) {
            const itemId = Number(simpanBtn.dataset.id);
            if (!itemId) { showAlert('ID unit tidak ditemukan. Scan ulang unit.', 'error'); return; }
            const res    = document.getElementById('smhScanResult');
            const plItems = [...res.querySelectorAll('.smh-pl-cb')].map(cb => ({ nama: cb.dataset.nama, ada: cb.checked }));
            simpanBtn.disabled = true;
            simpanBtn.textContent = 'Menyimpan...';
            try {
                await smhCheckItem(itemId, {
                    status_fisik:       'ada',
                    keterangan_fisik:   res.querySelector('#smhFormKetFisik')?.value || 'Fisik Ada',
                    tgl_periksa:        res.querySelector('#smhFormTgl')?.value || null,
                    keterangan_kondisi: res.querySelector('#smhFormKondisi')?.value || null,
                    perlengkapan_json:  plItems,
                });
                showAlert('Pemeriksaan SMH berhasil disimpan.');
                renderSmhTable(document.getElementById('smhFilterStatus')?.value || '');
                populateSmhDropdown();
                // Reset scan form untuk siap scan unit berikutnya
                const scanInput = document.getElementById('smhScanInput');
                if (scanInput) { scanInput.value = ''; scanInput.focus(); }
                document.getElementById('smhScanResult')?.classList.add('hidden');
                hideSmhSuggestions();
            } catch (err) {
                showAlert('Gagal menyimpan: ' + err.message, 'error');
            } finally {
                simpanBtn.disabled = false;
                simpanBtn.textContent = 'Simpan Pemeriksaan';
            }
            return;
        }
        // Tombol Tidak Ditemukan
        const btn = e.target.closest('[data-scan-check]');
        if (!btn) return;
        const itemId = Number(btn.dataset.scanCheck);
        const val    = btn.dataset.val;
        try {
            await smhCheckItem(itemId, { status_fisik: val, keterangan_fisik: 'Fisik Tidak Ada' });
            renderSmhTable(document.getElementById('smhFilterStatus')?.value || '');
            populateSmhDropdown();
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
            await smhCheckItem(itemId, { status_fisik: sel.value, keterangan_fisik: ket });
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
        try { await smhCheckItem(itemId, { status_fisik: item.statusFisik, keterangan_fisik: inp.value }); } catch (_) {}
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
    initMateraiForm();
    initBpkbForm();
    initBpkiForm();
    initKwForm();
    initPrForm();
    initPcdnForm();
    initTtpForm();
    initCfForm();
    initMtForm();
    initHgpForm();
    initHgaForm();
    initSmhTarikanForm();
    initLampiranForm();
    initGradingForm();
    initPicaForm();
    initRekomendasiForm();
    initBupTabForm();

    try {
        await loadCurrentUser();
        await loadPlans();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data audit.", "error");
    }
});

// ─── Pemeriksaan Materai ──────────────────────────────────────────────────────

async function loadMateraiTab() {
    const planId = activePlanId;
    if (!planId) return;
    const wrap = document.getElementById("mtResultWrap");
    if (wrap) wrap.innerHTML = '<p class="text-sm text-slate-400 px-1">Memuat data…</p>';
    const data = await fetchJson(`/api/audit-detail/materai?plan_audit_id=${planId}`, { headers: authHeaders() });
    renderMateraiAll(data.data ?? []);
}

function renderMateraiAll(rows) {
    const wrap = document.getElementById("mtResultWrap");
    if (!wrap) return;
    if (!rows.length) {
        wrap.innerHTML = '<p class="text-sm text-slate-400 px-1">Belum ada data. Silakan impor file HTML dari MTP SPP.</p>';
        return;
    }
    wrap.innerHTML = rows.map(renderMateraiBlock).join("");
    wrap.querySelectorAll(".mt-fisik-input, .mt-uang-input").forEach((inp) => {
        inp.addEventListener("change", onMateraiFisikChange);
    });
    wrap.querySelectorAll(".mt-delete-btn").forEach((btn) => {
        btn.addEventListener("click", onMateraiDelete);
    });
}

function renderMateraiBlock(rec) {
    const trx = rec.transaksi ?? [];
    const trxRows = trx.map((t) => `
        <tr class="border-b border-slate-700 text-xs">
            <td class="px-2 py-1 text-center text-slate-400">${t.no ?? ""}</td>
            <td class="px-2 py-1 text-slate-300">${t.tanggal ?? ""}</td>
            <td class="px-2 py-1 text-slate-300">${t.nomor ?? ""}</td>
            <td class="px-2 py-1 text-slate-300">${t.keterangan ?? ""}</td>
            <td class="px-2 py-1 text-right text-slate-200">${fmtNum(t.debet)}</td>
            <td class="px-2 py-1 text-right text-slate-200">${fmtNum(t.kredit)}</td>
            <td class="px-2 py-1 text-right font-semibold text-slate-100">${fmtNum(t.saldo)}</td>
        </tr>`).join("");

    const selisih = rec.selisih ?? null;
    const selisihHtml = selisih !== null
        ? `<span class="font-bold ${selisih === 0 ? "text-emerald-400" : "text-red-400"}">${selisih > 0 ? "+" : ""}${fmtNum(selisih)}</span>`
        : `<span class="text-slate-500">–</span>`;

    return `
    <div class="rounded-2xl border border-slate-700 bg-slate-900 overflow-hidden" data-mt-id="${rec.id}">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700 bg-slate-800/60">
            <span class="font-semibold text-slate-100">${escHtml(rec.jenisMaterai ?? "")}</span>
            <button class="mt-delete-btn rounded-lg px-3 py-1 text-xs font-semibold text-red-400 border border-red-800 hover:bg-red-900/30" data-id="${rec.id}">Hapus</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-800 text-slate-400">
                    <tr>
                        <th class="px-2 py-2 text-center w-8">No</th>
                        <th class="px-2 py-2 text-left">Tanggal</th>
                        <th class="px-2 py-2 text-left">Nomor</th>
                        <th class="px-2 py-2 text-left">Keterangan</th>
                        <th class="px-2 py-2 text-right">Debet</th>
                        <th class="px-2 py-2 text-right">Kredit</th>
                        <th class="px-2 py-2 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-700 bg-blue-900/20 text-xs font-semibold">
                        <td colspan="6" class="px-2 py-1 text-blue-300">Saldo Awal</td>
                        <td class="px-2 py-1 text-right text-blue-200">${fmtNum(rec.saldoAwal)}</td>
                    </tr>
                    ${trxRows}
                    <tr class="bg-slate-800 text-xs font-bold">
                        <td colspan="4" class="px-2 py-2 text-right text-slate-300">Total</td>
                        <td class="px-2 py-2 text-right text-slate-200">${fmtNum(rec.totalDebet)}</td>
                        <td class="px-2 py-2 text-right text-slate-200">${fmtNum(rec.totalKredit)}</td>
                        <td class="px-2 py-2 text-right text-white">${fmtNum(rec.saldoAkhir)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-4 px-4 py-3 border-t border-slate-700 bg-slate-800/40">
            <label class="text-sm font-semibold text-slate-300">Fisik (pcs):</label>
            <input type="number" min="0"
                class="mt-fisik-input w-28 rounded-lg border border-slate-600 bg-slate-800 px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                data-id="${rec.id}" value="${rec.fisik ?? ""}">
            <label class="text-sm font-semibold text-slate-300">Uang Rp.10.000 (pcs):</label>
            <input type="number" min="0"
                class="mt-uang-input w-28 rounded-lg border border-slate-600 bg-slate-800 px-3 py-1.5 text-sm text-slate-100 focus:border-blue-500 focus:outline-none"
                data-id="${rec.id}" value="${rec.uang10000 ?? ""}">
            <span class="mt-selisih-span text-sm text-slate-400">Selisih: ${selisihHtml}</span>
            <span class="ml-auto text-xs text-slate-500">Saldo buku: ${fmtNum(rec.saldoAkhir)}</span>
        </div>
    </div>`;
}

function fmtNum(val) {
    const n = parseInt(val ?? 0, 10);
    if (isNaN(n)) return "0";
    return (n < 0 ? "-" : "") + Math.abs(n).toLocaleString("id-ID");
}

function escHtml(str) {
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

async function onMateraiFisikChange(e) {
    const inp  = e.target;
    const id   = inp.dataset.id;
    const card = document.querySelector(`[data-mt-id="${id}"]`);
    if (!card) return;

    const fisikInp = card.querySelector(".mt-fisik-input");
    const uangInp  = card.querySelector(".mt-uang-input");
    const fisik    = parseInt(fisikInp?.value ?? 0, 10);
    const uang     = parseInt(uangInp?.value  ?? 0, 10);
    if (isNaN(fisik) || fisik < 0) return;

    try {
        const res = await fetchJson(`/api/audit-detail/materai/${id}/fisik`, {
            method: "PUT",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ fisik, uang_10000: isNaN(uang) ? 0 : uang }),
        });
        const rec     = res.data;
        const selisih = rec.selisih ?? null;
        const span    = card.querySelector(".mt-selisih-span");
        if (span) {
            span.innerHTML = `Selisih: <span class="font-bold ${selisih === 0 ? "text-emerald-400" : "text-red-400"}">${selisih > 0 ? "+" : ""}${fmtNum(selisih)}</span>`;
        }
    } catch (err) {
        showAlert(err.message || "Gagal menyimpan fisik.", "error");
    }
}

async function onMateraiDelete(e) {
    const id = e.currentTarget.dataset.id;
    if (!confirm("Hapus data meterai ini?")) return;
    try {
        await fetchJson(`/api/audit-detail/materai/${id}`, { method: "DELETE", headers: authHeaders() });
        document.querySelector(`[data-mt-id="${id}"]`)?.remove();
        const wrap = document.getElementById("mtResultWrap");
        if (wrap && !wrap.querySelector("[data-mt-id]")) {
            wrap.innerHTML = '<p class="text-muted">Belum ada data. Silakan impor file HTML dari MTP SPP.</p>';
        }
    } catch (err) {
        showAlert(err.message || "Gagal menghapus.", "error");
    }
}

async function uploadMateraiFile(file) {
    const planId = activePlanId;
    if (!planId) { showAlert("Pilih plan audit terlebih dahulu.", "warning"); return; }
    const msg = document.getElementById("mtUploadMsg");
    const showMsg = (text, color) => {
        if (!msg) return;
        msg.textContent = text;
        msg.className = `text-xs ${color}`;
        msg.classList.remove("hidden");
    };
    showMsg("Mengimpor…", "text-blue-400");
    const fd = new FormData();
    fd.append("file", file);
    fd.append("plan_audit_id", planId);
    try {
        const res = await fetchJson("/api/audit-detail/materai/upload", { method: "POST", headers: authHeaders(), body: fd });
        showMsg(res.message ?? "Berhasil diimpor.", "text-emerald-400");
        renderMateraiAll(res.data ?? []);
    } catch (err) {
        showMsg(err.message || "Gagal impor.", "text-red-400");
    }
}

function initMateraiForm() {
    const dropZone  = document.getElementById("mtDropZone");
    const fileInput = document.getElementById("mtFileInput");
    const fileLabel = document.getElementById("mtFileLabel");
    const uploadBtn = document.getElementById("mtUploadBtn");

    if (!dropZone) return;

    dropZone.addEventListener("click", () => fileInput?.click());

    dropZone.addEventListener("dragover", (e) => {
        e.preventDefault();
        dropZone.classList.add("border-primary");
    });
    dropZone.addEventListener("dragleave", () => {
        dropZone.classList.remove("border-primary");
    });
    dropZone.addEventListener("drop", (e) => {
        e.preventDefault();
        dropZone.classList.remove("border-primary");
        const file = e.dataTransfer?.files?.[0];
        if (file) {
            if (fileLabel) { fileLabel.textContent = file.name; fileLabel.classList.remove("hidden"); }
            uploadMateraiFile(file);
        }
    });

    fileInput?.addEventListener("change", () => {
        const file = fileInput.files?.[0];
        if (file && fileLabel) {
            fileLabel.textContent = file.name;
            fileLabel.classList.remove("hidden");
        }
    });

    uploadBtn?.addEventListener("click", () => {
        const file = fileInput?.files?.[0];
        if (!file) { showAlert("Pilih file HTML terlebih dahulu.", "warning"); return; }
        uploadMateraiFile(file);
    });
}

// ─── Onhand BPKB ─────────────────────────────────────────────────────────────

let bpkbData        = { summary: {}, items: [] };
let bpkbRtab        = "scan";
let bpkbSuggestTimer = null;
let bpkbAutoTimer    = null;

async function loadBpkbTab() {
    const planId = activePlanId;
    if (!planId) return;
    const res = await fetchJson(`/api/audit-detail/bpkb?plan_audit_id=${planId}`, { headers: authHeaders() });
    bpkbData = res;
    bpkbRenderStats(res.summary);
    bpkbRenderResult(res.items);
}

function bpkbRenderStats(s) {
    const statsEl = document.getElementById("bpkbStats");
    const dbStatus = document.getElementById("bpkbDbStatus");
    const colsEl   = document.getElementById("bpkbCols");
    if (!statsEl) return;
    if (!s || s.total === 0) {
        statsEl.classList.add("hidden");
        dbStatus?.classList.add("hidden");
        return;
    }
    dbStatus?.classList.remove("hidden");
    statsEl.classList.remove("hidden");
    if (colsEl) colsEl.classList.remove("hidden");

    const cards = [
        { label: "TOTAL BPKB",          val: s.total,                     cls: "text-slate-100" },
        { label: "REG",                  val: s.reg,                       cls: "text-slate-100" },
        { label: "KDS",                  val: s.kds,                       cls: "text-slate-100" },
        { label: "SUDAH SCAN",           val: s.sudahScan,                 cls: "text-emerald-400" },
        { label: "BELUM SCAN",           val: s.belumScan,                 cls: "text-red-400" },
        { label: `REG >120 HARI\nDARI TOTAL BPKB REG`, val: `${s.reg120} (${s.reg120Pct}%)`, cls: "text-amber-400" },
    ];
    statsEl.innerHTML = cards.map(c => `
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-3 text-center">
            <div class="text-lg font-bold ${c.cls}">${c.val}</div>
            <div class="mt-1 text-[10px] leading-tight text-slate-400 uppercase tracking-wide whitespace-pre-line">${c.label}</div>
        </div>`).join("");

    // update result sub-tab counters
    const items = bpkbData.items ?? [];
    document.getElementById("bpkbCountScan").textContent  = items.filter(i => i.sudahScan && i.jenis !== "LUAR").length;
    document.getElementById("bpkbCountBelum").textContent = items.filter(i => !i.sudahScan).length;
    document.getElementById("bpkbCountLuar").textContent  = items.filter(i => i.jenis === "LUAR").length;
    const scanCount = document.getElementById("bpkbScanCount");
    if (scanCount) { scanCount.textContent = `${s.sudahScan} TERSCAN`; scanCount.classList.remove("hidden"); }
    const summary = document.getElementById("bpkbResultSummary");
    if (summary) {
        summary.textContent = `Total DB: ${s.total}   Sudah Scan: ${s.sudahScan}   Belum: ${s.belumScan}`;
        summary.classList.remove("hidden");
    }
}

function bpkbRenderResult(items) {
    const wrap = document.getElementById("bpkbResultWrap");
    if (!wrap) return;

    let filtered;
    if (bpkbRtab === "scan")  filtered = items.filter(i => i.sudahScan && i.jenis !== "LUAR");
    if (bpkbRtab === "belum") filtered = items.filter(i => !i.sudahScan && i.jenis !== "LUAR");
    if (bpkbRtab === "luar")  filtered = items.filter(i => i.jenis === "LUAR");

    if (!filtered.length) {
        wrap.innerHTML = '<p class="text-sm text-slate-500 py-2">Tidak ada data.</p>';
        return;
    }

    const rows = filtered.map((item, idx) => {
        const ketHtml = item.keterangan
            ? `<span class="text-emerald-400 font-semibold">${escHtml(item.keterangan)}</span>`
            : `<span class="text-slate-600">—</span>`;
        const unscanBtn = item.sudahScan
            ? `<button class="bpkb-unscan-btn text-red-400 hover:text-red-300" data-id="${item.id}" title="Batalkan scan">✕</button>`
            : "";
        return `
        <tr class="border-b border-slate-800 hover:bg-slate-800/40 text-xs">
            <td class="px-3 py-2 text-slate-400">${idx + 1}</td>
            <td class="px-3 py-2 font-mono font-semibold text-slate-100">${escHtml(item.noBpkb ?? "")}</td>
            <td class="px-3 py-2 text-slate-300">${escHtml(item.noPolisi ?? "")}</td>
            <td class="px-3 py-2 text-slate-300">${item.tglTerima ?? ""}</td>
            <td class="px-3 py-2 text-slate-300">${escHtml(item.namaPemilik ?? "")}</td>
            <td class="px-3 py-2 font-mono text-slate-300">${escHtml(item.noMesin ?? "")}</td>
            <td class="px-3 py-2 font-mono text-slate-300">${escHtml(item.noRangka ?? "")}</td>
            <td class="px-3 py-2">
                <span class="rounded px-2 py-0.5 text-[10px] font-bold ${item.jenis === "REG" ? "bg-blue-900/50 text-blue-300" : item.jenis === "KDS" ? "bg-purple-900/50 text-purple-300" : "bg-red-900/50 text-red-300"}">${item.jenis ?? ""}</span>
            </td>
            <td class="px-3 py-2 text-slate-400">${item.umur ?? ""}</td>
            <td class="px-3 py-2">${ketHtml}</td>
            <td class="px-3 py-2">${unscanBtn}</td>
        </tr>`;
    }).join("");

    wrap.innerHTML = `
        <table class="w-full text-xs">
            <thead class="bg-slate-800 text-slate-400">
                <tr>
                    <th class="px-3 py-2 text-left w-8">NO.</th>
                    <th class="px-3 py-2 text-left">NO BPKB</th>
                    <th class="px-3 py-2 text-left">NO POLISI</th>
                    <th class="px-3 py-2 text-left">TGL TERIMA</th>
                    <th class="px-3 py-2 text-left">NAMA PEMILIK</th>
                    <th class="px-3 py-2 text-left">NO MESIN</th>
                    <th class="px-3 py-2 text-left">NO RANGKA</th>
                    <th class="px-3 py-2 text-left">JENIS</th>
                    <th class="px-3 py-2 text-left">UMUR</th>
                    <th class="px-3 py-2 text-left">KETERANGAN</th>
                    <th class="px-3 py-2 w-8"></th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;

    wrap.querySelectorAll(".bpkb-unscan-btn").forEach(btn => {
        btn.addEventListener("click", bpkbUnscan);
    });
}

async function bpkbUnscan(e) {
    const id = e.currentTarget.dataset.id;
    if (!confirm("Hapus scan item ini?")) return;
    try {
        await fetchJson(`/api/audit-detail/bpkb/scan/${id}`, { method: "DELETE", headers: authHeaders() });
        await loadBpkbTab();
    } catch (err) {
        showAlert(err.message || "Gagal menghapus.", "error");
    }
}

async function bpkbScanSubmit() {
    const planId = activePlanId;
    if (!planId) { showAlert("Pilih plan audit terlebih dahulu.", "warning"); return; }
    const noBpkb = document.getElementById("bpkbScanInput")?.value?.trim();
    if (!noBpkb) return;

    const resultEl = document.getElementById("bpkbScanResult");
    try {
        const res = await fetchJson("/api/audit-detail/bpkb/scan", {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ plan_audit_id: planId, no_bpkb: noBpkb }),
        });
        const item = res.data;
        if (resultEl) {
            if (res.status === "found") {
                const pol = item.noPolisi ? " — " + escHtml(item.noPolisi) : "";
                resultEl.innerHTML = `<span class="text-emerald-400">✓ Ditemukan: ${escHtml(item.namaPemilik ?? "")}${pol}</span>`;
            } else {
                resultEl.innerHTML = `<span class="text-amber-400">⚠ Tidak ada di onhand — dicatat sebagai Fisik Diluar On Hand</span>`;
            }
            resultEl.classList.remove("hidden");
        }
        document.getElementById("bpkbScanInput").value = "";
        document.getElementById("bpkbSuggestions")?.classList.add("hidden");
        await loadBpkbTab();
    } catch (err) {
        if (resultEl) { resultEl.innerHTML = `<span class="text-red-400">${err.message}</span>`; resultEl.classList.remove("hidden"); }
    }
}

async function bpkbSearchSuggest(q) {
    const planId = activePlanId;
    if (!planId || q.length < 3) {
        document.getElementById("bpkbSuggestions")?.classList.add("hidden");
        return;
    }
    try {
        const res = await fetchJson(`/api/audit-detail/bpkb/search?plan_audit_id=${planId}&q=${encodeURIComponent(q)}`, { headers: authHeaders() });
        const box = document.getElementById("bpkbSuggestions");
        if (!box) return;
        const items = res.data ?? [];
        if (!items.length) { box.classList.add("hidden"); return; }
        box.innerHTML = items.map(i => `
            <div class="bpkb-suggest-item cursor-pointer px-4 py-2 text-sm text-slate-200 hover:bg-slate-700 border-b border-slate-700 last:border-0"
                data-val="${escHtml(i.noBpkb)}">
                <span class="font-mono font-semibold">${escHtml(i.noBpkb)}</span>
                <span class="ml-3 text-slate-400">${escHtml(i.namaPemilik ?? "")} ${escHtml(i.noPolisi ?? "")}</span>
            </div>`).join("");
        box.classList.remove("hidden");
        box.querySelectorAll(".bpkb-suggest-item").forEach(el => {
            el.addEventListener("click", () => {
                document.getElementById("bpkbScanInput").value = el.dataset.val;
                box.classList.add("hidden");
            });
        });
    } catch (_) {}
}

function initBpkbForm() {
    const dropZone  = document.getElementById("bpkbDropZone");
    const fileInput = document.getElementById("bpkbFileInput");
    const fileLabel = document.getElementById("bpkbFileLabel");
    const uploadBtn = document.getElementById("bpkbUploadBtn");
    const resetBtn  = document.getElementById("bpkbResetBtn");
    const scanInput = document.getElementById("bpkbScanInput");

    if (!dropZone) return;

    // Upload Excel
    const doUpload = async (file) => {
        const planId = activePlanId;
        if (!planId) { showAlert("Pilih plan audit terlebih dahulu.", "warning"); return; }
        const msg = document.getElementById("bpkbUploadMsg");
        const showMsg = (text, cls) => { if (msg) { msg.textContent = text; msg.className = `text-xs ${cls}`; msg.classList.remove("hidden"); } };
        showMsg("Mengimpor…", "text-blue-400");
        const fd = new FormData();
        fd.append("file", file);
        fd.append("plan_audit_id", planId);
        try {
            const res = await fetchJson("/api/audit-detail/bpkb/upload", { method: "POST", headers: authHeaders(), body: fd });
            showMsg(res.message ?? "Berhasil diimpor.", "text-emerald-400");
            await loadBpkbTab();
        } catch (err) {
            showMsg(err.message || "Gagal impor.", "text-red-400");
        }
    };

    dropZone.addEventListener("click", () => fileInput?.click());
    dropZone.addEventListener("dragover", (e) => { e.preventDefault(); dropZone.classList.add("border-yellow-500"); });
    dropZone.addEventListener("dragleave", () => dropZone.classList.remove("border-yellow-500"));
    dropZone.addEventListener("drop", (e) => {
        e.preventDefault(); dropZone.classList.remove("border-yellow-500");
        const file = e.dataTransfer?.files?.[0];
        if (file) { if (fileLabel) { fileLabel.textContent = file.name; fileLabel.classList.remove("hidden"); } doUpload(file); }
    });
    fileInput?.addEventListener("change", () => {
        const file = fileInput.files?.[0];
        if (file && fileLabel) { fileLabel.textContent = file.name; fileLabel.classList.remove("hidden"); }
    });
    uploadBtn?.addEventListener("click", () => {
        const file = fileInput?.files?.[0];
        if (!file) { showAlert("Pilih file Excel terlebih dahulu.", "warning"); return; }
        doUpload(file);
    });

    // Reset
    resetBtn?.addEventListener("click", async () => {
        const planId = activePlanId;
        if (!planId) return;
        if (!confirm("Hapus semua data BPKB untuk plan ini?")) return;
        try {
            await fetchJson(`/api/audit-detail/bpkb/reset?plan_audit_id=${planId}`, { method: "DELETE", headers: authHeaders() });
            bpkbData = { summary: {}, items: [] };
            bpkbRenderStats({});
            bpkbRenderResult([]);
            document.getElementById("bpkbDbStatus")?.classList.add("hidden");
            document.getElementById("bpkbStats")?.classList.add("hidden");
        } catch (err) { showAlert(err.message || "Gagal reset.", "error"); }
    });

    // Scan input
    scanInput?.addEventListener("input", () => {
        const q = scanInput.value.trim();
        clearTimeout(bpkbSuggestTimer);
        clearTimeout(bpkbAutoTimer);
        // Autocomplete suggest
        bpkbSuggestTimer = setTimeout(() => bpkbSearchSuggest(q), 200);
        // Auto-scan: jika input berhenti 600ms dan panjang >= 5 (karakter BPKB)
        if (q.length >= 5) {
            bpkbAutoTimer = setTimeout(() => bpkbScanSubmit(), 600);
        }
    });
    scanInput?.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            clearTimeout(bpkbAutoTimer);
            bpkbScanSubmit();
        }
    });
    document.addEventListener("click", (e) => {
        if (!e.target.closest("#bpkbScanInput") && !e.target.closest("#bpkbSuggestions")) {
            document.getElementById("bpkbSuggestions")?.classList.add("hidden");
        }
    });
    // Result sub-tabs
    document.getElementById("bpkbResultTabs")?.addEventListener("click", (e) => {
        const btn = e.target.closest(".bpkb-result-tab");
        if (!btn) return;
        bpkbRtab = btn.dataset.rtab;
        document.querySelectorAll(".bpkb-result-tab").forEach(b => {
            b.className = "bpkb-result-tab rounded-lg px-4 py-1.5 text-xs font-semibold text-slate-300 border border-slate-700 hover:bg-slate-800";
        });
        btn.className = "bpkb-result-tab rounded-lg px-4 py-1.5 text-xs font-semibold bg-blue-600 text-white";
        bpkbRenderResult(bpkbData.items ?? []);
    });
}

// ─── BPKB Inproses ───────────────────────────────────────────────────────────

const BPKI_LEFT_SECTIONS = {
    penerimaanFisik: { container: "bpkiPenerimaanFisikRows", labelPlaceholder: "Keterangan", qtyLabel: "QTY" },
    pengeluaranBpkb: { container: "bpkiPengeluaranBpkbRows", labelPlaceholder: "Keterangan", qtyLabel: "QTY" },
};

let _bpkiBlockUid = 0;

async function loadBpkiTab() {
    const planId = activePlanId;
    if (!planId) return;
    const res = await fetchJson(`/api/audit-detail/bpkb-inproses?plan_audit_id=${planId}`, { headers: authHeaders() });
    bpkiPopulate(res.data);
}

function bpkiPopulate(data) {
    const d = data ?? {};
    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ""; };
    setVal("bpkiTglAwal",                d.tglAwal ?? "");
    setVal("bpkiSaldoAwalFisik",         d.saldoAwalFisik ?? 0);
    setVal("bpkiFisikBpkbHitung",        d.fisikBpkbHitung ?? "");
    setVal("bpkiKeteranganSelisih",      d.keteranganSelisih ?? "");
    setVal("bpkiOnhandBpkb",             d.onhandBpkb ?? 0);
    setVal("bpkiKeteranganSelisihOnhand",d.keteranganSelisihOnhand ?? "");

    bpkiRenderLeftRows("penerimaanFisik", d.penerimaanFisik ?? [{}]);
    bpkiRenderLeftRows("pengeluaranBpkb", d.pengeluaranBpkb ?? [{}]);

    // Clear dynamic blocks
    _bpkiBlockUid = 0;
    const blocksEl = document.getElementById("bpkiInprosesBlocks");
    const ketEl    = document.getElementById("bpkiKetSelisihSection");
    if (blocksEl) blocksEl.innerHTML = "";
    if (ketEl)    ketEl.innerHTML = "";

    // Build blocks array (backward-compat: old single fields → one block)
    const blocks = (d.inprosesBlocks && d.inprosesBlocks.length > 0)
        ? d.inprosesBlocks
        : [{
            filterInproses:      d.filterInproses,
            saldoAwalInproses:   d.saldoAwalInproses,
            pendaftaranBpkb:     d.pendaftaranBpkb,
            penyelesaianInproses:d.penyelesaianInproses,
            fisikInprosesHitung: d.fisikInprosesHitung,
            ketSelisihInproses:  d.ketSelisihInproses,
            rincianInproses:     d.rincianInproses,
          }];

    blocks.forEach(b => bpkiAddInprosesBlock(b));
    bpkiRecalc();
}

function bpkiRenderLeftRows(section, rows) {
    const cfg = BPKI_LEFT_SECTIONS[section];
    if (!cfg) return;
    const wrap = document.getElementById(cfg.container);
    if (!wrap) return;
    wrap.innerHTML = "";
    (rows.length ? rows : [{}]).forEach(row => bpkiAddLeftRow(section, row));
}

function bpkiAddLeftRow(section, data = {}) {
    const cfg = BPKI_LEFT_SECTIONS[section];
    if (!cfg) return;
    const wrap = document.getElementById(cfg.container);
    if (!wrap) return;
    const div = document.createElement("div");
    div.className = "flex items-center gap-2";
    div.dataset.bpkiRow = section;
    div.innerHTML = `
        <input type="text" placeholder="${cfg.labelPlaceholder}" value="${escHtml(data.keterangan ?? "")}"
            class="bpki-label flex-1 rounded-lg border border-slate-600 bg-slate-800 px-3 py-1.5 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
        <input type="number" min="0" value="${data.qty ?? 0}"
            class="bpki-qty w-20 rounded-lg border border-slate-600 bg-slate-800 px-2 py-1.5 text-xs text-slate-100 focus:border-blue-500 focus:outline-none bpki-recalc">
        <button type="button" class="text-red-400 hover:text-red-300 px-1 text-xs">✕</button>`;
    wrap.appendChild(div);
    div.querySelector(".bpki-qty")?.addEventListener("input", bpkiRecalc);
    div.querySelector("button")?.addEventListener("click", () => { div.remove(); bpkiRecalc(); });
}

function bpkiSumLeft(section) {
    const cfg = BPKI_LEFT_SECTIONS[section];
    if (!cfg) return 0;
    const wrap = document.getElementById(cfg.container);
    if (!wrap) return 0;
    return [...wrap.querySelectorAll("[data-bpki-row] .bpki-qty")]
        .reduce((s, el) => s + (parseInt(el.value, 10) || 0), 0);
}

function bpkiGetLeftRows(section) {
    const cfg = BPKI_LEFT_SECTIONS[section];
    if (!cfg) return [];
    const wrap = document.getElementById(cfg.container);
    if (!wrap) return [];
    return [...wrap.querySelectorAll("[data-bpki-row]")].map(row => ({
        keterangan: row.querySelector(".bpki-label")?.value?.trim() ?? "",
        qty: parseInt(row.querySelector(".bpki-qty")?.value ?? 0, 10) || 0,
    })).filter(r => r.keterangan || r.qty);
}

function bpkiAddInprosesBlock(data = {}) {
    const blocksEl = document.getElementById("bpkiInprosesBlocks");
    const ketEl    = document.getElementById("bpkiKetSelisihSection");
    if (!blocksEl || !ketEl) return;

    const uid = ++_bpkiBlockUid;
    const isFirst = blocksEl.children.length === 0;
    const label = data.filterInproses ? escHtml(data.filterInproses) : `Inproses ${uid}`;

    // ── Block column ──
    const block = document.createElement("div");
    block.className = "bpki-inp-block w-72 flex-shrink-0 space-y-3 rounded-xl border border-blue-800 bg-slate-900 p-3";
    block.dataset.uid = uid;

    const removeBtn = isFirst ? "" : `
        <button type="button" class="bpki-block-remove text-red-400 hover:text-red-300 text-xs ml-auto" data-uid="${uid}">✕ Hapus</button>`;

    block.innerHTML = `
        <div class="flex items-center gap-2 pb-1 border-b border-slate-700">
            <span class="text-xs font-bold text-blue-300 uppercase">📋 Inproses</span>
            <input type="text" value="${label}" placeholder="Filter / Label"
                class="bpki-block-filter flex-1 rounded-lg border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
            ${removeBtn}
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1 uppercase">Saldo Awal (Unit)</label>
            <input type="number" min="0" value="${data.saldoAwalInproses ?? 0}"
                class="bpki-block-saldo-awal w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-1.5 text-xs text-slate-100 focus:border-blue-500 focus:outline-none bpki-recalc">
        </div>
        <div>
            <p class="text-xs font-semibold text-blue-400 mb-1">+ Pendaftaran BPKB</p>
            <div class="bpki-block-pendaftaran-rows space-y-1"></div>
            <button type="button" class="bpki-block-add-row mt-1 text-xs text-slate-400 border border-dashed border-slate-600 rounded px-3 py-1 hover:text-blue-400 hover:border-blue-500" data-target="pendaftaran">+ Baris</button>
        </div>
        <div>
            <p class="text-xs font-semibold text-red-400 mb-1">− Penyelesaian Inproses</p>
            <div class="bpki-block-penyelesaian-rows space-y-1"></div>
            <button type="button" class="bpki-block-add-row mt-1 text-xs text-slate-400 border border-dashed border-slate-600 rounded px-3 py-1 hover:text-red-400 hover:border-red-500" data-target="penyelesaian">+ Baris</button>
        </div>
        <div class="rounded-lg border border-slate-700 bg-slate-800/40 p-2 space-y-1 text-xs">
            <div class="flex justify-between"><span class="text-slate-400">Saldo Awal</span><span class="bpki-block-r-saldo-awal text-slate-200">0</span></div>
            <div class="flex justify-between"><span class="text-blue-400">+ Pendaftaran</span><span class="bpki-block-r-pendaftaran text-blue-400">0</span></div>
            <div class="flex justify-between"><span class="text-red-400">− Penyelesaian</span><span class="bpki-block-r-penyelesaian text-red-400">0</span></div>
            <div class="flex justify-between border-t border-slate-700 pt-1 font-bold"><span class="text-slate-200">Saldo Buku</span><span class="bpki-block-r-buku text-slate-100">0</span></div>
            <div class="flex justify-between font-bold"><span class="text-amber-300">Selisih</span><span class="bpki-block-r-selisih text-emerald-400">Nihil</span></div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-400 mb-1 uppercase">Fisik Inproses (Hitung)</label>
            <input type="number" min="0" value="${data.fisikInprosesHitung ?? ""}" placeholder="0"
                class="bpki-block-fisik-hitung w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-1.5 text-xs text-slate-100 focus:border-blue-500 focus:outline-none bpki-recalc">
        </div>`;

    blocksEl.appendChild(block);

    // Populate rows
    const pendRows = block.querySelector(".bpki-block-pendaftaran-rows");
    const penyRows = block.querySelector(".bpki-block-penyelesaian-rows");
    (data.pendaftaranBpkb?.length ? data.pendaftaranBpkb : [{}]).forEach(r => _bpkiAddBlockRow(pendRows, r));
    (data.penyelesaianInproses?.length ? data.penyelesaianInproses : [{}]).forEach(r => _bpkiAddBlockRow(penyRows, r));

    // Events
    block.querySelector(".bpki-block-saldo-awal")?.addEventListener("input", bpkiRecalc);
    block.querySelector(".bpki-block-fisik-hitung")?.addEventListener("input", bpkiRecalc);
    block.querySelector(".bpki-block-remove")?.addEventListener("click", () => {
        block.remove();
        document.querySelector(`.bpki-ket-block[data-uid="${uid}"]`)?.remove();
        bpkiRecalc();
    });
    block.querySelectorAll(".bpki-block-add-row").forEach(btn => {
        btn.addEventListener("click", () => {
            const target = btn.dataset.target === "pendaftaran" ? pendRows : penyRows;
            _bpkiAddBlockRow(target);
            bpkiRecalc();
        });
    });

    // ── Ket Selisih + Rincian section ──
    const ketBlock = document.createElement("div");
    ketBlock.className = "bpki-ket-block rounded-xl border border-slate-700 bg-slate-900 overflow-hidden";
    ketBlock.dataset.uid = uid;
    ketBlock.innerHTML = `
        <div class="px-4 py-2.5 bg-slate-800 flex items-center gap-2">
            <span class="text-xs font-bold text-slate-200 uppercase">📝 Ket. Selisih &amp; Rincian — <span class="bpki-ket-label text-blue-300">${label}</span></span>
        </div>
        <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-semibold text-slate-400 mb-2 uppercase">Keterangan Selisih Inproses</p>
                <div class="bpki-ket-rows space-y-1"></div>
                <button type="button" class="bpki-ket-add mt-2 rounded-lg border border-dashed border-slate-600 px-4 py-1.5 text-xs font-semibold text-slate-400 hover:border-slate-400">+ Tambah Baris</button>
                <div class="mt-2 text-right text-xs text-slate-400">Total: <span class="bpki-ket-total text-slate-200">0</span> buku</div>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 mb-2 uppercase">Rincian Per Bulan</p>
                <div class="bpki-rincian-rows space-y-1"></div>
                <button type="button" class="bpki-rincian-add mt-2 rounded-lg border border-dashed border-slate-600 px-4 py-1.5 text-xs font-semibold text-slate-400 hover:border-slate-400">+ Tambah Bulan</button>
                <div class="mt-2 text-right text-xs text-slate-400">Total: <span class="bpki-rincian-total text-slate-200">0</span> buku</div>
            </div>
        </div>`;

    ketEl.appendChild(ketBlock);

    const ketRows     = ketBlock.querySelector(".bpki-ket-rows");
    const rincianRows = ketBlock.querySelector(".bpki-rincian-rows");
    (data.ketSelisihInproses?.length ? data.ketSelisihInproses : [{}]).forEach(r => _bpkiAddKetRow(ketRows, r, false));
    (data.rincianInproses?.length    ? data.rincianInproses    : [{}]).forEach(r => _bpkiAddKetRow(rincianRows, r, true));

    ketBlock.querySelector(".bpki-ket-add")?.addEventListener("click", () => { _bpkiAddKetRow(ketRows, {}, false); bpkiRecalc(); });
    ketBlock.querySelector(".bpki-rincian-add")?.addEventListener("click", () => { _bpkiAddKetRow(rincianRows, {}, true); bpkiRecalc(); });

    // Sync label
    block.querySelector(".bpki-block-filter")?.addEventListener("input", (e) => {
        const lbl = ketBlock.querySelector(".bpki-ket-label");
        if (lbl) lbl.textContent = e.target.value || `Inproses ${uid}`;
    });
}

function _bpkiAddBlockRow(container, data = {}) {
    const div = document.createElement("div");
    div.className = "flex items-center gap-1";
    div.innerHTML = `
        <input type="text" placeholder="Keterangan" value="${escHtml(data.keterangan ?? "")}"
            class="flex-1 rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
        <input type="number" min="0" value="${data.qty ?? 0}"
            class="bpki-block-row-qty w-16 rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none bpki-recalc">
        <button type="button" class="text-red-400 hover:text-red-300 text-xs px-1">✕</button>`;
    container.appendChild(div);
    div.querySelector(".bpki-block-row-qty")?.addEventListener("input", bpkiRecalc);
    div.querySelector("button")?.addEventListener("click", () => { div.remove(); bpkiRecalc(); });
}

function _bpkiAddKetRow(container, data = {}, isRincian = false) {
    const div = document.createElement("div");
    div.className = "flex items-center gap-1";
    const placeholder = isRincian ? "Contoh: Maret 2025" : "Keterangan";
    const val = escHtml(data.keterangan ?? data.bulan ?? "");
    div.innerHTML = `
        <input type="text" placeholder="${placeholder}" value="${val}"
            class="flex-1 rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
        <input type="number" min="0" value="${data.qty ?? 0}"
            class="bpki-ket-qty w-16 rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
        <button type="button" class="text-red-400 hover:text-red-300 text-xs px-1">✕</button>`;
    container.appendChild(div);
    div.querySelector(".bpki-ket-qty")?.addEventListener("input", bpkiRecalc);
    div.querySelector("button")?.addEventListener("click", () => { div.remove(); bpkiRecalc(); });
}

function bpkiCollectBlock(block) {
    const uid = block.dataset.uid;
    const ketBlock = document.querySelector(`.bpki-ket-block[data-uid="${uid}"]`);

    const getRows = (container, isRincian = false) =>
        [...(container?.querySelectorAll(".flex.items-center") ?? [])].map(row => {
            const inputs = row.querySelectorAll("input");
            const label = inputs[0]?.value?.trim() ?? "";
            const qty = parseInt(inputs[1]?.value ?? 0, 10) || 0;
            return isRincian ? { bulan: label, qty } : { keterangan: label, qty };
        }).filter(r => (r.keterangan ?? r.bulan) || r.qty);

    return {
        filterInproses:      block.querySelector(".bpki-block-filter")?.value?.trim() || null,
        saldoAwalInproses:   parseInt(block.querySelector(".bpki-block-saldo-awal")?.value ?? 0, 10) || 0,
        pendaftaranBpkb:     getRows(block.querySelector(".bpki-block-pendaftaran-rows")),
        penyelesaianInproses:getRows(block.querySelector(".bpki-block-penyelesaian-rows")),
        fisikInprosesHitung: (() => { const v = parseInt(block.querySelector(".bpki-block-fisik-hitung")?.value, 10); return isNaN(v) ? null : v; })(),
        ketSelisihInproses:  getRows(ketBlock?.querySelector(".bpki-ket-rows")),
        rincianInproses:     getRows(ketBlock?.querySelector(".bpki-rincian-rows"), true),
    };
}

function bpkiRecalc() {
    const g = (id) => parseInt(document.getElementById(id)?.value ?? 0, 10) || 0;
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    const selisihHtml = (diff) => diff === 0
        ? '<span class="text-emerald-400">Nihil</span>'
        : `<span class="text-red-400">${diff > 0 ? "+" : ""}${diff}</span>`;

    // Left panel – Fisik
    const saldoAwalFisik   = g("bpkiSaldoAwalFisik");
    const penerimaanFisik  = bpkiSumLeft("penerimaanFisik");
    const pengeluaranFisik = bpkiSumLeft("pengeluaranBpkb");
    const saldoBukuFisik   = saldoAwalFisik + penerimaanFisik - pengeluaranFisik;
    const fisikHitung      = g("bpkiFisikBpkbHitung");

    set("bpkiRFisikSaldoAwal",   saldoAwalFisik);
    set("bpkiRFisikPenerimaan",  penerimaanFisik);
    set("bpkiRFisikPengeluaran", pengeluaranFisik);
    set("bpkiRFisikBuku",        saldoBukuFisik);
    const selElF = document.getElementById("bpkiRFisikSelisih");
    if (selElF) selElF.innerHTML = selisihHtml(fisikHitung - saldoBukuFisik);

    // Per-block inproses
    document.querySelectorAll(".bpki-inp-block").forEach(block => {
        const saldo    = parseInt(block.querySelector(".bpki-block-saldo-awal")?.value ?? 0, 10) || 0;
        const pendSum  = [...block.querySelectorAll(".bpki-block-pendaftaran-rows .bpki-block-row-qty")]
            .reduce((s, el) => s + (parseInt(el.value, 10) || 0), 0);
        const penySum  = [...block.querySelectorAll(".bpki-block-penyelesaian-rows .bpki-block-row-qty")]
            .reduce((s, el) => s + (parseInt(el.value, 10) || 0), 0);
        const buku     = saldo + pendSum - penySum;
        const fisik    = parseInt(block.querySelector(".bpki-block-fisik-hitung")?.value ?? 0, 10) || 0;

        const setText = (cls, val) => { const el = block.querySelector(cls); if (el) el.textContent = val; };
        setText(".bpki-block-r-saldo-awal",   saldo);
        setText(".bpki-block-r-pendaftaran",   pendSum);
        setText(".bpki-block-r-penyelesaian",  penySum);
        setText(".bpki-block-r-buku",          buku);
        const selEl = block.querySelector(".bpki-block-r-selisih");
        if (selEl) selEl.innerHTML = selisihHtml(fisik - buku);
    });

    // Ket totals
    document.querySelectorAll(".bpki-ket-block").forEach(kb => {
        const ketTotal     = [...kb.querySelectorAll(".bpki-ket-rows .bpki-ket-qty")]
            .reduce((s, el) => s + (parseInt(el.value, 10) || 0), 0);
        const rincianTotal = [...kb.querySelectorAll(".bpki-rincian-rows .bpki-ket-qty")]
            .reduce((s, el) => s + (parseInt(el.value, 10) || 0), 0);
        const kt = kb.querySelector(".bpki-ket-total");
        const rt = kb.querySelector(".bpki-rincian-total");
        if (kt) kt.textContent = ketTotal;
        if (rt) rt.textContent = rincianTotal;
    });

    // On Hand vs Fisik
    const onhand = g("bpkiOnhandBpkb");
    set("bpkiOhFisik",  fisikHitung);
    set("bpkiOhOnhand", onhand);
    const selElO = document.getElementById("bpkiOhSelisih");
    if (selElO) selElO.innerHTML = selisihHtml(onhand - fisikHitung);
}

async function saveBpki() {
    const planId = activePlanId;
    if (!planId) { showAlert("Pilih plan audit terlebih dahulu.", "warning"); return; }
    const g  = (id) => document.getElementById(id)?.value?.trim() ?? "";
    const gi = (id) => { const v = parseInt(document.getElementById(id)?.value, 10); return isNaN(v) ? null : v; };

    const inprosesBlocks = [...document.querySelectorAll(".bpki-inp-block")].map(bpkiCollectBlock);
    const first = inprosesBlocks[0] ?? {};

    const payload = {
        planAuditId:             planId,
        tglAwal:                 g("bpkiTglAwal") || null,
        saldoAwalFisik:          gi("bpkiSaldoAwalFisik") ?? 0,
        penerimaanFisik:         bpkiGetLeftRows("penerimaanFisik"),
        pengeluaranBpkb:         bpkiGetLeftRows("pengeluaranBpkb"),
        fisikBpkbHitung:         gi("bpkiFisikBpkbHitung"),
        keteranganSelisih:       g("bpkiKeteranganSelisih") || null,
        onhandBpkb:              gi("bpkiOnhandBpkb") ?? 0,
        keteranganSelisihOnhand: g("bpkiKeteranganSelisihOnhand") || null,
        inprosesBlocks,
        // backward-compat single-block fields
        filterInproses:          first.filterInproses ?? null,
        saldoAwalInproses:       first.saldoAwalInproses ?? 0,
        pendaftaranBpkb:         first.pendaftaranBpkb ?? [],
        penyelesaianInproses:    first.penyelesaianInproses ?? [],
        fisikInprosesHitung:     first.fisikInprosesHitung ?? null,
        ketSelisihInproses:      first.ketSelisihInproses ?? [],
        rincianInproses:         first.rincianInproses ?? [],
    };

    const msg = document.getElementById("bpkiSaveMsg");
    const showMsg = (text, cls) => { if (msg) { msg.textContent = text; msg.className = `text-xs ${cls}`; msg.classList.remove("hidden"); } };
    showMsg("Menyimpan…", "text-blue-400");
    try {
        await fetchJson("/api/audit-detail/bpkb-inproses", {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });
        showMsg("Tersimpan.", "text-emerald-400");
        setTimeout(() => msg?.classList.add("hidden"), 2000);
    } catch (err) {
        showMsg(err.message || "Gagal menyimpan.", "text-red-400");
    }
}

function initBpkiForm() {
    const panel = document.getElementById("tabPanel-bpkb-inproses");
    if (!panel) return;

    // Left panel add-row buttons
    panel.addEventListener("click", (e) => {
        const addBtn = e.target.closest("[data-bpki-add]");
        if (addBtn) { bpkiAddLeftRow(addBtn.dataset.bpkiAdd); bpkiRecalc(); }
    });

    // Recalc on left panel numeric inputs
    panel.addEventListener("input", (e) => {
        if (e.target.classList.contains("bpki-recalc")) bpkiRecalc();
    });

    // Add inproses block
    document.getElementById("bpkiAddBlockBtn")?.addEventListener("click", () => {
        bpkiAddInprosesBlock();
        bpkiRecalc();
    });

    // Save
    document.getElementById("bpkiSaveBtn")?.addEventListener("click", () => {
        saveBpki().catch(err => showAlert(err.message || "Gagal menyimpan.", "error"));
    });
}

// ─── Kwitansi Gantung ────────────────────────────────────────────────────────

let _kwItems = [];   // current kwitansi array in memory

async function loadKwTab() {
    const planId = activePlanId;
    if (!planId) { kwRender(); return; }
    const res = await fetchJson(`/api/audit-detail/kwitansi?plan_audit_id=${planId}`, { headers: authHeaders() });
    if (res.data && (res.data.kwitansi ?? []).length > 0) {
        const el = document.getElementById("kwTglAudit");
        if (el) el.value = res.data.tglAudit ?? "";
        _kwItems = res.data.kwitansi;
    } else if (res.data) {
        // DB has a record but empty kwitansi — only overwrite if we have nothing in memory
        if (_kwItems.length === 0) {
            const el = document.getElementById("kwTglAudit");
            if (el) el.value = res.data.tglAudit ?? "";
            _kwItems = [];
        }
    }
    // If DB returns nothing and _kwItems already has data, keep existing in-memory data
    kwRender();
}

// ── Render ──

function kwRender() {
    const items = _kwItems;
    const tglAuditVal = document.getElementById("kwTglAudit")?.value;
    const tglAudit    = tglAuditVal ? new Date(tglAuditVal) : null;

    // Recalc diff when tgl changes
    if (tglAudit) {
        items.forEach(it => {
            if (it.tglKwitansi) {
                const d = new Date(it.tglKwitansi);
                if (!isNaN(d)) it.diff = Math.round((tglAudit - d) / 864e5);
            }
        });
    }

    // Stats
    const totalNilai    = items.reduce((s, it) => s + (it.nilaiKwitansi || 0), 0);
    const uniqueCustomer = new Set(items.map(it => it.namaCustomer).filter(Boolean)).size;
    const uniqueLeasing  = new Set(items.map(it => it.leasing).filter(Boolean)).size;
    document.getElementById("kwStatTransaksi").textContent = items.length;
    document.getElementById("kwStatCustomer").textContent  = uniqueCustomer;
    document.getElementById("kwStatLeasing").textContent   = uniqueLeasing;
    document.getElementById("kwStatNilai").textContent     = kwFmtNilai(totalNilai);

    const avgSec  = document.getElementById("kwAvgSection");
    const tblSec  = document.getElementById("kwTableSection");
    const tblBody = document.getElementById("kwTableBody");
    const tblCount= document.getElementById("kwTableCount");

    if (!items.length) {
        if (avgSec) avgSec.classList.add("hidden");
        if (tblSec) tblSec.classList.add("hidden");
        return;
    }

    // Rata-rata hari
    const withDiff = items.filter(it => it.diff !== null);
    const totalDiff = withDiff.reduce((s, it) => s + it.diff, 0);
    const avgAll    = withDiff.length ? (totalDiff / withDiff.length).toFixed(1) : "0.0";
    if (document.getElementById("kwAvgAll"))      document.getElementById("kwAvgAll").textContent = avgAll;
    if (document.getElementById("kwAvgSubtitle")) document.getElementById("kwAvgSubtitle").textContent =
        `Total diff: ${totalDiff} hari + ${withDiff.length} kwitansi`;

    // Per leasing avg
    const byLeasing = {};
    items.forEach(it => {
        if (!byLeasing[it.leasing]) byLeasing[it.leasing] = [];
        byLeasing[it.leasing].push(it);
    });
    const perLeasingEl = document.getElementById("kwAvgPerLeasing");
    if (perLeasingEl) {
        perLeasingEl.innerHTML = Object.entries(byLeasing).map(([ls, arr]) => {
            const dArr = arr.filter(i => i.diff !== null);
            const avg  = dArr.length ? (dArr.reduce((s, i) => s + i.diff, 0) / dArr.length).toFixed(1) : "-";
            return `<div class="text-center bg-slate-700 rounded-lg px-3 py-2">
                <div class="text-lg font-bold text-slate-100">${avg}</div>
                <div class="text-xs text-slate-400 font-semibold">${escHtml(ls)}</div>
                <div class="text-xs text-slate-500">(${arr.length} kwt)</div>
            </div>`;
        }).join("");
    }

    if (avgSec) avgSec.classList.remove("hidden");

    // Table grouped by leasing
    if (tblCount) tblCount.textContent = `${items.length} KWITANSI`;
    if (tblBody) {
        tblBody.innerHTML = "";
        Object.entries(byLeasing).forEach(([ls, arr]) => {
            // Group header
            const hdr = document.createElement("tr");
            hdr.className = "bg-slate-800/80";
            hdr.innerHTML = `<td colspan="10" class="px-3 py-2 text-xs font-bold text-blue-300 uppercase">
                🏦 ${escHtml(ls)} <span class="text-slate-400 font-normal">(${arr.length} kwitansi)</span>
            </td>`;
            tblBody.appendChild(hdr);

            let subTotal = 0;
            let subDiff  = 0;
            let subDiffN = 0;

            arr.forEach((it, i) => {
                subTotal += it.nilaiKwitansi || 0;
                if (it.diff !== null) { subDiff += it.diff; subDiffN++; }

                const tr = document.createElement("tr");
                tr.className = "hover:bg-slate-800/40";
                tr.dataset.kwIdx = items.indexOf(it);
                const diffHtml = it.diff !== null
                    ? `<span class="inline-flex items-center justify-center rounded-full w-8 h-5 text-xs font-bold ${it.diff <= 3 ? 'bg-emerald-900 text-emerald-300' : it.diff <= 7 ? 'bg-amber-900 text-amber-300' : 'bg-red-900 text-red-300'}">${it.diff}</span>`
                    : `<span class="text-slate-500">-</span>`;
                tr.innerHTML = `
                    <td class="px-3 py-2 text-slate-500">${i + 1}</td>
                    <td class="px-3 py-2 font-mono text-slate-200">${escHtml(it.noKwitansi)}</td>
                    <td class="px-3 py-2 text-slate-300">${escHtml(it.tglKwitansi)}</td>
                    <td class="px-3 py-2 text-slate-200">${escHtml(it.namaCustomer)}</td>
                    <td class="px-3 py-2 text-slate-400 font-mono">${escHtml(it.noAr)}</td>
                    <td class="px-3 py-2 text-slate-400 font-mono">${escHtml(it.noFaktur)}</td>
                    <td class="px-3 py-2 text-right font-mono text-slate-200">${it.nilaiKwitansi.toLocaleString("id-ID")}</td>
                    <td class="px-3 py-2 text-center">${diffHtml}</td>
                    <td class="px-3 py-2">
                        <input type="text" value="${escHtml(it.keterangan ?? "")}" placeholder="Keterangan... (opsional)"
                            class="w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none kw-ket-input">
                    </td>
                    <td class="px-3 py-2 text-center">
                        <input type="checkbox" ${it.fisik ? "checked" : ""} class="w-4 h-4 rounded accent-blue-500 kw-fisik-input">
                    </td>`;
                // Sync keterangan & fisik back to _kwItems
                tr.querySelector(".kw-ket-input")?.addEventListener("input", (e) => {
                    it.keterangan = e.target.value;
                });
                tr.querySelector(".kw-fisik-input")?.addEventListener("change", (e) => {
                    it.fisik = e.target.checked;
                });
                tblBody.appendChild(tr);
            });

            // Sub-total
            const subAvg = subDiffN ? (subDiff / subDiffN).toFixed(1) : "-";
            const sftr = document.createElement("tr");
            sftr.className = "bg-slate-800/50 border-t border-slate-700";
            sftr.innerHTML = `
                <td colspan="6" class="px-3 py-2 text-xs font-bold text-slate-300 text-right">Sub Total ${escHtml(ls)}</td>
                <td class="px-3 py-2 text-right text-xs font-bold text-slate-100">${subTotal.toLocaleString("id-ID")}</td>
                <td class="px-3 py-2 text-center text-xs font-bold text-amber-300">${subAvg}</td>
                <td colspan="2"></td>`;
            tblBody.appendChild(sftr);
        });
    }

    if (tblSec) tblSec.classList.remove("hidden");
}

function kwFmtNilai(val) {
    if (!val) return "0";
    if (val >= 1e9)  return (val / 1e9).toFixed(1) + " M";
    if (val >= 1e6)  return (val / 1e6).toFixed(1) + " Jt";
    return val.toLocaleString("id-ID");
}

// ── Save ──

async function saveKw() {
    const planId = activePlanId;
    if (!planId) { showAlert("Pilih plan audit terlebih dahulu.", "warning"); return; }
    const tglAudit = document.getElementById("kwTglAudit")?.value?.trim() || null;

    const msg = document.getElementById("kwSaveMsg");
    const showMsg = (text, cls) => { if (msg) { msg.textContent = text; msg.className = `text-xs ${cls}`; msg.classList.remove("hidden"); } };
    showMsg("Menyimpan…", "text-blue-400");
    try {
        await fetchJson("/api/audit-detail/kwitansi", {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ planAuditId: planId, tglAudit, kwitansi: _kwItems }),
        });
        showMsg("Tersimpan.", "text-emerald-400");
        setTimeout(() => msg?.classList.add("hidden"), 2000);
    } catch (err) {
        showMsg(err.message || "Gagal menyimpan.", "text-red-400");
    }
}

// ── Init ──

function initKwForm() {
    const panel = document.getElementById("tabPanel-kwitansi");
    if (!panel) return;

    // Tgl audit change → recalc diff
    document.getElementById("kwTglAudit")?.addEventListener("change", kwRender);

    // File input
    const fileInput = document.getElementById("kwFileInput");
    fileInput?.addEventListener("change", async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        await kwHandleFile(file);
        fileInput.value = "";
    });

    // Drag & drop
    const dropzone = document.getElementById("kwDropzone");
    if (dropzone) {
        dropzone.addEventListener("dragover", (e) => { e.preventDefault(); dropzone.classList.add("border-blue-400"); });
        dropzone.addEventListener("dragleave", ()  => dropzone.classList.remove("border-blue-400"));
        dropzone.addEventListener("drop", async (e) => {
            e.preventDefault();
            dropzone.classList.remove("border-blue-400");
            const file = e.dataTransfer.files[0];
            if (file) await kwHandleFile(file);
        });
    }

    // Save button
    document.getElementById("kwSaveBtn")?.addEventListener("click", () => {
        saveKw().catch(err => showAlert(err.message || "Gagal menyimpan.", "error"));
    });
}

async function kwHandleFile(file) {
    const msgEl = document.getElementById("kwImportMsg");
    try {
        if (msgEl) { msgEl.textContent = "⏳ Memproses file..."; msgEl.classList.remove("hidden"); }

        const tglAudit = document.getElementById("kwTglAudit")?.value ?? "";
        const formData = new FormData();
        formData.append("file", file);
        formData.append("tgl_audit", tglAudit);

        const res = await fetch("/api/audit-detail/kwitansi/parse-excel", {
            method: "POST",
            headers: authHeaders(),
            body: formData,
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message ?? "Gagal memproses file di server.");

        const mapped = json.data ?? [];
        if (!mapped.length) {
            if (msgEl) { msgEl.textContent = "Tidak ada data kwitansi ditemukan di file."; msgEl.classList.remove("hidden"); }
            return;
        }
        // Merge: keep keterangan & fisik for matching noKwitansi
        const existingMap = {};
        _kwItems.forEach(it => { existingMap[it.noKwitansi] = it; });
        _kwItems = mapped.map(it => {
            const old = existingMap[it.noKwitansi];
            if (old) { it.keterangan = old.keterangan; it.fisik = old.fisik; }
            return it;
        });
        if (msgEl) {
            msgEl.textContent = `✅ ${mapped.length} kwitansi berhasil dimuat dari "${file.name}"`;
            msgEl.classList.remove("hidden");
        }
        kwRender();
        saveKw().catch(() => {});
    } catch (err) {
        if (msgEl) { msgEl.textContent = "❌ " + err.message; msgEl.classList.remove("hidden"); }
    }
}

// ══════════════════════════════════════════════════════════
// ── Piutang Reguler Module ──
// ══════════════════════════════════════════════════════════

let _prItems = [];   // current piutang array in memory

async function loadPrTab() {
    const planId = activePlanId;
    if (!planId) { prRender(); return; }
    const res = await fetchJson(`/api/audit-detail/piutang-reguler?plan_audit_id=${planId}`, { headers: authHeaders() });
    if (res.data && (res.data.piutang ?? []).length > 0) {
        _prItems = res.data.piutang;
    }
    // If DB has no data but memory has data, keep memory (handles failed saves)
    prRender();
}

function prFmtRp(val) {
    if (!val && val !== 0) return '-';
    const n = Number(val);
    if (n === 0) return '-';
    if (Math.abs(n) >= 1e9) return `Rp ${(n / 1e9).toFixed(1)} M`;
    if (Math.abs(n) >= 1e6) return `Rp ${(n / 1e6).toFixed(1)} Jt`;
    return `Rp ${n.toLocaleString('id-ID')}`;
}

function prFmtNum(val) {
    const n = Number(val) || 0;
    if (n === 0) return '-';
    return n.toLocaleString('id-ID');
}

function prRender() {
    const items = _prItems;

    // Stat cards
    const totalCustomer  = items.length;
    const totalBelumJto  = items.reduce((s, it) => s + (it.belumJto || 0), 0);
    const totalTung15    = items.reduce((s, it) => s + (it.tung15 || 0), 0);
    const totalTung630   = items.reduce((s, it) => s + (it.tung630 || 0), 0);
    const totalTung3160  = items.reduce((s, it) => s + (it.tung3160 || 0), 0);
    const totalTung60    = items.reduce((s, it) => s + (it.tung60 || 0), 0);
    const totalSaldoAkhir = items.reduce((s, it) => s + (it.saldoAkhir || 0), 0);

    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('prStatCustomer',   totalCustomer);
    set('prStatBelumJto',   prFmtRp(totalBelumJto));
    set('prStatTung15',     prFmtRp(totalTung15));
    set('prStatTung630',    prFmtRp(totalTung630));
    set('prStatTung3160',   prFmtRp(totalTung3160));
    set('prStatTung60',     prFmtRp(totalTung60));
    set('prStatSaldoAkhir', prFmtRp(totalSaldoAkhir));

    const tblSec  = document.getElementById('prTableSection');
    const tblBody = document.getElementById('prTableBody');
    const tblCount = document.getElementById('prTableCount');
    if (!tblBody) return;

    if (!items.length) {
        if (tblSec) tblSec.classList.add('hidden');
        return;
    }
    if (tblSec) tblSec.classList.remove('hidden');
    if (tblCount) tblCount.textContent = `${items.length} Customer`;

    const escHtml = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    tblBody.innerHTML = items.map((it, idx) => {
        const hasTung  = (it.tung15 || 0) + (it.tung630 || 0) + (it.tung3160 || 0) + (it.tung60 || 0) > 0;
        const saldoCls = it.saldoAkhir > 0 ? 'line-through text-slate-500' : 'font-bold text-slate-100';
        return `<tr class="hover:bg-slate-800/30 transition-colors">
            <td class="px-3 py-2 text-slate-400 text-center">${idx + 1}</td>
            <td class="px-3 py-2 font-medium text-blue-300 whitespace-nowrap">${escHtml(it.customer)}</td>
            <td class="px-3 py-2 text-slate-300 font-mono">${escHtml(it.noFaktur)}</td>
            <td class="px-3 py-2 text-slate-300 whitespace-nowrap">${escHtml(it.tanggal || '-')}</td>
            <td class="px-3 py-2"><span class="rounded px-1.5 py-0.5 text-xs font-bold bg-indigo-600/20 text-indigo-300">${escHtml(it.type)}</span></td>
            <td class="px-3 py-2 text-right text-slate-300">${prFmtNum(it.saldoAwal)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${prFmtNum(it.pokok)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${prFmtNum(it.ppn)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${prFmtNum(it.lain2)}</td>
            <td class="px-3 py-2 text-slate-300 font-mono">${escHtml(it.noKwit || '-')}</td>
            <td class="px-3 py-2 text-slate-300 whitespace-nowrap">${escHtml(it.tglKredit || '-')}</td>
            <td class="px-3 py-2 text-right text-green-400">${prFmtNum(it.pembayaran)}</td>
            <td class="px-3 py-2 text-right ${saldoCls}">${prFmtNum(it.saldoAkhir)}</td>
            <td class="px-3 py-2 text-right text-slate-300">${prFmtNum(it.belumJto)}</td>
            <td class="px-3 py-2 text-right ${it.tung15 > 0 ? 'text-orange-300 font-semibold' : 'text-slate-500'}">${prFmtNum(it.tung15)}</td>
            <td class="px-3 py-2 text-right ${it.tung630 > 0 ? 'text-orange-400 font-semibold' : 'text-slate-500'}">${prFmtNum(it.tung630)}</td>
            <td class="px-3 py-2 text-right ${it.tung3160 > 0 ? 'text-red-400 font-semibold' : 'text-slate-500'}">${prFmtNum(it.tung3160)}</td>
            <td class="px-3 py-2 text-right ${it.tung60 > 0 ? 'text-red-500 font-bold' : 'text-slate-500'}">${prFmtNum(it.tung60)}</td>
            <td class="px-3 py-2 text-slate-300 whitespace-nowrap">${escHtml(it.giroGantung || '-')}</td>
            <td class="px-3 py-2">
                <input type="text" value="${escHtml(it.keterangan || '')}"
                    data-pr-idx="${idx}"
                    placeholder="Keterangan... (opsional)"
                    class="w-36 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-slate-100 placeholder-slate-500 focus:border-blue-500 focus:outline-none">
            </td>
        </tr>`;
    }).join('');

    // Keterangan input handlers
    tblBody.querySelectorAll('input[data-pr-idx]').forEach(inp => {
        inp.addEventListener('input', (e) => {
            const i = parseInt(e.target.dataset.prIdx, 10);
            if (_prItems[i]) _prItems[i].keterangan = e.target.value;
        });
        inp.addEventListener('blur', () => savePr().catch(() => {}));
    });
}

async function savePr() {
    const planId = activePlanId;
    if (!planId) throw new Error('Pilih plan audit terlebih dahulu.');
    const res = await fetchJson('/api/audit-detail/piutang-reguler', {
        method:  'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body:    JSON.stringify({ planAuditId: planId, piutang: _prItems }),
    });
    if (!res.message) throw new Error('Gagal menyimpan.');
    showAlert(res.message, 'success');
}

async function prHandleFile(file) {
    const msgEl = document.getElementById('prImportMsg');
    try {
        if (msgEl) { msgEl.textContent = '⏳ Memproses file...'; msgEl.classList.remove('hidden'); }

        const formData = new FormData();
        formData.append('file', file);

        const res = await fetch('/api/audit-detail/piutang-reguler/parse-excel', {
            method:  'POST',
            headers: authHeaders(),
            body:    formData,
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message ?? 'Gagal memproses file di server.');

        const mapped = json.data ?? [];
        if (!mapped.length) {
            if (msgEl) { msgEl.textContent = 'Tidak ada data piutang ditemukan di file.'; msgEl.classList.remove('hidden'); }
            return;
        }
        // Preserve existing keterangan
        const existingMap = {};
        _prItems.forEach(it => { existingMap[it.noFaktur + it.customer] = it; });
        _prItems = mapped.map(it => {
            const old = existingMap[it.noFaktur + it.customer];
            if (old) it.keterangan = old.keterangan;
            return it;
        });
        if (msgEl) {
            msgEl.textContent = `✅ ${mapped.length} customer berhasil dimuat dari "${file.name}"`;
            msgEl.classList.remove('hidden');
        }
        prRender();
        savePr().catch(() => {});
    } catch (err) {
        if (msgEl) { msgEl.textContent = '❌ ' + err.message; msgEl.classList.remove('hidden'); }
    }
}

function initPrForm() {
    const fileInput = document.getElementById('prFileInput');
    fileInput?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        await prHandleFile(file);
        fileInput.value = '';
    });

    const dropzone = document.getElementById('prDropzone');
    if (dropzone) {
        dropzone.addEventListener('dragover',  (e) => { e.preventDefault(); dropzone.classList.add('border-blue-400'); });
        dropzone.addEventListener('dragleave', ()  => dropzone.classList.remove('border-blue-400'));
        dropzone.addEventListener('drop', async (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-blue-400');
            const file = e.dataTransfer.files[0];
            if (file) await prHandleFile(file);
        });
    }

    document.getElementById('prSaveBtn')?.addEventListener('click', () => {
        savePr().catch(err => showAlert(err.message || 'Gagal menyimpan.', 'error'));
    });
}

// ══════════════════════════════════════════════════════════
// ── Piutang CDN Module ──
// ══════════════════════════════════════════════════════════

let _pcdnItems = [];

async function loadPcdnTab() {
    if (!activePlanId) { pcdnRender(); return; }
    const res = await fetchJson(`/api/audit-detail/piutang-cdn?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    if (res.data && (res.data.piutang ?? []).length > 0) {
        _pcdnItems = res.data.piutang;
    }
    // If DB has no data but memory has data, keep memory (handles failed saves)
    pcdnRender();
}

function pcdnFmtRp(val) {
    const n = parseFloat(val) || 0;
    if (n === 0) return 'Rp 0';
    const abs = Math.abs(n);
    if (abs >= 1_000_000_000) return (n < 0 ? '-' : '') + 'Rp ' + (abs / 1_000_000_000).toFixed(1) + ' M';
    if (abs >= 1_000_000)     return (n < 0 ? '-' : '') + 'Rp ' + (abs / 1_000_000).toFixed(1) + ' Jt';
    if (abs >= 1_000)         return (n < 0 ? '-' : '') + 'Rp ' + (abs / 1_000).toFixed(1) + ' Rb';
    return 'Rp ' + n.toLocaleString('id-ID');
}

function pcdnFmtNum(val) {
    const n = parseFloat(val) || 0;
    return n === 0 ? '-' : n.toLocaleString('id-ID');
}

function pcdnRender() {
    const items = _pcdnItems;

    // Stat cards
    const totSaldo    = items.reduce((s, r) => s + (r.saldoPiutang || 0), 0);
    const totBelumJto = items.reduce((s, r) => s + (r.belumJto || 0), 0);
    const totTung15   = items.reduce((s, r) => s + (r.tung15 || 0), 0);
    const totTung630  = items.reduce((s, r) => s + (r.tung630 || 0), 0);
    const totTung3160 = items.reduce((s, r) => s + (r.tung3160 || 0), 0);
    const totTung60   = items.reduce((s, r) => s + (r.tung60 || 0), 0);

    const s = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    s('pcdnStatCustomer', items.length);
    s('pcdnStatSaldo',    pcdnFmtRp(totSaldo));
    s('pcdnStatBelumJto', pcdnFmtRp(totBelumJto));
    s('pcdnStatTung15',   pcdnFmtRp(totTung15));
    s('pcdnStatTung630',  pcdnFmtRp(totTung630));
    s('pcdnStatTung3160', pcdnFmtRp(totTung3160));
    s('pcdnStatTung60',   pcdnFmtRp(totTung60));

    const section = document.getElementById('pcdnTableSection');
    const count   = document.getElementById('pcdnTableCount');
    const tbody   = document.getElementById('pcdnTableBody');
    if (!tbody) return;

    if (items.length === 0) {
        if (section) section.classList.add('hidden');
        tbody.innerHTML = '';
        return;
    }

    if (section) section.classList.remove('hidden');
    if (count) count.textContent = `${items.length} Debitur`;

    const tung = (v) => {
        const n = parseFloat(v) || 0;
        if (n === 0) return `<span class="text-slate-500">-</span>`;
        return `<span class="font-semibold text-red-400">${n.toLocaleString('id-ID')}</span>`;
    };

    tbody.innerHTML = items.map((r, i) => `
        <tr class="hover:bg-slate-800/40 transition">
            <td class="px-3 py-2 text-slate-400">${i + 1}</td>
            <td class="px-3 py-2 font-mono text-blue-300">${r.noKontrak || '-'}</td>
            <td class="px-3 py-2 text-slate-300">${r.tanggal || '-'}</td>
            <td class="px-3 py-2 font-semibold text-slate-100">${r.customer}</td>
            <td class="px-3 py-2 text-right text-slate-200">${pcdnFmtNum(r.saldoPiutang)}</td>
            <td class="px-3 py-2 text-right text-green-400">${pcdnFmtNum(r.belumJto)}</td>
            <td class="px-3 py-2 text-right">${tung(r.tung15)}</td>
            <td class="px-3 py-2 text-right">${tung(r.tung630)}</td>
            <td class="px-3 py-2 text-right">${tung(r.tung3160)}</td>
            <td class="px-3 py-2 text-right">${tung(r.tung60)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${pcdnFmtNum(r.analisa0)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${pcdnFmtNum(r.analisa1)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${pcdnFmtNum(r.analisa2)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${pcdnFmtNum(r.analisa3)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${pcdnFmtNum(r.analisa4)}</td>
            <td class="px-3 py-2 text-right text-slate-400">${pcdnFmtNum(r.analisa5)}</td>
            <td class="px-3 py-2">
                <input type="text" value="${(r.keterangan || '').replace(/"/g, '&quot;')}"
                    data-pcdn-idx="${i}" placeholder="Keterangan... (opsional)"
                    class="w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none pcdn-ket-input">
            </td>
        </tr>`).join('');

    // Sync keterangan edits back to _pcdnItems and auto-save on blur
    tbody.querySelectorAll('.pcdn-ket-input').forEach(inp => {
        inp.addEventListener('input', (e) => {
            const i = parseInt(e.target.dataset.pcdnIdx, 10);
            if (_pcdnItems[i]) _pcdnItems[i].keterangan = e.target.value;
        });
        inp.addEventListener('blur', () => savePcdn().catch(() => {}));
    });
}

async function savePcdn() {
    if (!activePlanId) { showAlert('Pilih plan audit terlebih dahulu.', 'error'); return; }
    const res = await fetchJson('/api/audit-detail/piutang-cdn', {
        method:  'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body:    JSON.stringify({ planAuditId: activePlanId, piutang: _pcdnItems }),
    });
    if (!res.message) throw new Error('Gagal menyimpan.');
    showAlert(res.message, 'success');
}

async function pcdnHandleFile(file) {
    const msgEl = document.getElementById('pcdnImportMsg');
    try {
        if (msgEl) { msgEl.textContent = '⏳ Memproses file...'; msgEl.classList.remove('hidden'); }
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch('/api/audit-detail/piutang-cdn/parse-excel', {
            method: 'POST',
            headers: authHeaders(),
            body: fd,
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Gagal memproses file.');
        const raw = json.data ?? [];
        if (raw.length === 0) throw new Error('Tidak ada data piutang ditemukan dalam file.');
        // Preserve existing keterangan on re-import
        const existingMap = {};
        _pcdnItems.forEach(it => { existingMap[it.noKontrak + it.customer] = it; });
        _pcdnItems = raw.map(it => {
            const old = existingMap[it.noKontrak + it.customer];
            if (old) it.keterangan = old.keterangan;
            return it;
        });
        if (msgEl) {
            msgEl.textContent = `✅ ${raw.length} debitur berhasil dimuat dari "${file.name}"`;
            msgEl.classList.remove('hidden');
        }
        pcdnRender();
        savePcdn().catch(() => {});
    } catch (err) {
        if (msgEl) { msgEl.textContent = '❌ ' + err.message; msgEl.classList.remove('hidden'); }
    }
}

function initPcdnForm() {
    const fileInput = document.getElementById('pcdnFileInput');
    fileInput?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        await pcdnHandleFile(file);
        fileInput.value = '';
    });

    const dropzone = document.getElementById('pcdnDropzone');
    if (dropzone) {
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-blue-400'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-400'));
        dropzone.addEventListener('drop', async (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-blue-400');
            const file = e.dataTransfer.files[0];
            if (file) await pcdnHandleFile(file);
        });
    }

    document.getElementById('pcdnSaveBtn')?.addEventListener('click', () => {
        savePcdn().catch(err => showAlert(err.message || 'Gagal menyimpan.', 'error'));
    });
}

// ══════════════════════════════════════════════════════════
// ── TTP Gantung Module ──
// ══════════════════════════════════════════════════════════

let _ttpItems = [];

async function loadTtpTab() {
    if (!activePlanId) { ttpRender(); return; }
    const res = await fetchJson(`/api/audit-detail/ttp-gantung?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    if (res.data && (res.data.ttp ?? []).length > 0) {
        _ttpItems = res.data.ttp;
        const el = document.getElementById('ttpTglAudit');
        if (el && res.data.tglAudit) el.value = res.data.tglAudit;
    }
    ttpRender();
}

function ttpDiff(tglTtp) {
    if (!tglTtp) return null;
    const ref = document.getElementById('ttpTglAudit')?.value || new Date().toISOString().slice(0, 10);
    const d1  = new Date(tglTtp);
    const d2  = new Date(ref);
    if (isNaN(d1) || isNaN(d2)) return null;
    return Math.floor((d2 - d1) / 86400000);
}

function ttpFmtRp(val) {
    const n = parseFloat(val) || 0;
    if (n === 0) return 'Rp 0';
    const abs = Math.abs(n);
    if (abs >= 1_000_000_000) return (n < 0 ? '-' : '') + 'Rp ' + (abs / 1_000_000_000).toFixed(1) + ' M';
    if (abs >= 1_000_000)     return (n < 0 ? '-' : '') + 'Rp ' + (abs / 1_000_000).toFixed(1) + ' Jt';
    if (abs >= 1_000)         return (n < 0 ? '-' : '') + 'Rp ' + (abs / 1_000).toFixed(1) + ' Rb';
    return 'Rp ' + n.toLocaleString('id-ID');
}

function ttpFmtNum(val) {
    const n = parseFloat(val) || 0;
    return n === 0 ? '0' : n.toLocaleString('id-ID');
}

function ttpRender() {
    const items = _ttpItems;

    const totBelum = items.reduce((s, r) => s + (r.belumCair || 0), 0);
    const diffs    = items.map(r => ttpDiff(r.tglTtp)).filter(d => d !== null && d >= 0);
    const maxDiff  = diffs.length ? Math.max(...diffs) : 0;

    const s = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    s('ttpStatTotal', items.length);
    s('ttpStatBelum', ttpFmtRp(totBelum));
    s('ttpStatDiff',  maxDiff + ' hari');

    const section = document.getElementById('ttpTableSection');
    const count   = document.getElementById('ttpTableCount');
    const tbody   = document.getElementById('ttpTableBody');
    if (!tbody) return;

    if (items.length === 0) {
        if (section) section.classList.add('hidden');
        tbody.innerHTML = '';
        return;
    }

    if (section) section.classList.remove('hidden');
    if (count) count.textContent = `${items.length} Data`;

    let currentLeasing = '';
    let no = 0;
    const rows = [];

    items.forEach((r, idx) => {
        // Insert leasing group header row
        if (r.leasing !== currentLeasing) {
            currentLeasing = r.leasing;
            rows.push(`
                <tr class="bg-slate-700/60">
                    <td class="px-3 py-1.5"></td>
                    <td colspan="12" class="px-3 py-1.5 font-bold text-slate-200 uppercase tracking-wide">${currentLeasing}</td>
                </tr>`);
        }
        no++;
        const diff = ttpDiff(r.tglTtp);
        const diffTxt = diff === null ? '-' : diff + ' hr';
        const diffCls = diff === null ? 'text-slate-500'
                      : diff > 60    ? 'text-red-400 font-bold'
                      : diff > 30    ? 'text-orange-400 font-semibold'
                      : 'text-slate-300';
        const belumCls = r.belumCair > 0 ? 'text-orange-300 font-semibold' : 'text-slate-500';

        rows.push(`
            <tr class="hover:bg-slate-800/40 transition">
                <td class="px-3 py-2 text-slate-400">${no}</td>
                <td class="px-3 py-2 font-mono text-blue-300">${r.noTtp || '-'}</td>
                <td class="px-3 py-2 text-slate-300 whitespace-nowrap">${r.tglTtp || '-'}</td>
                <td class="px-3 py-2 font-mono text-xs text-slate-300">${r.noFaktur || '-'}</td>
                <td class="px-3 py-2 text-slate-100">${r.nama || '-'}</td>
                <td class="px-3 py-2 text-right text-slate-200">${ttpFmtNum(r.nilai)}</td>
                <td class="px-3 py-2 text-right ${r.sudahCair > 0 ? 'text-green-400' : 'text-slate-500'}">${ttpFmtNum(r.sudahCair)}</td>
                <td class="px-3 py-2 text-center text-slate-400">${r.pencTgl || '-'}</td>
                <td class="px-3 py-2 text-right ${r.pencNilai > 0 ? 'text-green-400' : 'text-slate-500'}">${ttpFmtNum(r.pencNilai)}</td>
                <td class="px-3 py-2 text-right ${belumCls}">${ttpFmtNum(r.belumCair)}</td>
                <td class="px-3 py-2 text-xs text-slate-400 max-w-[220px] whitespace-pre-wrap">${r.keterangan || '-'}</td>
                <td class="px-3 py-2 text-center ${diffCls}">${diffTxt}</td>
                <td class="px-3 py-2 text-center">
                    <input type="checkbox" data-ttp-idx="${idx}" ${r.fisik ? 'checked' : ''}
                        class="ttp-fisik-cb h-4 w-4 cursor-pointer accent-blue-500">
                </td>
            </tr>`);
    });

    tbody.innerHTML = rows.join('');

    // Fisik checkbox sync + auto-save
    tbody.querySelectorAll('.ttp-fisik-cb').forEach(cb => {
        cb.addEventListener('change', (e) => {
            const i = parseInt(e.target.dataset.ttpIdx, 10);
            if (_ttpItems[i]) _ttpItems[i].fisik = e.target.checked;
            saveTtp().catch(() => {});
        });
    });
}

async function saveTtp() {
    if (!activePlanId) { showAlert('Pilih plan audit terlebih dahulu.', 'error'); return; }
    const tglAudit = document.getElementById('ttpTglAudit')?.value || null;
    const res = await fetchJson('/api/audit-detail/ttp-gantung', {
        method:  'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body:    JSON.stringify({ planAuditId: activePlanId, tglAudit, ttp: _ttpItems }),
    });
    if (!res.message) throw new Error('Gagal menyimpan.');
    showAlert(res.message, 'success');
}

async function ttpHandleFile(file) {
    const msgEl = document.getElementById('ttpImportMsg');
    try {
        if (msgEl) { msgEl.textContent = '⏳ Memproses file...'; msgEl.classList.remove('hidden'); }
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch('/api/audit-detail/ttp-gantung/parse-html', {
            method: 'POST',
            headers: authHeaders(),
            body: fd,
        });
        const json = await res.json();
        if (!res.ok) throw new Error(json.message || 'Gagal memproses file.');
        const raw = json.data ?? [];
        if (raw.length === 0) throw new Error('Tidak ada data TTP ditemukan dalam file.');
        // Preserve existing fisik & keterangan on re-import
        const existingMap = {};
        _ttpItems.forEach(it => { existingMap[it.noTtp] = it; });
        _ttpItems = raw.map(it => {
            const old = existingMap[it.noTtp];
            if (old) { it.fisik = old.fisik; it.keterangan = it.keterangan || old.keterangan; }
            return it;
        });
        if (msgEl) {
            msgEl.textContent = `✅ ${raw.length} data dipulihkan dari "${file.name}"`;
            msgEl.classList.remove('hidden');
        }
        ttpRender();
        saveTtp().catch(() => {});
    } catch (err) {
        if (msgEl) { msgEl.textContent = '❌ ' + err.message; msgEl.classList.remove('hidden'); }
    }
}

function initTtpForm() {
    const fileInput = document.getElementById('ttpFileInput');
    fileInput?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        await ttpHandleFile(file);
        fileInput.value = '';
    });

    const dropzone = document.getElementById('ttpDropzone');
    if (dropzone) {
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-blue-400'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-400'));
        dropzone.addEventListener('drop', async (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-blue-400');
            const file = e.dataTransfer.files[0];
            if (file) await ttpHandleFile(file);
        });
    }

    // Re-render when tgl audit changes (diff recalculates)
    document.getElementById('ttpTglAudit')?.addEventListener('change', () => {
        ttpRender();
        saveTtp().catch(() => {});
    });

    document.getElementById('ttpSaveBtn')?.addEventListener('click', () => {
        saveTtp().catch(err => showAlert(err.message || 'Gagal menyimpan.', 'error'));
    });
}


// ══════════════════════════════════════════════════════════
// ── Cek Fisik Module ──
// ══════════════════════════════════════════════════════════

let _cfData = null;

function cfEmptyData() {
    return {
        company: '', tglPemeriksaan: '',
        saldoAwal: { tanggal: '', cf: 0, stuj: 0, fstnk: 0 },
        penerimaan: [],
        pengeluaran: [],
        saldoAkhir: { cf: 0, stuj: 0, fstnk: 0 },
        fisik: { cf: 0, stuj: 0, fstnk: 0 },
        selisih: { cf: 0, stuj: 0, fstnk: 0 },
    };
}

async function loadCfTab() {
    if (!activePlanId) { cfInitForm(); return; }
    const res = await fetchJson(`/api/audit-detail/cek-fisik?plan_audit_id=${activePlanId}`,
        { headers: authHeaders() });
    // res.data = toAktaArray() = { id, planAuditId, data: {...}, updatedAt }
    // res.data.data = the stored _cfData object
    if (res.data && res.data.data && !Array.isArray(res.data.data)) {
        _cfData = { ...cfEmptyData(), ...res.data.data };
    }
    cfInitForm();
}

function cfN(v) { return parseFloat(v) || 0; }

function cfCalcAndRefresh() {
    if (!_cfData) return;
    const sa = _cfData.saldoAwal;
    let totCf = cfN(sa.cf), totStuj = cfN(sa.stuj), totFstnk = cfN(sa.fstnk);
    (_cfData.penerimaan || []).forEach(r => { totCf += cfN(r.cf); totStuj += cfN(r.stuj); totFstnk += cfN(r.fstnk); });
    (_cfData.pengeluaran || []).forEach(r => { totCf -= cfN(r.cf); totStuj -= cfN(r.stuj); totFstnk -= cfN(r.fstnk); });
    _cfData.saldoAkhir = { cf: totCf, stuj: totStuj, fstnk: totFstnk };
    _cfData.selisih = {
        cf:    totCf    - cfN(_cfData.fisik?.cf),
        stuj:  totStuj  - cfN(_cfData.fisik?.stuj),
        fstnk: totFstnk - cfN(_cfData.fisik?.fstnk),
    };

    // Update stat cards
    const s = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    s('cfStatCfAwal',    cfN(sa.cf));   s('cfStatCfAkhir',   totCf);   cfColorStat('cfStatCfSelisih',   _cfData.selisih.cf);
    s('cfStatStujAwal',  cfN(sa.stuj)); s('cfStatStujAkhir', totStuj); cfColorStat('cfStatStujSelisih',  _cfData.selisih.stuj);
    s('cfStatFstnkAwal', cfN(sa.fstnk));s('cfStatFstnkAkhir',totFstnk);cfColorStat('cfStatFstnkSelisih',_cfData.selisih.fstnk);

    // Update ringkasan cells without re-rendering DOM (prevents scroll jump)
    const cls = v => v === 0 ? 'text-green-400 font-bold' : 'text-red-400 font-bold';
    const updateTd = (id, v) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = cfN(v);
        el.className = `px-4 py-2 text-right ${cls(v)}`;
    };
    s('cfRingkasanAkhirCf',    cfN(totCf));
    s('cfRingkasanAkhirStuj',  cfN(totStuj));
    s('cfRingkasanAkhirFstnk', cfN(totFstnk));
    updateTd('cfSelisihCf',    _cfData.selisih.cf);
    updateTd('cfSelisihStuj',  _cfData.selisih.stuj);
    updateTd('cfSelisihFstnk', _cfData.selisih.fstnk);
}

function cfColorStat(id, v) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = v;
    el.className = el.className.replace(/text-(green|red|slate)-\d+/g, '') +
        (v === 0 ? ' text-green-400' : ' text-red-400 font-bold');
}

function cfInitForm() {
    if (!_cfData) _cfData = cfEmptyData();

    // Fill saldo awal
    const saFields = {
        cfSaldoAwalTgl:   _cfData.saldoAwal?.tanggal || '',
        cfSaldoAwalCf:    cfN(_cfData.saldoAwal?.cf),
        cfSaldoAwalStuj:  cfN(_cfData.saldoAwal?.stuj),
        cfSaldoAwalFstnk: cfN(_cfData.saldoAwal?.fstnk),
    };
    Object.entries(saFields).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.value = val;
    });

    cfRenderPenerimaan();
    cfRenderPengeluaran();
    cfRenderRingkasan();
    cfCalcAndRefresh();
}

function cfSyncSaldoAwal() {
    if (!_cfData) return;
    _cfData.saldoAwal = {
        tanggal: document.getElementById('cfSaldoAwalTgl')?.value || '',
        cf:      cfN(document.getElementById('cfSaldoAwalCf')?.value),
        stuj:    cfN(document.getElementById('cfSaldoAwalStuj')?.value),
        fstnk:   cfN(document.getElementById('cfSaldoAwalFstnk')?.value),
    };
}

function cfRenderPenerimaan() {
    const tbody = document.getElementById('cfPenerimaanBody');
    if (!tbody) return;
    const rows = _cfData?.penerimaan || [];
    tbody.innerHTML = rows.length === 0
        ? `<tr><td colspan="6" class="px-4 py-3 text-center text-xs text-slate-500">Belum ada data penerimaan. Klik "+ Tambah Baris".</td></tr>`
        : rows.map((r, i) => `
        <tr class="border-b border-slate-800/60 hover:bg-slate-800/20" data-cf-p-idx="${i}">
            <td class="px-3 py-2"><input type="date" value="${r.tanggal||''}"
                class="cf-p-tgl rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none w-36"></td>
            <td class="px-3 py-2"><input type="text" value="${(r.noDokumen||'').replace(/"/g,'&quot;')}" placeholder="No. Dokumen"
                class="cf-p-nodok w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none"></td>
            <td class="px-3 py-2"><input type="number" value="${cfN(r.cf)}" min="0"
                class="cf-p-cf w-20 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc"></td>
            <td class="px-3 py-2"><input type="number" value="${cfN(r.stuj)}" min="0"
                class="cf-p-stuj w-20 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc"></td>
            <td class="px-3 py-2"><input type="number" value="${cfN(r.fstnk)}" min="0"
                class="cf-p-fstnk w-20 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc"></td>
            <td class="px-3 py-2 text-center"><button type="button" data-del-p="${i}" class="text-red-400 hover:text-red-300 text-base leading-none">✕</button></td>
        </tr>`).join('');

    tbody.querySelectorAll('tr[data-cf-p-idx]').forEach(tr => {
        const idx = parseInt(tr.dataset.cfPIdx);
        const syncRow = () => {
            if (!_cfData.penerimaan[idx]) return;
            _cfData.penerimaan[idx].tanggal   = tr.querySelector('.cf-p-tgl')?.value || '';
            _cfData.penerimaan[idx].noDokumen = tr.querySelector('.cf-p-nodok')?.value || '';
            _cfData.penerimaan[idx].cf        = cfN(tr.querySelector('.cf-p-cf')?.value);
            _cfData.penerimaan[idx].stuj      = cfN(tr.querySelector('.cf-p-stuj')?.value);
            _cfData.penerimaan[idx].fstnk     = cfN(tr.querySelector('.cf-p-fstnk')?.value);
        };
        tr.querySelectorAll('input').forEach(inp => {
            inp.addEventListener('input', () => { syncRow(); cfCalcAndRefresh(); });
            inp.addEventListener('blur',  () => _doSaveCf().catch(() => {}));
        });
        tr.querySelector('[data-del-p]')?.addEventListener('click', () => {
            _cfData.penerimaan.splice(idx, 1);
            cfRenderPenerimaan();
            cfCalcAndRefresh();
            _doSaveCf().catch(() => {});
        });
    });
}

function cfRenderPengeluaran() {
    const tbody = document.getElementById('cfPengeluaranBody');
    if (!tbody) return;
    const rows = _cfData?.pengeluaran || [];
    tbody.innerHTML = rows.length === 0
        ? `<tr><td colspan="5" class="px-4 py-3 text-center text-xs text-slate-500">Belum ada data pengeluaran. Klik "+ Tambah Baris".</td></tr>`
        : rows.map((r, i) => `
        <tr class="border-b border-slate-800/60 hover:bg-slate-800/20" data-cf-k-idx="${i}">
            <td class="px-3 py-2"><input type="text" value="${(r.noDokumen||'').replace(/"/g,'&quot;')}" placeholder="No. Dokumen"
                class="cf-k-nodok w-full rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none"></td>
            <td class="px-3 py-2"><input type="number" value="${cfN(r.cf)}" min="0"
                class="cf-k-cf w-20 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc"></td>
            <td class="px-3 py-2"><input type="number" value="${cfN(r.stuj)}" min="0"
                class="cf-k-stuj w-20 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc"></td>
            <td class="px-3 py-2"><input type="number" value="${cfN(r.fstnk)}" min="0"
                class="cf-k-fstnk w-20 rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-right text-slate-100 focus:border-blue-500 focus:outline-none cf-auto-calc"></td>
            <td class="px-3 py-2 text-center"><button type="button" data-del-k="${i}" class="text-red-400 hover:text-red-300 text-base leading-none">✕</button></td>
        </tr>`).join('');

    tbody.querySelectorAll('tr[data-cf-k-idx]').forEach(tr => {
        const idx = parseInt(tr.dataset.cfKIdx);
        const syncRow = () => {
            if (!_cfData.pengeluaran[idx]) return;
            _cfData.pengeluaran[idx].noDokumen = tr.querySelector('.cf-k-nodok')?.value || '';
            _cfData.pengeluaran[idx].cf        = cfN(tr.querySelector('.cf-k-cf')?.value);
            _cfData.pengeluaran[idx].stuj      = cfN(tr.querySelector('.cf-k-stuj')?.value);
            _cfData.pengeluaran[idx].fstnk     = cfN(tr.querySelector('.cf-k-fstnk')?.value);
        };
        tr.querySelectorAll('input').forEach(inp => {
            inp.addEventListener('input', () => { syncRow(); cfCalcAndRefresh(); });
            inp.addEventListener('blur',  () => _doSaveCf().catch(() => {}));
        });
        tr.querySelector('[data-del-k]')?.addEventListener('click', () => {
            _cfData.pengeluaran.splice(idx, 1);
            cfRenderPengeluaran();
            cfCalcAndRefresh();
            _doSaveCf().catch(() => {});
        });
    });
}

function cfRenderRingkasan() {
    const tbody = document.getElementById('cfRingkasanBody');
    if (!tbody) return;
    const ak = _cfData?.saldoAkhir || { cf: 0, stuj: 0, fstnk: 0 };
    const fi = _cfData?.fisik      || { cf: 0, stuj: 0, fstnk: 0 };
    const sl = _cfData?.selisih    || { cf: 0, stuj: 0, fstnk: 0 };
    const cls = v => v === 0 ? 'text-green-400 font-bold' : 'text-red-400 font-bold';
    tbody.innerHTML = `
        <tr class="border-b border-slate-800/60">
            <td class="px-4 py-2 text-slate-300">Saldo Akhir (Sistem)</td>
            <td id="cfRingkasanAkhirCf"    class="px-4 py-2 text-right font-semibold text-blue-400">${cfN(ak.cf)}</td>
            <td id="cfRingkasanAkhirStuj"  class="px-4 py-2 text-right font-semibold text-blue-400">${cfN(ak.stuj)}</td>
            <td id="cfRingkasanAkhirFstnk" class="px-4 py-2 text-right font-semibold text-blue-400">${cfN(ak.fstnk)}</td>
        </tr>
        <tr class="border-b border-slate-800/60 bg-slate-800/30">
            <td class="px-4 py-2 font-semibold text-slate-200">Fisik (Hasil Pemeriksaan)</td>
            <td class="px-4 py-2 text-right"><input type="number" id="cfFisikCf" value="${cfN(fi.cf)}" min="0"
                class="w-24 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-right text-sm text-slate-100 focus:border-blue-500 focus:outline-none cf-fisik-inp"></td>
            <td class="px-4 py-2 text-right"><input type="number" id="cfFisikStuj" value="${cfN(fi.stuj)}" min="0"
                class="w-24 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-right text-sm text-slate-100 focus:border-blue-500 focus:outline-none cf-fisik-inp"></td>
            <td class="px-4 py-2 text-right"><input type="number" id="cfFisikFstnk" value="${cfN(fi.fstnk)}" min="0"
                class="w-24 rounded border border-slate-600 bg-slate-900 px-2 py-1 text-right text-sm text-slate-100 focus:border-blue-500 focus:outline-none cf-fisik-inp"></td>
        </tr>
        <tr>
            <td class="px-4 py-2 font-bold text-slate-100">Selisih</td>
            <td id="cfSelisihCf"    class="px-4 py-2 text-right ${cls(sl.cf)}">${cfN(sl.cf)}</td>
            <td id="cfSelisihStuj"  class="px-4 py-2 text-right ${cls(sl.stuj)}">${cfN(sl.stuj)}</td>
            <td id="cfSelisihFstnk" class="px-4 py-2 text-right ${cls(sl.fstnk)}">${cfN(sl.fstnk)}</td>
        </tr>`;

    tbody.querySelectorAll('.cf-fisik-inp').forEach(inp => {
        inp.addEventListener('input', () => {
            if (!_cfData) return;
            _cfData.fisik = {
                cf:    cfN(document.getElementById('cfFisikCf')?.value),
                stuj:  cfN(document.getElementById('cfFisikStuj')?.value),
                fstnk: cfN(document.getElementById('cfFisikFstnk')?.value),
            };
            cfCalcAndRefresh();
        });
        inp.addEventListener('blur', () => _doSaveCf().catch(() => {}));
    });
}

async function _doSaveCf() {
    if (!activePlanId) throw new Error('Pilih plan audit terlebih dahulu.');
    if (!_cfData) _cfData = cfEmptyData();
    cfSyncSaldoAwal();
    return await fetchJson('/api/audit-detail/cek-fisik', {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ planAuditId: activePlanId, data: _cfData }),
    });
}

async function saveCf() {
    const res = await _doSaveCf();
    showAlert(res.message, 'success');
}

function initCfForm() {
    // Saldo awal inputs → recalc live
    ['cfSaldoAwalCf','cfSaldoAwalStuj','cfSaldoAwalFstnk','cfSaldoAwalTgl'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { cfSyncSaldoAwal(); cfCalcAndRefresh(); });
        document.getElementById(id)?.addEventListener('blur',  () => _doSaveCf().catch(() => {}));
    });

    document.getElementById('cfAddPenerimaan')?.addEventListener('click', () => {
        if (!_cfData) _cfData = cfEmptyData();
        _cfData.penerimaan.push({ tanggal: '', noDokumen: '', cf: 0, stuj: 0, fstnk: 0 });
        cfRenderPenerimaan();
    });

    document.getElementById('cfAddPengeluaran')?.addEventListener('click', () => {
        if (!_cfData) _cfData = cfEmptyData();
        _cfData.pengeluaran.push({ noDokumen: '', cf: 0, stuj: 0, fstnk: 0 });
        cfRenderPengeluaran();
    });

    document.getElementById('cfSaveBtn')?.addEventListener('click', () => {
        saveCf().catch(err => showAlert(err.message || 'Gagal menyimpan.', 'error'));
    });
}

// ══════════════════════════════════════════════════════════════════
// MT — Pemeriksaan MT (Lama / FI / Baru)
// ══════════════════════════════════════════════════════════════════

let _mtData           = null;
let _mtToolsCache     = {};
let _mtActiveMekanik  = null; // currently selected mechanic name

const MT_KATEGORI = ['bagus', 'rusak', 'skAudit', 'hilang'];
const MT_LABEL    = { bagus: 'Bagus', rusak: 'Rusak', skAudit: 'SK Audit', hilang: 'Hilang' };
const MT_COLOR    = { bagus: 'emerald', rusak: 'red', skAudit: 'blue', hilang: 'orange' };

function mtEmptyData() { return { entries: [], mekanikSelectedJenis: {} }; }
function mtActiveJenis()   { return document.querySelector('.mt-jenis-btn.active')?.dataset.mtJenis || 'baru'; }
function mtActiveMekanik() { return _mtActiveMekanik; }

// Returns unique mechanic names from entries
function mtMekanikList() {
    if (!_mtData?.entries) return [];
    return [...new Set(_mtData.entries.map(e => e.mekanik).filter(Boolean))];
}

function mtSelectMekanik(name) {
    _mtActiveMekanik = name;
    const label = document.getElementById('mtActiveMekanikLabel');
    if (label) label.textContent = name || '';
    const jenisPanel = document.getElementById('mtJenisPanel');
    if (jenisPanel) jenisPanel.classList.toggle('hidden', !name);
    // Restore saved jenis for this mechanic
    if (name) {
        const savedJenis = _mtData?.mekanikSelectedJenis?.[name] || 'baru';
        document.querySelectorAll('.mt-jenis-btn').forEach(b => {
            const isActive = b.dataset.mtJenis === savedJenis;
            b.classList.toggle('active', isActive);
            b.classList.toggle('bg-blue-600', isActive);
            b.classList.toggle('text-white', isActive);
            b.classList.toggle('border-blue-600', isActive);
            b.classList.toggle('text-slate-300', !isActive);
        });
    }
    mtRenderMekanikList();
    mtRenderKategori();
}

function mtRenderMekanikList() {
    const list = document.getElementById('mtMekanikList');
    if (!list) return;
    const mechanics = mtMekanikList();
    if (mechanics.length === 0) {
        list.innerHTML = `<p class="text-xs text-slate-500 italic">Belum ada mekanik. Klik "+ Tambah Mekanik".</p>`;
        return;
    }
    list.innerHTML = mechanics.map(name => {
        const isActive = name === _mtActiveMekanik;
        return `<div class="mt-mekanik-chip inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold cursor-pointer transition
            ${isActive ? 'bg-blue-600 text-white border border-blue-500' : 'bg-slate-800 text-slate-300 border border-slate-600 hover:border-blue-500 hover:text-blue-300'}"
            data-mekanik="${escHtml(name)}">
            ${escHtml(name)}
            <button class="mt-mekanik-del ml-1 text-xs opacity-60 hover:opacity-100 leading-none" data-mekanik="${escHtml(name)}" title="Hapus mekanik ini">✕</button>
        </div>`;
    }).join('');

    list.querySelectorAll('.mt-mekanik-chip').forEach(chip => {
        chip.addEventListener('click', (e) => {
            if (e.target.classList.contains('mt-mekanik-del')) return;
            mtSelectMekanik(chip.dataset.mekanik);
        });
    });
    list.querySelectorAll('.mt-mekanik-del').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const name = btn.dataset.mekanik;
            if (!confirm(`Hapus semua data mekanik "${name}"?`)) return;
            _mtData.entries = (_mtData.entries || []).filter(e => e.mekanik !== name);
            if (_mtActiveMekanik === name) {
                const remaining = mtMekanikList();
                mtSelectMekanik(remaining[0] || null);
            } else {
                mtRenderMekanikList();
            }
            _doSaveMt().catch(() => {});
        });
    });
}

function mtGetEntry(mekanik, jenis) {
    if (!_mtData) _mtData = mtEmptyData();
    let e = (_mtData.entries || []).find(e => e.mekanik === mekanik && e.jenis === jenis);
    if (!e) {
        e = { mekanik, jenis, bagus: [], rusak: [], skAudit: [], hilang: [] };
        (_mtData.entries = _mtData.entries || []).push(e);
    }
    return e;
}

// Semua tools yg sudah ada di kategori manapun (untuk exclude dari dropdown)
function mtUsedTools(entry) {
    const used = new Set();
    MT_KATEGORI.forEach(k => (entry[k] || []).forEach(t => used.add(t)));
    return used;
}

async function mtLoadTools(jenis) {
    if (_mtToolsCache[jenis]) return _mtToolsCache[jenis];
    const res = await fetchJson(`/api/audit-detail/mt/tools?jenis=${jenis}`, { headers: authHeaders() });
    _mtToolsCache[jenis] = (res.data || []).map(t => t.nama || t.namaSingkat || '').filter(Boolean);
    return _mtToolsCache[jenis];
}

async function loadMtTab() {
    _mtActiveMekanik = null;
    if (!activePlanId) { mtInitForm(); return; }
    const res = await fetchJson(`/api/audit-detail/mt?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    if (res.data && res.data.data && !Array.isArray(res.data.data)) {
        _mtData = { ...mtEmptyData(), ...res.data.data };
    }
    mtInitForm();
}

function mtInitForm() {
    if (!_mtData) _mtData = mtEmptyData();
    // Auto-select first jenis button if none active
    if (!document.querySelector('.mt-jenis-btn.active')) {
        const first = document.querySelector('.mt-jenis-btn');
        if (first) first.classList.add('active', 'bg-blue-600', 'text-white', 'border-blue-600');
    }
    // Auto-select first mechanic if none selected
    const mechanics = mtMekanikList();
    if (!_mtActiveMekanik && mechanics.length > 0) {
        _mtActiveMekanik = mechanics[0];
    }
    mtRenderMekanikList();
    const jenisPanel = document.getElementById('mtJenisPanel');
    if (jenisPanel) jenisPanel.classList.toggle('hidden', !_mtActiveMekanik);
    const label = document.getElementById('mtActiveMekanikLabel');
    if (label && _mtActiveMekanik) label.textContent = _mtActiveMekanik;
    mtRenderKategori();
}

async function mtAutoLoadTools() {
    const mekanik = mtActiveMekanik();
    const jenis   = mtActiveJenis();
    if (!mekanik) return;
    const entry = mtGetEntry(mekanik, jenis);
    const isEmpty = MT_KATEGORI.every(k => (entry[k] || []).length === 0);
    if (!isEmpty) return;
    const tools = await mtLoadTools(jenis);
    if (!tools.length) return;
    entry.bagus = [...tools];
    mtRenderKategori();
    _doSaveMt().catch(() => {});
}

function mtRenderKategori() {
    const wrap = document.getElementById('mtKategoriWrap');
    if (!wrap) return;
    const mekanik = mtActiveMekanik();
    const jenis   = mtActiveJenis();

    if (!mekanik) {
        wrap.innerHTML = `<p class="text-sm text-slate-500 text-center py-4">Isi nama mekanik terlebih dahulu.</p>`;
        return;
    }

    const entry   = mtGetEntry(mekanik, jenis);
    const allTools = _mtToolsCache[jenis] || [];
    const used    = mtUsedTools(entry);

    wrap.innerHTML = MT_KATEGORI.map(kat => {
        const color = MT_COLOR[kat];
        const items = entry[kat] || [];

        // Dropdown: tools NOT used anywhere (for bagus = all unused, for others = tools in bagus)
        // For Rusak/SK Audit/Hilang: can only pick from Bagus list
        // For Bagus: can pick from all unused tools
        const available = kat === 'bagus'
            ? allTools.filter(t => !used.has(t))
            : (entry.bagus || []);   // move from bagus only

        const chips = items.map((nama, i) => `
            <span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-800 px-3 py-1 text-xs text-slate-200">
                ${escapeHtml(nama)}
                <button type="button" data-mt-rm-kat="${kat}" data-mt-rm-idx="${i}"
                    class="ml-1 text-slate-400 hover:text-red-400 leading-none text-sm font-bold">×</button>
            </span>`).join('');

        const opts = available.map(t =>
            `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('');

        return `
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-4 space-y-3">
            <span class="text-sm font-semibold text-${color}-400">${MT_LABEL[kat]}
                <span class="text-slate-400 font-normal text-xs">: ${items.length}</span>
            </span>
            <select id="mtSel-${kat}"
                class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-${color}-500 focus:outline-none">
                <option value="">-- Pilih tool --</option>
                ${opts}
            </select>
            <div class="flex flex-wrap gap-2 min-h-6">
                ${chips || `<span class="text-xs text-slate-500 italic">Belum ada item.</span>`}
            </div>
        </div>`;
    }).join('');

    // Wire events
    MT_KATEGORI.forEach(kat => {
        document.getElementById(`mtSel-${kat}`)?.addEventListener('change', function() {
            const val = this.value;
            if (!val) return;
            const e2 = mtGetEntry(mekanik, jenis);
            MT_KATEGORI.forEach(k => {
                if (k !== kat) e2[k] = (e2[k] || []).filter(t => t !== val);
            });
            if (!(e2[kat] || []).includes(val)) {
                e2[kat] = [...(e2[kat] || []), val];
            }
            mtRenderKategori();
            _doSaveMt().catch(() => {});
        });

        // Remove chip → move back to Bagus
        wrap.querySelectorAll(`[data-mt-rm-kat="${kat}"]`).forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.mtRmIdx);
                const e2  = mtGetEntry(mekanik, jenis);
                const val = (e2[kat] || [])[idx];
                e2[kat].splice(idx, 1);
                // Move back to bagus if it's from the DB tools list
                if (val && kat !== 'bagus' && (_mtToolsCache[jenis] || []).includes(val)) {
                    e2.bagus = [...(e2.bagus || []), val];
                }
                mtRenderKategori();
                _doSaveMt().catch(() => {});
            });
        });
    });

    mtAutoLoadTools().catch(() => {});
}

async function _doSaveMt() {
    if (!activePlanId) throw new Error('Pilih plan audit terlebih dahulu.');
    if (!_mtData) _mtData = mtEmptyData();
    return await fetchJson('/api/audit-detail/mt', {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ planAuditId: activePlanId, data: _mtData }),
    });
}

async function saveMt() {
    const res = await _doSaveMt();
    showAlert(res.message, 'success');
}

function initMtForm() {
    document.querySelectorAll('.mt-jenis-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mt-jenis-btn').forEach(b => {
                b.classList.remove('active', 'bg-blue-600', 'text-white', 'border-blue-600');
                b.classList.add('text-slate-300');
            });
            btn.classList.add('active', 'bg-blue-600', 'text-white', 'border-blue-600');
            btn.classList.remove('text-slate-300');
            // Save selected jenis for current mechanic
            if (_mtActiveMekanik && _mtData) {
                if (!_mtData.mekanikSelectedJenis) _mtData.mekanikSelectedJenis = {};
                _mtData.mekanikSelectedJenis[_mtActiveMekanik] = btn.dataset.mtJenis;
            }
            mtRenderKategori();
        });
    });

    // Add mechanic flow
    const addBtn     = document.getElementById('mtAddMekanikBtn');
    const addForm    = document.getElementById('mtAddForm');
    const confirmBtn = document.getElementById('mtConfirmAddBtn');
    const cancelBtn  = document.getElementById('mtCancelAddBtn');
    const nameInput  = document.getElementById('mtNewMekanikInput');

    addBtn?.addEventListener('click', () => {
        addForm?.classList.remove('hidden');
        nameInput?.focus();
    });
    cancelBtn?.addEventListener('click', () => {
        addForm?.classList.add('hidden');
        if (nameInput) nameInput.value = '';
    });
    const confirmAdd = () => {
        const name = nameInput?.value.trim();
        if (!name) { showAlert('Nama mekanik tidak boleh kosong.', 'error'); return; }
        if (mtMekanikList().includes(name)) { showAlert('Mekanik dengan nama ini sudah ada.', 'error'); return; }
        // Create a default entry so the mechanic appears in the list
        if (!_mtData) _mtData = mtEmptyData();
        _mtData.entries.push({ mekanik: name, jenis: 'baru', bagus: [], rusak: [], skAudit: [], hilang: [] });
        addForm?.classList.add('hidden');
        if (nameInput) nameInput.value = '';
        mtSelectMekanik(name);
        // Set jenis to baru
        document.querySelectorAll('.mt-jenis-btn').forEach(b => {
            b.classList.remove('active', 'bg-blue-600', 'text-white', 'border-blue-600');
            b.classList.add('text-slate-300');
        });
        const baruBtn = document.querySelector('.mt-jenis-btn[data-mt-jenis="baru"]');
        if (baruBtn) { baruBtn.classList.add('active', 'bg-blue-600', 'text-white', 'border-blue-600'); baruBtn.classList.remove('text-slate-300'); }
        mtAutoLoadTools();
        _doSaveMt().catch(() => {});
    };
    confirmBtn?.addEventListener('click', confirmAdd);
    nameInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') confirmAdd(); });

    document.getElementById('mtSaveBtn')?.addEventListener('click', () => {
        saveMt().catch(err => showAlert(err.message || 'Gagal menyimpan.', 'error'));
    });
}

/* ============================================================
   HGP & AHM Oils
   ============================================================ */
let _hgpData = null; // { items: [ { sparepart, saldoAkhir, fisik, akhir, selisih, keterangan, tgl, logScan:[] } ] }

function hgpEmptyData() { return { items: [] }; }

function hgpN(v) {
    if (v === null || v === undefined || v === '') return 0;
    return parseFloat(v) || 0;
}

// Saldo baseline = saldo akhir sistem (fallback ke field lama saldoAwal)
function hgpSaldo(item) {
    return hgpN(item?.saldoAkhir ?? item?.saldoAwal);
}

function hgpCalcItem(item) {
    const fisik   = hgpN(item.fisik);
    const wo      = hgpN(item.wo);           // WO menambah fisik
    const total   = fisik + wo;
    const saldo   = hgpSaldo(item);
    item.akhir    = saldo - total;           // Akhir = saldo - (fisik + wo)
    item.selisih  = total - saldo;           // Selisih = (fisik + wo) - saldo
}

function hgpUpdateStats() {
    const items   = _hgpData?.items || [];
    const total   = items.length;
    const selisih = items.filter(it => it.selisih !== 0).length;
    const scan    = items.reduce((s, it) => s + (it.logScan?.length || 0), 0);
    const el = id => document.getElementById(id);
    if (el('hgpStatTotal'))   el('hgpStatTotal').textContent   = total;
    if (el('hgpStatSelisih')) el('hgpStatSelisih').textContent = selisih;
    if (el('hgpStatScan'))    el('hgpStatScan').textContent    = scan;
    if (el('hgpTableCount'))  el('hgpTableCount').textContent  = `${total} Item`;
}

function hgpRenderItems() {
    const tbody = document.getElementById('hgpTableBody');
    if (!tbody) return;
    const items = _hgpData?.items || [];
    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="12" class="px-4 py-8 text-center text-slate-400 text-xs">Belum ada data — import file Excel terlebih dahulu.</td></tr>`;
        hgpUpdateStats();
        return;
    }
    tbody.innerHTML = items.map((it, i) => {
        const selisih  = hgpN(it.selisih);
        const selClass = selisih < 0 ? 'text-red-400 font-bold' : selisih > 0 ? 'text-yellow-400 font-bold' : 'text-slate-300';
        const scan     = it.logScan?.length || 0;
        const selSign  = selisih >= 0 ? '+' : '';
        const harga    = hgpN(it.hargaHet);
        const jumlah   = harga * selisih;   // negatif = kekurangan, positif = kelebihan
        const jumlahFmt = jumlah === 0 ? '-' : (jumlah >= 0 ? '+' : '') + Math.round(jumlah).toLocaleString('id-ID');
        const jumlahClass = jumlah < 0 ? 'text-red-400 font-bold' : jumlah > 0 ? 'text-yellow-400 font-bold' : 'text-slate-400';
        const wo = hgpN(it.wo);
        return `<tr class="hover:bg-slate-800/40">
            <td class="px-3 py-2 text-slate-400">${i + 1}</td>
            <td class="px-3 py-2 text-slate-400 text-xs">${it.noPart || ''}</td>
            <td class="px-3 py-2 text-slate-100 font-medium">${it.sparepart || ''}</td>
            <td class="px-3 py-2 text-center text-slate-300">${it.tgl || '<span class="text-slate-600">—</span>'}</td>
            <td class="px-3 py-2 text-right text-slate-300">${hgpSaldo(it)}</td>
            <td class="px-3 py-2 text-right text-slate-100 font-semibold">${hgpN(it.fisik)}</td>
            <td class="px-3 py-2 text-right">
                <input type="number" min="0" data-hgp-i="${i}" data-hgp-f="wo"
                    value="${wo || ''}" placeholder="0"
                    class="hgp-inp w-16 rounded border border-amber-700/50 bg-amber-900/20 px-2 py-1 text-xs text-amber-300 text-right focus:border-amber-500 focus:outline-none">
            </td>
            <td class="px-3 py-2 text-right text-slate-300">${hgpN(it.akhir)}</td>
            <td class="px-3 py-2 text-right ${selClass}">${selSign}${selisih}</td>
            <td class="px-3 py-2 text-right text-slate-300">${harga > 0 ? harga.toLocaleString('id-ID') : '<span class="text-slate-600">—</span>'}</td>
            <td class="px-3 py-2 text-right ${jumlahClass}">${jumlahFmt}</td>
            <td class="px-3 py-2">
                <input type="text" data-hgp-i="${i}" data-hgp-f="keterangan"
                    value="${it.keterangan || ''}"
                    placeholder="Keterangan..."
                    class="hgp-inp w-full rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
            </td>
            <td class="px-3 py-2 text-xs">
                <div class="flex flex-col gap-0.5 text-slate-400">
                    <span>Fisik Terscan : <span class="text-green-400 font-semibold">${scan}</span></span>
                    <span>Saldo Akhir : <span class="text-slate-300">${hgpSaldo(it)}</span></span>
                    <span>Selisih Scan : <span class="${selisih < 0 ? 'text-red-400' : selisih > 0 ? 'text-yellow-400' : 'text-slate-300'} font-semibold">${selSign}${selisih}</span></span>
                </div>
            </td>
        </tr>`;
    }).join('');

    // Input change — keterangan (text) dan wo (number → recalc in-place)
    tbody.querySelectorAll('.hgp-inp').forEach(inp => {
        inp.addEventListener('input', () => {
            const i = parseInt(inp.dataset.hgpI);
            const f = inp.dataset.hgpF;
            if (f === 'wo') {
                _hgpData.items[i].wo = hgpN(inp.value);
                hgpCalcItem(_hgpData.items[i]);
                // Update only the affected cells in-place (no full re-render)
                const row = inp.closest('tr');
                if (row) {
                    const it = _hgpData.items[i];
                    const sel = hgpN(it.selisih);
                    const selSign = sel >= 0 ? '+' : '';
                    const selClass = sel < 0 ? 'text-red-400 font-bold' : sel > 0 ? 'text-yellow-400 font-bold' : 'text-slate-300';
                    const harga = hgpN(it.hargaHet);
                    const jumlah = harga * sel;
                    const jumlahFmt = jumlah === 0 ? '-' : (jumlah >= 0 ? '+' : '') + Math.round(jumlah).toLocaleString('id-ID');
                    const jumlahClass = jumlah < 0 ? 'text-red-400 font-bold' : jumlah > 0 ? 'text-yellow-400 font-bold' : 'text-slate-400';
                    const cells = row.querySelectorAll('td');
                    // col indices: 0=no,1=noPart,2=nama,3=tgl,4=saldo,5=fisik,6=wo-input,7=akhir,8=selisih,9=harga,10=jumlah,11=ket,12=log
                    if (cells[7]) cells[7].textContent = hgpN(it.akhir);
                    if (cells[8]) { cells[8].textContent = `${selSign}${sel}`; cells[8].className = `px-3 py-2 text-right ${selClass}`; }
                    if (cells[10]) { cells[10].textContent = jumlahFmt; cells[10].className = `px-3 py-2 text-right ${jumlahClass}`; }
                }
            } else {
                _hgpData.items[i][f] = inp.value;
            }
        });
        inp.addEventListener('blur', () => { _doSaveHgp().catch(() => {}); });
    });

    hgpUpdateStats();
}

async function hgpEnrichWithHet(items) {
    if (!items || !items.length) return;
    const kodes = [...new Set(items.map(it => it.noPart).filter(Boolean))];
    if (!kodes.length) return;
    try {
        const res = await fetchJson('/api/audit-detail/hgp/batch-het', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ kodes }),
        });
        const map = res.data || {};
        items.forEach(it => {
            const hit = it.noPart ? map[it.noPart] : null;
            if (hit) {
                if (!it.sparepart || it.sparepart === it.noPart) it.sparepart = hit.nama || it.sparepart;
                it.hargaHet = hgpN(hit.hargaHet);
            }
        });
    } catch (_) {}
}

async function hgpHandleFile(file) {
    const msg = document.getElementById('hgpImportMsg');
    if (msg) { msg.classList.remove('hidden'); msg.textContent = 'Mengupload...'; }
    try {
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetchJson('/api/audit-detail/hgp/parse-excel', {
            method: 'POST',
            headers: authHeaders(),
            body: fd,
        });
        if (!res.data || !res.data.length) {
            if (msg) msg.textContent = 'Tidak ada data ditemukan dalam file.';
            return;
        }
        if (!_hgpData) _hgpData = hgpEmptyData();
        // Replace: import = master data baru. Pertahankan fisik & logScan untuk noPart yang cocok.
        const prevByPart = {};
        (_hgpData.items || []).forEach(it => {
            if (it.noPart) prevByPart[it.noPart.toLowerCase()] = it;
        });
        _hgpData.items = res.data.map(it => {
            const prev = it.noPart ? prevByPart[it.noPart.toLowerCase()] : null;
            if (prev) {
                it.fisik   = hgpN(prev.fisik);
                it.logScan = Array.isArray(prev.logScan) ? prev.logScan : [];
                hgpCalcItem(it);
            }
            return it;
        });
        if (msg) { msg.textContent = `${res.data.length} item diimport — memuat harga HET...`; }
        await hgpEnrichWithHet(_hgpData.items);
        if (msg) { msg.textContent = `${res.data.length} item diimport (data lama diganti).`; }
        hgpRenderItems();
        hgpPopulateDatalist();
        _doSaveHgp().catch(() => {});
    } catch (e) {
        if (msg) msg.textContent = 'Gagal: ' + (e.message || 'Unknown error');
    }
}

/* ---- Form pemeriksaan (scan / dropdown No. Part) ---- */
let _hgpSelIdx = -1; // index item yang sedang dipilih di form

function hgpPopulateDatalist() {
    const dl = document.getElementById('hgpPartList');
    if (!dl) return;
    const items = _hgpData?.items || [];
    const esc = s => (s || '').replace(/"/g, '&quot;');
    dl.innerHTML = items.map(it => {
        const noPart = esc(it.noPart || it.sparepart || '');
        const nama   = esc(it.sparepart || '');
        // Tampilkan No. Part sebagai nilai utama; nama part jadi keterangan
        return `<option value="${noPart}" label="${noPart}${nama ? ' — ' + nama : ''}">${noPart}</option>`;
    }).join('');
}

function hgpFindIdx(code) {
    const term = (code || '').trim().toLowerCase();
    if (!term) return -1;
    const items = _hgpData?.items || [];
    let idx = items.findIndex(it => (it.noPart || '').toLowerCase() === term);
    if (idx < 0) idx = items.findIndex(it => (it.sparepart || '').toLowerCase() === term);
    if (idx < 0) idx = items.findIndex(it => (it.noPart || '').toLowerCase().includes(term));
    return idx;
}

function hgpFormRecalc() {
    const qty = hgpN(document.getElementById('hgpFormQty')?.value);
    const it  = _hgpSelIdx >= 0 ? _hgpData.items[_hgpSelIdx] : null;
    const saldo = it ? hgpSaldo(it) : 0;
    // Preview: akhir setelah tambah qty baru ini (akumulasi ke fisik yang sudah ada)
    const fisikTotal = hgpN(it?.fisik) + qty;
    const akhir = saldo - fisikTotal;
    const selisih = fisikTotal - saldo;
    const elAkhir = document.getElementById('hgpFormAkhir');
    const elSel   = document.getElementById('hgpFormSelisih');
    if (elAkhir) elAkhir.value = akhir;
    if (elSel) {
        elSel.value = (selisih >= 0 ? '+' : '') + selisih;
        elSel.classList.remove('text-red-400', 'text-yellow-400', 'text-slate-300');
        elSel.classList.add(selisih < 0 ? 'text-red-400' : selisih > 0 ? 'text-yellow-400' : 'text-slate-300');
    }
    const log = document.getElementById('hgpFormLog');
    if (log) {
        const scan = it ? (it.logScan?.length || 0) : 0;
        log.textContent = `Fisik Terscan : ${scan} | Saldo Akhir : ${it ? saldo : '-'}`;
    }
}

function hgpFormSelectPart(code) {
    const info = document.getElementById('hgpFormPartInfo');
    const idx  = hgpFindIdx(code);
    _hgpSelIdx = idx;
    if (idx < 0) {
        if (info) { info.textContent = code ? `No. Part "${code}" tidak ditemukan dalam data import.` : ''; info.className = 'mt-0.5 text-xs text-red-400'; }
        hgpFormRecalc();
        return;
    }
    const it = _hgpData.items[idx];
    if (info) { info.textContent = `${it.noPart || '-'} — ${it.sparepart || ''} | Saldo Akhir: ${hgpSaldo(it)} | Fisik Terscan: ${hgpN(it.fisik)} | Sisa: ${hgpN(it.akhir)}`; info.className = 'mt-0.5 text-xs text-green-400'; }
    document.getElementById('hgpFormFields')?.classList.remove('hidden');
    // Pre-fill dari record yang ada
    const qtyEl = document.getElementById('hgpFormQty');
    const ketEl = document.getElementById('hgpFormKet');
    const tglEl = document.getElementById('hgpFormTgl');
    if (qtyEl) qtyEl.value = 0;  // qty ini untuk scan baru, bukan total
    if (ketEl) ketEl.value = it.keterangan || '';
    if (tglEl && it.tgl) tglEl.value = it.tgl;
    hgpFormRecalc();
}

// Scan barcode: akumulasi fisik +1 setiap scan kode yang sama, tiap scan tercatat di log.
// Setelah scan, form dikosongkan total agar siap scan berikutnya.
let _hgpScanGuard = false;
function hgpScanAccumulate(code) {
    const msg  = document.getElementById('hgpFormMsg');
    const showMsg = (text, ok) => {
        if (!msg) return;
        msg.classList.remove('hidden');
        msg.textContent = text;
        msg.className = 'text-xs font-medium ' + (ok ? 'text-green-400' : 'text-red-400');
    };
    const term = (code || '').trim();
    if (!term) return;

    const idx = hgpFindIdx(term);
    if (idx < 0) {
        showMsg(`✗ No. Part "${term}" tidak ditemukan dalam data import.`, false);
        hgpFormClearInputs();
        return;
    }

    const it = _hgpData.items[idx];
    it.fisik = hgpN(it.fisik) + 1;                       // akumulasi
    if (!Array.isArray(it.logScan)) it.logScan = [];
    it.logScan.push({ at: new Date().toISOString(), qty: 1 }); // history per scan
    it.tgl = document.getElementById('hgpFormTgl')?.value || it.tgl;
    hgpCalcItem(it);

    hgpRenderItems();
    _doSaveHgp().catch(() => {});

    // Pesan hasil scan, lalu kosongkan form untuk scan berikutnya
    showMsg(`✓ ${it.noPart || it.sparepart} — ${it.sparepart} | Fisik: ${hgpN(it.fisik)} | Akhir: ${it.akhir} | Selisih: ${it.selisih >= 0 ? '+' : ''}${it.selisih} (Terscan: ${it.logScan.length})`, true);
    hgpFormClearInputs();
}

// Kosongkan input form (tanpa menyentuh pesan hasil scan)
function hgpFormClearInputs() {
    _hgpSelIdx = -1;
    const partInput = document.getElementById('hgpFormPart');
    const qtyEl = document.getElementById('hgpFormQty');
    const ketEl = document.getElementById('hgpFormKet');
    const akhirEl = document.getElementById('hgpFormAkhir');
    const selEl = document.getElementById('hgpFormSelisih');
    const info  = document.getElementById('hgpFormPartInfo');
    const log   = document.getElementById('hgpFormLog');
    document.getElementById('hgpFormFields')?.classList.add('hidden');
    if (partInput) {
        _hgpScanGuard = true;
        partInput.value = '';
        setTimeout(() => { _hgpScanGuard = false; partInput.focus(); }, 0);
    }
    if (qtyEl)  qtyEl.value = 0;
    if (ketEl)  ketEl.value = '';
    if (akhirEl) akhirEl.value = 0;
    if (selEl)  selEl.value = 0;
    if (info)   info.textContent = '';
    if (log)    log.textContent = 'Fisik Terscan : 0 | Saldo Akhir : -';
}

function hgpFormSaveEntry() {
    const msg = document.getElementById('hgpFormMsg');
    const showMsg = (text, ok) => {
        if (!msg) return;
        msg.classList.remove('hidden');
        msg.textContent = text;
        msg.className = 'text-xs font-medium ' + (ok ? 'text-green-400' : 'text-red-400');
    };
    if (_hgpSelIdx < 0) { showMsg('Pilih / scan No. Part terlebih dahulu.', false); return; }
    const it = _hgpData.items[_hgpSelIdx];
    const qty = hgpN(document.getElementById('hgpFormQty')?.value);
    if (qty <= 0) { showMsg('Qty harus lebih dari 0.', false); return; }
    it.fisik = hgpN(it.fisik) + qty;  // akumulasi, bukan replace
    it.keterangan = document.getElementById('hgpFormKet')?.value || '';
    it.tgl = document.getElementById('hgpFormTgl')?.value || it.tgl;
    if (!Array.isArray(it.logScan)) it.logScan = [];
    it.logScan.push({ at: new Date().toISOString(), qty });
    hgpCalcItem(it);
    hgpRenderItems();
    _doSaveHgp().catch(() => {});
    showMsg(`✓ Tersimpan: ${it.noPart || it.sparepart} — Fisik ${hgpN(it.fisik)}, Akhir ${it.akhir}, Selisih ${it.selisih >= 0 ? '+' : ''}${it.selisih}`, true);
    hgpFormClearInputs();
}

function hgpFormReset() {
    _hgpSelIdx = -1;
    const ids = ['hgpFormPart', 'hgpFormKet'];
    ids.forEach(id => { const e = document.getElementById(id); if (e) e.value = ''; });
    const qty = document.getElementById('hgpFormQty'); if (qty) qty.value = 0;
    const info = document.getElementById('hgpFormPartInfo'); if (info) info.textContent = '';
    const msg = document.getElementById('hgpFormMsg'); if (msg) msg.classList.add('hidden');
    hgpFormRecalc();
}

async function loadHgpTab() {
    if (!activePlanId) { hgpRenderItems(); return; }
    const res = await fetchJson(`/api/audit-detail/hgp?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    if (res.data && Array.isArray(res.data.items) && res.data.items.length > 0) {
        _hgpData = { items: res.data.items };
    }
    if (!_hgpData) _hgpData = hgpEmptyData();
    (_hgpData.items || []).forEach(it => hgpCalcItem(it));
    await hgpEnrichWithHet(_hgpData.items);
    hgpRenderItems();
    hgpPopulateDatalist();
}

async function _doSaveHgp() {
    if (!activePlanId) throw new Error('Pilih plan audit terlebih dahulu.');
    if (!_hgpData) _hgpData = hgpEmptyData();
    return await fetchJson('/api/audit-detail/hgp', {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ planAuditId: activePlanId, items: _hgpData.items }),
    });
}

async function saveHgp() {
    const res = await _doSaveHgp();
    showAlert(res.message, 'success');
}

function initHgpForm() {
    const fileInput = document.getElementById('hgpFileInput');
    const dropzone  = document.getElementById('hgpDropzone');

    fileInput?.addEventListener('change', () => {
        if (fileInput.files[0]) hgpHandleFile(fileInput.files[0]);
        fileInput.value = '';
    });

    dropzone?.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('border-blue-400'); });
    dropzone?.addEventListener('dragleave', () => { dropzone.classList.remove('border-blue-400'); });
    dropzone?.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('border-blue-400');
        const f = e.dataTransfer.files[0];
        if (f) hgpHandleFile(f);
    });

    document.getElementById('hgpSaveBtn')?.addEventListener('click', () => {
        saveHgp().catch(err => showAlert(err.message || 'Gagal menyimpan.', 'error'));
    });

    // Form pemeriksaan: scan / pilih No. Part
    const partInput = document.getElementById('hgpFormPart');
    // Pilih dari dropdown → load (tanpa akumulasi). Scan + Enter → akumulasi fisik +1.
    partInput?.addEventListener('change', () => {
        if (_hgpScanGuard) return;
        if (partInput.value) hgpFormSelectPart(partInput.value);
    });
    partInput?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); hgpScanAccumulate(partInput.value); }
    });

    const qtyInput = document.getElementById('hgpFormQty');
    qtyInput?.addEventListener('input', () => hgpFormRecalc());
    document.getElementById('hgpFormQtyInc')?.addEventListener('click', () => {
        if (qtyInput) { qtyInput.value = hgpN(qtyInput.value) + 1; hgpFormRecalc(); }
    });
    document.getElementById('hgpFormQtyDec')?.addEventListener('click', () => {
        if (qtyInput) { const v = hgpN(qtyInput.value); qtyInput.value = v > 0 ? v - 1 : 0; hgpFormRecalc(); }
    });

    document.getElementById('hgpFormSaveBtn')?.addEventListener('click', () => hgpFormSaveEntry());
    document.getElementById('hgpFormResetBtn')?.addEventListener('click', () => hgpFormReset());

    // Default tanggal hari ini
    const tglEl = document.getElementById('hgpFormTgl');
    if (tglEl && !tglEl.value) tglEl.value = new Date().toISOString().slice(0, 10);

    // Tambah Part Manual
    const addPartBtn    = document.getElementById('hgpAddPartBtn');
    const addPartForm   = document.getElementById('hgpAddPartForm');
    const addPartNo     = document.getElementById('hgpAddPartNo');
    const addPartNama   = document.getElementById('hgpAddPartNama');
    const addPartSave   = document.getElementById('hgpAddPartSave');
    const addPartCancel = document.getElementById('hgpAddPartCancel');
    const addPartMsg    = document.getElementById('hgpAddPartMsg');

    addPartBtn?.addEventListener('click', () => {
        addPartForm?.classList.toggle('hidden');
        if (!addPartForm?.classList.contains('hidden')) addPartNo?.focus();
    });
    addPartCancel?.addEventListener('click', () => {
        addPartForm?.classList.add('hidden');
        if (addPartNo)  addPartNo.value  = '';
        if (addPartNama) addPartNama.value = '';
        if (addPartMsg) addPartMsg.classList.add('hidden');
    });
    addPartSave?.addEventListener('click', () => {
        const noPart = (addPartNo?.value || '').trim();
        const nama   = (addPartNama?.value || '').trim();
        const showAddMsg = (text, ok) => {
            if (!addPartMsg) return;
            addPartMsg.classList.remove('hidden');
            addPartMsg.textContent = text;
            addPartMsg.className = 'text-xs ' + (ok ? 'text-emerald-400' : 'text-red-400');
        };
        if (!noPart) { showAddMsg('No. Part wajib diisi.', false); return; }
        if (!_hgpData) _hgpData = hgpEmptyData();
        const exists = _hgpData.items.some(it => (it.noPart || '').toLowerCase() === noPart.toLowerCase());
        if (exists) { showAddMsg(`No. Part "${noPart}" sudah ada dalam daftar.`, false); return; }
        const newItem = {
            noPart, sparepart: nama || noPart,
            saldoAkhir: 0, fisik: 0, wo: 0, akhir: 0, selisih: 0,
            keterangan: '', tgl: new Date().toISOString().slice(0, 10), logScan: [],
            _manual: true,
        };
        _hgpData.items.push(newItem);
        hgpRenderItems();
        hgpPopulateDatalist();
        _doSaveHgp().catch(() => {});
        showAddMsg(`✓ "${noPart}" ditambahkan ke daftar scan.`, true);
        if (addPartNo)  addPartNo.value  = '';
        if (addPartNama) addPartNama.value = '';
        setTimeout(() => addPartForm?.classList.add('hidden'), 1500);
    });
    // Auto-fill nama dari db_het saat No. Part diketik
    let _hetLookupTimer = null;
    addPartNo?.addEventListener('input', () => {
        clearTimeout(_hetLookupTimer);
        const kode = (addPartNo.value || '').trim();
        if (!kode || kode.length < 3) return;
        _hetLookupTimer = setTimeout(async () => {
            try {
                const res = await fetchJson(`/api/audit-detail/hgp/lookup-het?kode=${encodeURIComponent(kode)}`, { headers: authHeaders() });
                if (res.data?.nama && addPartNama && !addPartNama.value.trim()) {
                    addPartNama.value = res.data.nama;
                }
            } catch (_) {}
        }, 400);
    });
    addPartNo?.addEventListener('keydown', e => { if (e.key === 'Enter') addPartSave?.click(); });
    addPartNama?.addEventListener('keydown', e => { if (e.key === 'Enter') addPartSave?.click(); });

    document.getElementById('hgpClearBtn')?.addEventListener('click', () => {
        if (!confirm('Hapus semua data HGP & AHM Oils? Data lama akan dikosongkan, lalu import ulang file Excel.')) return;
        _hgpData = hgpEmptyData();
        hgpRenderItems();
        hgpPopulateDatalist();
        hgpFormReset();
        const msg = document.getElementById('hgpImportMsg');
        if (msg) { msg.classList.remove('hidden'); msg.textContent = 'Data dikosongkan. Silakan import ulang file Excel.'; }
        _doSaveHgp().catch(() => {});
    });
}

/* ============================================================
   SMH TARIKAN MODULE
   ============================================================ */
let _smhTarikanData = null;

function smhTarikanEmpty() { return { items: [] }; }

function smhTarikanN(v) { return parseFloat(v) || 0; }

function smhTarikanIsLengkap(it) {
    return !!(it.nama && it.noBast);
}

function smhTarikanUpdateStats() {
    const items   = _smhTarikanData?.items || [];
    const total   = items.length;
    const lengkap = items.filter(smhTarikanIsLengkap).length;
    const draft   = total - lengkap;
    const el = id => document.getElementById(id);
    if (el('smhTarikanStatTotal'))   el('smhTarikanStatTotal').textContent   = total;
    if (el('smhTarikanStatLengkap')) el('smhTarikanStatLengkap').textContent = lengkap;
    if (el('smhTarikanStatDraft'))   el('smhTarikanStatDraft').textContent   = draft;
    if (el('smhTarikanCount'))       el('smhTarikanCount').textContent       = `${total} Unit`;
}

function smhTarikanRender() {
    const tbody = document.getElementById('smhTarikanTableBody');
    if (!tbody) return;
    const items = _smhTarikanData?.items || [];
    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="14" class="px-4 py-8 text-center text-slate-400 text-xs">Belum ada data — klik "+ Tambah Unit" untuk mulai.</td></tr>`;
        smhTarikanUpdateStats();
        return;
    }
    const esc = v => escapeHtml(v ?? '');
    tbody.innerHTML = items.map((it, i) => {
        const piutang = smhTarikanN(it.sisaPiutang);
        const piutangFmt = piutang > 0 ? piutang.toLocaleString('id-ID') : '—';
        const tglPengajuan = it.sudahAjukan
            ? `<span class="text-slate-300">${it.tglPengajuan || '—'}</span>`
            : `<span class="text-yellow-400 font-medium">Belum Ajukan</span>`;
        const badge = smhTarikanIsLengkap(it)
            ? `<span class="rounded-full bg-emerald-600/20 px-2 py-0.5 text-emerald-400 text-xs">Lengkap</span>`
            : `<span class="rounded-full bg-yellow-600/20 px-2 py-0.5 text-yellow-400 text-xs">Draft</span>`;
        return `<tr class="hover:bg-slate-800/40">
            <td class="px-3 py-2 text-slate-400">${i + 1}</td>
            <td class="px-3 py-2 text-slate-100 font-medium">${esc(it.nama)}</td>
            <td class="px-3 py-2 text-slate-300">${esc(it.noBast)}</td>
            <td class="px-3 py-2 text-slate-300">${esc(it.merk)}</td>
            <td class="px-3 py-2 text-center text-slate-300">${it.tahun || '—'}</td>
            <td class="px-3 py-2 text-slate-400 font-mono text-xs">${esc(it.noMesin)}</td>
            <td class="px-3 py-2 text-slate-400 font-mono text-xs">${esc(it.noRangka)}</td>
            <td class="px-3 py-2 text-slate-300">${esc(it.nopol)}</td>
            <td class="px-3 py-2 text-slate-300">${esc(it.noKontrak)}</td>
            <td class="px-3 py-2 text-right text-slate-100 font-semibold">${piutangFmt}</td>
            <td class="px-3 py-2 text-slate-400 max-w-[160px] truncate" title="${esc(it.kondisi)}">${esc(it.kondisi) || '—'}</td>
            <td class="px-3 py-2 text-slate-400 max-w-[160px] truncate" title="${esc(it.perlengkapan)}">${esc(it.perlengkapan) || '—'}</td>
            <td class="px-3 py-2 text-center text-xs">${tglPengajuan}</td>
            <td class="px-3 py-2 text-center">
                <div class="flex items-center justify-center gap-1">
                    ${badge}
                    <button data-st-edit="${i}" class="rounded bg-blue-700/40 px-2 py-0.5 text-blue-300 hover:bg-blue-700/70 text-xs">Edit</button>
                    <button data-st-del="${i}" class="rounded bg-red-700/30 px-2 py-0.5 text-red-400 hover:bg-red-700/60 text-xs">✕</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-st-edit]').forEach(btn => {
        btn.addEventListener('click', () => smhTarikanOpenForm(parseInt(btn.dataset.stEdit)));
    });
    tbody.querySelectorAll('[data-st-del]').forEach(btn => {
        btn.addEventListener('click', () => {
            const i = parseInt(btn.dataset.stDel);
            const it = _smhTarikanData.items[i];
            if (!confirm(`Hapus data "${it.nama || 'unit ini'}"?`)) return;
            _smhTarikanData.items.splice(i, 1);
            smhTarikanRender();
            _doSaveSmhTarikan().catch(() => {});
        });
    });

    smhTarikanUpdateStats();
}

function smhTarikanOpenForm(idx = -1) {
    const form = document.getElementById('smhTarikanForm');
    if (!form) return;
    form.classList.remove('hidden');
    document.getElementById('smhTarikanFormIdx').value = idx;
    document.getElementById('smhTarikanFormTitle').textContent = idx < 0 ? 'Tambah Unit SMH Tarikan' : 'Edit Unit SMH Tarikan';

    const it = idx >= 0 ? (_smhTarikanData?.items[idx] || {}) : {};
    document.getElementById('smhTarikanNama').value         = it.nama         || '';
    document.getElementById('smhTarikanNoBast').value       = it.noBast       || '';
    document.getElementById('smhTarikanMerk').value         = it.merk         || '';
    document.getElementById('smhTarikanTahun').value        = it.tahun        || '';
    document.getElementById('smhTarikanNoMesin').value      = it.noMesin      || '';
    document.getElementById('smhTarikanNoRangka').value     = it.noRangka     || '';
    document.getElementById('smhTarikanNopol').value        = it.nopol        || '';
    document.getElementById('smhTarikanNoKontrak').value    = it.noKontrak    || '';
    document.getElementById('smhTarikanSisaPiutang').value  = it.sisaPiutang ? Number(it.sisaPiutang).toLocaleString('id-ID') : '';
    document.getElementById('smhTarikanKondisi').value      = it.kondisi      || '';
    document.getElementById('smhTarikanPerlengkapan').value = it.perlengkapan || '';
    const sudah = !!it.sudahAjukan;
    const cbSudah = document.getElementById('smhTarikanSudahAjukan');
    const tglEl   = document.getElementById('smhTarikanTglPengajuan');
    const belumEl = document.getElementById('smhTarikanBelumAjukan');
    if (cbSudah) cbSudah.checked = sudah;
    if (tglEl)   { tglEl.value = it.tglPengajuan || ''; tglEl.classList.toggle('hidden', !sudah); }
    if (belumEl) belumEl.classList.toggle('hidden', sudah);

    const msg = document.getElementById('smhTarikanFormMsg');
    if (msg) msg.textContent = '';
    document.getElementById('smhTarikanNama')?.focus();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function smhTarikanCloseForm() {
    document.getElementById('smhTarikanForm')?.classList.add('hidden');
}

function smhTarikanFormSave() {
    const msg = document.getElementById('smhTarikanFormMsg');
    const showMsg = (text, ok) => {
        if (!msg) return;
        msg.textContent = text;
        msg.className = 'text-xs font-medium ' + (ok ? 'text-emerald-400' : 'text-red-400');
    };

    const nama = document.getElementById('smhTarikanNama')?.value.trim();
    if (!nama) { showMsg('Nama Konsumen wajib diisi.', false); return; }

    const item = {
        nama,
        noBast:      document.getElementById('smhTarikanNoBast')?.value.trim()    || '',
        merk:        document.getElementById('smhTarikanMerk')?.value.trim()       || '',
        tahun:       document.getElementById('smhTarikanTahun')?.value             || '',
        noMesin:     (document.getElementById('smhTarikanNoMesin')?.value.trim()   || '').toUpperCase(),
        noRangka:    (document.getElementById('smhTarikanNoRangka')?.value.trim()  || '').toUpperCase(),
        nopol:       (document.getElementById('smhTarikanNopol')?.value.trim()     || '').toUpperCase(),
        noKontrak:   document.getElementById('smhTarikanNoKontrak')?.value.trim()  || '',
        sisaPiutang:  smhTarikanN((document.getElementById('smhTarikanSisaPiutang')?.value || '').replace(/\./g, '')),
        kondisi:      document.getElementById('smhTarikanKondisi')?.value.trim()      || '',
        perlengkapan: document.getElementById('smhTarikanPerlengkapan')?.value.trim() || '',
        sudahAjukan:  document.getElementById('smhTarikanSudahAjukan')?.checked ?? false,
        tglPengajuan: document.getElementById('smhTarikanSudahAjukan')?.checked
            ? (document.getElementById('smhTarikanTglPengajuan')?.value || '')
            : '',
        createdAt:    new Date().toISOString(),
    };

    if (!_smhTarikanData) _smhTarikanData = smhTarikanEmpty();
    const idx = parseInt(document.getElementById('smhTarikanFormIdx')?.value ?? '-1');
    if (idx >= 0) {
        item.createdAt = _smhTarikanData.items[idx].createdAt;
        _smhTarikanData.items[idx] = item;
    } else {
        _smhTarikanData.items.push(item);
    }

    smhTarikanRender();
    _doSaveSmhTarikan()
        .then(() => { showMsg('✓ Tersimpan.', true); setTimeout(smhTarikanCloseForm, 800); })
        .catch(() => showMsg('Gagal menyimpan ke server.', false));
}

async function _doSaveSmhTarikan() {
    if (!activePlanId) throw new Error('Pilih plan audit terlebih dahulu.');
    if (!_smhTarikanData) _smhTarikanData = smhTarikanEmpty();
    return await fetchJson('/api/audit-detail/smh-tarikan', {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ planAuditId: activePlanId, items: _smhTarikanData.items }),
    });
}

async function loadSmhTarikanTab() {
    if (!activePlanId) { smhTarikanRender(); return; }
    const res = await fetchJson(`/api/audit-detail/smh-tarikan?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    if (res.data && Array.isArray(res.data.items)) {
        _smhTarikanData = { items: res.data.items };
    }
    if (!_smhTarikanData) _smhTarikanData = smhTarikanEmpty();
    smhTarikanRender();
}

function initSmhTarikanForm() {
    // Toggle tgl pengajuan
    document.getElementById('smhTarikanSudahAjukan')?.addEventListener('change', function () {
        document.getElementById('smhTarikanTglPengajuan')?.classList.toggle('hidden', !this.checked);
        document.getElementById('smhTarikanBelumAjukan')?.classList.toggle('hidden', this.checked);
        if (this.checked && !document.getElementById('smhTarikanTglPengajuan').value) {
            document.getElementById('smhTarikanTglPengajuan').value = new Date().toISOString().slice(0, 10);
        }
    });

    // Format ribuan otomatis pada Sisa Piutang
    const sisaEl = document.getElementById('smhTarikanSisaPiutang');
    if (sisaEl) {
        sisaEl.addEventListener('input', () => {
            const raw = sisaEl.value.replace(/\./g, '').replace(/[^0-9]/g, '');
            const num = raw ? parseInt(raw, 10) : '';
            const pos = sisaEl.selectionStart;
            const prevLen = sisaEl.value.length;
            sisaEl.value = num !== '' ? num.toLocaleString('id-ID') : '';
            // Adjust cursor position
            const diff = sisaEl.value.length - prevLen;
            sisaEl.setSelectionRange(pos + diff, pos + diff);
        });
    }

    document.getElementById('smhTarikanAddBtn')?.addEventListener('click', () => smhTarikanOpenForm(-1));
    document.getElementById('smhTarikanFormClose')?.addEventListener('click', smhTarikanCloseForm);
    document.getElementById('smhTarikanFormSave')?.addEventListener('click', smhTarikanFormSave);
    document.getElementById('smhTarikanFormReset')?.addEventListener('click', () => smhTarikanOpenForm(
        parseInt(document.getElementById('smhTarikanFormIdx')?.value ?? '-1')
    ));
    document.getElementById('smhTarikanSaveBtn')?.addEventListener('click', () => {
        _doSaveSmhTarikan()
            .then(r => showAlert(r.message || 'Data SMH Tarikan tersimpan.', 'success'))
            .catch(e => showAlert(e.message || 'Gagal menyimpan.', 'error'));
    });
}

/* ============================================================
   HGA (ACCESSORIES) MODULE — identik pola HGP
   ============================================================ */
let _hgaData = null;
let _hgaSelIdx = -1;
let _hgaScanGuard = false;

function hgaEmptyData() { return { items: [] }; }
function hgaN(v) { return parseFloat(v) || 0; }
function hgaSaldo(item) { return hgaN(item?.saldoAkhir ?? item?.saldoAwal); }

function hgaCalcItem(item) {
    const fisikScan = hgaN(item.fisik);
    const fisikTtp  = hgaN(item.fisikTtp);
    const totalFisik = fisikScan + fisikTtp;
    // Gunakan saldoPts sebagai referensi jika ada, fallback ke saldoAkhir
    const refSaldo  = (item.saldoPts !== undefined && item.saldoPts !== null) ? hgaN(item.saldoPts) : hgaSaldo(item);
    item.akhir   = refSaldo - totalFisik;
    item.selisih = totalFisik - refSaldo;
}

function hgaUpdateStats() {
    const items   = _hgaData?.items || [];
    const total   = items.length;
    const selisih = items.filter(it => it.selisih !== 0).length;
    const scan    = items.reduce((s, it) => s + (it.logScan?.length || 0), 0);
    const el = id => document.getElementById(id);
    if (el('hgaStatTotal'))   el('hgaStatTotal').textContent   = total;
    if (el('hgaStatSelisih')) el('hgaStatSelisih').textContent = selisih;
    if (el('hgaStatScan'))    el('hgaStatScan').textContent    = scan;
    if (el('hgaTableCount'))  el('hgaTableCount').textContent  = `${total} Item`;
}

function hgaRenderItems() {
    const tbody = document.getElementById('hgaTableBody');
    if (!tbody) return;
    const items = _hgaData?.items || [];
    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="14" class="px-4 py-8 text-center text-slate-400 text-xs">Belum ada data — import file Excel terlebih dahulu.</td></tr>`;
        hgaUpdateStats();
        return;
    }
    tbody.innerHTML = items.map((it, i) => {
        const selisih     = hgaN(it.selisih);
        const selClass    = selisih < 0 ? 'text-red-400 font-bold' : selisih > 0 ? 'text-yellow-400 font-bold' : 'text-slate-300';
        const scan        = it.logScan?.length || 0;
        const selSign     = selisih >= 0 ? '+' : '';
        const harga       = hgaN(it.hargaHet);
        const jumlah      = harga * selisih;
        const jumlahFmt   = jumlah === 0 ? '-' : (jumlah >= 0 ? '+' : '') + Math.round(jumlah).toLocaleString('id-ID');
        const jumlahClass = jumlah < 0 ? 'text-red-400 font-bold' : jumlah > 0 ? 'text-yellow-400 font-bold' : 'text-slate-400';
        const saldoPts    = it.saldoPts !== undefined && it.saldoPts !== null ? hgaN(it.saldoPts) : null;
        const saldoPtsFmt = saldoPts === null ? '<span class="text-slate-600">—</span>' : `<span class="text-purple-300">${saldoPts}</span>`;
        const saldoHgaFmt = it._ptsOnly ? '<span class="text-slate-600">—</span>' : hgaSaldo(it);
        const fisikScan   = hgaN(it.fisik);
        const fisikTtp    = hgaN(it.fisikTtp);
        return `<tr class="hover:bg-slate-800/40">
            <td class="px-3 py-2 text-slate-400">${i + 1}</td>
            <td class="px-3 py-2 text-slate-400 text-xs">${it.noPart || ''}</td>
            <td class="px-3 py-2 text-slate-100 font-medium">${it.sparepart || ''}</td>
            <td class="px-3 py-2 text-center text-slate-300">${it.tgl || '<span class="text-slate-600">—</span>'}</td>
            <td class="px-3 py-2 text-right text-slate-300">${saldoHgaFmt}</td>
            <td class="px-3 py-2 text-right">${saldoPtsFmt}</td>
            <td class="px-3 py-2 text-right text-green-400 font-semibold">${fisikScan}</td>
            <td class="px-3 py-2">
                <input type="number" data-hga-i="${i}" data-hga-f="fisikTtp" min="0"
                    value="${fisikTtp}"
                    class="hga-inp w-16 rounded border border-yellow-600/50 bg-slate-800 px-2 py-1 text-xs text-yellow-300 text-right focus:border-yellow-400 focus:outline-none">
            </td>
            <td class="px-3 py-2 text-right text-slate-300">${hgaN(it.akhir)}</td>
            <td class="px-3 py-2 text-right ${selClass}">${selSign}${selisih}</td>
            <td class="px-3 py-2 text-right text-slate-300">${harga > 0 ? harga.toLocaleString('id-ID') : '<span class="text-slate-600">—</span>'}</td>
            <td class="px-3 py-2 text-right ${jumlahClass}">${jumlahFmt}</td>
            <td class="px-3 py-2">
                <div class="flex flex-col gap-1">
                    <textarea data-hga-i="${i}" data-hga-f="keterangan" rows="2"
                        placeholder="Ket. Scan..."
                        class="hga-inp w-full rounded border border-slate-600 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none resize-none">${it.keterangan || ''}</textarea>
                    <textarea data-hga-i="${i}" data-hga-f="keteranganTtp" rows="2"
                        placeholder="Ket. TTP..."
                        class="hga-inp w-full rounded border border-yellow-600/40 bg-slate-800/60 px-2 py-1 text-xs text-yellow-300 focus:border-yellow-400 focus:outline-none resize-none">${it.keteranganTtp || ''}</textarea>
                </div>
            </td>
            <td class="px-3 py-2 text-xs">
                <div class="flex flex-col gap-0.5 text-slate-400">
                    <span>Scan : <span class="text-green-400 font-semibold">${fisikScan}</span> | TTP : <span class="text-yellow-400 font-semibold">${fisikTtp}</span></span>
                    <span>Ref Saldo : <span class="text-slate-300">${saldoPts !== null ? saldoPts : hgaSaldo(it)}</span></span>
                    <span>Selisih : <span class="${selisih < 0 ? 'text-red-400' : selisih > 0 ? 'text-yellow-400' : 'text-slate-300'} font-semibold">${selSign}${selisih}</span></span>
                </div>
            </td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('.hga-inp').forEach(inp => {
        inp.addEventListener('input', () => {
            const idx = parseInt(inp.dataset.hgaI);
            const field = inp.dataset.hgaF;
            _hgaData.items[idx][field] = field === 'fisikTtp' ? hgaN(inp.value) : inp.value;
            if (field === 'fisikTtp') {
                hgaCalcItem(_hgaData.items[idx]);
                // Update Akhir & Selisih cells in same row
                const row = inp.closest('tr');
                if (row) {
                    const cells = row.querySelectorAll('td');
                    const akhir   = hgaN(_hgaData.items[idx].akhir);
                    const selisih = hgaN(_hgaData.items[idx].selisih);
                    const selSign = selisih >= 0 ? '+' : '';
                    const selClass = selisih < 0 ? 'text-red-400 font-bold' : selisih > 0 ? 'text-yellow-400 font-bold' : 'text-slate-300';
                    if (cells[8])  cells[8].textContent  = akhir;
                    if (cells[9])  { cells[9].textContent = selSign + selisih; cells[9].className = `px-3 py-2 text-right ${selClass}`; }
                    // Update jumlah (cell index 11 → now 10 after merging keterangan)
                    const harga   = hgaN(_hgaData.items[idx].hargaHet);
                    const jumlah  = harga * selisih;
                    const jumlahFmt = jumlah === 0 ? '-' : (jumlah >= 0 ? '+' : '') + Math.round(jumlah).toLocaleString('id-ID');
                    const jumlahClass = jumlah < 0 ? 'text-red-400 font-bold' : jumlah > 0 ? 'text-yellow-400 font-bold' : 'text-slate-400';
                    if (cells[10]) { cells[10].textContent = jumlahFmt; cells[10].className = `px-3 py-2 text-right ${jumlahClass}`; }
                    // Update log scan cell (index 13 → now 12)
                    const saldoPts = _hgaData.items[idx].saldoPts !== undefined ? hgaN(_hgaData.items[idx].saldoPts) : null;
                    const refSaldo = saldoPts !== null ? saldoPts : hgaSaldo(_hgaData.items[idx]);
                    if (cells[13]) cells[13].innerHTML = `<div class="flex flex-col gap-0.5 text-slate-400"><span>Scan : <span class="text-green-400 font-semibold">${hgaN(_hgaData.items[idx].fisik)}</span> | TTP : <span class="text-yellow-400 font-semibold">${hgaN(inp.value)}</span></span><span>Ref Saldo : <span class="text-slate-300">${refSaldo}</span></span><span>Selisih : <span class="${selisih < 0 ? 'text-red-400' : selisih > 0 ? 'text-yellow-400' : 'text-slate-300'} font-semibold">${selSign}${selisih}</span></span></div>`;
                    hgaUpdateStats();
                }
            }
        });
        inp.addEventListener('blur', () => { _doSaveHga().catch(() => {}); });
    });
    hgaUpdateStats();
}

function hgaPopulateDatalist() {
    const dl = document.getElementById('hgaPartList');
    if (!dl) return;
    const esc = s => (s || '').replace(/"/g, '&quot;');
    dl.innerHTML = (_hgaData?.items || []).map(it => {
        const noPart = esc(it.noPart || it.sparepart || '');
        const nama   = esc(it.sparepart || '');
        return `<option value="${noPart}" label="${noPart}${nama ? ' — ' + nama : ''}">${noPart}</option>`;
    }).join('');
}

function hgaFindIdx(code) {
    const term  = (code || '').trim().toLowerCase();
    if (!term) return -1;
    const items = _hgaData?.items || [];
    let idx = items.findIndex(it => (it.noPart || '').toLowerCase() === term);
    if (idx < 0) idx = items.findIndex(it => (it.sparepart || '').toLowerCase() === term);
    if (idx < 0) idx = items.findIndex(it => (it.noPart || '').toLowerCase().includes(term));
    return idx;
}

function hgaFormRecalc() {
    const qty  = hgaN(document.getElementById('hgaFormQty')?.value);
    const it   = _hgaSelIdx >= 0 ? _hgaData.items[_hgaSelIdx] : null;
    const saldo = it ? hgaSaldo(it) : 0;
    const fisikTotal = hgaN(it?.fisik) + qty;
    const akhir   = saldo - fisikTotal;
    const selisih = fisikTotal - saldo;
    const elAkhir = document.getElementById('hgaFormAkhir');
    const elSel   = document.getElementById('hgaFormSelisih');
    if (elAkhir) elAkhir.value = akhir;
    if (elSel) {
        elSel.value = (selisih >= 0 ? '+' : '') + selisih;
        elSel.classList.remove('text-red-400', 'text-yellow-400', 'text-slate-300');
        elSel.classList.add(selisih < 0 ? 'text-red-400' : selisih > 0 ? 'text-yellow-400' : 'text-slate-300');
    }
    const log = document.getElementById('hgaFormLog');
    if (log) {
        const scan = it ? (it.logScan?.length || 0) : 0;
        log.textContent = `Fisik Terscan : ${scan} | Saldo Akhir : ${it ? saldo : '-'}`;
    }
}

function hgaFormSelectPart(code) {
    const info = document.getElementById('hgaFormPartInfo');
    const idx  = hgaFindIdx(code);
    _hgaSelIdx = idx;
    if (idx < 0) {
        if (info) { info.textContent = code ? `No. Part "${code}" tidak ditemukan.` : ''; info.className = 'mt-0.5 text-xs text-red-400'; }
        hgaFormRecalc();
        return;
    }
    const it = _hgaData.items[idx];
    if (info) { info.textContent = `${it.noPart || '-'} — ${it.sparepart || ''} | Saldo Akhir: ${hgaSaldo(it)} | Fisik: ${hgaN(it.fisik)} | Sisa: ${hgaN(it.akhir)}`; info.className = 'mt-0.5 text-xs text-green-400'; }
    document.getElementById('hgaFormFields')?.classList.remove('hidden');
    const qtyEl = document.getElementById('hgaFormQty');
    const ketEl = document.getElementById('hgaFormKet');
    const tglEl = document.getElementById('hgaFormTgl');
    if (qtyEl) qtyEl.value = 0;
    if (ketEl) ketEl.value = it.keterangan || '';
    if (tglEl && it.tgl) tglEl.value = it.tgl;
    hgaFormRecalc();
}

function hgaScanAccumulate(code) {
    const msg = document.getElementById('hgaFormMsg');
    const showMsg = (text, ok) => {
        if (!msg) return;
        msg.textContent = text;
        msg.className = 'text-xs font-medium ' + (ok ? 'text-green-400' : 'text-red-400');
    };
    const term = (code || '').trim();
    if (!term) return;
    const idx = hgaFindIdx(term);
    if (idx < 0) { showMsg(`✗ No. Part "${term}" tidak ditemukan dalam data import.`, false); hgaFormClearInputs(); return; }
    const it = _hgaData.items[idx];
    it.fisik = hgaN(it.fisik) + 1;
    if (!Array.isArray(it.logScan)) it.logScan = [];
    it.logScan.push({ at: new Date().toISOString(), qty: 1 });
    it.tgl = document.getElementById('hgaFormTgl')?.value || it.tgl;
    hgaCalcItem(it);
    hgaRenderItems();
    _doSaveHga().catch(() => {});
    showMsg(`✓ ${it.noPart || it.sparepart} — ${it.sparepart} | Fisik: ${hgaN(it.fisik)} | Akhir: ${it.akhir} | Selisih: ${it.selisih >= 0 ? '+' : ''}${it.selisih} (Terscan: ${it.logScan.length})`, true);
    hgaFormClearInputs();
}

function hgaFormClearInputs() {
    _hgaSelIdx = -1;
    document.getElementById('hgaFormFields')?.classList.add('hidden');
    const partInput = document.getElementById('hgaFormPart');
    if (partInput) { _hgaScanGuard = true; partInput.value = ''; setTimeout(() => { _hgaScanGuard = false; partInput.focus(); }, 0); }
    const el = id => document.getElementById(id);
    if (el('hgaFormQty'))    el('hgaFormQty').value    = 0;
    if (el('hgaFormKet'))    el('hgaFormKet').value    = '';
    if (el('hgaFormAkhir'))  el('hgaFormAkhir').value  = 0;
    if (el('hgaFormSelisih')) el('hgaFormSelisih').value = 0;
    if (el('hgaFormPartInfo')) el('hgaFormPartInfo').textContent = '';
    if (el('hgaFormLog'))    el('hgaFormLog').textContent = 'Fisik Terscan : 0 | Saldo Akhir : -';
}

function hgaFormSaveEntry() {
    const msg = document.getElementById('hgaFormMsg');
    const showMsg = (text, ok) => { if (!msg) return; msg.textContent = text; msg.className = 'text-xs font-medium ' + (ok ? 'text-green-400' : 'text-red-400'); };
    if (_hgaSelIdx < 0) { showMsg('Pilih / scan No. Part terlebih dahulu.', false); return; }
    const it  = _hgaData.items[_hgaSelIdx];
    const qty = hgaN(document.getElementById('hgaFormQty')?.value);
    if (qty <= 0) { showMsg('Qty harus lebih dari 0.', false); return; }
    it.fisik = hgaN(it.fisik) + qty;
    it.keterangan = document.getElementById('hgaFormKet')?.value || '';
    it.tgl = document.getElementById('hgaFormTgl')?.value || it.tgl;
    if (!Array.isArray(it.logScan)) it.logScan = [];
    it.logScan.push({ at: new Date().toISOString(), qty });
    hgaCalcItem(it);
    hgaRenderItems();
    _doSaveHga().catch(() => {});
    showMsg(`✓ Tersimpan: ${it.noPart || it.sparepart} — Fisik ${hgaN(it.fisik)}, Akhir ${it.akhir}, Selisih ${it.selisih >= 0 ? '+' : ''}${it.selisih}`, true);
    hgaFormClearInputs();
}

async function hgaEnrichWithHet(items) {
    if (!items?.length) return;
    const kodes = [...new Set(items.map(it => it.noPart).filter(Boolean))];
    if (!kodes.length) return;
    try {
        const res = await fetchJson('/api/audit-detail/hgp/batch-het', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ kodes }),
        });
        const map = res.data || {};
        items.forEach(it => {
            const hit = it.noPart ? map[it.noPart] : null;
            if (hit) { it.hargaHet = hgaN(hit.hargaHet); }
        });
    } catch (_) {}
}

async function hgaHandleFile(file) {
    const msg = document.getElementById('hgaImportMsg');
    if (msg) { msg.classList.remove('hidden'); msg.textContent = 'Mengupload...'; }
    try {
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetchJson('/api/audit-detail/hga/parse-excel', {
            method: 'POST', headers: authHeaders(), body: fd,
        });
        if (!res.data || !res.data.length) { if (msg) msg.textContent = 'Tidak ada data ditemukan dalam file.'; return; }
        if (!_hgaData) _hgaData = hgaEmptyData();
        const prevByPart = {};
        (_hgaData.items || []).forEach(it => { if (it.noPart) prevByPart[it.noPart.toLowerCase()] = it; });
        _hgaData.items = res.data.map(it => {
            const prev = it.noPart ? prevByPart[it.noPart.toLowerCase()] : null;
            if (prev) { it.fisik = hgaN(prev.fisik); it.logScan = Array.isArray(prev.logScan) ? prev.logScan : []; hgaCalcItem(it); }
            return it;
        });
        if (msg) msg.textContent = `${res.data.length} item diimport — memuat harga HET...`;
        await hgaEnrichWithHet(_hgaData.items);
        if (msg) msg.textContent = `${res.data.length} item diimport.`;
        hgaRenderItems();
        hgaPopulateDatalist();
        _doSaveHga().catch(() => {});
    } catch (e) { if (msg) msg.textContent = 'Gagal: ' + (e.message || 'Unknown error'); }
}

async function hgaHandlePtsFile(file) {
    const msg = document.getElementById('hgaPtsImportMsg');
    if (msg) { msg.classList.remove('hidden'); msg.textContent = 'Mengupload PTS...'; }
    try {
        const fd = new FormData();
        fd.append('file', file);
        // Reuse same parse-excel endpoint — format PTS lebih sederhana tapi hasilnya sama strukturnya
        const res = await fetchJson('/api/audit-detail/hga/parse-excel-pts', {
            method: 'POST', headers: authHeaders(), body: fd,
        });
        if (!res.data) { if (msg) msg.textContent = 'Tidak ada data ditemukan dalam file PTS.'; return; }
        const ptsItems = res.data; // [{noPart, sparepart, saldoAkhir}]
        if (!_hgaData) _hgaData = hgaEmptyData();

        // Build map dari data HGA existing
        const mainMap = {};
        (_hgaData.items || []).forEach((it, i) => { if (it.noPart) mainMap[it.noPart.toLowerCase()] = i; });

        // Merge: set saldoPts ke existing items, atau tambah item baru dengan saldoAkhir=0
        ptsItems.forEach(pts => {
            const key = (pts.noPart || '').toLowerCase();
            if (!key) return;
            const idx = mainMap[key];
            if (idx !== undefined) {
                _hgaData.items[idx].saldoPts = hgaN(pts.saldoAkhir);
            } else {
                // Tidak ada di stok HGA → tambah sebagai item PTS-only, saldoAkhir=0 untuk kalkulasi
                const newItem = {
                    noPart: pts.noPart,
                    sparepart: pts.sparepart || pts.noPart,
                    saldoAkhir: 0,
                    saldoPts: hgaN(pts.saldoAkhir),
                    fisik: 0, akhir: 0, selisih: 0,
                    keterangan: '', tgl: '', logScan: [],
                    _ptsOnly: true,
                };
                hgaCalcItem(newItem);
                _hgaData.items.push(newItem);
                mainMap[key] = _hgaData.items.length - 1;
            }
        });

        // Semua item HGA yang tidak ada di PTS → saldoPts = 0
        (_hgaData.items || []).forEach(it => {
            if (it.saldoPts === undefined) it.saldoPts = 0;
        });

        const added = ptsItems.filter(p => mainMap[(p.noPart || '').toLowerCase()] !== undefined).length;
        if (msg) msg.textContent = `PTS: ${ptsItems.length} item diproses, data tersinkronisasi.`;
        hgaRenderItems();
        hgaPopulateDatalist();
        _doSaveHga().catch(() => {});
    } catch (e) { if (msg) msg.textContent = 'Gagal: ' + (e.message || 'Unknown error'); }
}

async function loadHgaTab() {
    if (!activePlanId) { hgaRenderItems(); return; }
    const res = await fetchJson(`/api/audit-detail/hga?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
    if (res.data && Array.isArray(res.data.items) && res.data.items.length > 0) {
        _hgaData = { items: res.data.items };
    }
    if (!_hgaData) _hgaData = hgaEmptyData();
    (_hgaData.items || []).forEach(it => hgaCalcItem(it));
    await hgaEnrichWithHet(_hgaData.items);
    hgaRenderItems();
    hgaPopulateDatalist();
}

async function _doSaveHga() {
    if (!activePlanId) throw new Error('Pilih plan audit terlebih dahulu.');
    if (!_hgaData) _hgaData = hgaEmptyData();
    return await fetchJson('/api/audit-detail/hga', {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ planAuditId: activePlanId, items: _hgaData.items }),
    });
}

function initHgaForm() {
    const fileInput = document.getElementById('hgaFileInput');
    const dropzone  = document.getElementById('hgaDropzone');
    fileInput?.addEventListener('change', () => { if (fileInput.files[0]) hgaHandleFile(fileInput.files[0]); fileInput.value = ''; });
    dropzone?.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('border-blue-400'); });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-400'));
    dropzone?.addEventListener('drop', e => { e.preventDefault(); dropzone.classList.remove('border-blue-400'); const f = e.dataTransfer.files[0]; if (f) hgaHandleFile(f); });

    // PTS / Part Dept dropzone
    const ptsFileInput = document.getElementById('hgaPtsFileInput');
    const ptsDropzone  = document.getElementById('hgaPtsDropzone');
    ptsFileInput?.addEventListener('change', () => { if (ptsFileInput.files[0]) hgaHandlePtsFile(ptsFileInput.files[0]); ptsFileInput.value = ''; });
    ptsDropzone?.addEventListener('dragover', e => { e.preventDefault(); ptsDropzone.classList.add('border-purple-400'); });
    ptsDropzone?.addEventListener('dragleave', () => ptsDropzone.classList.remove('border-purple-400'));
    ptsDropzone?.addEventListener('drop', e => { e.preventDefault(); ptsDropzone.classList.remove('border-purple-400'); const f = e.dataTransfer.files[0]; if (f) hgaHandlePtsFile(f); });

    document.getElementById('hgaSaveBtn')?.addEventListener('click', () => {
        _doSaveHga().then(r => showAlert(r.message || 'Tersimpan.', 'success')).catch(e => showAlert(e.message || 'Gagal.', 'error'));
    });

    const partInput = document.getElementById('hgaFormPart');
    partInput?.addEventListener('change', () => { if (_hgaScanGuard) return; if (partInput.value) hgaFormSelectPart(partInput.value); });
    partInput?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); hgaScanAccumulate(partInput.value); } });

    const qtyInput = document.getElementById('hgaFormQty');
    qtyInput?.addEventListener('input', () => hgaFormRecalc());
    document.getElementById('hgaFormQtyInc')?.addEventListener('click', () => { if (qtyInput) { qtyInput.value = hgaN(qtyInput.value) + 1; hgaFormRecalc(); } });
    document.getElementById('hgaFormQtyDec')?.addEventListener('click', () => { if (qtyInput) { const v = hgaN(qtyInput.value); qtyInput.value = v > 0 ? v - 1 : 0; hgaFormRecalc(); } });

    document.getElementById('hgaFormSaveBtn')?.addEventListener('click', () => hgaFormSaveEntry());
    document.getElementById('hgaFormResetBtn')?.addEventListener('click', () => hgaFormClearInputs());

    const tglEl = document.getElementById('hgaFormTgl');
    if (tglEl && !tglEl.value) tglEl.value = new Date().toISOString().slice(0, 10);

    // Tambah Part Manual
    const addPartBtn    = document.getElementById('hgaAddPartBtn');
    const addPartForm   = document.getElementById('hgaAddPartForm');
    const addPartNo     = document.getElementById('hgaAddPartNo');
    const addPartNama   = document.getElementById('hgaAddPartNama');
    const addPartSave   = document.getElementById('hgaAddPartSave');
    const addPartCancel = document.getElementById('hgaAddPartCancel');
    const addPartMsg    = document.getElementById('hgaAddPartMsg');

    addPartBtn?.addEventListener('click', () => { addPartForm?.classList.toggle('hidden'); if (!addPartForm?.classList.contains('hidden')) addPartNo?.focus(); });
    addPartCancel?.addEventListener('click', () => { addPartForm?.classList.add('hidden'); if (addPartNo) addPartNo.value = ''; if (addPartNama) addPartNama.value = ''; if (addPartMsg) addPartMsg.classList.add('hidden'); });
    addPartSave?.addEventListener('click', () => {
        const noPart = (addPartNo?.value || '').trim();
        const nama   = (addPartNama?.value || '').trim();
        const showAddMsg = (text, ok) => { if (!addPartMsg) return; addPartMsg.classList.remove('hidden'); addPartMsg.textContent = text; addPartMsg.className = 'text-xs ' + (ok ? 'text-emerald-400' : 'text-red-400'); };
        if (!noPart) { showAddMsg('No. Part wajib diisi.', false); return; }
        if (!_hgaData) _hgaData = hgaEmptyData();
        if (_hgaData.items.some(it => (it.noPart || '').toLowerCase() === noPart.toLowerCase())) { showAddMsg(`No. Part "${noPart}" sudah ada.`, false); return; }
        _hgaData.items.push({ noPart, sparepart: nama || noPart, saldoAkhir: 0, fisik: 0, akhir: 0, selisih: 0, keterangan: '', tgl: '', logScan: [], _manual: true });
        hgaRenderItems(); hgaPopulateDatalist(); _doSaveHga().catch(() => {});
        showAddMsg(`✓ "${noPart}" ditambahkan.`, true);
        if (addPartNo) addPartNo.value = ''; if (addPartNama) addPartNama.value = '';
        setTimeout(() => addPartForm?.classList.add('hidden'), 1500);
    });
    addPartNo?.addEventListener('keydown', e => { if (e.key === 'Enter') addPartSave?.click(); });
    addPartNama?.addEventListener('keydown', e => { if (e.key === 'Enter') addPartSave?.click(); });

    let _hgaHetTimer = null;
    addPartNo?.addEventListener('input', () => {
        clearTimeout(_hgaHetTimer);
        const kode = (addPartNo.value || '').trim();
        if (!kode || kode.length < 3) return;
        _hgaHetTimer = setTimeout(async () => {
            try {
                const res = await fetchJson(`/api/audit-detail/hgp/lookup-het?kode=${encodeURIComponent(kode)}`, { headers: authHeaders() });
                if (res.data?.nama && addPartNama && !addPartNama.value.trim()) addPartNama.value = res.data.nama;
            } catch (_) {}
        }, 400);
    });

    document.getElementById('hgaClearBtn')?.addEventListener('click', () => {
        if (!confirm('Hapus semua data HGA Accessories?')) return;
        _hgaData = hgaEmptyData();
        hgaRenderItems(); hgaPopulateDatalist();
        const msg = document.getElementById('hgaImportMsg');
        if (msg) { msg.classList.remove('hidden'); msg.textContent = 'Data dikosongkan. Silakan import ulang file Excel.'; }
        _doSaveHga().catch(() => {});
    });
}

/* ============================================================
   LAMPIRAN MODULE
   ============================================================ */
let _lampiranData = null;

function lampiranFmtSize(bytes) {
    if (!bytes) return '0 KB';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function lampiranIcon(ext) {
    const icons = { pdf: '📄', jpg: '🖼️', jpeg: '🖼️', png: '🖼️', doc: '📝', docx: '📝' };
    return icons[ext] || '📎';
}

function lampiranUpdateStats() {
    const files = _lampiranData?.files || [];
    const total = files.length;
    const size  = files.reduce((s, f) => s + (f.size || 0), 0);
    const hasMerged = !!_lampiranData?.mergedPdf;

    const el = id => document.getElementById(id);
    if (el('lampiranStatTotal'))  el('lampiranStatTotal').textContent  = total;
    if (el('lampiranStatMerged')) el('lampiranStatMerged').textContent = hasMerged ? '✅ Siap' : '—';
    if (el('lampiranStatSize'))   el('lampiranStatSize').textContent   = lampiranFmtSize(size);
    if (el('lampiranTableCount')) el('lampiranTableCount').textContent = `${total} File`;

    const dlBtn      = el('lampiranDownloadBtn');
    const mergedInfo = el('lampiranMergedInfo');
    if (hasMerged) {
        dlBtn?.classList.remove('hidden');
        mergedInfo?.classList.remove('hidden');
    } else {
        dlBtn?.classList.add('hidden');
        mergedInfo?.classList.add('hidden');
    }
}

function lampiranRenderFiles() {
    const container = document.getElementById('lampiranFileList');
    if (!container) return;
    const files = _lampiranData?.files || [];
    if (files.length === 0) {
        container.innerHTML = '<p class="px-4 py-6 text-center text-xs text-slate-500">Belum ada file — upload file di atas.</p>';
        lampiranUpdateStats();
        return;
    }
    container.innerHTML = files.map((f, i) => `
        <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-800/30 rounded-xl">
            <span class="text-2xl">${lampiranIcon(f.ext)}</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-slate-100 truncate">${f.name}</p>
                <p class="text-xs text-slate-400">${f.ext?.toUpperCase()} · ${lampiranFmtSize(f.size)} · ${f.uploadedAt || ''}</p>
            </div>
            <span class="rounded-full px-2 py-0.5 text-xs font-bold uppercase ${
                f.ext === 'pdf' ? 'bg-red-900/40 text-red-300' :
                ['jpg','jpeg','png'].includes(f.ext) ? 'bg-blue-900/40 text-blue-300' :
                'bg-slate-700 text-slate-300'
            }">${f.ext}</span>
            <button type="button" data-lmp-del="${i}"
                class="rounded-lg border border-red-700/40 bg-red-900/20 px-2 py-1 text-xs text-red-400 hover:bg-red-900/40 transition">
                🗑️
            </button>
        </div>
    `).join('');

    container.querySelectorAll('[data-lmp-del]').forEach(btn => {
        btn.addEventListener('click', () => lampiranDeleteFile(parseInt(btn.dataset.lmpDel)));
    });
    lampiranUpdateStats();
}

async function lampiranUploadFile(file) {
    const msg = document.getElementById('lampiranUploadMsg');
    if (msg) { msg.classList.remove('hidden'); msg.textContent = `Mengupload "${file.name}"...`; }

    const fd = new FormData();
    fd.append('file', file);
    fd.append('plan_audit_id', activePlanId);

    try {
        const res = await fetchJson('/api/audit-detail/lampiran/upload', {
            method: 'POST', headers: authHeaders(), body: fd,
        });
        _lampiranData = res.data;
        lampiranRenderFiles();
        if (msg) msg.textContent = `✓ "${file.name}" berhasil diupload.`;
    } catch (e) {
        if (msg) msg.textContent = `✗ Gagal: ${e.message}`;
    }
}

async function lampiranDeleteFile(idx) {
    if (!confirm('Hapus file ini?')) return;
    try {
        const res = await fetchJson('/api/audit-detail/lampiran/delete-file', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ plan_audit_id: activePlanId, index: idx }),
        });
        _lampiranData = res.data;
        lampiranRenderFiles();
    } catch (e) { showAlert(e.message || 'Gagal menghapus.', 'error'); }
}

async function loadLampiranTab() {
    if (!activePlanId) { lampiranRenderFiles(); return; }
    try {
        const res = await fetchJson(`/api/audit-detail/lampiran?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
        _lampiranData = res.data || { files: [], mergedPdf: null };
    } catch (_) { _lampiranData = { files: [], mergedPdf: null }; }
    lampiranRenderFiles();
}

function initLampiranForm() {
    const fileInput = document.getElementById('lampiranFileInput');
    const dropzone  = document.getElementById('lampiranDropzone');

    fileInput?.addEventListener('change', async () => {
        if (!activePlanId) { showAlert('Pilih plan audit terlebih dahulu.', 'error'); return; }
        const files = Array.from(fileInput.files);
        for (const f of files) await lampiranUploadFile(f);
        fileInput.value = '';
    });

    dropzone?.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('border-blue-400', 'bg-blue-900/10'); });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-400', 'bg-blue-900/10'));
    dropzone?.addEventListener('drop', async e => {
        e.preventDefault();
        dropzone.classList.remove('border-blue-400', 'bg-blue-900/10');
        if (!activePlanId) { showAlert('Pilih plan audit terlebih dahulu.', 'error'); return; }
        const files = Array.from(e.dataTransfer.files);
        for (const f of files) await lampiranUploadFile(f);
    });

    document.getElementById('lampiranMergeBtn')?.addEventListener('click', async () => {
        if (!activePlanId) { showAlert('Pilih plan audit terlebih dahulu.', 'error'); return; }
        const files = _lampiranData?.files || [];
        if (files.length === 0) { showAlert('Tidak ada file yang diupload.', 'error'); return; }
        const btn = document.getElementById('lampiranMergeBtn');
        if (btn) { btn.textContent = '⏳ Memproses...'; btn.disabled = true; }
        try {
            const res = await fetchJson('/api/audit-detail/lampiran/merge-pdf', {
                method: 'POST',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ plan_audit_id: activePlanId }),
            });
            if (_lampiranData) _lampiranData.mergedPdf = res.mergedPdf;
            lampiranUpdateStats();
            showAlert(res.message || 'PDF berhasil digabung!', 'success');
        } catch (e) {
            showAlert(e.message || 'Gagal menggabungkan PDF.', 'error');
        } finally {
            if (btn) { btn.textContent = '🔗 Gabung jadi 1 PDF'; btn.disabled = false; }
        }
    });

    document.getElementById('lampiranDownloadBtn')?.addEventListener('click', async () => {
        if (!activePlanId) return;
        const btn = document.getElementById('lampiranDownloadBtn');
        if (btn) { btn.textContent = '⏳ Menyiapkan...'; btn.disabled = true; }
        try {
            const resp = await fetch(`/api/audit-detail/lampiran/download?plan_audit_id=${activePlanId}`, {
                headers: authHeaders(),
            });
            if (!resp.ok) throw new Error('Gagal mengunduh file.');
            const blob = await resp.blob();
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = `Lampiran_Audit_${activePlanId}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            showAlert(e.message || 'Gagal download.', 'error');
        } finally {
            if (btn) { btn.textContent = '⬇️ Download PDF Gabungan'; btn.disabled = false; }
        }
    });
}


// ══════════════════════════════════════════════════════════════════════════════
// GRADING MODULE
// ══════════════════════════════════════════════════════════════════════════════

let _gradingData        = null;
let _gradingMaster      = [];
let _gradingEditIdx     = -1;
let _gradingCurrentOpts = [];  // hasilOptions untuk pemeriksaan yang sedang dipilih di modal
let _gradingLoadedPlanId = null; // planId yang sudah dimuat, untuk menghindari reset saat ganti tab

function gradingN(v) { return parseFloat(v) || 0; }

function gradingUpdateStats() {
    const details = _gradingData?.details || [];
    const total   = details.reduce((s, d) => s + gradingN(d.nilai), 0);
    const elCount = document.getElementById('gradingStatItem');
    const elTotal = document.getElementById('gradingStatNilai');
    if (elCount) elCount.textContent = details.length;
    if (elTotal) elTotal.textContent = total.toFixed(2);
}

function gradingRenderDetails() {
    const tbody = document.getElementById('gradingDetailBody');
    if (!tbody) return;
    const details = _gradingData?.details || [];
    if (details.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-6 text-center text-slate-400 text-sm">Belum ada item pemeriksaan.</td></tr>`;
        gradingUpdateStats();
        return;
    }
    const isSaved = !!_gradingData?.id; // sudah tersimpan ke server jika punya id
    tbody.innerHTML = details.map((d, i) => {
        const isPica = picaIsLowGrade(d.hasilPemeriksaan);
        const hasPica = isPica && (d.currentCondition || '').trim() !== '';
        const picaBtn = isPica
            ? `<button onclick="gradingOpenPicaModal(${i})" title="Isi PICA" class="ml-1 text-xs px-1.5 py-0.5 rounded font-semibold ${hasPica ? 'bg-emerald-700 text-emerald-200' : 'bg-amber-700 text-amber-200'} hover:opacity-80">PICA</button>`
            : '';
        const editDeleteBtns = isSaved ? '' : `
                <button onclick="gradingOpenDetailModal(${i})" class="text-blue-400 hover:text-blue-200 mr-1 text-xs">✏️</button>
                <button onclick="gradingDeleteDetail(${i})" class="text-red-400 hover:text-red-200 text-xs">🗑️</button>`;
        return `
        <tr class="hover:bg-slate-800/40 border-b border-slate-800">
            <td class="px-3 py-2 text-center text-slate-500">${i + 1}</td>
            <td class="px-3 py-2 text-slate-200">${escapeHtml(d.namaPemeriksaan || '')}</td>
            <td class="px-3 py-2 text-slate-300">${escapeHtml(d.hasilPemeriksaan || '')}</td>
            <td class="px-3 py-2 text-right font-mono text-yellow-300">${gradingN(d.nilai).toFixed(2)}</td>
            <td class="px-3 py-2 text-center whitespace-nowrap">${editDeleteBtns}${picaBtn}</td>
        </tr>`;
    }).join('');
    gradingUpdateStats();
    // Sembunyikan tombol Tambah Item & Simpan jika sudah tersimpan ke server
    const gradingSaved = !!_gradingData?.id;
    const addBtn  = document.getElementById('gradingAddDetailBtn');
    const saveBtn = document.getElementById('gradingSaveBtn');
    if (addBtn)  addBtn.classList.toggle('hidden', gradingSaved);
    if (saveBtn) saveBtn.classList.toggle('hidden', gradingSaved);
}

function gradingOpenPicaModal(idx) {
    if (!_gradingData?.details) return;
    const d = _gradingData.details[idx];
    if (!d) return;
    const modal = document.getElementById('gradingPicaModal');
    if (!modal) return;
    document.getElementById('gradingPicaNama').textContent   = d.namaPemeriksaan  || '-';
    document.getElementById('gradingPicaHasil').textContent  = d.hasilPemeriksaan || '-';
    document.getElementById('gradingPicaNilai').textContent  = gradingN(d.nilai).toFixed(2);
    document.getElementById('gradingPicaCondition').value    = d.currentCondition || '';
    modal.dataset.picaIdx = idx;
    modal.classList.remove('hidden');
    document.getElementById('gradingPicaCondition').focus();
}

function gradingClosePicaModal() {
    const modal = document.getElementById('gradingPicaModal');
    if (modal) modal.classList.add('hidden');
}

function gradingSavePicaModal() {
    const modal = document.getElementById('gradingPicaModal');
    if (!modal || !_gradingData?.details) return;
    const idx = parseInt(modal.dataset.picaIdx, 10);
    const condition = (document.getElementById('gradingPicaCondition')?.value || '').trim();
    if (!condition) { showAlert('Current Condition wajib diisi.', 'error'); return; }
    _gradingData.details[idx] = { ..._gradingData.details[idx], currentCondition: condition };
    gradingClosePicaModal();
    gradingRenderDetails();
}

async function gradingLoadMaster() {
    const jenis   = _gradingData?.jenis || '';
    const wilayah = _gradingData?.area  || '';
    if (!jenis) { _gradingMaster = []; return; }
    try {
        // 1. Coba filter jenis + wilayah
        let res = await fetchJson(
            `/api/audit-detail/grading/master?jenis=${encodeURIComponent(jenis)}&wilayah=${encodeURIComponent(wilayah)}`,
            { headers: authHeaders() }
        );
        _gradingMaster = res.data || [];

        // 2. Jika kosong, coba filter jenis saja (tanpa wilayah)
        if (_gradingMaster.length === 0 && wilayah) {
            res = await fetchJson(
                `/api/audit-detail/grading/master?jenis=${encodeURIComponent(jenis)}`,
                { headers: authHeaders() }
            );
            _gradingMaster = res.data || [];
        }

        // 3. Jika masih kosong (jenis tidak cocok di db), ambil semua data
        //    (controller sudah deduplikasi by label, tidak akan ada duplikat)
        if (_gradingMaster.length === 0) {
            res = await fetchJson(`/api/audit-detail/grading/master?wilayah=${encodeURIComponent(wilayah)}`, { headers: authHeaders() });
            _gradingMaster = res.data || [];
        }
        if (_gradingMaster.length === 0) {
            res = await fetchJson(`/api/audit-detail/grading/master`, { headers: authHeaders() });
            _gradingMaster = res.data || [];
        }
    } catch (e) {
        _gradingMaster = [];
    }
}

function gradingPopulateNamaSelect(currentVal = '') {
    const sel = document.getElementById('gradingDetailNama');
    if (!sel) return;
    // Nama yang sudah dipilih di detail lain (kecuali item yang sedang diedit)
    const usedNames = new Set(
        (_gradingData?.details || [])
            .filter((_, i) => i !== _gradingEditIdx)
            .map(d => d.namaPemeriksaan)
    );
    const available = _gradingMaster.filter(m => !usedNames.has(m.namaPemeriksaan));
    // Saat edit: pastikan nilai saat ini selalu ada di dropdown meski tidak ada di master
    if (currentVal && !available.find(m => m.namaPemeriksaan === currentVal)) {
        available.unshift({ namaPemeriksaan: currentVal });
    }
    sel.innerHTML = '<option value="">-- Pilih Pemeriksaan --</option>' +
        available.map(m => `<option value="${escapeHtml(m.namaPemeriksaan)}">${escapeHtml(m.namaPemeriksaan)}</option>`).join('');
    if (currentVal) sel.value = currentVal;
}

function gradingNilaiFromOption(o) {
    const bbnkb = _gradingData?.bbnkb || 'N';
    const fraud  = _gradingData?.fraud  || 'N';
    // Kombinasi BBNKB + Fraud → kolom nilai, fallback ke nilai jika 0
    let v = 0;
    if (bbnkb === 'Y' && fraud === 'N') v = gradingN(o.pnknf);
    else if (bbnkb === 'Y' && fraud === 'Y') v = gradingN(o.pnkf);
    else if (bbnkb === 'N' && fraud === 'Y') v = gradingN(o.pkf);
    else v = gradingN(o.pknf);
    // Jika kolom spesifik kosong/0, gunakan kolom nilai
    return v !== 0 ? v : gradingN(o.nilai);
}

function gradingPopulateHasilSelect(namaPemeriksaan, currentVal = '') {
    const sel = document.getElementById('gradingDetailHasil');
    if (!sel) return;
    const master = _gradingMaster.find(m => m.namaPemeriksaan === namaPemeriksaan);
    _gradingCurrentOpts = master?.hasilOptions || [];
    // Saat edit: pastikan hasil saat ini ada di dropdown meski tidak ada di master
    const labels = _gradingCurrentOpts.map(o => o.label);
    const extraOpts = (currentVal && !labels.includes(currentVal))
        ? [`<option value="${escapeHtml(currentVal)}">${escapeHtml(currentVal)}</option>`]
        : [];
    sel.innerHTML = '<option value="">-- Pilih Hasil --</option>' +
        _gradingCurrentOpts.map(o =>
            `<option value="${escapeHtml(o.label)}">${escapeHtml(o.label)}</option>`
        ).join('') +
        extraOpts.join('');
    if (currentVal) sel.value = currentVal;
    // Auto-fill nilai berdasarkan pilihan sekarang (hanya jika ada di master)
    if (_gradingCurrentOpts.find(o => o.label === currentVal)) {
        gradingFillNilaiFromHasil(sel.value);
    }
}

function gradingFillNilaiFromHasil(hasilLabel) {
    const elNilai = document.getElementById('gradingDetailNilai');
    if (!elNilai) return;
    if (!hasilLabel) { elNilai.value = ''; return; }
    const opt = _gradingCurrentOpts.find(o => o.label === hasilLabel);
    const nilai = opt ? gradingNilaiFromOption(opt) : '';
    elNilai.value = nilai;
}

function gradingOpenDetailModal(idx = -1) {
    _gradingEditIdx = idx;
    const modal = document.getElementById('gradingDetailModal');
    if (!modal) return;

    const _doOpen = () => {
        const title = document.getElementById('gradingDetailModalTitle');
        if (idx >= 0) {
            const d = (_gradingData?.details || [])[idx];
            if (title) title.textContent = 'Edit Item Pemeriksaan';
            gradingPopulateNamaSelect(d?.namaPemeriksaan || '');
            gradingPopulateHasilSelect(d?.namaPemeriksaan || '', d?.hasilPemeriksaan || '');
            // Pertahankan nilai yang sudah ada
            const elNilai = document.getElementById('gradingDetailNilai');
            if (elNilai && d?.nilai != null) elNilai.value = d.nilai;
        } else {
            if (title) title.textContent = 'Tambah Item Pemeriksaan';
            gradingPopulateNamaSelect('');
            gradingPopulateHasilSelect('', '');
            const elNilai = document.getElementById('gradingDetailNilai');
            if (elNilai) elNilai.value = '';
        }
        const msg = document.getElementById('gradingDetailMsg');
        if (msg) msg.classList.add('hidden');
        modal.classList.remove('hidden');
    };

    // Jika master belum ada, load dulu lalu buka modal; jika sudah ada langsung buka
    if (_gradingMaster.length === 0) {
        gradingLoadMaster().then(_doOpen).catch(_doOpen);
    } else {
        _doOpen();
    }
}

function gradingCloseDetailModal() {
    const modal = document.getElementById('gradingDetailModal');
    if (modal) modal.classList.add('hidden');
    _gradingEditIdx = -1;
}

function gradingDeleteDetail(idx) {
    if (!_gradingData) return;
    _gradingData.details = (_gradingData.details || []).filter((_, i) => i !== idx);
    gradingRenderDetails();
}

function gradingSetJenis(jenis) {
    if (!_gradingData) _gradingData = {};
    _gradingData.jenis = jenis;
    document.querySelectorAll('.grading-jenis-btn').forEach(btn => {
        const active = btn.dataset.gradingJenis === jenis;
        btn.className = btn.className
            .replace(/\bbg-indigo-\S+|\btext-white\b|\btext-slate-300\b|\bborder-slate-600\b|\bborder-indigo-600\b/g, '')
            .trim();
        if (active) {
            btn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
        } else {
            btn.classList.add('text-slate-300', 'border-slate-600');
        }
    });
    gradingLoadMaster().then(() => gradingRenderDetails());
}

function gradingSetFlag(field, val) {
    if (!_gradingData) _gradingData = {};
    _gradingData[field] = val;
    const dataAttr = `grading${field.charAt(0).toUpperCase() + field.slice(1)}`;
    document.querySelectorAll(`.grading-${field}-btn`).forEach(btn => {
        const btnVal = btn.dataset[dataAttr] || btn.dataset.gradingBbnkb || btn.dataset.gradingFraud;
        const active = btnVal === val;
        btn.className = btn.className
            .replace(/\bbg-blue-600\b|\bbg-red-600\b|\btext-white\b|\btext-slate-300\b|\btext-red-400\b|\bborder-blue-600\b|\bborder-red-600\b|\bborder-slate-600\b/g, '')
            .trim();
        if (active) {
            const color = field === 'fraud' ? 'red' : 'blue';
            btn.classList.add(`bg-${color}-600`, 'text-white', `border-${color}-600`);
        } else {
            const isRedBtn = btn.classList.contains('hover:border-red-400');
            btn.classList.add(isRedBtn ? 'text-red-400' : 'text-slate-300', 'border-slate-600');
        }
    });
    if (field === 'fraud') {
        const sec = document.getElementById('gradingFraudDetail');
        if (sec) sec.classList.toggle('hidden', val !== 'Y');
    }
    // Refresh nilai di modal jika sedang terbuka (BBNKB/Fraud berubah → recalc nilai)
    const modal = document.getElementById('gradingDetailModal');
    if (modal && !modal.classList.contains('hidden')) {
        const hasil = document.getElementById('gradingDetailHasil')?.value || '';
        if (hasil) gradingFillNilaiFromHasil(hasil);
    }
}

function gradingRenderFraudTags() {
    const container = document.getElementById('gradingJenisFraudTags');
    if (!container) return;
    const tags = _gradingData?.jenisFraud || [];
    container.innerHTML = tags.map((t, i) => `
        <span class="inline-flex items-center gap-1 bg-red-900/40 text-red-300 text-xs rounded-full px-3 py-1">
            ${escapeHtml(t)}
            <button onclick="gradingRemoveFraudTag(${i})" class="hover:text-red-100 font-bold">×</button>
        </span>
    `).join('');
}

function gradingRemoveFraudTag(idx) {
    if (!_gradingData) return;
    _gradingData.jenisFraud = (_gradingData.jenisFraud || []).filter((_, i) => i !== idx);
    gradingRenderFraudTags();
}

async function loadGradingTab(forceReload = false) {
    if (!activePlanId) return;

    // Jika data sudah dimuat untuk plan yang sama, cukup re-render (jangan reset)
    if (!forceReload && _gradingData && _gradingLoadedPlanId === activePlanId) {
        await gradingLoadMaster();
        gradingRenderDetails();
        return;
    }

    // Reset
    _gradingData = null;
    _gradingMaster = [];
    _gradingLoadedPlanId = activePlanId;

    // Load plan info for auto-fill area & jenis
    let autoJenis = '', autoArea = '', autoCabang = '';
    try {
        const pi = await fetchJson(`/api/audit-detail/grading/plan-info?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
        if (pi.data) {
            autoArea   = pi.data.area         || '';
            autoJenis  = pi.data.jenisGrading || '';
            autoCabang = pi.data.cabang       || '';
        }
    } catch (e) { /* ignore */ }

    // Load existing grading record
    try {
        const res = await fetchJson(`/api/audit-detail/grading?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
        if (res.data) {
            _gradingData = {
                id:              res.data.id,
                idGrading:       res.data.idGrading       || '',
                jenis:           res.data.jenis            || autoJenis,
                area:            res.data.area             || autoArea,
                bbnkb:           res.data.bbnkb            || 'N',
                fraud:           res.data.fraud            || 'N',
                jenisFraud:      res.data.jenisFraud       || [],
                keteranganFraud: res.data.keteranganFraud  || '',
                details:         res.data.details          || [],
                totalNilai:      res.data.totalNilai,
            };
        }
    } catch (e) { /* ignore */ }

    if (!_gradingData) {
        _gradingData = { bbnkb: 'N', fraud: 'N', jenis: autoJenis, area: autoArea, details: [], jenisFraud: [] };
    }

    // Populate fields
    const elIdGrading = document.getElementById('gradingIdGrading');
    const elArea      = document.getElementById('gradingArea');
    const elKetFraud  = document.getElementById('gradingKeteranganFraud');
    // Auto-fill tanggal hari ini jika belum ada
    const today = new Date().toISOString().split('T')[0];
    if (elIdGrading) elIdGrading.value = _gradingData.idGrading || today;
    if (elArea)      elArea.value      = _gradingData.area       || '';
    const elAreaInfo = document.getElementById('gradingAreaInfo');
    if (elAreaInfo && autoCabang) elAreaInfo.textContent = `Unit Usaha: ${autoCabang}`;
    if (elKetFraud)  elKetFraud.value  = _gradingData.keteranganFraud || '';

    if (_gradingData.jenis) gradingSetJenis(_gradingData.jenis);
    gradingSetFlag('bbnkb', _gradingData.bbnkb || 'N');
    gradingSetFlag('fraud', _gradingData.fraud  || 'N');
    gradingRenderFraudTags();

    await gradingLoadMaster();
    gradingRenderDetails();
}

async function _doSaveGrading() {
    if (!activePlanId) { showAlert('Pilih plan audit terlebih dahulu.', 'error'); return; }

    const elIdGrading = document.getElementById('gradingIdGrading');
    const elArea      = document.getElementById('gradingArea');
    const elKetFraud  = document.getElementById('gradingKeteranganFraud');

    const details   = _gradingData?.details || [];
    const totalNilai = details.reduce((s, d) => s + gradingN(d.nilai), 0);

    const payload = {
        planAuditId:     activePlanId,
        idGrading:       elIdGrading?.value || '',
        jenis:           _gradingData?.jenis || '',
        area:            elArea?.value || '',
        bbnkb:           _gradingData?.bbnkb || 'N',
        fraud:           _gradingData?.fraud || 'N',
        jenisFraud:      _gradingData?.jenisFraud || [],
        keteranganFraud: elKetFraud?.value || '',
        details,
        totalNilai,
    };

    const btn = document.getElementById('gradingSaveBtn');
    if (btn) { btn.textContent = '⏳ Menyimpan...'; btn.disabled = true; }
    try {
        const res = await fetchJson('/api/audit-detail/grading', {
            method:  'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body:    JSON.stringify(payload),
        });
        if (res.data) {
            _gradingData = {
                id:              res.data.id,
                idGrading:       res.data.idGrading       || '',
                jenis:           res.data.jenis            || '',
                area:            res.data.area             || '',
                bbnkb:           res.data.bbnkb            || 'N',
                fraud:           res.data.fraud            || 'N',
                jenisFraud:      res.data.jenisFraud       || [],
                keteranganFraud: res.data.keteranganFraud  || '',
                details:         res.data.details          || [],
                totalNilai:      res.data.totalNilai,
            };
        }
        gradingRenderDetails();
        showAlert(res.message || 'Grading tersimpan.', 'success');
    } catch (e) {
        const msg = e.message || '';
        if (msg.includes('migrate') || msg.includes('42S02') || msg.includes("doesn't exist")) {
            showAlert('Tabel grading belum dibuat. Jalankan perintah: php artisan migrate — atau import file database/sql/create_audit_gradings.sql ke phpMyAdmin.', 'error');
        } else {
            showAlert(msg || 'Gagal menyimpan grading.', 'error');
        }
    } finally {
        if (btn) { btn.textContent = '💾 Simpan Grading'; btn.disabled = false; }
    }
}

function initGradingForm() {
    // Jenis toggle
    document.querySelectorAll('.grading-jenis-btn').forEach(btn => {
        btn.addEventListener('click', () => gradingSetJenis(btn.dataset.gradingJenis));
    });

    // BBNKB toggle — data-grading-bbnkb attribute
    document.querySelectorAll('.grading-bbnkb-btn').forEach(btn => {
        btn.addEventListener('click', () => gradingSetFlag('bbnkb', btn.dataset.gradingBbnkb));
    });

    // Fraud toggle — data-grading-fraud attribute
    document.querySelectorAll('.grading-fraud-btn').forEach(btn => {
        btn.addEventListener('click', () => gradingSetFlag('fraud', btn.dataset.gradingFraud));
    });

    // Add fraud tag
    document.getElementById('gradingJenisFraudAdd')?.addEventListener('click', () => {
        const inp = document.getElementById('gradingJenisFraudInput');
        const val = inp?.value?.trim();
        if (!val) return;
        if (!_gradingData) _gradingData = {};
        if (!_gradingData.jenisFraud) _gradingData.jenisFraud = [];
        if (!_gradingData.jenisFraud.includes(val)) {
            _gradingData.jenisFraud.push(val);
            gradingRenderFraudTags();
        }
        if (inp) inp.value = '';
    });
    document.getElementById('gradingJenisFraudInput')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('gradingJenisFraudAdd')?.click(); }
    });

    // Add detail button
    document.getElementById('gradingAddDetailBtn')?.addEventListener('click', () => {
        if (!_gradingData?.jenis) { showAlert('Pilih Jenis terlebih dahulu.', 'error'); return; }
        gradingOpenDetailModal(-1);
    });

    // Modal: nama change → repopulate hasil
    document.getElementById('gradingDetailNama')?.addEventListener('change', function () {
        gradingPopulateHasilSelect(this.value, '');
    });

    // Modal: hasil change → auto-fill nilai dari _gradingCurrentOpts
    document.getElementById('gradingDetailHasil')?.addEventListener('change', function () {
        gradingFillNilaiFromHasil(this.value);
    });

    // Modal save
    document.getElementById('gradingDetailSave')?.addEventListener('click', () => {
        const nama  = document.getElementById('gradingDetailNama')?.value?.trim();
        const hasil = document.getElementById('gradingDetailHasil')?.value?.trim();
        const nilai = gradingN(document.getElementById('gradingDetailNilai')?.value);
        const msg   = document.getElementById('gradingDetailMsg');

        if (!nama) {
            if (msg) { msg.textContent = 'Pilih nama pemeriksaan.'; msg.classList.remove('hidden'); }
            return;
        }
        if (msg) msg.classList.add('hidden');

        if (!_gradingData) _gradingData = {};
        if (!_gradingData.details) _gradingData.details = [];

        const item = { namaPemeriksaan: nama, hasilPemeriksaan: hasil || '', nilai };
        if (_gradingEditIdx >= 0) {
            _gradingData.details[_gradingEditIdx] = item;
        } else {
            _gradingData.details.push(item);
        }
        gradingCloseDetailModal();
        gradingRenderDetails();
    });

    // Modal cancel & close
    document.getElementById('gradingDetailCancel')?.addEventListener('click', gradingCloseDetailModal);
    document.getElementById('gradingDetailModalClose')?.addEventListener('click', gradingCloseDetailModal);
    document.getElementById('gradingDetailModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) gradingCloseDetailModal();
    });

    // Save grading
    document.getElementById('gradingSaveBtn')?.addEventListener('click', () => _doSaveGrading());

    // PICA modal backdrop close
    document.getElementById('gradingPicaModal')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) gradingClosePicaModal();
    });
}

// ══════════════════════════════════════════════════════════════════════════════
// PICA MODULE (berdasarkan item Grading dengan hasil nomor 1 & 2)
// ══════════════════════════════════════════════════════════════════════════════

// Cek apakah hasil pemeriksaan termasuk kategori PICA (diawali "1." atau "2.")
function picaIsLowGrade(hasilLabel) {
    return /^\s*[12]\s*\./.test(hasilLabel || '');
}

// Ambil daftar detail grading yang termasuk PICA (dengan index aslinya)
function picaGetItems() {
    const details = _gradingData?.details || [];
    return details
        .map((d, idx) => ({ ...d, _idx: idx }))
        .filter(d => picaIsLowGrade(d.hasilPemeriksaan));
}

function picaUpdateStats() {
    const items  = picaGetItems();
    const filled = items.filter(d => (d.currentCondition || '').trim() !== '').length;
    const elTotal  = document.getElementById('picaStatTotal');
    const elFilled = document.getElementById('picaStatFilled');
    if (elTotal)  elTotal.textContent  = items.length;
    if (elFilled) elFilled.textContent = filled;
}

function picaRenderList() {
    const wrap  = document.getElementById('picaList');
    const empty = document.getElementById('picaEmpty');
    if (!wrap) return;

    const items = picaGetItems();

    if (items.length === 0) {
        wrap.innerHTML = '';
        if (empty) empty.classList.remove('hidden');
        picaUpdateStats();
        return;
    }
    if (empty) empty.classList.add('hidden');

    wrap.innerHTML = items.map(d => {
        const nomor = (d.hasilPemeriksaan || '').trim().charAt(0); // "1" atau "2"
        const badgeColor = nomor === '1' ? 'bg-red-900/40 text-red-300 border-red-700/40'
                                         : 'bg-amber-900/30 text-amber-300 border-amber-700/40';
        return `
        <div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-5 space-y-4" data-pica-idx="${d._idx}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl border ${badgeColor} text-sm font-bold">${escapeHtml(nomor)}</span>
                    <div>
                        <div class="text-sm font-bold text-slate-100">${escapeHtml(d.namaPemeriksaan || '-')}</div>
                        <div class="text-xs text-slate-400">Nilai: <span class="font-mono text-yellow-300">${gradingN(d.nilai).toFixed(2)}</span></div>
                    </div>
                </div>
                ${(d.currentCondition || '').trim() !== ''
                    ? '<span class="rounded-full bg-emerald-900/30 text-emerald-300 text-[11px] font-semibold px-3 py-1 border border-emerald-700/40">✓ Terisi</span>'
                    : '<span class="rounded-full bg-slate-700/50 text-slate-300 text-[11px] font-semibold px-3 py-1">Belum diisi</span>'}
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-slate-400">Hasil Pemeriksaan</label>
                <div class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-xs text-slate-300">${escapeHtml(d.hasilPemeriksaan || '-')}</div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-slate-300">Current Condition <span class="text-red-400">*</span></label>
                <textarea class="pica-condition rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-slate-100 focus:border-blue-400 focus:outline-none resize-y"
                    rows="3" placeholder="Tuliskan kondisi saat ini / temuan untuk item ini..."
                    data-pica-idx="${d._idx}">${escapeHtml(d.currentCondition || '')}</textarea>
            </div>
        </div>`;
    }).join('');

    // Wire textarea input → update _gradingData.details langsung
    wrap.querySelectorAll('.pica-condition').forEach(ta => {
        ta.addEventListener('input', function () {
            const idx = parseInt(this.dataset.picaIdx, 10);
            if (_gradingData?.details?.[idx]) {
                _gradingData.details[idx].currentCondition = this.value;
            }
        });
        ta.addEventListener('blur', picaUpdateStats);
    });

    picaUpdateStats();
}

async function loadPicaTab() {
    if (!activePlanId) return;
    // Muat grading jika belum ada untuk plan ini (tidak reset data yang sudah ada)
    if (!_gradingData || _gradingLoadedPlanId !== activePlanId) {
        await loadGradingTab();
    }
    picaRenderList();
}

async function _doSavePica() {
    if (!activePlanId) { showAlert('Pilih plan audit terlebih dahulu.', 'error'); return; }
    if (!_gradingData) { showAlert('Data grading belum dimuat.', 'error'); return; }

    // Validasi: semua item PICA harus terisi
    const items   = picaGetItems();
    const kosong  = items.filter(d => (d.currentCondition || '').trim() === '');
    if (items.length === 0) {
        showAlert('Tidak ada item PICA untuk disimpan.', 'info');
        return;
    }
    if (kosong.length > 0) {
        showAlert(`Masih ada ${kosong.length} item PICA yang belum diisi Current Condition-nya.`, 'error');
        return;
    }

    const details    = _gradingData.details || [];
    const totalNilai = details.reduce((s, d) => s + gradingN(d.nilai), 0);

    const payload = {
        planAuditId:     activePlanId,
        idGrading:       document.getElementById('gradingIdGrading')?.value || _gradingData.idGrading || '',
        jenis:           _gradingData.jenis || '',
        area:            _gradingData.area  || '',
        bbnkb:           _gradingData.bbnkb || 'N',
        fraud:           _gradingData.fraud || 'N',
        jenisFraud:      _gradingData.jenisFraud || [],
        keteranganFraud: _gradingData.keteranganFraud || '',
        details,
        totalNilai,
    };

    const btn = document.getElementById('picaSaveBtn');
    if (btn) { btn.textContent = '⏳ Menyimpan...'; btn.disabled = true; }
    try {
        const res = await fetchJson('/api/audit-detail/grading', {
            method:  'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body:    JSON.stringify(payload),
        });
        if (res.data?.details) _gradingData.details = res.data.details;
        picaRenderList();
        showAlert(res.message || 'PICA tersimpan.', 'success');
    } catch (e) {
        showAlert(e.message || 'Gagal menyimpan PICA.', 'error');
    } finally {
        if (btn) { btn.textContent = '💾 Simpan PICA'; btn.disabled = false; }
    }
}

function initPicaForm() {
    document.getElementById('picaSaveBtn')?.addEventListener('click', () => _doSavePica());
}


// ── Rekomendasi Tab ───────────────────────────────────────────────────────────
let _rekomendasiEditId = null;

async function loadRekomendasiTab() {
    if (!activePlanId) return;
    // Auto-fill read-only fields dari activePlan
    const plan = activePlan || {};
    const noSptEl  = document.getElementById('rekomendasiNoSpt');
    const cabangEl = document.getElementById('rekomendasiCabang');
    const tglEl    = document.getElementById('rekomendasiTglAudit');
    if (noSptEl)  noSptEl.value  = plan.noSpt   || plan.no_spt   || '';
    if (cabangEl) cabangEl.value = plan.cabang   || '';
    if (tglEl) tglEl.value = plan.tglMulai || plan.tgl_mulai || plan.tglPlan || '';

    await rekomendasiLoadList();
}

async function rekomendasiLoadList() {
    const list = document.getElementById('rekomendasiList');
    if (!list || !activePlanId) return;
    try {
        const res = await fetchJson('/api/recommendations?plan_audit_id=' + activePlanId, { headers: authHeaders() });
        const rows = res.data ?? [];
        if (!rows.length) {
            list.innerHTML = '<p class="py-8 text-center text-sm text-slate-500">Belum ada rekomendasi untuk pemeriksaan ini.</p>';
            return;
        }
        list.innerHTML = rows.map(r => {
            const prioBadge = { rendah: 'bg-slate-700 text-slate-300', sedang: 'bg-amber-900/60 text-amber-300', tinggi: 'bg-orange-900/60 text-orange-300', urgent: 'bg-red-900/60 text-red-300' }[r.prioritas] || 'bg-slate-700 text-slate-300';
            const statusBadge = { draft: 'text-slate-400', open: 'text-blue-400', in_progress: 'text-amber-400', done: 'text-emerald-400', cancelled: 'text-red-400' }[r.status] || 'text-slate-400';
            return `<div class="rounded-2xl border border-slate-700 bg-slate-800/60 p-4 space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-200">${escapeHtml(r.judul)}</p>
                        ${r.deskripsi ? `<p class="mt-1 text-xs text-slate-400">${escapeHtml(r.deskripsi)}</p>` : ''}
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ${prioBadge}">${r.prioritas}</span>
                        <span class="text-xs font-semibold ${statusBadge}">${r.status}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 text-xs text-slate-500">
                    ${r.pic ? `<span>PIC: <span class="text-slate-300">${escapeHtml(r.pic)}</span></span>` : ''}
                    ${r.deadline ? `<span>Deadline: <span class="text-slate-300">${r.deadline}</span></span>` : ''}
                </div>
                <div class="flex gap-2 justify-end">
                    <button onclick="rekomendasiEdit(${r.id})" class="rounded-lg bg-slate-700 px-3 py-1 text-xs text-slate-200 hover:bg-slate-600 transition">Edit</button>
                    <button onclick="rekomendasiDelete(${r.id})" class="rounded-lg bg-red-900/40 px-3 py-1 text-xs text-red-300 hover:bg-red-800 transition">Hapus</button>
                </div>
            </div>`;
        }).join('');
    } catch (e) {
        if (document.getElementById('rekomendasiList'))
            document.getElementById('rekomendasiList').innerHTML = `<p class="py-6 text-center text-sm text-red-400">${escapeHtml(e.message)}</p>`;
    }
}

function rekomendasiShowForm(id = null) {
    _rekomendasiEditId = id;
    document.getElementById('rekomendasiForm')?.classList.remove('hidden');
    if (!id) {
        document.getElementById('rekomendasiIsi').value  = '';
        document.getElementById('rekomendasiFileName').classList.add('hidden');
        document.getElementById('rekomendasiFileInput').value = '';
        // Auto-fill isi rekomendasi dari semua data pemeriksaan
        rekomendasiAutoFill();
    }
    document.getElementById('rekomendasiIsi')?.focus();
}

async function rekomendasiAutoFill() {
    if (!activePlanId) return;
    const isiEl = document.getElementById('rekomendasiIsi');
    if (!isiEl) return;
    isiEl.value = '⏳ Memuat data pemeriksaan...';

    const fmtRp  = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    const sep    = '─'.repeat(48);
    const blocks = [];

    try {
        // ── 1. PEMERIKSAAN KAS ──────────────────────────────
        // Hitung persis seperti PDF: dari detail_json → kas_besar, kas_kecil, pecahan
        try {
            const kasRes  = await fetchJson(`/api/audit-detail/kas?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const kasRows = kasRes.data ?? kasRes ?? [];
            let kbSaldoBuku = 0, kbSaldoFisik = 0;
            let kkSaldoBuku = 0, kkSaldoFisik = 0;
            for (const k of kasRows) {
                const d   = k.detail_json ?? k.detailJson ?? {};
                const kb  = d.kas_besar ?? d.kasBesar ?? {};
                const kk  = d.kas_kecil ?? d.kasKecil ?? {};
                const pcn = d.pecahan   ?? [];
                // Kas Besar
                const kbSaldoAwal   = Number(kb.saldo_awal ?? kb.saldoAwal ?? 0);
                const kbTotalTerima = (kb.penerimaan ?? []).reduce((s, r) => s + Number(r.jumlah ?? 0), 0);
                const kbTotalKeluar = (kb.pengeluaran ?? []).reduce((s, r) => s + Number(r.jumlah ?? 0), 0);
                kbSaldoBuku  += kbSaldoAwal + kbTotalTerima - kbTotalKeluar;
                kbSaldoFisik += pcn.reduce((s, p) => s + Number(p.nominal ?? 0) * Number(p.lembar_besar ?? p.lembarBesar ?? 0), 0);
                // Kas Kecil
                const kkCadangan  = Number(kk.cadangan ?? 0);
                const kkTotalBon  = (kk.bon ?? []).reduce((s, r) => s + Number(r.jumlah ?? 0), 0);
                kkSaldoBuku  += kkCadangan - kkTotalBon;
                kkSaldoFisik += pcn.reduce((s, p) => s + Number(p.nominal ?? 0) * Number(p.lembar_kecil ?? p.lembarKecil ?? 0), 0);
            }
            const kbSel = kbSaldoFisik - kbSaldoBuku;
            const kkSel = kkSaldoFisik - kkSaldoBuku;
            const totBuku = kbSaldoBuku + kkSaldoBuku;
            const totFisik = kbSaldoFisik + kkSaldoFisik;
            const totSel = kbSel + kkSel;
            const rows = [];
            rows.push(`  ${'Pos Kas'.padEnd(12)} ${'Saldo Buku'.padStart(16)} ${'Saldo Fisik'.padStart(16)} ${'Selisih'.padStart(14)}`);
            rows.push(`  ${'─'.repeat(60)}`);
            rows.push(`  ${'Kas Besar'.padEnd(12)} ${fmtRp(kbSaldoBuku).padStart(16)} ${fmtRp(kbSaldoFisik).padStart(16)} ${(kbSel !== 0 ? (kbSel > 0 ? '+' : '') + fmtRp(kbSel) : 'Rp 0').padStart(14)}`);
            rows.push(`  ${'Kas Kecil'.padEnd(12)} ${fmtRp(kkSaldoBuku).padStart(16)} ${fmtRp(kkSaldoFisik).padStart(16)} ${(kkSel !== 0 ? (kkSel > 0 ? '+' : '') + fmtRp(kkSel) : 'Rp 0').padStart(14)}`);
            rows.push(`  ${'─'.repeat(60)}`);
            rows.push(`  ${'TOTAL'.padEnd(12)} ${fmtRp(totBuku).padStart(16)} ${fmtRp(totFisik).padStart(16)} ${(totSel !== 0 ? (totSel > 0 ? '+' : '') + fmtRp(totSel) : 'Rp 0').padStart(14)}`);
            if (totSel !== 0 || kbSel !== 0 || kkSel !== 0)
                blocks.push(`1. PEMERIKSAAN KAS\n${rows.join('\n')}`);
        } catch {}

        // ── 2. PEMERIKSAAN BANK ─────────────────────────────
        try {
            const bankRes  = await fetchJson(`/api/audit-detail/bank?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const bankRows = bankRes.data ?? bankRes ?? [];
            const bankWithSel = [];
            for (const b of bankRows) {
                const saldoBuku = Number(b.saldo_buku ?? b.saldoBuku ?? 0);
                const saldoRk   = Number(b.saldo_bank ?? b.saldoBank ?? b.saldo_rk ?? b.saldoRk ?? 0);
                const selisih   = saldoRk - saldoBuku;
                if (selisih !== 0) {
                    bankWithSel.push({ nama: b.nama_bank ?? b.namaBank ?? '-', saldoBuku, saldoRk, selisih });
                }
            }
            if (bankWithSel.length > 0) {
                const rows = [];
                rows.push(`  ${'Nama Bank'.padEnd(20)} ${'Saldo Buku'.padStart(16)} ${'Saldo Rek. Koran'.padStart(18)} ${'Selisih'.padStart(14)}`);
                rows.push(`  ${'─'.repeat(70)}`);
                for (const b of bankWithSel) {
                    const selStr = (b.selisih > 0 ? '+' : '') + fmtRp(b.selisih);
                    rows.push(`  ${b.nama.padEnd(20)} ${fmtRp(b.saldoBuku).padStart(16)} ${fmtRp(b.saldoRk).padStart(18)} ${selStr.padStart(14)}`);
                }
                blocks.push(`2. PEMERIKSAAN BANK\n${rows.join('\n')}`);
            }
        } catch {}

        // ── 3. CEK FISIK SMH ────────────────────────────────
        // Hanya masuk rekomendasi jika ada unit TIDAK ditemukan > 0
        try {
            const smhRes  = await fetchJson(`/api/audit-detail/smh?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const smhRows = smhRes.data ?? smhRes ?? [];
            let totalUnit = 0, totalTemukan = 0;
            for (const s of smhRows) {
                totalUnit    += Number(s.totalUnit ?? s.total_unit ?? 0);
                totalTemukan += Number(s.ditemukan ?? s.totalDitemukan ?? 0);
            }
            const tidakTemukan = totalUnit - totalTemukan;
            if (totalUnit > 0 && tidakTemukan > 0) {
                const rows = [
                    `  • Total unit diperiksa : ${totalUnit}`,
                    `  • Ditemukan            : ${totalTemukan}`,
                    `  • Tidak ditemukan      : ${tidakTemukan} unit`,
                ];
                blocks.push(`3. CEK FISIK SMH\n${rows.join('\n')}`);
            }
        } catch {}

        // ── 3. PERLENGKAPAN SMH — rekap gabungan per jenis ──
        try {
            const [smhSumRes, luarRes] = await Promise.all([
                fetchJson(`/api/audit-detail/perlengkapan/smh-summary?plan_audit_id=${activePlanId}`, { headers: authHeaders() }),
                fetchJson(`/api/audit-detail/perlengkapan?plan_audit_id=${activePlanId}`, { headers: authHeaders() }),
            ]);
            const smhMap = {};
            for (const r of (smhSumRes.data ?? [])) {
                const nm = (r.nama || '').trim();
                if (nm) smhMap[nm] = { smhSaldo: Number(r.total ?? 0), smhFisik: Number(r.ada ?? 0) };
            }
            const luarMap = {};
            for (const p of (luarRes.data ?? [])) {
                const nm = (p.jenisPerlengkapan ?? p.jenis_perlengkapan ?? p.jenis ?? '').trim();
                if (!nm) continue;
                if (!luarMap[nm]) luarMap[nm] = { luarSelisih: 0 };
                luarMap[nm].luarSelisih += Number(p.selisih ?? 0);
            }
            const allJenis = [...new Set([...Object.keys(smhMap), ...Object.keys(luarMap)])].sort();
            const rows = [];
            let grandSel = 0;
            for (const jenis of allJenis) {
                const smhD  = smhMap[jenis]  ?? { smhSaldo: 0, smhFisik: 0 };
                const luarD = luarMap[jenis] ?? { luarSelisih: 0 };
                const totalSel = (smhD.smhFisik - smhD.smhSaldo) + luarD.luarSelisih;
                grandSel += totalSel;
                if (totalSel !== 0) rows.push(`  • ${jenis.padEnd(26)} selisih: ${totalSel}`);
            }
            if (rows.length) blocks.push(`4. PERLENGKAPAN SMH\n${rows.join('\n')}\n  ${'─'.repeat(40)}\n  Total selisih: ${grandSel}`);
        } catch {}

        // ── 5. MATERAI ──────────────────────────────────────
        try {
            const matRes  = await fetchJson(`/api/audit-detail/materai?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const matRows = matRes.data ?? matRes ?? [];
            const matWithSel = [];
            for (const m of matRows) {
                const saldoBuku = Number(m.saldoAkhir ?? m.saldo_akhir ?? 0);
                const fisik     = Number(m.fisik ?? 0);
                const selisih   = fisik - saldoBuku;
                if (selisih !== 0) matWithSel.push({ jenis: m.jenisMaterai ?? m.jenis_materai ?? '-', saldoBuku, fisik, selisih });
            }
            if (matWithSel.length > 0) {
                const rows = [];
                rows.push(`  ${'Jenis Materai'.padEnd(20)} ${'Saldo Buku'.padStart(12)} ${'Fisik'.padStart(8)} ${'Selisih'.padStart(10)}`);
                rows.push(`  ${'─'.repeat(52)}`);
                for (const m of matWithSel) {
                    rows.push(`  ${m.jenis.padEnd(20)} ${String(m.saldoBuku).padStart(12)} ${String(m.fisik).padStart(8)} ${((m.selisih > 0 ? '+' : '') + m.selisih).padStart(10)}`);
                }
                blocks.push(`5. MATERAI\n${rows.join('\n')}`);
            }
        } catch {}

        // ── 5. CEK FISIK ────────────────────────────────────
        try {
            const cfRes   = await fetchJson(`/api/audit-detail/cek-fisik?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const cf      = cfRes.data ?? cfRes ?? {};
            const cfItems = cf.items_json ?? cf.itemsJson ?? cf.items ?? [];
            let total = 0, tidakAda = 0;
            for (const it of cfItems) { total++; if (!(it.ada ?? it.ditemukan ?? true)) tidakAda++; }
            if (tidakAda > 0)
                blocks.push(`6. CEK FISIK\n  • Tidak ditemukan: ${tidakAda} dari ${total} item`);
        } catch {}

        // ── 6. HGP & AHM OILS ───────────────────────────────
        try {
            const hgpRes   = await fetchJson(`/api/audit-detail/hgp?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const hgp      = hgpRes.data ?? hgpRes ?? {};
            const hgpItems = hgp.items_json ?? hgp.itemsJson ?? hgp.items ?? [];
            let cntSel = 0, totalNilai = 0;
            const rows = [];
            for (const it of hgpItems) {
                const sel   = Number(it.selisih ?? 0);
                const harga = Number(it.hargaHet ?? it.harga_het ?? 0);
                if (sel !== 0) {
                    cntSel++;
                    totalNilai += Math.abs(sel) * harga;
                    const nama = (it.sparepart || it.noPart || '-').substring(0, 28);
                    rows.push(`  • ${nama.padEnd(30)} sel: ${sel}  nilai: ${fmtRp(Math.abs(sel) * harga)}`);
                }
            }
            if (cntSel > 0) {
                blocks.push(`7. HGP & AHM OILS\n  • Item selisih : ${cntSel}\n  • Total nilai  : ${fmtRp(totalNilai)}\n${rows.slice(0, 12).join('\n')}`);
            }
        } catch {}

        // ── 7. MEKANIK TOOLS (MT) ───────────────────────────
        try {
            const mtRes     = await fetchJson(`/api/audit-detail/mt?plan_audit_id=${activePlanId}`, { headers: authHeaders() });
            const mt        = mtRes.data ?? mtRes ?? {};
            const mtEntries = mt.entries ?? mt.items_json ?? mt.itemsJson ?? [];
            const mtSel     = mt.mekanikSelectedJenis ?? {};
            const rows = [];
            for (const entry of mtEntries) {
                const mekanik = entry.mekanik || '-';
                if ((entry.jenis || '') !== (mtSel[mekanik] || 'baru')) continue;
                const rusak  = (entry.rusak  ?? []);
                const hilang = (entry.hilang ?? []);
                if (!rusak.length && !hilang.length) continue;
                rows.push(`  • ${mekanik}`);
                if (rusak.length)  rows.push(`    Rusak  (${rusak.length})  : ${rusak.map(t => t.nama || t).join(', ')}`);
                if (hilang.length) rows.push(`    Hilang (${hilang.length}) : ${hilang.map(t => t.nama || t).join(', ')}`);
            }
            if (rows.length) blocks.push(`8. MEKANIK TOOLS (MT)\n${rows.join('\n')}`);
        } catch {}

        isiEl.value = blocks.length
            ? blocks.join('\n\n' + sep + '\n\n')
            : '(Tidak ada temuan signifikan dari data pemeriksaan)';
    } catch {
        isiEl.value = '';
    }
}

function rekomendasiHideForm() {
    _rekomendasiEditId = null;
    document.getElementById('rekomendasiForm')?.classList.add('hidden');
}

async function rekomendasiEdit(id) {
    try {
        const res = await fetchJson('/api/recommendations/' + id, { headers: authHeaders() });
        const r   = res.data ?? res;
        rekomendasiShowForm(id);
        document.getElementById('rekomendasiIsi').value      = r.judul || '';
        document.getElementById('rekomendasiTglAudit').value = r.tglAudit || document.getElementById('rekomendasiTglAudit').value;
    } catch (e) {
        rekomendasiAlert(e.message, 'error');
    }
}

async function rekomendasiDelete(id) {
    if (!confirm('Hapus rekomendasi ini?')) return;
    try {
        await fetchJson('/api/recommendations/' + id, { method: 'DELETE', headers: authHeaders() });
        await rekomendasiLoadList();
    } catch (e) {
        rekomendasiAlert(e.message, 'error');
    }
}

async function rekomendasiSave() {
    const judul = (document.getElementById('rekomendasiIsi')?.value ?? '').trim();
    if (!judul) { rekomendasiAlert('Isi Rekomendasi wajib diisi.', 'error'); return; }

    const fileInput = document.getElementById('rekomendasiFileInput');
    const file      = fileInput?.files?.[0] || null;
    const fileName  = file ? file.name : null;

    const payload = {
        plan_audit_id: activePlanId,
        judul,
        prioritas: 'sedang',
        status:    'open',
        deskripsi: fileName ? 'Lampiran: ' + fileName : null,
    };

    const btn = document.getElementById('rekomendasiSimpanBtn');
    if (btn) { btn.textContent = 'Menyimpan...'; btn.disabled = true; }
    try {
        const headers = authHeaders({ 'Content-Type': 'application/json' });
        if (_rekomendasiEditId) {
            await fetchJson('/api/recommendations/' + _rekomendasiEditId, { method: 'PUT', headers, body: JSON.stringify(payload) });
        } else {
            await fetchJson('/api/recommendations', { method: 'POST', headers, body: JSON.stringify(payload) });
        }
        rekomendasiHideForm();
        await rekomendasiLoadList();
        rekomendasiAlert('Rekomendasi tersimpan.', 'success');
    } catch (e) {
        rekomendasiAlert(e.message, 'error');
    } finally {
        if (btn) { btn.textContent = 'Save'; btn.disabled = false; }
    }
}

function rekomendasiAlert(msg, type = 'success') {
    const el = document.getElementById('rekomendasiAlert');
    if (!el) return;
    el.textContent = msg;
    el.className = 'rounded-xl border px-4 py-3 text-sm ' + (
        type === 'error'
            ? 'border-red-500/30 bg-red-500/10 text-red-200'
            : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
    );
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 4000);
}

function initRekomendasiForm() {
    document.getElementById('rekomendasiTambahBtn')?.addEventListener('click', () => rekomendasiShowForm());
    document.getElementById('rekomendasiBatalBtn')?.addEventListener('click', rekomendasiHideForm);
    document.getElementById('rekomendasiSimpanBtn')?.addEventListener('click', rekomendasiSave);

    // File input: tampilkan nama file yang dipilih
    document.getElementById('rekomendasiFileInput')?.addEventListener('change', function () {
        const nameEl = document.getElementById('rekomendasiFileName');
        if (!nameEl) return;
        if (this.files?.[0]) {
            nameEl.textContent = this.files[0].name;
            nameEl.classList.remove('hidden');
        } else {
            nameEl.classList.add('hidden');
        }
    });

    // Drag & drop
    const dz = document.getElementById('rekomendasiDropzone');
    if (dz) {
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('border-blue-400'); });
        dz.addEventListener('dragleave', () => dz.classList.remove('border-blue-400'));
        dz.addEventListener('drop', e => {
            e.preventDefault();
            dz.classList.remove('border-blue-400');
            const fi = document.getElementById('rekomendasiFileInput');
            if (fi && e.dataTransfer.files.length) {
                fi.files = e.dataTransfer.files;
                fi.dispatchEvent(new Event('change'));
            }
        });
    }
}

// ── BU Performance Tab ────────────────────────────────────────────────────────
async function loadBupTab() {
    if (!activePlanId) return;
    const plan = activePlan || {};
    // Auto-set bulan dari tgl_mulai plan
    const tglMulai = plan.tglMulai || plan.tgl_mulai || plan.tglPlan || '';
    if (tglMulai) {
        const d = new Date(tglMulai);
        if (!isNaN(d)) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const bupEl = document.getElementById('bupTabBulan');
            if (bupEl && !bupEl.value) bupEl.value = `${y}-${m}`;
        }
    }
    await bupTabLoadList();
}

async function bupTabLoadList() {
    const tbody = document.getElementById('bupTabTableBody');
    if (!tbody) return;
    try {
        const res  = await fetchJson('/api/bu-performance', { headers: authHeaders() });
        const rows = res.data ?? [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada data.</td></tr>';
            return;
        }
        let html = '';
        rows.forEach(r => {
            const penilaian = r.penilaian ?? [];
            const items = penilaian.length ? penilaian : [{ pic: '-', jabatan: '-', uraian: 'TIDAK ADA MASALAH' }];
            items.forEach((p, idx) => {
                const uraianCls = p.uraian && p.uraian !== 'TIDAK ADA MASALAH' ? 'text-amber-300' : 'text-slate-400 italic';
                html += `<tr class="hover:bg-slate-800/40">
                    <td class="px-4 py-2 border-r border-slate-800 font-medium text-slate-200">${idx === 0 ? escapeHtml(r.unitUsaha) : ''}</td>
                    <td class="px-4 py-2 border-r border-slate-800 text-slate-400">${idx === 0 ? escapeHtml(r.auditor ?? '-') : ''}</td>
                    <td class="px-4 py-2 border-r border-slate-800 text-slate-300">${escapeHtml(p.pic ?? '-')}</td>
                    <td class="px-4 py-2 border-r border-slate-800 text-slate-300">${escapeHtml(p.jabatan ?? '-')}</td>
                    <td class="px-4 py-2 border-r border-slate-800 ${uraianCls}">${escapeHtml(p.uraian ?? '-')}</td>
                    <td class="px-3 py-2 text-center">
                        ${idx === 0 ? `<span class="text-emerald-400 font-bold">&#10003;</span>
                        <button onclick="bupTabDelete(${r.id})" class="ml-1 text-xs text-red-400 hover:text-red-300">&times;</button>` : ''}
                    </td>
                </tr>`;
            });
        });
        tbody.innerHTML = html;
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-red-400">${escapeHtml(e.message)}</td></tr>`;
    }
}

function bupTabOpenForm() {
    document.getElementById('bupTabForm')?.classList.remove('hidden');
    document.getElementById('bupTabInputBody').innerHTML = '';

    // Ambil data dari activePlan
    const plan     = activePlan || {};
    const unitUsaha = plan.cabang || '';
    const tim       = [plan.kepalaTim, ...(plan.tim || [])].filter(Boolean);
    const auditor   = tim.join('/');

    // 1 baris pre-filled, sisanya kosong
    bupTabAddRow({ unitUsaha, auditor });
    bupTabAddRow({ unitUsaha, auditor });
    bupTabAddRow({ unitUsaha, auditor });
}

function bupTabCloseForm() {
    document.getElementById('bupTabForm')?.classList.add('hidden');
}

function bupTabAddRow(data = {}) {
    const tbody = document.getElementById('bupTabInputBody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.className = 'bup-tab-row';
    tr.innerHTML = `
        <td class="px-2 py-1.5"><input type="text" placeholder="Unit Usaha" value="${escapeHtml(data.unitUsaha ?? '')}" class="bup-t-unit w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500"></td>
        <td class="px-2 py-1.5"><input type="text" placeholder="Auditor" value="${escapeHtml(data.auditor ?? '')}" class="bup-t-auditor w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500"></td>
        <td class="px-2 py-1.5"><input type="text" placeholder="PIC" value="${escapeHtml(data.pic ?? '')}" class="bup-t-pic w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500"></td>
        <td class="px-2 py-1.5"><input type="text" placeholder="Jabatan" value="${escapeHtml(data.jabatan ?? '')}" class="bup-t-jabatan w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500"></td>
        <td class="px-2 py-1.5"><input type="text" placeholder="Uraian..." value="${escapeHtml(data.uraian ?? '')}" class="bup-t-uraian w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500"></td>
        <td class="px-2 py-1.5 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-300">&times;</button></td>
    `;
    tbody.appendChild(tr);
}

async function bupTabSave() {
    const bulanRaw = document.getElementById('bupTabBulan')?.value;
    if (!bulanRaw) { bupTabAlert('Bulan wajib diisi.', 'error'); return; }

    const rows = [];
    document.querySelectorAll('.bup-tab-row').forEach(tr => {
        const unitUsaha = tr.querySelector('.bup-t-unit')?.value.trim();
        if (!unitUsaha) return;
        rows.push({
            unitUsaha,
            auditor:  tr.querySelector('.bup-t-auditor')?.value.trim() || null,
            penilaian: [{ pic: tr.querySelector('.bup-t-pic')?.value.trim() || '-', jabatan: tr.querySelector('.bup-t-jabatan')?.value.trim() || '-', uraian: tr.querySelector('.bup-t-uraian')?.value.trim() || 'TIDAK ADA MASALAH' }],
        });
    });
    if (!rows.length) { bupTabAlert('Tambahkan minimal 1 baris.', 'error'); return; }

    const [y, m] = bulanRaw.split('-');
    const names  = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const bulan  = (names[parseInt(m, 10) - 1] ?? m) + ' ' + y;

    const btn = document.getElementById('bupTabSaveBtn');
    if (btn) { btn.textContent = 'Menyimpan...'; btn.disabled = true; }
    try {
        await fetchJson('/api/bu-performance', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ bulan, rows }),
        });
        bupTabCloseForm();
        await bupTabLoadList();
        bupTabAlert('Data BU Performance tersimpan.', 'success');
    } catch (e) {
        bupTabAlert(e.message, 'error');
    } finally {
        if (btn) { btn.textContent = 'Simpan'; btn.disabled = false; }
    }
}

async function bupTabDelete(id) {
    if (!confirm('Hapus data ini?')) return;
    try {
        await fetchJson('/api/bu-performance/' + id, { method: 'DELETE', headers: authHeaders() });
        await bupTabLoadList();
    } catch (e) {
        bupTabAlert(e.message, 'error');
    }
}

function bupTabAlert(msg, type = 'success') {
    const el = document.getElementById('bupTabAlert');
    if (!el) return;
    el.textContent = msg;
    el.className = 'rounded-xl border px-4 py-3 text-sm ' + (type === 'error' ? 'border-red-500/30 bg-red-500/10 text-red-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200');
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 4000);
}

function initBupTabForm() {
    document.getElementById('bupTabTambahBtn')?.addEventListener('click', bupTabOpenForm);
    document.getElementById('bupTabCancelBtn')?.addEventListener('click', bupTabCloseForm);
    document.getElementById('bupTabSaveBtn')?.addEventListener('click', bupTabSave);
    document.getElementById('bupTabAddRowBtn')?.addEventListener('click', () => bupTabAddRow());
}

// Expose functions needed by inline onclick handlers (Vite bundles as ES module)
window.gradingOpenDetailModal  = gradingOpenDetailModal;
window.gradingDeleteDetail     = gradingDeleteDetail;
window.gradingOpenPicaModal    = gradingOpenPicaModal;
window.gradingClosePicaModal   = gradingClosePicaModal;
window.gradingSavePicaModal    = gradingSavePicaModal;
window.gradingRemoveFraudTag   = gradingRemoveFraudTag;
window.rekomendasiEdit         = rekomendasiEdit;
window.rekomendasiDelete       = rekomendasiDelete;
window.bupTabDelete            = bupTabDelete;
