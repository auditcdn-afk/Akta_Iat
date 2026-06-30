// ─── Session / Auth ───────────────────────────────────────────────────────────
const SESSION_KEY = 'akta_session';

function getSession() {
    try { return JSON.parse(sessionStorage.getItem(SESSION_KEY)); } catch { return null; }
}

function authHeaders() {
    const s = getSession();
    return { Accept: 'application/json', Authorization: `${s?.tokenType || 'Bearer'} ${s?.token}` };
}

async function fetchJson(url, options = {}) {
    const res = await fetch(url, options);
    const payload = await res.json().catch(() => ({}));
    if (!res.ok) {
        const firstErr = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(firstErr || payload.message || 'Request gagal.');
    }
    return payload;
}

// ─── State ────────────────────────────────────────────────────────────────────
let _allRows    = [];
let _detailData = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (!getSession()) { window.location.href = '/akta/login'; return; }
    loadGradingList();
    loadWilayahOptions();

    document.getElementById('gradingSearch')?.addEventListener('input', renderTable);
    document.getElementById('gradingWilayahFilter')?.addEventListener('change', renderTable);
    document.getElementById('gradingJenisFilter')?.addEventListener('change', renderTable);
});

// ─── API ──────────────────────────────────────────────────────────────────────
async function loadGradingList() {
    try {
        const res = await fetchJson('/api/gradings', { headers: authHeaders() });
        _allRows = res.data ?? [];
        renderTable();
    } catch (e) {
        showAlert('Gagal memuat data: ' + e.message, 'error');
        setTableEmpty('Gagal memuat data grading.');
    }
}

async function loadWilayahOptions() {
    try {
        const res = await fetchJson('/api/audit-detail/grading/wilayah', { headers: authHeaders() });
        const sel = document.getElementById('gradingWilayahFilter');
        if (!sel) return;
        (res.data ?? []).forEach(w => {
            const opt = document.createElement('option');
            opt.value = w; opt.textContent = w;
            sel.appendChild(opt);
        });
    } catch (_) {}
}

async function openGradingDetail(id) {
    try {
        const res = await fetchJson('/api/gradings/' + id, { headers: authHeaders() });
        _detailData = res.data;
        if (!_detailData) { showAlert('Data tidak ditemukan.', 'error'); return; }
        renderDetailModal();
        const modal = document.getElementById('gradingDetailModal');
        modal?.classList.remove('hidden');
        modal?.classList.add('flex');
    } catch (e) {
        showAlert('Gagal memuat detail: ' + e.message, 'error');
    }
}

// ─── Table ────────────────────────────────────────────────────────────────────
function getFiltered() {
    const q       = (document.getElementById('gradingSearch')?.value ?? '').toLowerCase();
    const wilayah = (document.getElementById('gradingWilayahFilter')?.value ?? '').toLowerCase();
    const jenis   = (document.getElementById('gradingJenisFilter')?.value ?? '').toLowerCase();

    return _allRows.filter(r => {
        if (q && ![(r.cabang ?? ''), (r.noSpt ?? ''), (r.area ?? '')].some(v => v.toLowerCase().includes(q))) return false;
        if (wilayah && (r.area  ?? '').toLowerCase() !== wilayah) return false;
        if (jenis   && (r.jenis ?? '').toLowerCase() !== jenis)   return false;
        return true;
    });
}

function renderTable() {
    const rows = getFiltered();
    updateStats(rows);

    const tbody = document.getElementById('gradingTableBody');
    if (!tbody) return;

    if (!rows.length) { setTableEmpty('Tidak ada data grading yang ditemukan.'); return; }

    tbody.innerHTML = rows.map(r => {
        const nc = nilaiColor(r.totalNilai);
        const fraud = r.fraud === 'Y'
            ? '<span class="rounded-full bg-red-900/60 px-2 py-0.5 text-xs text-red-300">Ya</span>'
            : '<span class="text-xs text-slate-600">-</span>';
        const bbnkb = r.bbnkb === 'Y'
            ? '<span class="rounded-full bg-amber-900/60 px-2 py-0.5 text-xs text-amber-300">Ya</span>'
            : '<span class="text-xs text-slate-600">-</span>';
        const nilai = r.totalNilai != null ? Number(r.totalNilai).toFixed(2) : '-';

        return `<tr class="hover:bg-slate-800/50 transition-colors cursor-default">
            <td class="px-4 py-3 font-medium text-slate-200">${esc(r.cabang)}</td>
            <td class="px-4 py-3 text-slate-300 font-mono text-xs">${esc(r.noSpt)}</td>
            <td class="px-4 py-3 text-slate-400">${esc(r.cabangArea ?? r.area ?? '-')}</td>
            <td class="px-4 py-3 text-slate-400">${esc(r.jenis ?? '-')}</td>
            <td class="px-4 py-3 text-slate-400 text-xs">${fmtDate(r.tglMulai)}</td>
            <td class="px-4 py-3 text-slate-400 text-xs">${fmtDate(r.tglSelesai)}</td>
            <td class="px-4 py-3 text-center font-bold font-mono ${nc}">${nilai}</td>
            <td class="px-4 py-3 text-center text-slate-400">${r.itemCount ?? 0}</td>
            <td class="px-4 py-3 text-center">${fraud}</td>
            <td class="px-4 py-3 text-center">${bbnkb}</td>
            <td class="px-4 py-3 text-center">
                <button onclick="openGradingDetail(${r.id})"
                    class="rounded-lg bg-blue-700/70 px-3 py-1 text-xs font-semibold text-blue-200 hover:bg-blue-600 transition">
                    Detail
                </button>
            </td>
        </tr>`;
    }).join('');
}

