const SESSION_KEY = "akta_session";

let tasks = [];
let currentUser = null;

function getSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function authHeaders(extra = {}) {
    const session = getSession();
    return {
        Accept: "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
        ...extra,
    };
}

function showAlert(message, type = "success") {
    const el = document.getElementById("taskAlert");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden", "border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200", "border-red-500/30", "bg-red-500/10", "text-red-200");
    if (type === "error") {
        el.classList.add("border-red-500/30", "bg-red-500/10", "text-red-200");
    } else {
        el.classList.add("border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200");
    }
    window.scrollTo({ top: 0, behavior: "smooth" });
    setTimeout(() => el.classList.add("hidden"), 6000);
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

const STATUS_META = {
    todo:        { label: "Belum Dikerjakan", badge: "bg-slate-500/10 text-slate-300 border-slate-500/20" },
    in_progress: { label: "Sedang Berjalan",  badge: "bg-amber-500/10 text-amber-300 border-amber-500/20" },
    review:      { label: "Review",           badge: "bg-blue-500/10 text-blue-300 border-blue-500/20" },
    done:        { label: "Selesai",          badge: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20" },
    cancelled:   { label: "Dibatalkan",       badge: "bg-red-500/10 text-red-300 border-red-500/20" },
};

async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(firstError || payload.message || "Request gagal.");
    }
    return payload;
}

async function loadCurrentUser() {
    const payload = await fetchJson("/api/auth/me", { headers: authHeaders() });
    currentUser = payload.user;
}

async function loadTasks() {
    const q = document.getElementById("taskSearch")?.value || "";
    const status = document.getElementById("taskStatusFilter")?.value || "";
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (status) params.set("status", status);
    const url = params.toString() ? `/api/tasks?${params}` : "/api/tasks";
    const payload = await fetchJson(url, { headers: authHeaders() });
    tasks = payload.data || [];
    renderTasks();
}

function fmtDateTime(value) {
    if (!value) return "-";
    return String(value).slice(0, 10);
}

