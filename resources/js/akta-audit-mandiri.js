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
function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function showAlert(message, type = 'success') {
    const el = document.getElementById('amAlert');
    if (!el) return;
    el.textContent = message;
    el.className = `rounded-xl border px-4 py-3 text-sm ${type === 'error' ? 'border-red-500/30 bg-red-500/10 text-red-300' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'}`;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 4000);
}

// ─── State ────────────────────────────────────────────────────────────────────
let _plans = [];
let _jenisPemeriksaan = 'audit_mandiri';

const JENIS_PEMERIKSAAN_LABEL = {
    audit_mandiri: 'Audit Mandiri',
    sertijab: 'Sertijab',
};

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (!getSession()) { window.location.href = '/akta/login'; return; }

    loadPlans();

    document.querySelectorAll('.am-jenis-pemeriksaan-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            _jenisPemeriksaan = btn.dataset.value;
            document.getElementById('amJenisPemeriksaan').value = _jenisPemeriksaan;
            document.querySelectorAll('.am-jenis-pemeriksaan-btn').forEach((b) => {
                const active = b === btn;
                b.classList.toggle('bg-blue-600', active);
                b.classList.toggle('border-blue-600', active);
                b.classList.toggle('text-white', active);
                b.classList.toggle('border-slate-700', !active);
                b.classList.toggle('text-slate-300', !active);
            });
        });
    });

    document.getElementById('amForm')?.addEventListener('submit', (e) => {
        createPlan(e).catch((err) => showAlert(err.message, 'error'));
    });

    document.getElementById('amSearch')?.addEventListener('input', renderTable);

    document.getElementById('amTableBody')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.am-delete-btn');
        if (!btn) return;
        deletePlan(btn.dataset.id).catch((err) => showAlert(err.message, 'error'));
    });
});

// ─── API ──────────────────────────────────────────────────────────────────────
async function loadPlans() {
    try {
        const res = await fetchJson('/api/plan-audit-mandiri', { headers: authHeaders() });
        _plans = res.data ?? [];
        renderTable();
    } catch (e) {
        showAlert(e.message, 'error');
    }
}

async function createPlan(e) {
    e.preventDefault();

    const jenisAudit = document.getElementById('amJenisAudit').value;
    if (!jenisAudit) {
        showAlert('Jenis Audit wajib dipilih.', 'error');
        return;
    }

    const body = {
        jenis_pemeriksaan: _jenisPemeriksaan,
        jenis_audit: jenisAudit,
        cabang: document.getElementById('amCabang').value || null,
        cabang_area: document.getElementById('amCabangArea').value || null,
        tgl_plan: document.getElementById('amTglPlan').value || null,
        catatan: document.getElementById('amCatatan').value || null,
    };

    const payload = await fetchJson('/api/plan-audit-mandiri', {
        method: 'POST',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });

    showAlert(payload.message || `Plan berhasil dibuat: ${payload.data?.noPlan || ''}`);
    document.getElementById('amForm').reset();
    await loadPlans();
}

async function deletePlan(id) {
    if (!id) return;
    if (!confirm('Hapus plan audit mandiri ini?')) return;

    const payload = await fetchJson(`/api/plan-audit-mandiri/${id}`, {
        method: 'DELETE',
        headers: authHeaders(),
    });

    showAlert(payload.message || 'Plan berhasil dihapus.');
    await loadPlans();
}

// ─── Render ───────────────────────────────────────────────────────────────────
function renderTable() {
    const tbody = document.getElementById('amTableBody');
    if (!tbody) return;

    const q = (document.getElementById('amSearch')?.value || '').toLowerCase();
    const filtered = q
        ? _plans.filter((p) => [p.noPlan, p.cabang, p.jenisAudit].filter(Boolean).some((v) => v.toLowerCase().includes(q)))
        : _plans;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">Belum ada plan audit mandiri.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map((p) => `
        <tr class="hover:bg-slate-950/50">
            <td class="px-4 py-4">
                <div class="font-semibold text-slate-100">${escapeHtml(p.noPlan)}</div>
                <div class="text-xs text-slate-500">${escapeHtml(p.jenisAudit)}</div>
            </td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(JENIS_PEMERIKSAAN_LABEL[p.jenisPemeriksaan] || p.jenisPemeriksaan)}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(p.cabang || '-')}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(p.tglPlan || '-')}</td>
            <td class="px-4 py-4 text-right">
                <button type="button" class="am-delete-btn rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${p.id}">Hapus</button>
            </td>
        </tr>
    `).join('');
}
