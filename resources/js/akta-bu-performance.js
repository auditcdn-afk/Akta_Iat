// ─── Session / Auth ───────────────────────────────────────────────────────────
const SESSION_KEY = 'akta_session';
function getSession() {
    try { return JSON.parse(sessionStorage.getItem(SESSION_KEY)); } catch { return null; }
}
function authHeaders(extra = {}) {
    const s = getSession();
    return { Accept: 'application/json', Authorization: `${s?.tokenType || 'Bearer'} ${s?.token}`, ...extra };
}
async function fetchJson(url, options = {}) {
    const res     = await fetch(url, options);
    const payload = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(Object.values(payload.errors ?? {}).flat()[0] || payload.message || 'Request gagal.');
    return payload;
}

// ─── State ────────────────────────────────────────────────────────────────────
let _allRows = [];

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (!getSession()) { window.location.href = '/akta/login'; return; }
    loadBulanOptions();
    loadList();

    document.getElementById('bupSearch')?.addEventListener('input', renderTable);
    document.getElementById('bupBulanFilter')?.addEventListener('change', renderTable);
    document.getElementById('bupUnitFilter')?.addEventListener('change', renderTable);
    document.getElementById('bupExportFilterBtn')?.addEventListener('click', () => exportCsv(getFiltered(), 'bu-performance-tampilan'));
    document.getElementById('bupExportAllBtn')?.addEventListener('click', () => exportCsv(_allRows, 'bu-performance-semua'));
});

// ─── API ──────────────────────────────────────────────────────────────────────
async function loadList() {
    try {
        const res = await fetchJson('/api/bu-performance', { headers: authHeaders() });
        _allRows = res.data ?? [];
        loadUnitOptions();
        renderTable();
    } catch (e) {
        showAlert('Gagal memuat data: ' + e.message, 'error');
        setTableEmpty('Gagal memuat data.');
    }
}

async function loadBulanOptions() {
    try {
        const res = await fetchJson('/api/bu-performance/bulans', { headers: authHeaders() });
        const sel = document.getElementById('bupBulanFilter');
        if (!sel) return;
        (res.data ?? []).forEach(b => {
            const opt = document.createElement('option');
            opt.value = b; opt.textContent = b;
            sel.appendChild(opt);
        });
    } catch (_) {}
}

function loadUnitOptions() {
    const sel = document.getElementById('bupUnitFilter');
    if (!sel) return;
    const current = sel.value;
    const units = [...new Set(_allRows.map(r => r.unitUsaha).filter(Boolean))].sort();
    sel.innerHTML = '<option value="">Semua Unit Usaha</option>' +
        units.map(u => `<option value="${esc(u)}">${esc(u)}</option>`).join('');
    sel.value = current;
}

async function deleteRow(id) {
    if (!confirm('Hapus data ini?')) return;
    try {
        await fetchJson('/api/bu-performance/' + id, { method: 'DELETE', headers: authHeaders() });
        await loadList();
        showAlert('Data dihapus.', 'success');
    } catch (e) {
        showAlert(e.message, 'error');
    }
}

// ─── Table Render ─────────────────────────────────────────────────────────────
function getFiltered() {
    const q     = (document.getElementById('bupSearch')?.value ?? '').toLowerCase();
    const bulan = document.getElementById('bupBulanFilter')?.value ?? '';
    const unit  = document.getElementById('bupUnitFilter')?.value ?? '';

    return _allRows.filter(r => {
        if (bulan && r.bulan !== bulan) return false;
        if (unit && r.unitUsaha !== unit) return false;
        if (q && ![(r.unitUsaha ?? ''), (r.auditor ?? '')].some(v => v.toLowerCase().includes(q))) return false;
        return true;
    });
}

function renderTable() {
    const rows  = getFiltered();
    const tbody = document.getElementById('bupTableBody');
    if (!tbody) return;

    if (!rows.length) { setTableEmpty('Tidak ada data BU Performance.'); return; }

    // Kelompokkan per bulan
    const byBulan = {};
    rows.forEach(r => {
        if (!byBulan[r.bulan]) byBulan[r.bulan] = [];
        byBulan[r.bulan].push(r);
    });

    let html = '';
    Object.entries(byBulan).forEach(([bulan, items]) => {
        html += `<tr class="bg-slate-800/80">
            <td colspan="6" class="px-4 py-2 text-xs font-bold uppercase tracking-wide text-blue-300">
                Bulan: ${esc(bulan)}
            </td>
        </tr>`;
        items.forEach(r => {
            const penilaian = r.penilaian ?? [];
            if (!penilaian.length) {
                html += tableRow(r, { pic: '-', jabatan: '-', uraian: 'TIDAK ADA MASALAH' }, true);
            } else {
                penilaian.forEach((p, idx) => {
                    html += tableRow(r, p, idx === 0);
                });
            }
        });
    });

    tbody.innerHTML = html;
}

function tableRow(r, p, showMeta) {
    const uraianClass = p.uraian && p.uraian !== 'TIDAK ADA MASALAH' ? 'text-amber-300' : 'text-slate-400 italic';
    return `<tr class="hover:bg-slate-800/40 transition-colors">
        <td class="px-4 py-2.5 border-r border-slate-800 font-medium text-slate-200">${showMeta ? esc(r.unitUsaha) : ''}</td>
        <td class="px-4 py-2.5 border-r border-slate-800 text-slate-400">${showMeta ? esc(r.auditor ?? '-') : ''}</td>
        <td class="px-4 py-2.5 border-r border-slate-800 text-slate-300">${esc(p.pic ?? '-')}</td>
        <td class="px-4 py-2.5 border-r border-slate-800 text-slate-300">${esc(p.jabatan ?? '-')}</td>
        <td class="px-4 py-2.5 border-r border-slate-800 ${uraianClass}">${esc(p.uraian ?? '-')}</td>
        <td class="px-3 py-2.5 text-center">
            ${showMeta ? `<button onclick="deleteRow(${r.id})" class="text-xs text-red-400 hover:text-red-300">&times;</button>` : ''}
        </td>
    </tr>`;
}

function setTableEmpty(msg) {
    const tbody = document.getElementById('bupTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-slate-500">${esc(msg)}</td></tr>`;
}

// ─── Export CSV ───────────────────────────────────────────────────────────────
function exportCsv(rows, filenamePrefix) {
    if (!rows.length) {
        showAlert('Tidak ada data untuk diexport.', 'error');
        return;
    }

    const header = ['Bulan', 'Unit Usaha', 'Auditor', 'PIC', 'Jabatan', 'Uraian'];
    const lines = [header.join(';')];

    rows.forEach(r => {
        const penilaian = r.penilaian?.length ? r.penilaian : [{ pic: '-', jabatan: '-', uraian: 'TIDAK ADA MASALAH' }];
        penilaian.forEach(p => {
            lines.push([
                csvCell(r.bulan), csvCell(r.unitUsaha), csvCell(r.auditor ?? '-'),
                csvCell(p.pic ?? '-'), csvCell(p.jabatan ?? '-'), csvCell(p.uraian ?? '-'),
            ].join(';'));
        });
    });

    const csvContent = '﻿' + lines.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    const stamp = new Date().toISOString().substring(0, 10);
    a.href = url;
    a.download = `${filenamePrefix}-${stamp}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

function csvCell(value) {
    const s = String(value ?? '').replaceAll('"', '""');
    return /[;"\n]/.test(s) ? `"${s}"` : s;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function showAlert(msg, type = 'success') {
    const el = document.getElementById('bupAlert');
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
window.deleteRow = deleteRow;