function renderTasks() {
    const tbody = document.getElementById("tasksTableBody");
    if (!tbody) return;

    if (!tasks.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Belum ada tugas audit untuk Anda.</td></tr>`;
        return;
    }

    tbody.innerHTML = tasks.map((task) => {
        const plan = task.planAudit || {};
        const meta = STATUS_META[task.status] || STATUS_META.todo;
        const pelaksanaan = task.startedAt
            ? `<div>${escapeHtml(fmtDateTime(task.startedAt))}</div><div class="text-xs text-slate-500">s/d ${escapeHtml(fmtDateTime(task.finishedAt))}</div>`
            : `<span class="text-xs text-slate-500">Belum dikerjakan</span>`;
        const lampiran = task.lampiranUrl
            ? `<a href="${escapeHtml(task.lampiranUrl)}" target="_blank" class="ml-2 text-xs text-blue-400 underline">Lampiran</a>`
            : "";

        return `
        <tr class="hover:bg-slate-950/50">
            <td class="px-4 py-4">
                <div class="font-semibold text-slate-100">${escapeHtml(plan.cabang || task.judul || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(plan.noSpt || "-")}</div>
            </td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(plan.jenisAudit || task.kategori || "-")}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(plan.tglPlan || task.dueDate || "-")}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${pelaksanaan}</td>
            <td class="px-4 py-4">
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${meta.badge}">${escapeHtml(meta.label)}</span>
                ${lampiran}
            </td>
            <td class="px-4 py-4 text-right">
                <button type="button" class="execute-task rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10" data-id="${task.id}">
                    ${isBranchUser() ? "Mulai Cabang" : isViewOnly() ? "Tinjau" : (task.status === "done" ? "Lihat / Ubah" : "Kerjakan")}
                </button>
            </td>
        </tr>`;
    }).join("");
}

// ── Modal ─────────────────────────────────────────────────────────────────────

function planDetailRow(label, value) {
    return `
        <div>
            <dt class="text-xs uppercase tracking-wide text-slate-500">${escapeHtml(label)}</dt>
            <dd class="mt-0.5 font-medium text-slate-200">${escapeHtml(value || "-")}</dd>
        </div>`;
}

function todayLocal() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function toDateOnly(value) {
    if (!value) return "";
    return String(value).slice(0, 10);
}

// Role approval untuk PLAN
const APPROVAL_STAGE = {
    koordinator: "pending_koordinator",
    manajer:     "pending_manajer",
    coo:         "pending_coo",
};

// Role approval untuk PINJAMAN CABANG (BPK & BPB)
const PINJAMAN_STAGE = {
    koordinator: "pending_koordinator",
    manajer:     "pending_manajer",
    coo:         "pending_coo",
    unit:        "pending_unit",
    bpk:         "pending_bpk",
};

const PINJAMAN_STATUS_LABEL = {
    pending_koordinator: "Menunggu Koordinator",
    pending_manajer:     "Menunggu Manajer Audit",
    pending_coo:         "Menunggu COO",
    pending_unit:        "Menunggu Unit Usaha",
    pending_bpk:         "Menunggu Role BPK",
    approved:            "Disetujui",
    rejected:            "Ditolak",
};

// HO roles (bukan branch user)
const HO_ROLES = ["admin", "manajer", "auditor", "koordinator", "coo", "unit", "bpk"];

function isApprovalRole() {
    return Object.prototype.hasOwnProperty.call(APPROVAL_STAGE, currentUser?.role);
}

function isPinjamanApprovalRole() {
    return Object.prototype.hasOwnProperty.call(PINJAMAN_STAGE, currentUser?.role);
}

function isBranchUser() {
    return !HO_ROLES.includes(currentUser?.role);
}

function isViewOnly() {
    // unit dan bpk tidak approve plan, tapi approval pinjaman saja
    return isApprovalRole() || isPinjamanApprovalRole();
}

// Label status birokrasi untuk timeline
const PLAN_STATUS_LABEL = {
    draft: "Draft",
    pending_koordinator: "Menunggu Koordinator",
    pending_manajer: "Menunggu Manajer Audit",
    pending_coo: "Menunggu COO",
    scheduled: "Terjadwal",
    running: "Audit Berjalan",
    cabang_active: "Cabang Aktif",
    revisi: "Perlu Perbaikan",
    done: "Selesai",
    cancelled: "Dibatalkan",
};

const ACTION_META = {
    created: { label: "Plan dibuat",   dot: "bg-blue-500" },
    advance: { label: "Disetujui",     dot: "bg-emerald-500" },
    reject:  { label: "Ditolak",       dot: "bg-red-500" },
    execute: { label: "Pelaksanaan",   dot: "bg-amber-500" },
};

const ROLE_LABEL = {
    admin: "Admin",
    manajer: "Manajer Audit",
    auditor: "Auditor",
    koordinator: "Koordinator",
    coo: "COO",
};

function statusLabel(s) {
    return PLAN_STATUS_LABEL[s] || (s || "-");
}

function renderTimeline(logs) {
    const el = document.getElementById("planTimeline");
    if (!el) return;

    if (!logs || !logs.length) {
        el.innerHTML = `<li class="text-xs text-slate-500">Belum ada riwayat status.</li>`;
        return;
    }

    el.innerHTML = logs.map((log) => {
        const meta = ACTION_META[log.action] || { label: log.action, dot: "bg-slate-500" };
        const role = ROLE_LABEL[log.actorRole] || log.actorRole || "";
        const who = [log.actor, role ? `(${role})` : ""].filter(Boolean).join(" ");
        const transisi = (log.fromStatus && log.toStatus && log.fromStatus !== log.toStatus)
            ? `<span class="text-slate-400">${escapeHtml(statusLabel(log.fromStatus))} → <span class="text-slate-200">${escapeHtml(statusLabel(log.toStatus))}</span></span>`
            : `<span class="text-slate-200">${escapeHtml(statusLabel(log.toStatus))}</span>`;

        return `
        <li class="flex gap-3">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${meta.dot}"></span>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-x-2">
                    <span class="font-semibold text-slate-100">${escapeHtml(meta.label)}</span>
                    ${transisi}
                </div>
                ${log.note ? `<div class="text-xs text-slate-400">${escapeHtml(log.note)}</div>` : ""}
                <div class="text-xs text-slate-500">${escapeHtml(who)} • ${escapeHtml(log.createdAt || "")}</div>
            </div>
        </li>`;
    }).join("");
}

function openModal(task) {
    const modal = document.getElementById("taskModal");
    const plan = task.planAudit || {};

    renderTimeline(plan.logs);

    document.getElementById("taskForm").reset();
    document.getElementById("taskId").value = task.id;

    document.getElementById("planDetail").innerHTML = [
        planDetailRow("No SPT", plan.noSpt),
        planDetailRow("Jenis Audit", plan.jenisAudit || task.kategori),
        planDetailRow("Cabang", plan.cabang),
        planDetailRow("Wilayah", plan.cabangArea),
        planDetailRow("Tanggal Plan", plan.tglPlan),
        planDetailRow("Kepala Tim", plan.kepalaTim),
        planDetailRow("Tim Audit", (plan.tim || []).join(", ")),
        planDetailRow("PIC Task", task.assignedTo),
        planDetailRow("Status Pelaksanaan", task.startedAt
            ? `${toDateOnly(task.startedAt)} s/d ${toDateOnly(task.finishedAt) || "-"}`
            : "Belum dikerjakan"),
        task.lampiranUrl
            ? `<div>
                   <dt class="text-xs uppercase tracking-wide text-slate-500">File Lampiran</dt>
                   <dd class="mt-0.5 font-medium"><a href="${escapeHtml(task.lampiranUrl)}" target="_blank" class="text-blue-400 underline">${escapeHtml(task.lampiranName || "Lihat lampiran")}</a></dd>
               </div>`
            : planDetailRow("File Lampiran", "Belum ada"),
    ].join("");

    const viewOnly = isViewOnly();
    const branch = isBranchUser();
    const execSection = document.getElementById("execSection");
    const approvalSection = document.getElementById("approvalSection");
    const branchSection = document.getElementById("branchSection");
    const selesaiCabangSection = document.getElementById("selesaiCabangSection");
    const pinjamanApprovalSection = document.getElementById("pinjamanApprovalSection");
    const revisiSection = document.getElementById("revisiSection");

    // Sembunyikan semua seksi dulu
    execSection?.classList.add("hidden");
    approvalSection?.classList.add("hidden");
    branchSection?.classList.add("hidden");
    selesaiCabangSection?.classList.add("hidden");
    pinjamanApprovalSection?.classList.add("hidden");
    revisiSection?.classList.add("hidden");

    if (branch) {
        // Branch user: tampilkan tombol Mulai Cabang jika plan sedang running
        if (plan.status === "running") {
            branchSection?.classList.remove("hidden");
            document.getElementById("approvePlanId").value = plan.id || "";
        }
        // Branch user: tampilkan tombol Selesai jika cabang_active, aktif hanya bila syarat lengkap
        if (plan.status === "cabang_active") {
            selesaiCabangSection?.classList.remove("hidden");
            document.getElementById("approvePlanId").value = plan.id || "";

            const lengkap = !!plan.canMarkSelesai;
            document.getElementById("selesaiCabangInfo")?.classList.toggle("hidden", !lengkap);
            document.getElementById("selesaiCabangBelumLengkap")?.classList.toggle("hidden", lengkap);
            const btn = document.getElementById("selesaiCabangBtn");
            if (btn) btn.disabled = !lengkap;
        }
    } else if (viewOnly) {
        // Tampilkan tombol approve/reject PLAN jika role ada di APPROVAL_STAGE
        const stage = APPROVAL_STAGE[currentUser?.role];
        const canApprovePlan = stage && plan.status === stage;
        if (approvalSection) {
            approvalSection.classList.toggle("hidden", !canApprovePlan);
            document.getElementById("approvePlanId").value = plan.id || "";

            const info = document.getElementById("approvalInfo");
            if (info) {
                const labelMap = {
                    koordinator: "Koordinator",
                    manajer: "Manajer Audit",
                    coo: "COO",
                };
                const who = labelMap[currentUser?.role] || "Anda";
                info.textContent = `Plan audit ini menunggu persetujuan ${who}. Periksa data plan di atas lalu pilih tindakan.`;
            }
        }
    } else if (plan.status === "revisi" && ["auditor", "admin"].includes(currentUser?.role)) {
        revisiSection?.classList.remove("hidden");
        document.getElementById("approvePlanId").value = plan.id || "";
        const revisiCatatan = document.getElementById("revisiCatatan");
        if (revisiCatatan) revisiCatatan.value = "";
    } else {
        execSection?.classList.remove("hidden");
        document.getElementById("approvePlanId").value = plan.id || "";

        // Prefill bila sudah pernah dikerjakan; jika belum, tanggal mulai = hari ini
        document.getElementById("startedAt").value = toDateOnly(task.startedAt) || todayLocal();
        document.getElementById("finishedAt").value = toDateOnly(task.finishedAt) || "";

        const current = document.getElementById("currentLampiran");
        if (task.lampiranUrl) {
            current.innerHTML = `Lampiran saat ini: <a href="${escapeHtml(task.lampiranUrl)}" target="_blank" class="text-blue-400 underline">${escapeHtml(task.lampiranName || "file")}</a>`;
            current.classList.remove("hidden");
        } else {
            current.classList.add("hidden");
        }
    }

    // Wire pinjaman section to this task (form untuk auditor)
    _pinjamanTaskId = task.id;
    const pinjamanSec = document.getElementById('pinjamanSection');
    if (pinjamanSec) {
        const hasS = !!toDateOnly(task.startedAt);
        const hasF = !!toDateOnly(task.finishedAt);
        pinjamanSec.classList.toggle('hidden', !(hasS && hasF));
    }
    pinjamanLoadList(task.id).catch(() => {});

    // Tampilkan pinjaman approval section untuk role yang ada di alur birokrasi pinjaman
    if (isPinjamanApprovalRole()) {
        pinjamanApprovalSection?.classList.remove("hidden");
        pinjamanApprovalLoadList(task.id).catch(() => {});
    }

    // Admin tools — reset status
    const adminSec = document.getElementById("adminToolsSection");
    if (adminSec) {
        if (currentUser?.role === 'admin') {
            adminSec.classList.remove("hidden");
            adminLoadPinjamanReset(task.id).catch(() => {});
        } else {
            adminSec.classList.add("hidden");
        }
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

async function approvePlan(planId) {
    if (!planId) return;

    // Cek apakah masih ada pinjaman yang menunggu giliran role ini
    if (_pinjamanTaskId && isPinjamanApprovalRole()) {
        try {
            const res = await fetchJson('/api/pinjaman-cabang?audit_task_id=' + _pinjamanTaskId, { headers: authHeaders() });
            const myStage = PINJAMAN_STAGE[currentUser?.role];
            const pending = (res.data ?? []).filter(p => p.status === myStage);
            if (pending.length > 0) {
                showAlert(
                    `Harap setujui atau tolak ${pending.length} pinjaman cabang (${pending.map(p=>p.jenis).join(', ')}) terlebih dahulu sebelum menyetujui plan audit.`,
                    'error'
                );
                return;
            }
        } catch (_) {}
    }

    if (!confirm("Setujui plan audit ini?")) return;
    try {
        const payload = await fetchJson(`/api/plans/${planId}/advance`, {
            method: "POST",
            headers: authHeaders(),
        });
        closeModal();
        showAlert(payload.message || "Plan audit disetujui.");
        await loadTasks();
    } catch (err) {
        showAlert(err.message || "Gagal menyetujui plan.", "error");
    }
}

async function mulaiCabang(planId) {
    if (!planId) return;
    if (!confirm("Konfirmasi kedatangan tim audit di cabang Anda?")) return;
    try {
        const payload = await fetchJson(`/api/plans/${planId}/advance`, {
            method: "POST",
            headers: authHeaders(),
        });
        closeModal();
        showAlert(payload.message || "Cabang aktif. Audit sedang berjalan.");
        await loadTasks();
    } catch (err) {
        showAlert(err.message || "Gagal mengonfirmasi.", "error");
    }
}

async function selesaiRevisi(planId) {
    if (!planId) return;
    const catatan = document.getElementById("revisiCatatan")?.value?.trim() || "";
    if (!catatan) {
        showAlert("Tanggapan perbaikan wajib diisi.", "error");
        return;
    }
    if (!confirm("Nyatakan perbaikan pemeriksaan ini selesai?")) return;
    try {
        const payload = await fetchJson(`/api/plans/${planId}/advance`, {
            method: "POST",
            headers: authHeaders({ "Content-Type": "application/json" }),
            body: JSON.stringify({ catatan }),
        });
        closeModal();
        showAlert(payload.message || "Perbaikan dinyatakan selesai.");
        await loadTasks();
    } catch (err) {
        showAlert(err.message || "Gagal menyelesaikan perbaikan.", "error");
    }
}

async function selesaiCabang(planId) {
    if (!planId) return;
    if (!confirm("Nyatakan pemeriksaan cabang ini selesai?")) return;
    try {
        const payload = await fetchJson(`/api/plans/${planId}/advance`, {
            method: "POST",
            headers: authHeaders(),
        });
        closeModal();
        showAlert(payload.message || "Pemeriksaan dinyatakan selesai.");
        await loadTasks();
    } catch (err) {
        showAlert(err.message || "Gagal menyelesaikan pemeriksaan.", "error");
    }
}

async function rejectPlan(planId) {
    if (!planId) return;
    const alasan = prompt("Masukkan alasan penolakan:");
    if (alasan === null) return;
    try {
        const payload = await fetchJson(`/api/plans/${planId}/reject`, {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ alasan }),
        });
        closeModal();
        showAlert(payload.message || "Plan audit ditolak.", "error");
        await loadTasks();
    } catch (err) {
        showAlert(err.message || "Gagal menolak plan.", "error");
    }
}

function closeModal() {
    const modal = document.getElementById("taskModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function saveExecution(event) {
    event.preventDefault();

    const id = document.getElementById("taskId").value;
    const startedAt = document.getElementById("startedAt").value;
    const finishedAt = document.getElementById("finishedAt").value;
    const fileInput = document.getElementById("lampiran");

    if (!startedAt || !finishedAt) {
        showAlert("Tanggal Mulai dan Selesai audit wajib diisi.", "error");
        return;
    }
    if (finishedAt < startedAt) {
        showAlert("Tanggal Selesai tidak boleh sebelum Tanggal Mulai.", "error");
        return;
    }

    const form = new FormData();
    form.append("started_at", startedAt);
    form.append("finished_at", finishedAt);
    if (fileInput.files?.[0]) {
        form.append("lampiran", fileInput.files[0]);
    }

    // Jangan set Content-Type agar boundary multipart otomatis
    const payload = await fetchJson(`/api/tasks/${id}/execute`, {
        method: "POST",
        headers: authHeaders(),
        body: form,
    });

    closeModal();
    showAlert(payload.message || "Pelaksanaan audit tersimpan.");
    await loadTasks();
}

// ── Pinjaman Cabang ───────────────────────────────────────────────────────────
let _pinjamanTaskId = null;

async function loadPinjamanCabangOptions() {
    const sel = document.getElementById('pinjamanCabang');
    if (!sel) return;
    try {
        const res = await fetchJson('/api/users/unit-usaha-by-role?role=h1', { headers: authHeaders() });
        const opts = res.data ?? [];
        sel.innerHTML = '<option value="">-- Pilih Unit Usaha --</option>' +
            opts.map(u => `<option value="${u}">${u}</option>`).join('');
    } catch (_) {}
}

function initPinjaman() {
    // Tampilkan section setelah kedua tanggal diisi
    ['startedAt', 'finishedAt'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => {
            const s = document.getElementById('startedAt')?.value;
            const f = document.getElementById('finishedAt')?.value;
            const sec = document.getElementById('pinjamanSection');
            if (sec) sec.classList.toggle('hidden', !(s && f));
        });
    });

    // Toggle BPK / BPB form
    document.getElementById('pinjamanBpkBtn')?.addEventListener('click', () => {
        document.getElementById('pinjamanBpkForm')?.classList.remove('hidden');
        document.getElementById('pinjamanBpbForm')?.classList.add('hidden');
        document.getElementById('pinjamanBpkBtn').classList.add('border-blue-500', 'text-blue-300');
        document.getElementById('pinjamanBpbBtn').classList.remove('border-purple-500', 'text-purple-300');
        loadPinjamanCabangOptions();
    });
    document.getElementById('pinjamanBpbBtn')?.addEventListener('click', () => {
        document.getElementById('pinjamanBpbForm')?.classList.remove('hidden');
        document.getElementById('pinjamanBpkForm')?.classList.add('hidden');
        document.getElementById('pinjamanBpbBtn').classList.add('border-purple-500', 'text-purple-300');
        document.getElementById('pinjamanBpkBtn').classList.remove('border-blue-500', 'text-blue-300');
    });

    // Auto terbilang dari nominal
    document.getElementById('pinjamanNominal')?.addEventListener('input', function () {
        document.getElementById('pinjamanTerbilang').value = terbilang(Number(this.value || 0));
    });
    document.getElementById('pinjamanBpbNominal')?.addEventListener('input', function () {
        document.getElementById('pinjamanBpbTerbilang').value = terbilang(Number(this.value || 0));
    });

    // Submit BPK
    document.getElementById('pinjamanBpkSubmit')?.addEventListener('click', async () => {
        if (!_pinjamanTaskId) return;
        const cabang = document.getElementById('pinjamanCabang')?.value;
        if (!cabang) { alert('Pilih Cabang Realisasi.'); return; }
        const noSpd   = document.getElementById('pinjamanNoSpd')?.value.trim();
        const nominal = document.getElementById('pinjamanNominal')?.value || '0';
        if (!noSpd) { alert('No SPD wajib diisi.'); return; }

        const form = new FormData();
        form.append('audit_task_id', _pinjamanTaskId);
        form.append('jenis', 'BPK');
        form.append('cabang_realisasi', JSON.stringify([cabang]));
        form.append('no_spd', noSpd);
        form.append('nominal', nominal);
        form.append('terbilang', document.getElementById('pinjamanTerbilang')?.value || '');
        form.append('catatan', document.getElementById('pinjamanCatatan')?.value || '');
        const bukti = document.getElementById('pinjamanBukti')?.files?.[0];
        if (bukti) form.append('bukti_file', bukti);

        await pinjamanSubmit(form);
    });

    // Submit BPB
    document.getElementById('pinjamanBpbSubmit')?.addEventListener('click', async () => {
        if (!_pinjamanTaskId) return;
        const nominal = document.getElementById('pinjamanBpbNominal')?.value || '0';

        const form = new FormData();
        form.append('audit_task_id', _pinjamanTaskId);
        form.append('jenis', 'BPB');
        form.append('departemen', 'Finance');
        form.append('nominal', nominal);
        form.append('terbilang', document.getElementById('pinjamanBpbTerbilang')?.value || '');
        form.append('catatan', document.getElementById('pinjamanBpbCatatan')?.value || '');

        await pinjamanSubmit(form);
    });
}

async function pinjamanSubmit(formData) {
    try {
        const res = await fetchJson('/api/pinjaman-cabang', {
            method: 'POST',
            headers: authHeaders(),
            body: formData,
        });
        showAlert(res.message || 'Pinjaman diajukan.');
        await pinjamanLoadList(_pinjamanTaskId);
        // Reset form
        document.getElementById('pinjamanBpkForm')?.classList.add('hidden');
        document.getElementById('pinjamanBpbForm')?.classList.add('hidden');
        const cabSel = document.getElementById('pinjamanCabang');
        if (cabSel) cabSel.value = '';
    } catch (e) {
        showAlert(e.message, 'error');
    }
}

async function pinjamanLoadList(taskId) {
    const listEl = document.getElementById('pinjamanList');
    if (!listEl || !taskId) return;
    try {
        const res  = await fetchJson('/api/pinjaman-cabang?audit_task_id=' + taskId, { headers: authHeaders() });
        const rows = res.data ?? [];
        if (!rows.length) { listEl.innerHTML = ''; return; }
        listEl.innerHTML = `<p class="text-xs font-semibold text-slate-400 mb-1">Pinjaman yang sudah diajukan:</p>` +
            rows.map(r => {
                const statusColor = r.status === 'approved' ? 'text-emerald-400' : r.status === 'rejected' ? 'text-red-400' : 'text-amber-400';
                return `<div class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2 text-xs flex justify-between items-center">
                    <div>
                        <span class="font-bold ${r.jenis === 'BPK' ? 'text-blue-300' : 'text-purple-300'}">${r.jenis}</span>
                        <span class="mx-2 text-slate-500">|</span>
                        <span class="text-slate-300">Rp ${Number(r.nominal).toLocaleString('id-ID')}</span>
                        ${r.jenis === 'BPK' ? `<span class="mx-2 text-slate-500">|</span><span class="text-slate-400">${(r.cabangRealisasi ?? []).join(', ')}</span>` : ''}
                    </div>
                    <span class="${statusColor} font-semibold">${r.status.replace(/_/g,' ')}</span>
                </div>`;
            }).join('');
    } catch (_) {}
}

function terbilang(n) {
    if (!n || n === 0) return 'Nol Rupiah';
    const satuan = ['','Satu','Dua','Tiga','Empat','Lima','Enam','Tujuh','Delapan','Sembilan'];
    const belasan = ['Sepuluh','Sebelas','Dua Belas','Tiga Belas','Empat Belas','Lima Belas','Enam Belas','Tujuh Belas','Delapan Belas','Sembilan Belas'];
    function ribuan(num) {
        if (num < 10)   return satuan[num];
        if (num < 20)   return belasan[num - 10];
        if (num < 100)  return satuan[Math.floor(num/10)] + ' Puluh ' + (satuan[num % 10] || '');
        if (num < 1000) return (num < 200 ? 'Seratus' : satuan[Math.floor(num/100)] + ' Ratus') + ' ' + (ribuan(num % 100) || '');
        if (num < 1e6)  return (num < 2000 ? 'Seribu' : ribuan(Math.floor(num/1000)) + ' Ribu') + ' ' + (ribuan(num % 1000) || '');
        if (num < 1e9)  return ribuan(Math.floor(num/1e6)) + ' Juta ' + (ribuan(num % 1e6) || '');
        return ribuan(Math.floor(num/1e9)) + ' Miliar ' + (ribuan(num % 1e9) || '');
    }
    return ribuan(Math.floor(n)).trim() + ' Rupiah';
}

async function pinjamanApprovalLoadList(taskId) {
    const listEl = document.getElementById('pinjamanApprovalList');
    if (!listEl || !taskId) return;
    try {
        const res  = await fetchJson('/api/pinjaman-cabang?audit_task_id=' + taskId, { headers: authHeaders() });
        const rows = res.data ?? [];
        if (!rows.length) {
            listEl.innerHTML = '<p class="text-xs text-slate-500">Tidak ada pinjaman yang diajukan.</p>';
            return;
        }

        const myStage = PINJAMAN_STAGE[currentUser?.role];
        const fmtRp = (n) => {
            try { return Number(n || 0).toLocaleString('id-ID'); }
            catch (_) { return String(Number(n || 0)); }
        };
        const html = [];
        for (const r of rows) {
            const canAct = myStage && r.status === myStage;
            const slabel = PINJAMAN_STATUS_LABEL[r.status] || String(r.status || '-');
            const scolor = r.status === 'approved' ? 'text-emerald-400' : r.status === 'rejected' ? 'text-red-400' : 'text-amber-400';
            const jenis  = String(r.jenis || '');
            const cabang = Array.isArray(r.cabangRealisasi) ? r.cabangRealisasi.join(', ') : (r.cabangRealisasi || '-');
            const approvalRows = Array.isArray(r.approvals) ? r.approvals.map(a =>
                `<div>${escapeHtml(String(a.role||''))} &mdash; ${escapeHtml(String(a.action||''))} (${escapeHtml(String((a.at||'').slice(0,10)))})</div>`
            ).join('') : '';
            html.push(`<div class="rounded-xl border border-slate-700 bg-slate-800/60 p-3 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-sm ${jenis === 'BPK' ? 'text-blue-300' : 'text-purple-300'}">${escapeHtml(jenis)}</span>
                    <span class="text-xs ${scolor} font-semibold">${escapeHtml(slabel)}</span>
                </div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-400">
                    <div><span class="text-slate-500">Nominal:</span> Rp ${fmtRp(r.nominal)}</div>
                    ${jenis === 'BPK' ? `<div><span class="text-slate-500">Cabang:</span> ${escapeHtml(String(cabang))}</div>` : `<div><span class="text-slate-500">Dept:</span> Finance</div>`}
                    ${r.noSpd ? `<div><span class="text-slate-500">No SPD:</span> ${escapeHtml(String(r.noSpd))}</div>` : ''}
                    ${r.catatan ? `<div class="col-span-2"><span class="text-slate-500">Catatan:</span> ${escapeHtml(String(r.catatan))}</div>` : ''}
                </div>
                ${approvalRows ? `<div class="text-xs text-slate-500 border-t border-slate-700 pt-2">${approvalRows}</div>` : ''}
                ${canAct ? `<div class="flex gap-2 pt-1">
                    <button type="button" onclick="pinjamanApprove(${r.id},'reject')" class="flex-1 rounded-lg border border-red-500/40 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10">Tolak</button>
                    <button type="button" onclick="pinjamanApprove(${r.id},'approve')" class="flex-1 rounded-lg bg-emerald-600 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Setujui</button>
                </div>` : ''}
            </div>`);
        }
        listEl.innerHTML = html.join('');
    } catch (e) {
        listEl.innerHTML = `<p class="text-xs text-red-400">Error: ${escapeHtml(e.message || String(e))}</p>`;
    }
}

async function pinjamanApprove(id, action) {
    const note = action === 'reject' ? (prompt('Alasan penolakan:') ?? '') : '';
    if (action === 'reject' && note === null) return;
    try {
        const res = await fetchJson(`/api/pinjaman-cabang/${id}/approve`, {
            method: 'POST',
            headers: { ...authHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, note }),
        });
        showAlert(res.message || 'Status pinjaman diperbarui.');
        pinjamanApprovalLoadList(_pinjamanTaskId).catch(() => {});
    } catch (e) {
        showAlert(e.message, 'error');
    }
}
window.pinjamanApprove = pinjamanApprove;

// ── Admin Tools ───────────────────────────────────────────────────────────────
async function adminLoadPinjamanReset(taskId) {
    const el = document.getElementById('adminPinjamanResetList');
    if (!el || !taskId) return;
    try {
        const res  = await fetchJson('/api/pinjaman-cabang?audit_task_id=' + taskId, { headers: authHeaders() });
        const rows = res.data ?? [];
        if (!rows.length) { el.innerHTML = '<p class="text-slate-500">Tidak ada pinjaman.</p>'; return; }
        el.innerHTML = rows.map(r => {
            const flow = r.jenis === 'BPK'
                ? ['pending_koordinator','pending_manajer','pending_coo','pending_unit','pending_bpk','approved','rejected']
                : ['pending_koordinator','pending_manajer','pending_bpk','approved','rejected'];
            const opts = flow.map(s => `<option value="${s}" ${s===r.status?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('');
            return `<div class="rounded-lg border border-slate-700 bg-slate-800/60 p-2 space-y-1">
                <div class="flex items-center gap-2">
                    <span class="font-bold ${r.jenis==='BPK'?'text-blue-300':'text-purple-300'}">${r.jenis}</span>
                    <span class="text-slate-400">— Rp ${Number(r.nominal||0).toLocaleString()}</span>
                    <span class="ml-auto text-amber-400">${escapeHtml(r.status||'')}</span>
                </div>
                <div class="flex gap-2">
                    <select id="adminPinjSel_${r.id}" class="flex-1 rounded-lg border border-slate-600 bg-slate-900 px-2 py-1 text-xs text-slate-100 outline-none">${opts}</select>
                    <button type="button" onclick="adminResetPinjaman(${r.id})"
                        class="rounded-lg bg-red-600/80 px-3 py-1 text-xs font-semibold text-white hover:bg-red-500">Reset</button>
                </div>
                <input id="adminPinjAlasan_${r.id}" type="text" placeholder="Alasan koreksi..."
                    class="w-full rounded-lg border border-slate-600 bg-slate-900 px-2 py-1 text-xs text-slate-100 outline-none">
            </div>`;
        }).join('');
    } catch (_) {}
}

