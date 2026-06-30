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
    document.getElementById('bupTambahBtn')?.addEventListener('click', openForm);
    document.getElementById('bupFormCloseBtn')?.addEventListener('click', closeForm);
    document.getElementById('bupCancelBtn')?.addEventListener('click', closeForm);
    document.getElementById('bupSaveBtn')?.addEventListener('click', saveForm);
    document.getElementById('bupTambahRowBtn')?.addEventListener('click', () => addInputRow());
});

// ─── API ──────────────────────────────────────────────────────────────────────
async function loadList() {
    try {
        const res = await fetchJson('/api/bu-performance', { headers: authHeaders() });
        _allRows = res.data ?? [];
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

async function saveForm() {
    const bulan = document.getElementById('bupBulan')?.value;
    if (!bulan) { showAlert('Bulan wajib diisi.', 'error'); return; }

    const rows = collectInputRows();
    if (!rows.length) { showAlert('Tambahkan minimal 1 baris data.', 'error'); return; }

    // Format bulan: "2026-01" → "Januari 2026"
    const bulanLabel = formatBulanLabel(bulan);

    const btn = document.getElementById('bupSaveBtn');
    if (btn) { btn.textContent = 'Menyimpan...'; btn.disabled = true; }
    try {
        await fetchJson('/api/bu-performance', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ bulan: bulanLabel, rows }),
        });
        closeForm();
        await loadList();
        await loadBulanOptions();
        showAlert('Data BU Performance tersimpan.', 'success');
    } catch (e) {
        showAlert(e.message, 'error');
    } finally {
        if (btn) { btn.textContent = 'Simpan'; btn.disabled = false; }
    }
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

// ─── Form ─────────────────────────────────────────────────────────────────────
function openForm() {
    document.getElementById('bupForm')?.classList.remove('hidden');
    document.getElementById('bupInputBody').innerHTML = '';
    // Default 3 baris kosong
    addInputRow(); addInputRow(); addInputRow();
    document.getElementById('bupBulan')?.focus();
}

function closeForm() {
    document.getElementById('bupForm')?.classList.add('hidden');
}

function addInputRow(data = {}) {
    const tbody = document.getElementById('bupInputBody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.className = 'bup-input-row';
    tr.innerHTML = `
        <td class="px-2 py-1.5">
            <input type="text" placeholder="Unit Usaha" value="${esc(data.unitUsaha ?? '')}"
                class="bup-unit w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500">
        </td>
        <td class="px-2 py-1.5">
            <input type="text" placeholder="Auditor" value="${esc(data.auditor ?? '')}"
                class="bup-auditor w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500">
        </td>
        <td class="px-2 py-1.5">
            <input type="text" placeholder="PIC" value="${esc(data.penilaian?.[0]?.pic ?? '')}"
                class="bup-pic w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500">
        </td>
        <td class="px-2 py-1.5">
            <input type="text" placeholder="Jabatan" value="${esc(data.penilaian?.[0]?.jabatan ?? '')}"
                class="bup-jabatan w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500">
        </td>
        <td class="px-2 py-1.5">
            <input type="text" placeholder="Uraian / temuan..." value="${esc(data.penilaian?.[0]?.uraian ?? '')}"
                class="bup-uraian w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs text-slate-100 outline-none focus:border-blue-500">
        </td>
        <td class="px-2 py-1.5 text-center">
            <button type="button" onclick="this.closest('tr').remove()"
                class="text-red-400 hover:text-red-300 text-base leading-none">&times;</button>
        </td>
    `;
    tbody.appendChild(tr);
}

function collectInputRows() {
    const rows = [];
    document.querySelectorAll('.bup-input-row').forEach(tr => {
        const unitUsaha = tr.querySelector('.bup-unit')?.value.trim();
        if (!unitUsaha) return;
        rows.push({
            unitUsaha,
            auditor:   tr.querySelector('.bup-auditor')?.value.trim() || null,
            penilaian: [{
                pic:     tr.querySelector('.bup-pic')?.value.trim()     || '-',
                jabatan: tr.querySelector('.bup-jabatan')?.value.trim() || '-',
                uraian:  tr.querySelector('.bup-uraian')?.value.trim()  || 'TIDAK ADA MASALAH',
            }],
        });
    });
    return rows;
}

// ─── Table Render ─────────────────────────────────────────────────────────────
function getFiltered() {
    const q     = (document.getElementById('bupSearch')?.value ?? '').toLowerCase();
    const bulan = document.getElementById('bupBulanFilter')?.value ?? '';

    return _allRows.filter(r => {
        if (bulan && r.bulan !== bulan) return false;
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
            ${showMeta ? `<span class="text-emerald-400 font-bold">&#10003;</span>` : ''}
            ${showMeta ? `<button onclick="deleteRow(${r.id})" class="ml-2 text-xs text-red-400 hover:text-red-300">&times;</button>` : ''}
        </td>
    </tr>`;
}

function setTableEmpty(msg) {
    const tbody = document.getElementById('bupTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-slate-500">${esc(msg)}</td></tr>`;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatBulanLabel(monthInput) {
    // "2026-01" → "Januari 2026"
    const [year, month] = monthInput.split('-');
    const names = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return (names[parseInt(month, 10) - 1] ?? month) + ' ' + year;
}

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