function setTableEmpty(msg) {
    const tbody = document.getElementById('gradingTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="11" class="py-12 text-center text-slate-500">${esc(msg)}</td></tr>`;
}

function updateStats(rows) {
    const fraud  = rows.filter(r => r.fraud === 'Y').length;
    const bbnkb  = rows.filter(r => r.bbnkb === 'Y').length;
    const nilais = rows.filter(r => r.totalNilai != null).map(r => Number(r.totalNilai));
    const avg    = nilais.length ? (nilais.reduce((a, b) => a + b, 0) / nilais.length).toFixed(2) : '-';

    setText('gStatTotal', rows.length);
    setText('gStatAvg',   avg);
    setText('gStatFraud', fraud);
    setText('gStatBbnkb', bbnkb);
}

// ─── Detail Modal ─────────────────────────────────────────────────────────────
function renderDetailModal() {
    const d = _detailData;
    if (!d) return;

    setText('gdmTitle',    d.cabang + ' — Grading');
    setText('gdmSubtitle', 'No SPT: ' + (d.noSpt ?? '-') + ' | ' + (d.jenisAudit ?? d.jenis ?? '-') + ' | ' + (d.cabangArea ?? d.area ?? '-'));
    setText('gdmFooterInfo', 'Terakhir diperbarui: ' + (d.updatedAt ?? '-'));

    const info = document.getElementById('gdmInfo');
    if (info) {
        info.innerHTML = [
            ['Jenis Grading', d.jenis ?? '-'],
            ['Total Nilai',   d.totalNilai != null ? Number(d.totalNilai).toFixed(2) : '-'],
            ['Fraud',         d.fraud === 'Y' ? 'Ya' : 'Tidak'],
            ['BBNKB',         d.bbnkb === 'Y' ? 'Ya' : 'Tidak'],
        ].map(([label, val]) =>
            `<div class="rounded-xl border border-slate-700 bg-slate-800 p-3">
                <div class="text-xs text-slate-500">${label}</div>
                <div class="mt-1 font-semibold text-slate-200">${esc(val)}</div>
            </div>`
        ).join('');
    }

    renderDetailTab(d);
    renderAnalisaTab(d);
    renderPicaTab(d);
    gradingDetailTab('detail');
}

function renderDetailTab(d) {
    const tbody = document.getElementById('gdmDetailBody');
    if (!tbody) return;
    const details = d.details ?? [];
    if (!details.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="py-6 text-center text-slate-500">Tidak ada item detail.</td></tr>';
        return;
    }
    tbody.innerHTML = details.map(item => {
        const low = picaIsLow(item.hasil ?? '');
        return `<tr class="${low ? 'text-red-300' : 'text-slate-300'}">
            <td class="py-2 pr-4 text-sm">${esc(item.namaPemeriksaan ?? '-')}</td>
            <td class="py-2 pr-4 text-sm">${esc(item.hasil ?? '-')}</td>
            <td class="py-2 text-right font-mono text-sm">${item.nilai != null ? Number(item.nilai).toFixed(2) : '-'}</td>
        </tr>`;
    }).join('');
}

function renderAnalisaTab(d) {
    const details = d.details ?? [];
    const nilais  = details.map(i => Number(i.nilai ?? 0));

    const distribusi = document.getElementById('gdmAnalisaDistribusi');
    if (distribusi) {
        const total = nilais.reduce((a, b) => a + b, 0);
        const max   = nilais.length ? Math.max(...nilais) : 0;
        const min   = nilais.length ? Math.min(...nilais) : 0;
        distribusi.innerHTML = `
            <div class="flex justify-between py-1 border-b border-slate-700"><span class="text-slate-500">Total Item</span><span>${details.length}</span></div>
            <div class="flex justify-between py-1 border-b border-slate-700"><span class="text-slate-500">Total Nilai</span><span class="font-bold text-blue-300">${total.toFixed(2)}</span></div>
            <div class="flex justify-between py-1 border-b border-slate-700"><span class="text-slate-500">Nilai Tertinggi</span><span class="text-emerald-400">${max.toFixed(2)}</span></div>
            <div class="flex justify-between py-1 border-b border-slate-700"><span class="text-slate-500">Nilai Terendah</span><span class="text-red-400">${min.toFixed(2)}</span></div>
            <div class="flex justify-between py-1 border-b border-slate-700"><span class="text-slate-500">Fraud</span><span class="${d.fraud==='Y'?'text-red-400':'text-slate-400'}">${d.fraud==='Y'?'Ya':'Tidak'}</span></div>
            <div class="flex justify-between py-1"><span class="text-slate-500">BBNKB</span><span class="${d.bbnkb==='Y'?'text-amber-400':'text-slate-400'}">${d.bbnkb==='Y'?'Ya':'Tidak'}</span></div>
        `;
    }

    const temuan = document.getElementById('gdmAnalisaTemuan');
    if (temuan) {
        const list = [];
        if (d.fraud === 'Y') {
            const jenis = (d.jenisFraud ?? []).join(', ') || '-';
            list.push(`<div class="text-red-400">&#9679; Indikasi fraud: ${esc(jenis)}</div>`);
            if (d.keteranganFraud) list.push(`<div class="pl-4 text-red-300 text-xs mt-1">${esc(d.keteranganFraud)}</div>`);
        }
        if (d.bbnkb === 'Y') list.push('<div class="text-amber-400">&#9679; Terdapat temuan BBNKB</div>');
        const lowCount = details.filter(i => picaIsLow(i.hasil ?? '')).length;
        if (lowCount) list.push(`<div class="text-orange-400">&#9679; ${lowCount} item nilai rendah (perlu PICA)</div>`);
        if (!list.length) list.push('<div class="text-slate-500">Tidak ada temuan khusus.</div>');
        temuan.innerHTML = list.join('');
    }

    const rendah = document.getElementById('gdmAnalisaRendah');
    if (rendah) {
        const lowItems = details.filter(i => picaIsLow(i.hasil ?? ''));
        if (!lowItems.length) {
            rendah.innerHTML = '<div class="text-slate-500 text-sm">Semua item memiliki nilai baik.</div>';
        } else {
            rendah.innerHTML = lowItems.map(i =>
                `<div class="flex justify-between rounded-lg bg-red-900/20 px-3 py-2 text-sm">
                    <span>${esc(i.namaPemeriksaan ?? '-')}</span>
                    <span class="font-mono ml-4 shrink-0">${esc(i.hasil ?? '-')} (${Number(i.nilai ?? 0).toFixed(2)})</span>
                </div>`
            ).join('');
        }
    }
}

function renderPicaTab(d) {
    const el = document.getElementById('gdmPicaList');
    if (!el) return;
    const lowItems = (d.details ?? []).filter(i => picaIsLow(i.hasil ?? ''));
    if (!lowItems.length) {
        el.innerHTML = '<div class="text-slate-500 text-sm">Tidak ada item yang memerlukan PICA.</div>';
        return;
    }
    el.innerHTML = `<p class="text-xs text-slate-500 mb-3">Item berikut memiliki nilai rendah dan perlu tindak lanjut PICA:</p>` +
        lowItems.map(i =>
            `<div class="rounded-lg border border-red-900/40 bg-red-900/10 px-4 py-3 text-sm">
                <div class="font-medium text-red-300">${esc(i.namaPemeriksaan ?? '-')}</div>
                <div class="text-xs text-red-400 mt-0.5">Hasil: ${esc(i.hasil ?? '-')} — Nilai: ${Number(i.nilai ?? 0).toFixed(2)}</div>
            </div>`
        ).join('');
}

// ─── Tab Switch ───────────────────────────────────────────────────────────────
function gradingDetailTab(tab) {
    ['detail', 'analisa', 'pica'].forEach(t => {
        const panel = document.getElementById('gdmPanel' + cap(t));
        const btn   = document.getElementById('gdmTab'   + cap(t));
        const on    = t === tab;
        panel?.classList.toggle('hidden', !on);
        if (btn) {
            btn.classList.toggle('text-blue-400',       on);
            btn.classList.toggle('border-blue-400',     on);
            btn.classList.toggle('text-slate-400',      !on);
            btn.classList.toggle('border-transparent',  !on);
        }
    });
}

function gradingCloseDetail() {
    const modal = document.getElementById('gradingDetailModal');
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    _detailData = null;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function picaIsLow(str) { return /^\s*[12]\s*\./.test(str); }

function nilaiColor(val) {
    if (val == null) return 'text-slate-400';
    const n = Number(val);
    if (n >= 80) return 'text-emerald-400';
    if (n >= 60) return 'text-amber-400';
    return 'text-red-400';
}

function fmtDate(str) {
    if (!str) return '-';
    try { return new Date(str).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }); }
    catch { return str; }
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

function showAlert(msg, type = 'success') {
    const el = document.getElementById('gradingAlert');
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

// ─── Window exports ───────────────────────────────────────────────────────────
window.openGradingDetail  = openGradingDetail;
window.gradingDetailTab   = gradingDetailTab;
window.gradingCloseDetail = gradingCloseDetail;
