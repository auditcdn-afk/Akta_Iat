const SESSION_KEY = 'akta_session';

let picas = [];
let recommendations = [];
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

function canManagePicas() {
    return ['admin', 'manajer', 'auditor'].includes(currentUser?.role);
}

function canClosePicas() {
    return ['admin', 'manajer'].includes(currentUser?.role);
}

function showAlert(message, type = 'success') {
    const alert = document.getElementById('picaAlert');

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
        open: 'bg-blue-500/10 text-blue-300 border-blue-500/20',
        progress: 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        closed: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
    };

    return map[status] || map.open;
}

function priorityBadge(priority) {
    const map = {
        rendah: 'bg-slate-500/10 text-slate-300 border-slate-500/20',
        sedang: 'bg-blue-500/10 text-blue-300 border-blue-500/20',
        tinggi: 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        kritis: 'bg-red-500/10 text-red-300 border-red-500/20',
    };

    return map[priority] || map.sedang;
}

function normalizeListPayload(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    return payload.data || [];
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

async function loadRecommendations() {
    const payload = await fetchJson('/api/recommendations');

    recommendations = normalizeListPayload(payload);

    fillRecommendationSelect('auditRecommendationId', 'Pilih Rekomendasi');
    fillRecommendationSelect('picaRecommendationFilter', 'Semua Rekomendasi');
}

async function loadPicas() {
    const q = document.getElementById('picaSearch')?.value || '';
    const status = document.getElementById('picaStatusFilter')?.value || '';
    const priority = document.getElementById('picaPriorityFilter')?.value || '';
    const recommendationId = document.getElementById('picaRecommendationFilter')?.value || '';

    const params = new URLSearchParams();

    if (q) {
        params.set('q', q);
    }

    if (status) {
        params.set('status', status);
    }

    if (priority) {
        params.set('priority', priority);
    }

    if (recommendationId) {
        params.set('audit_recommendation_id', recommendationId);
    }

    const url = params.toString() ? `/api/picas?${params.toString()}` : '/api/picas';
    const payload = await fetchJson(url);

    picas = normalizeListPayload(payload);

    renderStats();
    renderPicas();
}

function fillRecommendationSelect(elementId, firstLabel) {
    const select = document.getElementById(elementId);

    if (!select) {
        return;
    }

    select.innerHTML = `<option value="">${firstLabel}</option>`;

    recommendations.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = recommendationLabel(item);
        select.appendChild(option);
    });
}

function recommendationLabel(item) {
    const plan = item.planAudit || {};
    const task = item.auditTask || {};

    const title = item.judul || item.title || item.rekomendasi || item.description || 'Rekomendasi';
    const planText = plan.noSpt || plan.cabang ? ` • ${plan.noSpt || '-'} / ${plan.cabang || '-'}` : '';
    const taskText = task.judul ? ` • ${task.judul}` : '';

    return `#${item.id} - ${title}${planText}${taskText}`;
}

function renderStats() {
    document.getElementById('picaTotalStat').textContent = picas.length;
    document.getElementById('picaOpenStat').textContent = picas.filter((item) => item.status === 'open').length;
    document.getElementById('picaProgressStat').textContent = picas.filter((item) => item.status === 'progress').length;
    document.getElementById('picaClosedStat').textContent = picas.filter((item) => item.status === 'closed').length;
}