async function adminResetPinjaman(id) {
    const status = document.getElementById(`adminPinjSel_${id}`)?.value;
    const alasan = document.getElementById(`adminPinjAlasan_${id}`)?.value?.trim();
    if (!status) { alert('Pilih status tujuan.'); return; }
    if (!alasan) { alert('Alasan koreksi wajib diisi.'); return; }
    if (!confirm(`Reset pinjaman #${id} ke status [${status}]?`)) return;
    try {
        const res = await fetchJson(`/api/pinjaman-cabang/${id}/admin-reset`, {
            method: 'POST',
            headers: { ...authHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ status, alasan }),
        });
        showAlert(res.message || 'Status pinjaman direset.');
        adminLoadPinjamanReset(_pinjamanTaskId).catch(() => {});
        pinjamanApprovalLoadList(_pinjamanTaskId).catch(() => {});
    } catch (e) { showAlert(e.message, 'error'); }
}
window.adminResetPinjaman = adminResetPinjaman;

function setupFilters() {
    let timer = null;
    document.getElementById("taskSearch")?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadTasks().catch((e) => showAlert(e.message, "error")), 300);
    });
    document.getElementById("taskStatusFilter")?.addEventListener("change", () => {
        loadTasks().catch((e) => showAlert(e.message, "error"));
    });
}

