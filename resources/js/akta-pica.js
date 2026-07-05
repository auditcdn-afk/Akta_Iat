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

const BRANCH_ROLES = ['h1', 'h2', 'unit', 'bpk'];

function isBranchRole() {
    return BRANCH_ROLES.includes(currentUser?.role);
}

function canManagePicas() {
    return ['admin', 'manajer', 'auditor', 'h1', 'h2', 'unit'].includes(currentUser?.role);
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

async function loadUserDatalist() {
    try {
        const payload = await fetchJson('/api/users/names');
        const users = payload.data ?? [];
        const options = users.map(u => `<option value="${u.label}">`).join('');
        ['userDatalist1', 'userDatalist2'].forEach(id => {
            const dl = document.getElementById(id);
            if (dl) dl.innerHTML = options;
        });
    } catch (_) {
        // datalist kosong — user tetap bisa ketik manual
    }
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

        const isBranch = isBranchRole();
        const myUnit = currentUser?.unitUsaha || currentUser?.unit_usaha;
        // PICA diteruskan ke unit user ini: cek forwarded_to_unit, atau fallback ke relation_ship fields
        const forwardedByColumn = item.forwarded_to_unit && myUnit && item.forwarded_to_unit === myUnit;
        const forwardedByRelation = myUnit && !forwardedByColumn && (
            (item.relation_ship && item.relation_ship.includes(myUnit)) ||
            (item.relation_ship2 && item.relation_ship2.includes(myUnit))
        );
        const isForwardedToMe = forwardedByColumn || forwardedByRelation;
        // Forwarded party dianggap sudah mengisi jika ada field forwarded_filled_at (set saat mereka simpan)
        const forwardedAlreadyFilled = isForwardedToMe && !!item.forwarded_filled_at;

        // Cabang sudah mengisi jika problem_identification terisi
        const branchAlreadyFilled = isBranch && item.problem_identification;
        const deleteBtn = (!isBranch && !isForwardedToMe)
            ? `<button type="button" class="delete-pica ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${item.id}">Hapus</button>`
            : '';

        let actions;
        if (isForwardedToMe && !forwardedAlreadyFilled) {
            // Pihak Relation Ship: hanya tombol Isi
            actions = `
                <button type="button" class="edit-pica rounded-lg border border-amber-500/40 px-3 py-1.5 text-xs font-semibold text-amber-300 hover:bg-amber-500/10" data-id="${item.id}">
                    Isi
                </button>
            `;
        } else if (isForwardedToMe && forwardedAlreadyFilled) {
            actions = '<span class="text-xs text-emerald-500">✓ Sudah diisi</span>';
        } else if (!canManagePicas()) {
            actions = '<span class="text-xs text-slate-500">Read only</span>';
        } else if (isBranch && item.forwarded_filled_at) {
            // Forwarded party sudah mengisi → cabang bisa Re-Chek
            const rechekLabel = item.recheck_at ? '✓ Re-Chek' : 'Re-Chek';
            const rechekClass = item.recheck_at
                ? 'text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/10'
                : 'text-blue-300 border-blue-500/40 hover:bg-blue-500/10';
            actions = `
                <button type="button" class="recheck-pica rounded-lg border ${rechekClass} px-3 py-1.5 text-xs font-semibold" data-id="${item.id}">
                    ${rechekLabel}
                </button>
            `;
        } else if (isBranch && branchAlreadyFilled) {
            actions = '<span class="text-xs text-emerald-500">✓ Sudah diisi</span>';
        } else {
            const editLabel = isBranch ? 'Isi' : 'Edit';
            actions = `
                <button type="button" class="edit-pica rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                    ${editLabel}
                </button>
                ${deleteBtn}
                ${(isBranch || isForwardedToMe) ? '' : closeButton}
            `;
        }

        const branchPending = item.source_type === 'grading' && !item.problem_identification;
        const branchFilled  = item.source_type === 'grading' && item.problem_identification;

        // Hitung progress PICA (4 tahap × 25%)
        const step1 = !!item.current_condition;
        const step2 = !!(item.problem_identification && item.corrective_action && item.target_date && item.pic && (item.relation_ship || item.relation_ship2));
        const step3 = !!item.forwarded_filled_at;
        const step4 = !!item.recheck_at;
        // Jika Re-Chek sudah diisi → 100% langsung
        const pct = step4 ? 100 : ([step1, step2, step3].filter(Boolean).length * 25 + (step4 ? 25 : 0));
        const pctFull = step4 ? 100 : (step1 ? 25 : 0) + (step2 ? 25 : 0) + (step3 ? 25 : 0);
        const progressPct = step4 ? 100 : pctFull;
        const progressColor = progressPct === 100 ? 'bg-emerald-500' : progressPct >= 50 ? 'bg-amber-500' : 'bg-blue-500';
        const progressLabel = progressPct === 100 ? 'text-emerald-400' : progressPct >= 50 ? 'text-amber-400' : 'text-blue-400';

        return `
            <tr class="hover:bg-slate-950/50 ${branchPending ? 'border-l-2 border-l-amber-500' : ''}">
                <td class="px-4 py-4">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.pica_no || `PICA-${item.id}`)}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(item.title || '-')}</div>
                    ${item.unit_usaha ? `<div class="text-xs text-blue-400">Cabang: ${escapeHtml(item.unit_usaha)}</div>` : ''}
                    <div class="mt-2 max-w-xl text-xs text-slate-400 space-y-0.5">
                        ${item.current_condition ? `<div><span class="text-slate-500">Current Condition:</span> ${escapeHtml(item.current_condition)}</div>` : ''}
                        ${item.problem_identification ? `<div><span class="text-slate-500">Problem ID:</span> ${escapeHtml(item.problem_identification)}</div>` : ''}
                        ${item.corrective_action ? `<div><span class="text-slate-500">Corrective Action:</span> ${escapeHtml(item.corrective_action)}</div>` : ''}
                    </div>
                    ${branchPending ? `<span class="mt-1 inline-block text-xs font-semibold text-amber-400">⚠ Menunggu isian cabang</span>` : ''}
                    ${branchFilled  ? `<span class="mt-1 inline-block text-xs font-semibold text-emerald-400">✓ Cabang sudah mengisi</span>` : ''}
                    <div class="mt-2 w-full max-w-xs">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-slate-500">Progress</span>
                            <span class="text-xs font-bold ${progressLabel}">${progressPct}%</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-slate-700">
                            <div class="h-1.5 rounded-full ${progressColor} transition-all duration-300" style="width:${progressPct}%"></div>
                        </div>
                        <div class="mt-1 flex gap-1.5 text-xs text-slate-600">
                            <span title="Auditor" class="${step1 ? 'text-emerald-500' : ''}">①</span>
                            <span title="Unit Usaha" class="${step2 ? 'text-emerald-500' : ''}">②</span>
                            <span title="Relation Ship" class="${step3 ? 'text-emerald-500' : ''}">③</span>
                            <span title="Re-Chek" class="${step4 ? 'text-emerald-500' : ''}">④</span>
                        </div>
                    </div>
                </td>

                <td class="px-4 py-4 text-sm text-slate-300">
                    <div>${escapeHtml(item.pic || '-')}</div>
                    ${item.relation_ship  ? `<div class="text-xs text-slate-500">${escapeHtml(item.relation_ship)}</div>` : ''}
                    ${item.relation_ship2 ? `<div class="text-xs text-slate-500">${escapeHtml(item.relation_ship2)}</div>` : ''}
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
                    <div class="flex flex-col items-end gap-2">
                        <button type="button" class="view-pica rounded-lg border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${item.id}">
                            View
                        </button>
                        ${actions}
                    </div>
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
        if (document.getElementById('auditRecommendationId')) document.getElementById('auditRecommendationId').value = item.audit_recommendation_id || '';
        if (document.getElementById('picaNo')) document.getElementById('picaNo').value = item.pica_no || '';
        document.getElementById('title').value = item.title || '';
        document.getElementById('currentCondition').value = item.current_condition || '';
        document.getElementById('problemIdentification').value = item.problem_identification || '';
        const piReadonly = document.getElementById('problemIdentificationReadonly');
        if (piReadonly) piReadonly.value = item.problem_identification || '';
        document.getElementById('correctiveAction').value = item.corrective_action || '';
        document.getElementById('pic').value = item.pic || '';
        document.getElementById('relationShip').value = item.relation_ship || '';
        document.getElementById('relationShip2').value = item.relation_ship2 || '';
        if (document.getElementById('priority')) document.getElementById('priority').value = item.priority || 'sedang';
        if (document.getElementById('status')) document.getElementById('status').value = item.status || 'open';
        document.getElementById('targetDate').value = onlyDate(item.target_date);
        if (document.getElementById('actualDate')) document.getElementById('actualDate').value = onlyDate(item.actual_date);
        document.getElementById('notes').value = item.notes || '';
        document.getElementById('unitUsaha').value = item.unit_usaha || '';

        // Tampilkan field unit_usaha hanya untuk admin/manajer
        const isAdminOrMgr = ['admin', 'manajer'].includes(currentUser?.role);
        const unitWrap = document.getElementById('unitUsahaWrap');
        if (unitWrap) unitWrap.classList.toggle('hidden', !isAdminOrMgr);

        // Cabang ATAU pihak Relation Ship hanya bisa isi kolom tertentu
        const _myUnit = currentUser?.unitUsaha || currentUser?.unit_usaha;
        const _fwdByCol = item.forwarded_to_unit && _myUnit && item.forwarded_to_unit === _myUnit;
        const _fwdByRel = _myUnit && !_fwdByCol && (
            (item.relation_ship && item.relation_ship.includes(_myUnit)) ||
            (item.relation_ship2 && item.relation_ship2.includes(_myUnit))
        );
        const isForwardedToMe = _fwdByCol || _fwdByRel;
        const restrictedMode = isBranchRole() || isForwardedToMe;
        // Field yang TIDAK boleh diubah
        ['title','currentCondition','notes']
            .forEach(id => { const el = document.getElementById(id); if (el) el.disabled = restrictedMode; });
        // Field yang bisa diisi (aktifkan hanya yg editable)
        ['problemIdentification','targetDate']
            .forEach(id => { const el = document.getElementById(id); if (el) el.disabled = false; });
        // Field info cabang selalu read-only
        ['correctiveAction','pic','relationShip','relationShip2']
            .forEach(id => { const el = document.getElementById(id); if (el) el.disabled = true; });

        // Forwarded party mengisi form baru — kosongkan Tanggapan PICA agar tidak terisi data cabang
        if (isForwardedToMe) {
            document.getElementById('problemIdentification').value = '';
        }
    } else {
        title.textContent = 'Tambah PICA';

        document.getElementById('picaId').value = '';
        document.getElementById('priority').value = 'sedang';
        document.getElementById('status').value = 'open';
        const unitWrap = document.getElementById('unitUsahaWrap');
        if (unitWrap) unitWrap.classList.add('hidden');
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
        pica_no: emptyToNull(document.getElementById('picaNo')?.value),
        title: emptyToNull(document.getElementById('title').value),
        current_condition: emptyToNull(document.getElementById('currentCondition').value),
        problem_identification: emptyToNull(document.getElementById('problemIdentification').value),
        corrective_action: emptyToNull(document.getElementById('correctiveAction').value),
        pic: emptyToNull(document.getElementById('pic').value),
        relation_ship: emptyToNull(document.getElementById('relationShip').value),
        relation_ship2: emptyToNull(document.getElementById('relationShip2').value),
        priority: document.getElementById('priority')?.value || 'sedang',
        status: document.getElementById('status')?.value || 'open',
        target_date: emptyToNull(document.getElementById('targetDate').value),
        actual_date: emptyToNull(document.getElementById('actualDate')?.value),
        notes: emptyToNull(document.getElementById('notes').value),
        unit_usaha: emptyToNull(document.getElementById('unitUsaha')?.value),
    };
}

async function savePica(event) {
    event.preventDefault();

    const id = document.getElementById('picaId').value;
    const isEdit = Boolean(id);

    // Cek apakah user adalah forwarded party untuk PICA ini
    const currentPica = isEdit ? picas.find(p => String(p.id) === String(id)) : null;
    const myUnit = currentUser?.unitUsaha || currentUser?.unit_usaha;
    const isForwardedParty = currentPica && myUnit && (
        currentPica.forwarded_to_unit === myUnit ||
        (currentPica.relation_ship && currentPica.relation_ship.includes(myUnit)) ||
        (currentPica.relation_ship2 && currentPica.relation_ship2.includes(myUnit))
    );

    if (!canManagePicas() && !isForwardedParty) {
        showAlert('Role kamu hanya boleh melihat data.', 'error');
        return;
    }

    const formData = getFormPayload();
    console.log('Saving PICA payload:', formData);

    const payload = await fetchJson(isEdit ? `/api/picas/${id}` : '/api/picas', {
        method: isEdit ? 'PUT' : 'POST',
        body: JSON.stringify(formData),
    });
    console.log('PICA save response:', payload);

    closeModal();
    if (payload.forwarded_to) {
        showAlert(`✅ PICA diteruskan ke: ${payload.forwarded_to} — status berubah ke Progress.`);
    } else {
        showAlert(payload.message || 'PICA berhasil disimpan.');
    }
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

function fieldRow(label, value, amber = false) {
    if (!value) return '';
    const color = amber ? 'text-amber-300' : 'text-slate-400';
    return `
        <div class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-3">
            <div class="mb-1 text-xs font-semibold uppercase tracking-wide ${color}">${label}</div>
            <div class="whitespace-pre-wrap text-sm text-slate-100">${escapeHtml(value)}</div>
        </div>`;
}

function openViewModal(item) {
    document.getElementById('viewPicaTitle').textContent = item.title || 'Detail PICA';
    document.getElementById('viewPicaNo').textContent = item.pica_no || `PICA-${item.id}`;

    const step1 = !!item.current_condition;
    const step2 = !!(item.problem_identification && item.corrective_action && item.target_date && item.pic && (item.relation_ship || item.relation_ship2));
    const step3 = !!item.forwarded_filled_at;
    const step4 = !!item.recheck_at;
    const pct = step4 ? 100 : ([step1, step2, step3].filter(Boolean).length * 25);
    const pColor = pct === 100 ? '#10b981' : pct >= 50 ? '#f59e0b' : '#3b82f6';

    document.getElementById('viewPicaBody').innerHTML = `
        <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950 px-4 py-3">
            <span class="text-xs font-semibold uppercase text-slate-400">Progress Birokrasi</span>
            <span class="text-lg font-bold" style="color:${pColor}">${pct}%</span>
        </div>
        <div class="h-2 w-full rounded-full bg-slate-700">
            <div class="h-2 rounded-full transition-all" style="width:${pct}%;background:${pColor}"></div>
        </div>
        <div class="grid grid-cols-4 gap-2 text-center text-xs">
            ${[
                ['① Auditor', step1],
                ['② Unit Usaha', step2],
                ['③ Relation Ship', step3],
                ['④ Re-Chek', step4],
            ].map(([l, done]) => `
                <div class="rounded-lg border ${done ? 'border-emerald-700 bg-emerald-950 text-emerald-400' : 'border-slate-700 bg-slate-950 text-slate-500'} px-2 py-2 font-semibold">
                    ${done ? '✓' : '○'} ${l}
                </div>`).join('')}
        </div>
        <hr class="border-slate-800">
        <p class="text-xs font-semibold uppercase text-slate-500">— Auditor —</p>
        ${fieldRow('Current Condition', item.current_condition)}
        <hr class="border-slate-800">
        <p class="text-xs font-semibold uppercase text-slate-500">— Unit Usaha / Cabang —</p>
        ${fieldRow('Problem Identification', item.problem_identification)}
        ${fieldRow('Corrective Action', item.corrective_action)}
        ${fieldRow('PIC Completion', item.pic)}
        ${fieldRow('Deadline Completion', item.target_date ? String(item.target_date).slice(0,10) : null)}
        ${fieldRow('Relation Ship', item.relation_ship)}
        ${fieldRow('Relation Ship 2', item.relation_ship2)}
        <hr class="border-slate-800">
        <p class="text-xs font-semibold uppercase text-slate-500">— Tanggapan Relation Ship —</p>
        ${item.forwarded_filled_at
            ? fieldRow('Tanggapan PICA', item.problem_identification, true)
            : '<div class="text-xs text-slate-600 italic">Belum ada tanggapan.</div>'}
        <hr class="border-slate-800">
        <p class="text-xs font-semibold uppercase text-slate-500">— Re-Chek Unit Usaha —</p>
        ${item.recheck_at ? `
            ${fieldRow('Re-Chek at the next Review', item.recheck_note, true)}
            ${fieldRow('Deadline Recheck', item.recheck_deadline ? String(item.recheck_deadline).slice(0,10) : null)}
            ${item.recheck_file ? `<div class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-3"><div class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-300">Upload File</div><a href="${escapeHtml(item.recheck_file)}" target="_blank" class="text-blue-400 underline text-sm">Lihat File</a></div>` : ''}
        ` : '<div class="text-xs text-slate-600 italic">Belum ada re-chek.</div>'}
    `;

    const modal = document.getElementById('viewPicaModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeViewModal() {
    const modal = document.getElementById('viewPicaModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function openRechekModal(item) {
    document.getElementById('rechekPicaId').value = item.id;
    document.getElementById('rechekNote').value = item.recheck_note || '';
    document.getElementById('rechekDeadline').value = onlyDate(item.recheck_deadline);
    document.getElementById('rechekFile').value = '';
    const existing = document.getElementById('rechekFileExisting');
    if (existing) existing.textContent = item.recheck_file ? `File saat ini: ${item.recheck_file}` : '';
    const modal = document.getElementById('rechekModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRechekModal() {
    const modal = document.getElementById('rechekModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('openCreatePicaButton')?.addEventListener('click', () => openModal());
    document.getElementById('closePicaModalButton')?.addEventListener('click', closeModal);
    document.getElementById('cancelPicaFormButton')?.addEventListener('click', closeModal);

    document.getElementById('picaForm')?.addEventListener('submit', async (event) => {
        try {
            await savePica(event);
        } catch (error) {
            console.error('PICA save error:', error);
            const msg = error.message || 'Gagal menyimpan PICA.';
            showAlert(msg, 'error');
            alert('Error simpan PICA: ' + msg);
        }
    });

    document.getElementById('picasTableBody')?.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.edit-pica');
        const deleteButton = event.target.closest('.delete-pica');
        const closeButton = event.target.closest('.close-pica');
        const rechekButton = event.target.closest('.recheck-pica');
        const viewButton = event.target.closest('.view-pica');

        if (viewButton) {
            const item = picas.find((row) => String(row.id) === String(viewButton.dataset.id));
            if (item) openViewModal(item);
            return;
        }

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
            return;
        }

        if (rechekButton) {
            const item = picas.find((row) => String(row.id) === String(rechekButton.dataset.id));
            openRechekModal(item);
        }
    });

    // View modal handlers
    document.getElementById('closeViewPicaModal')?.addEventListener('click', closeViewModal);

    // Re-Chek modal handlers
    document.getElementById('closeRechekModal')?.addEventListener('click', closeRechekModal);
    document.getElementById('cancelRechekButton')?.addEventListener('click', closeRechekModal);
    document.getElementById('rechekForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('rechekPicaId').value;
        const note = document.getElementById('rechekNote').value.trim();
        const deadline = document.getElementById('rechekDeadline').value;
        const fileInput = document.getElementById('rechekFile');

        const saveBtn = document.getElementById('saveRechekButton');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Menyimpan...';

        try {
            let rechekFile = null;
            if (fileInput.files[0]) {
                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                const up = await fetch('/api/upload', {
                    method: 'POST',
                    headers: authHeaders(),
                    body: formData,
                });
                if (up.ok) {
                    const upData = await up.json();
                    rechekFile = upData.path ?? upData.url ?? null;
                }
            }

            await fetchJson(`/api/picas/${id}`, {
                method: 'PUT',
                headers: { ...authHeaders(), 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    recheck_note: note || null,
                    recheck_deadline: deadline || null,
                    recheck_file: rechekFile,
                    recheck_at: new Date().toISOString(),
                }),
            });

            showAlert('Re-Chek berhasil disimpan.');
            closeRechekModal();
            await loadPicas();
        } catch (err) {
            showAlert(err.message || 'Gagal menyimpan Re-Chek.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Simpan Re-Chek';
        }
    });

    setupFilters();

    document.getElementById('syncPicaBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('syncPicaBtn');
        if (btn) { btn.textContent = '⏳ Sinkronisasi...'; btn.disabled = true; }
        try {
            // Sync semua grading yang ada agar PICA-nya muncul
            const gradings = await fetchJson('/api/gradings');
            const list = Array.isArray(gradings) ? gradings : (gradings.data ?? []);
            let count = 0;
            for (const g of list) {
                const planId = g.plan_audit_id ?? g.planAuditId;
                if (!planId) continue;
                try {
                    await fetchJson('/api/audit-detail/grading/sync-pica', {
                        method: 'POST',
                        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
                        body: JSON.stringify({ planAuditId: planId }),
                    });
                    count++;
                } catch (_) {}
            }
            showAlert(`Sinkronisasi selesai: ${count} plan diproses.`);
            await loadPicas();
        } catch (e) {
            showAlert(e.message || 'Gagal sinkronisasi.', 'error');
        } finally {
            if (btn) { btn.textContent = '🔄 Sinkron dari Grading'; btn.disabled = false; }
        }
    });

    try {
        await loadCurrentUser();

        // Branch role tidak boleh buat PICA baru dan tidak tampil tombol Tambah
        if (!canManagePicas() || isBranchRole()) {
            document.getElementById('openCreatePicaButton')?.classList.add('hidden');
        }
        if (!['admin', 'manajer', 'auditor'].includes(currentUser?.role)) {
            document.getElementById('syncPicaBtn')?.classList.add('hidden');
        }

        await loadUserDatalist();
        await loadRecommendations();
        await loadPicas();
    } catch (error) {
        showAlert(error.message || 'Gagal memuat PICA.', 'error');
    }
});