function renderPicas() {
    const tbody = document.getElementById('picasTableBody');

    if (!tbody) {
        return;
    }

    if (!picas.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada PICA.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = picas.map((item) => {
        const recommendation = item.recommendation || {};
        const recommendationTitle = recommendation.id
            ? recommendationLabel(recommendation)
            : `Rekomendasi #${item.audit_recommendation_id || '-'}`;

        const closeButton = canClosePicas() && item.status !== 'closed'
            ? `
                <button type="button" class="close-pica ml-2 rounded-lg border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/10" data-id="${item.id}">
                    Close
                </button>
            `
            : '';

        const actions = canManagePicas()
            ? `
                <button type="button" class="edit-pica rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                    Edit
                </button>

                <button type="button" class="delete-pica ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">
                    Hapus
                </button>

                ${closeButton}
            `
            : '<span class="text-xs text-slate-500">Read only</span>';

        return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-4">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.pica_no || `PICA-${item.id}`)}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(item.title || '-')}</div>
                    <div class="mt-2 max-w-xl text-xs text-slate-400">
                        <div><span class="text-slate-500">Problem:</span> ${escapeHtml(item.problem || '-')}</div>
                        <div><span class="text-slate-500">Root Cause:</span> ${escapeHtml(item.root_cause || '-')}</div>
                    </div>
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    ${escapeHtml(recommendationTitle)}
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    ${escapeHtml(item.pic || '-')}
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    <div>${escapeHtml(formatDate(item.target_date))}</div>
                    <div class="text-xs text-slate-500">Actual: ${escapeHtml(formatDate(item.actual_date))}</div>
                </td>

                <td class="px-4 py-4">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${priorityBadge(item.priority)}">
                        ${escapeHtml(item.priority || 'sedang')}
                    </span>
                </td>

                <td class="px-4 py-4">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusBadge(item.status)}">
                        ${escapeHtml(item.status || 'open')}
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
    const modal = document.getElementById('picaModal');
    const title = document.getElementById('picaModalTitle');

    document.getElementById('picaForm').reset();

    if (item) {
        title.textContent = 'Edit PICA';

        document.getElementById('picaId').value = item.id;
        document.getElementById('auditRecommendationId').value = item.audit_recommendation_id || '';
        document.getElementById('picaNo').value = item.pica_no || '';
        document.getElementById('title').value = item.title || '';
        document.getElementById('problem').value = item.problem || '';
        document.getElementById('rootCause').value = item.root_cause || '';
        document.getElementById('correctiveAction').value = item.corrective_action || '';
        document.getElementById('preventiveAction').value = item.preventive_action || '';
        document.getElementById('pic').value = item.pic || '';
        document.getElementById('priority').value = item.priority || 'sedang';
        document.getElementById('status').value = item.status || 'open';
        document.getElementById('targetDate').value = onlyDate(item.target_date);
        document.getElementById('actualDate').value = onlyDate(item.actual_date);
        document.getElementById('notes').value = item.notes || '';
    } else {
        title.textContent = 'Tambah PICA';

        document.getElementById('picaId').value = '';
        document.getElementById('priority').value = 'sedang';
        document.getElementById('status').value = 'open';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('picaModal');

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function getFormPayload() {
    const recommendationId = document.getElementById('auditRecommendationId').value;

    return {
        audit_recommendation_id: recommendationId ? Number(recommendationId) : null,
        pica_no: emptyToNull(document.getElementById('picaNo').value),
        title: emptyToNull(document.getElementById('title').value),
        problem: emptyToNull(document.getElementById('problem').value),
        root_cause: emptyToNull(document.getElementById('rootCause').value),
        corrective_action: emptyToNull(document.getElementById('correctiveAction').value),
        preventive_action: emptyToNull(document.getElementById('preventiveAction').value),
        pic: emptyToNull(document.getElementById('pic').value),
        priority: document.getElementById('priority').value,
        status: document.getElementById('status').value,
        target_date: emptyToNull(document.getElementById('targetDate').value),
        actual_date: emptyToNull(document.getElementById('actualDate').value),
        notes: emptyToNull(document.getElementById('notes').value),
    };
}

async function savePica(event) {
    event.preventDefault();

    if (!canManagePicas()) {
        showAlert('Role kamu hanya boleh melihat data.', 'error');
        return;
    }

    const id = document.getElementById('picaId').value;
    const isEdit = Boolean(id);

    const payload = await fetchJson(isEdit ? `/api/picas/${id}` : '/api/picas', {
        method: isEdit ? 'PUT' : 'POST',
        body: JSON.stringify(getFormPayload()),
    });

    closeModal();
    showAlert(payload.message || 'PICA berhasil disimpan.');
    await loadPicas();
}

async function deletePica(id) {
    const item = picas.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(`Hapus PICA "${item.pica_no || item.title || item.id}"?`);

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/picas/${id}`, {
        method: 'DELETE',
    });

    showAlert(payload.message || 'PICA berhasil dihapus.');
    await loadPicas();
}

async function closePica(id) {
    const item = picas.find((row) => String(row.id) === String(id));

    if (!item) {
        return;
    }

    const confirmed = confirm(`Close PICA "${item.pica_no || item.title || item.id}"?`);

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/picas/${id}/close`, {
        method: 'POST',
        body: JSON.stringify({
            actual_date: new Date().toISOString().slice(0, 10),
            close_note: 'Closed dari halaman PICA.',
        }),
    });

    showAlert(payload.message || 'PICA berhasil ditutup.');
    await loadPicas();
}

function setupFilters() {
    let timer = null;

    document.getElementById('picaSearch')?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadPicas().catch((error) => showAlert(error.message, 'error')), 300);
    });

    document.getElementById('picaStatusFilter')?.addEventListener('change', () => {
        loadPicas().catch((error) => showAlert(error.message, 'error'));
    });

    document.getElementById('picaPriorityFilter')?.addEventListener('change', () => {
        loadPicas().catch((error) => showAlert(error.message, 'error'));
    });

    document.getElementById('picaRecommendationFilter')?.addEventListener('change', () => {
        loadPicas().catch((error) => showAlert(error.message, 'error'));
    });
}

function emptyToNull(value) {
    const clean = String(value || '').trim();

    return clean === '' ? null : clean;
}

function onlyDate(value) {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 10);
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return String(value).slice(0, 10);
}

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('openCreatePicaButton')?.addEventListener('click', () => openModal());
    document.getElementById('closePicaModalButton')?.addEventListener('click', closeModal);
    document.getElementById('cancelPicaFormButton')?.addEventListener('click', closeModal);

    document.getElementById('picaForm')?.addEventListener('submit', async (event) => {
        try {
            await savePica(event);
        } catch (error) {
            showAlert(error.message || 'Gagal menyimpan PICA.', 'error');
        }
    });

    document.getElementById('picasTableBody')?.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.edit-pica');
        const deleteButton = event.target.closest('.delete-pica');
        const closeButton = event.target.closest('.close-pica');

        if (editButton) {
            const item = picas.find((row) => String(row.id) === String(editButton.dataset.id));
            openModal(item);
            return;
        }

        if (deleteButton) {
            try {
                await deletePica(deleteButton.dataset.id);
            } catch (error) {
                showAlert(error.message || 'Gagal menghapus PICA.', 'error');
            }
            return;
        }

        if (closeButton) {
            try {
                await closePica(closeButton.dataset.id);
            } catch (error) {
                showAlert(error.message || 'Gagal close PICA.', 'error');
            }
        }
    });

    setupFilters();

    try {
        await loadCurrentUser();

        if (!canManagePicas()) {
            document.getElementById('openCreatePicaButton')?.classList.add('hidden');
        }

        await loadRecommendations();
        await loadPicas();
    } catch (error) {
        showAlert(error.message || 'Gagal memuat PICA.', 'error');
    }
});
