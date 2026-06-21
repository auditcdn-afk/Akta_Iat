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

        const actions = canManageRecommendations()
            ? `
                <button type="button" class="edit-recommendation rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                    Edit
                </button>

                <button type="button" class="delete-recommendation ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">
                    Hapus
                </button>

                ${approveButton}
            `
            : '<span class="text-xs text-slate-500">Read only</span>';

        return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-4">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.judul || '-')}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(item.kategori || '-')}</div>
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
