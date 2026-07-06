const SESSION_KEY = 'akta_session';

let recommendations = [];
let plans = [];
let tasks = [];
let currentUser = null;

function getSession() {
    try {
        const rawSession = sessionStorage.getItem(SESSION_KEY);
        return rawSession ? JSON.parse(rawSession) : null;
    } catch {
        return null;
    }
}

function authHeaders() {
    const session = getSession();

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `${session?.tokenType || 'Bearer'} ${session?.token}`,
    };
}

function canManageRecommendations() {
    return ['admin', 'manajer', 'auditor'].includes(currentUser?.role);
}

function canApproveRecommendations() {
    return ['admin', 'manajer'].includes(currentUser?.role);
}

function showAlert(message, type = 'success') {
    const alert = document.getElementById('recommendationAlert');

    if (!alert) {
        return;
    }

    alert.textContent = message;
    alert.classList.remove(
        'hidden',
        'border-emerald-500/30',
        'bg-emerald-500/10',
        'text-emerald-200',
        'border-red-500/30',
        'bg-red-500/10',
        'text-red-200'
    );

    if (type === 'error') {
        alert.classList.add('border-red-500/30', 'bg-red-500/10', 'text-red-200');
    } else {
        alert.classList.add('border-emerald-500/30', 'bg-emerald-500/10', 'text-emerald-200');
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function statusBadge(status) {
    const map = {
        draft: 'bg-slate-500/10 text-slate-300 border-slate-500/20',
        open: 'bg-blue-500/10 text-blue-300 border-blue-500/20',
        in_progress: 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        waiting_approval: 'bg-violet-500/10 text-violet-300 border-violet-500/20',
        approved: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
        done: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
        cancelled: 'bg-red-500/10 text-red-300 border-red-500/20',
    };

    return map[status] || map.draft;
}

function priorityBadge(priority) {
    const map = {
        rendah: 'bg-slate-500/10 text-slate-300 border-slate-500/20',
        sedang: 'bg-blue-500/10 text-blue-300 border-blue-500/20',
        tinggi: 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        urgent: 'bg-red-500/10 text-red-300 border-red-500/20',
    };

    return map[priority] || map.sedang;
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            ...authHeaders(),
            ...(options.headers || {}),
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : null;

        throw new Error(firstError || payload.message || 'Request gagal.');
    }

    return payload;
}

async function loadCurrentUser() {
    const payload = await fetchJson('/api/auth/me');
    currentUser = payload.user;
}

async function loadPlans() {
    const payload = await fetchJson('/api/plans');

    plans = payload.data || [];

    const select = document.getElementById('planAuditId');

    if (!select) {
        return;
    }

    select.innerHTML = '<option value="">Tanpa Plan</option>';

    plans.forEach((plan) => {
        const option = document.createElement('option');
        option.value = plan.id;
        option.textContent = `${plan.noSpt || '-'} • ${plan.cabang || '-'} • ${plan.status || '-'}`;
        select.appendChild(option);
    });
}

async function loadTasks() {
    const payload = await fetchJson('/api/tasks');

    tasks = payload.data || [];

    const select = document.getElementById('auditTaskId');

    if (!select) {
        return;
    }

    select.innerHTML = '<option value="">Tanpa Task</option>';

    tasks.forEach((task) => {
        const option = document.createElement('option');
        option.value = task.id;
        option.dataset.planAuditId = task.planAuditId || '';
        option.textContent = `${task.judul || '-'} • ${task.planAudit?.cabang || '-'} • ${task.status || '-'}`;
        select.appendChild(option);
    });
}

async function loadRecommendations() {
    const q = document.getElementById('recommendationSearch')?.value || '';
    const status = document.getElementById('recommendationStatusFilter')?.value || '';
    const prioritas = document.getElementById('recommendationPriorityFilter')?.value || '';

    const params = new URLSearchParams();

    if (q) {
        params.set('q', q);
    }

    if (status) {
        params.set('status', status);
    }

    if (prioritas) {
        params.set('prioritas', prioritas);
    }

    const url = params.toString() ? `/api/recommendations?${params.toString()}` : '/api/recommendations';
    const payload = await fetchJson(url);

    recommendations = payload.data || [];
    renderRecommendations();
}

function renderRecommendations() {
    const tbody = document.getElementById('recommendationsTableBody');

    if (!tbody) {
        return;
    }

    if (!recommendations.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada rekomendasi.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = recommendations.map((item) => {
        const plan = item.planAudit || {};
        const task = item.auditTask || {};

        const approveButton = canApproveRecommendations() && item.status === 'waiting_approval'
            ? `
                <button type="button" class="approve-recommendation ml-2 rounded-lg border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/10" data-id="${item.id}">
                    Approve
                </button>
            `
            : '';

        // Check if this recommendation has been filled (has isi_rekomendasi step)
        const isiStep = (item.steps ?? []).find(s => s.step === 'isi_rekomendasi');
        const isiBtn = `<button type="button" class="isi-recommendation rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10 transition" data-id="${item.id}" data-judul="${escapeHtml(item.judul)}">
                    ${isiStep ? 'Lihat / Edit Isian' : 'Isi'}
                </button>`;

        const actions = canManageRecommendations()
            ? `
                <button type="button" class="edit-recommendation rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                    Edit
                </button>

                <button type="button" class="delete-recommendation ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">
                    Hapus
                </button>

                ${approveButton}
                ${isiBtn}
            `
            : isiBtn;

        return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-4">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.judul || '-')}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(item.kategori || '-')}</div>
                    ${buildBirokrasiCards(item)}
                </td>

                <td class="px-4 py-4">
                    <div class="text-sm font-semibold text-slate-200">${escapeHtml(plan.noSpt || '-')} • ${escapeHtml(plan.cabang || '-')}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(task.judul || '-')}</div>
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    ${escapeHtml(item.pic || '-')}
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    <div>${escapeHtml(item.deadline || '-')}</div>
                    <div class="text-xs text-slate-500">Selesai: ${escapeHtml(item.tglSelesai || '-')}</div>
                </td>

                <td class="px-4 py-4">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${priorityBadge(item.prioritas)}">
                        ${escapeHtml(item.prioritas || 'sedang')}
                    </span>
                </td>

                <td class="px-4 py-4">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusBadge(item.status)}">
                        ${escapeHtml(String(item.status || 'draft').replaceAll('_', ' '))}
                    </span>
                </td>

                <td class="px-4 py-4 text-right">
                    ${actions}
                </td>
            </tr>
        `;
    }).join('');
}

function openModal(item = null) {
    const modal = document.getElementById('recommendationModal');
    const title = document.getElementById('recommendationModalTitle');

    document.getElementById('recommendationForm').reset();

    if (item) {
        title.textContent = 'Edit Rekomendasi';

        document.getElementById('recommendationId').value = item.id;
        document.getElementById('planAuditId').value = item.planAuditId || '';
        document.getElementById('auditTaskId').value = item.auditTaskId || '';
        document.getElementById('judul').value = item.judul || '';
        document.getElementById('deskripsi').value = item.deskripsi || '';
        document.getElementById('kategori').value = item.kategori || '';
        document.getElementById('pic').value = item.pic || '';
        document.getElementById('prioritas').value = item.prioritas || 'sedang';
        document.getElementById('status').value = item.status || 'draft';
        document.getElementById('deadline').value = item.deadline || '';
        document.getElementById('tglSelesai').value = item.tglSelesai || '';
    } else {
        title.textContent = 'Tambah Rekomendasi';

        document.getElementById('recommendationId').value = '';
        document.getElementById('prioritas').value = 'sedang';
        document.getElementById('status').value = 'draft';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('recommendationModal');

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function getFormPayload() {
    const planAuditId = document.getElementById('planAuditId').value;
    const auditTaskId = document.getElementById('auditTaskId').value;

    return {
        plan_audit_id: planAuditId ? Number(planAuditId) : null,
        audit_task_id: auditTaskId ? Number(auditTaskId) : null,
        judul: document.getElementById('judul').value.trim(),
        deskripsi: document.getElementById('deskripsi').value.trim(),
        kategori: document.getElementById('kategori').value.trim(),
        pic: document.getElementById('pic').value.trim(),
        prioritas: document.getElementById('prioritas').value,
        status: document.getElementById('status').value,
        deadline: document.getElementById('deadline').value || null,
        tgl_selesai: document.getElementById('tglSelesai').value || null,
    };
}

async function saveRecommendation(event) {
    event.preventDefault();

    if (!canManageRecommendations()) {
        showAlert('Role kamu hanya boleh melihat data.', 'error');
        return;
    }

    const id = document.getElementById('recommendationId').value;
    const isEdit = Boolean(id);

    const payload = await fetchJson(isEdit ? `/api/recommendations/${id}` : '/api/recommendations', {
        method: isEdit ? 'PUT' : 'POST',
        body: JSON.stringify(getFormPayload()),
    });

    closeModal();
    showAlert(payload.message || 'Rekomendasi berhasil disimpan.');
    await loadRecommendations();
}

async function deleteRecommendation(id) {
    const item = recommendations.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(`Hapus rekomendasi "${item.judul}"?`);

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/recommendations/${id}`, {
        method: 'DELETE',
    });

    showAlert(payload.message || 'Rekomendasi berhasil dihapus.');
    await loadRecommendations();
}

function buildBirokrasiCards(item) {
    const birokrasiSteps = (item.steps ?? []).filter(s => s.step !== 'created' && s.step !== 'isi_rekomendasi');
    if (!birokrasiSteps.length) return '';
    const cards = birokrasiSteps.map((s, idx) => {
        const done = s.status === 'done' || s.status === 'approved';
        const prevDone = idx === 0 || (() => { const p = birokrasiSteps[idx-1]; return p?.status === 'done' || p?.status === 'approved'; })();
        const canIsi = !done && prevDone;
        const fullIdx = (item.steps ?? []).findIndex((fs, fi) => fi > 0 && fs.step === s.step);
        const bg = done ? 'bg-slate-50 border-slate-200' : canIsi ? 'bg-amber-50 border-amber-200' : 'bg-slate-50 border-slate-100';
        const content = done && s.note
            ? `<p class="mt-1 text-xs text-slate-700">${escapeHtml(s.note)}</p><p class="mt-1 text-[10px] text-slate-400">${escapeHtml(s.user ?? '')}${s.time ? ' · ' + s.time.substring(0,10) : ''}</p>`
            : `<p class="mt-1 text-xs text-slate-400 italic">${canIsi ? 'Giliran mengisi...' : 'Belum giliran'}</p>`;
        const btn = canIsi
            ? `<button onclick="openIsiStepFromReko(${item.id}, ${fullIdx < 0 ? idx+1 : fullIdx}, '${escapeHtml(s.step)}')" class="mt-2 rounded-lg bg-blue-600 hover:bg-blue-500 px-2 py-1 text-[11px] font-semibold text-white">Isi Keputusan</button>`
            : '';
        return `<div class="rounded-lg border p-2.5 ${bg}" style="min-width:160px;max-width:220px">
            <p class="text-xs font-bold text-slate-800">${escapeHtml(s.step)}</p>
            ${content}
            ${btn}
        </div>`;
    }).join('');
    return `<div class="mt-2 overflow-x-auto"><div class="flex gap-2 pb-1" style="min-width:max-content">${cards}</div></div>`;
}

function openIsiStepFromReko(rekId, stepIdx, roleName) {
    const modal = document.getElementById('isiModal');
    if (!modal) return;
    modal.dataset.mode    = 'step';
    modal.dataset.rekId   = rekId;
    modal.dataset.stepIdx = stepIdx;
    document.getElementById('isiModalSubtitle').textContent = 'Isi keputusan: ' + (roleName || '');
    document.getElementById('isiTglPengisian').value = new Date().toISOString().substring(0, 10);
    document.getElementById('isiKonten').value = '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('isiKonten').focus();
}

function openIsiModal(id, judul) {
    const item  = recommendations.find(r => String(r.id) === String(id));
    const modal = document.getElementById('isiModal');
    if (modal) modal.dataset.mode = 'isi';
    document.getElementById('isiRecommendationId').value = id;
    document.getElementById('isiModalSubtitle').textContent = judul || 'Tindak lanjut atas rekomendasi audit.';

    const today       = new Date().toISOString().substring(0, 10);
    const existingIsi = (item?.steps ?? []).find(s => s.step === 'isi_rekomendasi');
    document.getElementById('isiTglPengisian').value = existingIsi?.time || today;
    document.getElementById('isiKonten').value = existingIsi?.note || '';

    if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    document.getElementById('isiKonten').focus();
}

function closeIsiModal() {
    const modal = document.getElementById('isiModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function saveIsi(event) {
    event.preventDefault();
    const modal = document.getElementById('isiModal');
    const mode  = modal?.dataset.mode;
    const tgl   = document.getElementById('isiTglPengisian').value;
    const isi   = document.getElementById('isiKonten').value.trim();
    const btn   = document.getElementById('saveIsiBtn');

    if (!tgl || !isi) { showAlert('Tanggal dan isi wajib diisi.', 'error'); return; }

    btn.textContent = 'Menyimpan...';
    btn.disabled = true;
    try {
        let payload;
        if (mode === 'step') {
            const rekId   = modal.dataset.rekId;
            const stepIdx = modal.dataset.stepIdx;
            payload = await fetchJson(`/api/recommendations/${rekId}/approve-step`, {
                method: 'POST',
                body: JSON.stringify({ step_index: Number(stepIdx), note: isi, tgl_isi: tgl }),
            });
        } else {
            const id = document.getElementById('isiRecommendationId').value;
            payload = await fetchJson(`/api/recommendations/${id}/isi`, {
                method: 'POST',
                body: JSON.stringify({ tgl_isi: tgl, isi }),
            });
        }
        modal.dataset.mode = '';
        closeIsiModal();
        showAlert(payload.message || 'Isian berhasil disimpan.');
        await loadRecommendations();
    } catch (e) {
        showAlert(e.message || 'Gagal menyimpan isian.', 'error');
    } finally {
        btn.textContent = 'Simpan';
        btn.disabled = false;
    }
}

async function approveRecommendation(id) {
    const item = recommendations.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(`Approve rekomendasi "${item.judul}"?`);

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/recommendations/${id}/approve`, {
        method: 'POST',
    });

    showAlert(payload.message || 'Rekomendasi berhasil disetujui.');
    await loadRecommendations();
}

function setupFilters() {
    let timer = null;

    document.getElementById('recommendationSearch')?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadRecommendations().catch((error) => showAlert(error.message, 'error')), 300);
    });

    document.getElementById('recommendationStatusFilter')?.addEventListener('change', () => {
        loadRecommendations().catch((error) => showAlert(error.message, 'error'));
    });

    document.getElementById('recommendationPriorityFilter')?.addEventListener('change', () => {
        loadRecommendations().catch((error) => showAlert(error.message, 'error'));
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('openCreateRecommendationButton')?.addEventListener('click', () => openModal());
    document.getElementById('closeRecommendationModalButton')?.addEventListener('click', closeModal);
    document.getElementById('cancelRecommendationFormButton')?.addEventListener('click', closeModal);
    document.getElementById('closeIsiModalBtn')?.addEventListener('click', closeIsiModal);
    document.getElementById('cancelIsiModalBtn')?.addEventListener('click', closeIsiModal);
    document.getElementById('isiForm')?.addEventListener('submit', async (e) => {
        try { await saveIsi(e); } catch (err) { showAlert(err.message || 'Gagal menyimpan.', 'error'); }
    });

    document.getElementById('recommendationForm')?.addEventListener('submit', async (event) => {
        try {
            await saveRecommendation(event);
        } catch (error) {
            showAlert(error.message || 'Gagal menyimpan rekomendasi.', 'error');
        }
    });

    document.getElementById('recommendationsTableBody')?.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.edit-recommendation');
        const deleteButton = event.target.closest('.delete-recommendation');
        const approveButton = event.target.closest('.approve-recommendation');
        const isiButton = event.target.closest('.isi-recommendation');

        if (isiButton) {
            openIsiModal(isiButton.dataset.id, isiButton.dataset.judul);
            return;
        }

        if (editButton) {
            const item = recommendations.find((row) => String(row.id) === String(editButton.dataset.id));
            openModal(item);
            return;
        }

        if (deleteButton) {
            try {
                await deleteRecommendation(deleteButton.dataset.id);
            } catch (error) {
                showAlert(error.message || 'Gagal menghapus rekomendasi.', 'error');
            }
            return;
        }

        if (approveButton) {
            try {
                await approveRecommendation(approveButton.dataset.id);
            } catch (error) {
                showAlert(error.message || 'Gagal approve rekomendasi.', 'error');
            }
        }
    });

    setupFilters();

    try {
        await loadCurrentUser();

        if (!canManageRecommendations()) {
            document.getElementById('openCreateRecommendationButton')?.classList.add('hidden');
        }

        await loadPlans();
        await loadTasks();
        await loadRecommendations();
    } catch (error) {
        showAlert(error.message || 'Gagal memuat rekomendasi.', 'error');
    }
});
