const SESSION_KEY = 'akta_session';

let tasks = [];
let plans = [];
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

function canManageTasks() {
    return ['admin', 'manajer', 'auditor'].includes(currentUser?.role);
}

function showAlert(message, type = 'success') {
    const alert = document.getElementById('taskAlert');

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
        todo: 'bg-slate-500/10 text-slate-300 border-slate-500/20',
        in_progress: 'bg-blue-500/10 text-blue-300 border-blue-500/20',
        review: 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        done: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
        cancelled: 'bg-red-500/10 text-red-300 border-red-500/20',
    };

    return map[status] || map.todo;
}

function priorityBadge(priority) {
    const map = {
        low: 'bg-slate-500/10 text-slate-300 border-slate-500/20',
        normal: 'bg-blue-500/10 text-blue-300 border-blue-500/20',
        high: 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        urgent: 'bg-red-500/10 text-red-300 border-red-500/20',
    };

    return map[priority] || map.normal;
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
    const q = document.getElementById('taskSearch')?.value || '';
    const status = document.getElementById('taskStatusFilter')?.value || '';
    const priority = document.getElementById('taskPriorityFilter')?.value || '';

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

    const url = params.toString() ? `/api/tasks?${params.toString()}` : '/api/tasks';
    const payload = await fetchJson(url);

    tasks = payload.data || [];
    renderTasks();
}

function renderTasks() {
    const tbody = document.getElementById('tasksTableBody');

    if (!tbody) {
        return;
    }

    if (!tasks.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada task audit.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = tasks.map((task) => {
        const plan = task.planAudit || {};

        const actions = canManageTasks()
            ? `
                <button type="button" class="edit-task rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${task.id}">
                    Edit
                </button>

                <button type="button" class="delete-task ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${task.id}">
                    Hapus
                </button>
            `
            : '<span class="text-xs text-slate-500">Read only</span>';

        return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-4">
                    <div class="font-semibold text-slate-100">${escapeHtml(task.judul || '-')}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(task.kategori || '-')}</div>
                </td>

                <td class="px-4 py-4">
                    <div class="text-sm font-semibold text-slate-200">${escapeHtml(plan.noSpt || '-')}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(plan.cabang || '-')}</div>
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    ${escapeHtml(task.assignedTo || '-')}
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    ${escapeHtml(task.dueDate || '-')}
                </td>

                <td class="px-4 py-4">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${priorityBadge(task.priority)}">
                        ${escapeHtml(task.priority || 'normal')}
                    </span>
                </td>

                <td class="px-4 py-4">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusBadge(task.status)}">
                        ${escapeHtml(String(task.status || 'todo').replaceAll('_', ' '))}
                    </span>
                </td>

                <td class="px-4 py-4 text-right">
                    ${actions}
                </td>
            </tr>
        `;
    }).join('');
}

function openModal(task = null) {
    const modal = document.getElementById('taskModal');
    const title = document.getElementById('taskModalTitle');

    document.getElementById('taskForm').reset();

    if (task) {
        title.textContent = 'Edit Task Audit';

        document.getElementById('taskId').value = task.id;
        document.getElementById('planAuditId').value = task.planAuditId || '';
        document.getElementById('judul').value = task.judul || '';
        document.getElementById('kategori').value = task.kategori || '';
        document.getElementById('assignedTo').value = task.assignedTo || '';
        document.getElementById('priority').value = task.priority || 'normal';
        document.getElementById('status').value = task.status || 'todo';
        document.getElementById('dueDate').value = task.dueDate || '';
        document.getElementById('catatan').value = task.catatan || '';
    } else {
        title.textContent = 'Tambah Task Audit';

        document.getElementById('taskId').value = '';
        document.getElementById('priority').value = 'normal';
        document.getElementById('status').value = 'todo';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('taskModal');

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function getFormPayload() {
    const planAuditId = document.getElementById('planAuditId').value;

    return {
        plan_audit_id: planAuditId ? Number(planAuditId) : null,
        judul: document.getElementById('judul').value.trim(),
        kategori: document.getElementById('kategori').value.trim(),
        assigned_to: document.getElementById('assignedTo').value.trim(),
        priority: document.getElementById('priority').value,
        status: document.getElementById('status').value,
        due_date: document.getElementById('dueDate').value || null,
        catatan: document.getElementById('catatan').value.trim(),
    };
}

async function saveTask(event) {
    event.preventDefault();

    if (!canManageTasks()) {
        showAlert('Role kamu hanya boleh melihat data.', 'error');
        return;
    }

    const id = document.getElementById('taskId').value;
    const isEdit = Boolean(id);

    const payload = await fetchJson(isEdit ? `/api/tasks/${id}` : '/api/tasks', {
        method: isEdit ? 'PUT' : 'POST',
        body: JSON.stringify(getFormPayload()),
    });

    closeModal();
    showAlert(payload.message || 'Task audit berhasil disimpan.');
    await loadTasks();
}

async function deleteTask(id) {
    if (!canManageTasks()) {
        showAlert('Role kamu hanya boleh melihat data.', 'error');
        return;
    }

    const task = tasks.find((item) => String(item.id) === String(id));

    if (!task) {
        return;
    }

    const confirmed = confirm(`Hapus task audit "${task.judul}"?`);

    if (!confirmed) {
        return;
    }

    const payload = await fetchJson(`/api/tasks/${id}`, {
        method: 'DELETE',
    });

    showAlert(payload.message || 'Task audit berhasil dihapus.');
    await loadTasks();
}

function setupFilters() {
    let timer = null;

    document.getElementById('taskSearch')?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadTasks().catch((error) => showAlert(error.message, 'error')), 300);
    });

    document.getElementById('taskStatusFilter')?.addEventListener('change', () => {
        loadTasks().catch((error) => showAlert(error.message, 'error'));
    });

    document.getElementById('taskPriorityFilter')?.addEventListener('change', () => {
        loadTasks().catch((error) => showAlert(error.message, 'error'));
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('openCreateTaskButton')?.addEventListener('click', () => openModal());
    document.getElementById('closeTaskModalButton')?.addEventListener('click', closeModal);
    document.getElementById('cancelTaskFormButton')?.addEventListener('click', closeModal);

    document.getElementById('taskForm')?.addEventListener('submit', async (event) => {
        try {
            await saveTask(event);
        } catch (error) {
            showAlert(error.message || 'Gagal menyimpan task audit.', 'error');
        }
    });

    document.getElementById('tasksTableBody')?.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.edit-task');
        const deleteButton = event.target.closest('.delete-task');

        if (editButton) {
            const task = tasks.find((item) => String(item.id) === String(editButton.dataset.id));
            openModal(task);
            return;
        }

        if (deleteButton) {
            try {
                await deleteTask(deleteButton.dataset.id);
            } catch (error) {
                showAlert(error.message || 'Gagal menghapus task audit.', 'error');
            }
        }
    });

    setupFilters();

    try {
        await loadCurrentUser();

        if (!canManageTasks()) {
            document.getElementById('openCreateTaskButton')?.classList.add('hidden');
        }

        await loadPlans();
        await loadTasks();
    } catch (error) {
        showAlert(error.message || 'Gagal memuat task audit.', 'error');
    }
});ss