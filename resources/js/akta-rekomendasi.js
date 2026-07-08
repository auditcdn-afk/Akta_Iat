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

function isInternal() {
    return ['admin', 'manajer', 'auditor'].includes(currentUser?.role);
}

function canManageRecommendations() {
    return isInternal();
}

function canApproveRecommendations() {
    return ['admin', 'manajer'].includes(currentUser?.role);
}

// Returns true if the current user can fill the "Isi" (unit usaha response) for this recommendation
function canIsiRekomendasi(item) {
    if (isInternal()) return true;
    const planCabang = item.planAudit?.cabang ?? '';
    const myUnit     = currentUser?.unitUsaha ?? '';
    return myUnit && myUnit === planCabang;
}

// Returns true if current user can fill a specific birokrasi step role
function canIsiStep(roleName) {
    if (isInternal()) return true;
    const stepRole = (roleName ?? '').toUpperCase();
    if (!stepRole) return false;
    // Match by role (e.g. user.role = "rss" matches step "RSS")
    const myRole = (currentUser?.role ?? '').toUpperCase();
    if (myRole && myRole === stepRole) return true;
    // Match by unit_usaha (e.g. user.unitUsaha = "SO ALB" matches step "SO ALB")
    const myUnit = (currentUser?.unitUsaha ?? '').toUpperCase();
    if (myUnit && myUnit === stepRole) return true;
    return false;
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
        const userCanIsi = canIsiRekomendasi(item);
        const isiBtn = userCanIsi
            ? `<button type="button" class="isi-recommendation rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10 transition" data-id="${item.id}" data-judul="${escapeHtml(item.judul)}">
                    ${isiStep ? 'Lihat / Edit Isian' : 'Isi'}
                </button>`
            : '';

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
    const allSteps = item.steps ?? [];
    const birokrasiSteps = allSteps
        .map((s, realIdx) => ({ ...s, realIdx }))
        .filter(s => s.step !== 'created' && s.step !== 'isi_rekomendasi');
    if (!birokrasiSteps.length) return '';

    const cards = birokrasiSteps.map((s, idx) => {
        const done     = s.status === 'done' || s.status === 'approved';
        const prevDone = idx === 0 || (() => {
            const p = birokrasiSteps[idx - 1];
            return p?.status === 'done' || p?.status === 'approved';
        })();
        const canIsi = !done && prevDone;

        const bgStyle = done
            ? 'background:#1e293b;border:1px solid #334155'
            : canIsi
                ? 'background:#1c1008;border:1px solid #92400e'
                : 'background:#0f172a;border:1px solid #1e293b';

        const statusLabel = done ? '✓ Sudah diisi' : canIsi ? '⏳ Giliran mengisi' : '— Menunggu';
        const statusColor = done ? '#34d399' : canIsi ? '#fbbf24' : '#64748b';

        const content = done && s.note
            ? '<p style="margin:4px 0 0;font-size:11px;color:#e2e8f0;white-space:pre-line;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical">' + escapeHtml(s.note) + '</p>'
              + '<p style="margin:2px 0 0;font-size:10px;color:#64748b">' + escapeHtml(s.user ?? '') + (s.time ? ' · ' + String(s.time).substring(0, 10) : '') + '</p>'
            : '<p style="margin:4px 0 0;font-size:11px;color:' + statusColor + ';font-style:italic">' + statusLabel + '</p>';

        const btn = canIsi && canIsiStep(s.step)
            ? '<button onclick="window.openIsiStepFromReko(' + item.id + ',' + s.realIdx + ',\'' + escapeHtml(s.step) + '\')" style="margin-top:6px;width:100%;border-radius:6px;background:#2563eb;border:none;padding:4px 8px;font-size:11px;font-weight:600;color:#fff;cursor:pointer">Isi Keputusan</button>'
            : '';

        return '<div style="min-width:150px;max-width:200px;border-radius:8px;padding:10px;flex-shrink:0;' + bgStyle + '">'
            + '<p style="margin:0;font-size:11px;font-weight:700;color:#cbd5e1">' + escapeHtml(s.step) + '</p>'
            + content
            + btn
            + '</div>';
    }).join('');

    return '<div style="margin-top:8px;overflow-x:auto"><div style="display:flex;gap:8px;padding-bottom:4px;min-width:max-content">' + cards + '</div></div>';
}

window.openIsiStepFromReko = function openIsiStepFromReko(rekId, stepIdx, roleName) {
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
    document.getElementById('isiModalSubtitle').textContent = judul || '';

    // Show auditor's original recommendation
    const rekAwal = item?.deskripsi || item?.judul || '-';
    const rekMeta = item?.createdBy ? `Dibuat oleh: ${item.createdBy}` : '';
    document.getElementById('isiModalRekomendasiAwal').textContent = rekAwal;
    document.getElementById('isiModalRekomendasiMeta').textContent = rekMeta;

    // Build history from steps (all filled steps except 'created')
    const histSteps = (item?.steps ?? []).filter(s => s.step !== 'created' && s.note);
    const histEl    = document.getElementById('isiModalHistori');
    const histList  = document.getElementById('isiModalHistoriList');
    if (histSteps.length) {
        histList.innerHTML = histSteps.map(s => `
            <div class="rounded-xl border border-slate-700 bg-slate-800/60 px-4 py-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold text-slate-300">${escapeHtml(s.step === 'isi_rekomendasi' ? 'Isian Unit Usaha' : s.step)}</span>
                    <span class="text-[10px] text-slate-500">${escapeHtml(s.user ?? '')}${s.time ? ' · ' + s.time.substring(0,10) : ''}</span>
                </div>
                <p class="text-sm text-slate-200 whitespace-pre-wrap">${escapeHtml(s.note)}</p>
            </div>`).join('');
        histEl.classList.remove('hidden');
    } else {
        histEl.classList.add('hidden');
    }

    // Form fields
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