document.addEventListener("DOMContentLoaded", async () => {
    document.getElementById("closeTaskModalButton")?.addEventListener("click", closeModal);
    document.getElementById("cancelTaskFormButton")?.addEventListener("click", closeModal);
    document.getElementById("cancelTaskFormButton2")?.addEventListener("click", closeModal);
    document.getElementById("cancelBranchButton")?.addEventListener("click", closeModal);
    document.getElementById("cancelSelesaiCabangButton")?.addEventListener("click", closeModal);
    document.getElementById("cancelRevisiButton")?.addEventListener("click", closeModal);

    document.getElementById("revisiSelesaiBtn")?.addEventListener("click", () => {
        const planId = document.getElementById("approvePlanId")?.value;
        selesaiRevisi(planId).catch((err) => showAlert(err.message, "error"));
    });

    document.getElementById("mulaiCabangBtn")?.addEventListener("click", () => {
        const planId = document.getElementById("approvePlanId")?.value;
        mulaiCabang(planId).catch((err) => showAlert(err.message, "error"));
    });

    document.getElementById("selesaiCabangBtn")?.addEventListener("click", () => {
        const planId = document.getElementById("approvePlanId")?.value;
        selesaiCabang(planId).catch((err) => showAlert(err.message, "error"));
    });

    document.getElementById("taskForm")?.addEventListener("submit", async (e) => {
        try { await saveExecution(e); }
        catch (err) { showAlert(err.message || "Gagal menyimpan pelaksanaan audit.", "error"); }
    });

    document.getElementById("approveBtn")?.addEventListener("click", () => {
        const planId = document.getElementById("approvePlanId")?.value;
        approvePlan(planId).catch((err) => showAlert(err.message, "error"));
    });

    document.getElementById("rejectBtn")?.addEventListener("click", () => {
        const planId = document.getElementById("approvePlanId")?.value;
        rejectPlan(planId).catch((err) => showAlert(err.message, "error"));
    });

    document.getElementById("adminPlanResetBtn")?.addEventListener("click", async () => {
        const planId = document.getElementById("approvePlanId")?.value;
        const status = document.getElementById("adminPlanStatus")?.value;
        const alasan = document.getElementById("adminPlanAlasan")?.value?.trim();
        if (!planId) { showAlert('Buka modal task terlebih dahulu.', 'error'); return; }
        if (!status) { showAlert('Pilih status tujuan.', 'error'); return; }
        if (!alasan) { showAlert('Alasan koreksi wajib diisi.', 'error'); return; }
        if (!confirm(`Reset status plan ke [${status}]?\nAlasan: ${alasan}`)) return;
        try {
            const res = await fetchJson(`/api/plans/${planId}/admin-reset`, {
                method: 'POST',
                headers: { ...authHeaders(), 'Content-Type': 'application/json' },
                body: JSON.stringify({ status, alasan }),
            });
            showAlert(res.message || 'Status plan direset.');
            document.getElementById("adminPlanAlasan").value = '';
            closeModal();
            await loadTasks();
        } catch (e) { showAlert(e.message, 'error'); }
    });

    document.getElementById("tasksTableBody")?.addEventListener("click", (e) => {
        const btn = e.target.closest(".execute-task");
        if (!btn) return;
        const task = tasks.find((t) => String(t.id) === String(btn.dataset.id));
        if (task) openModal(task);
    });

    setupFilters();
    initPinjaman();

    try {
        await loadCurrentUser();
        await loadTasks();
    } catch (err) {
        showAlert(err.message || "Gagal memuat tugas audit.", "error");
    }
});
