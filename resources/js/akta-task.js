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
                    ${isViewOnly() ? "Tinjau" : (task.status === "done" ? "Lihat / Ubah" : "Kerjakan")}
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

// Role approval: hanya melihat data plan + tombol Setujui/Tolak (bukan form pelaksanaan).
// Tiap role menyetujui pada tahap status tertentu sesuai birokrasi.
const APPROVAL_STAGE = {
    koordinator: "pending_koordinator",
    manajer:     "pending_manajer",
    coo:         "pending_coo",
};

function isViewOnly() {
    return Object.prototype.hasOwnProperty.call(APPROVAL_STAGE, currentUser?.role);
}

function openModal(task) {
    const modal = document.getElementById("taskModal");
    const plan = task.planAudit || {};

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
    const execSection = document.getElementById("execSection");
    const approvalSection = document.getElementById("approvalSection");

    if (viewOnly) {
        // Sembunyikan form pelaksanaan
        execSection?.classList.add("hidden");

        // Tampilkan tombol approve/reject jika plan berada di tahap role ini
        const stage = APPROVAL_STAGE[currentUser?.role];
        const canApprove = stage && plan.status === stage;
        if (approvalSection) {
            approvalSection.classList.toggle("hidden", !canApprove);
            document.getElementById("approvePlanId").value = plan.id || "";

            // Sesuaikan teks info menunggu persetujuan
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
    } else {
        execSection?.classList.remove("hidden");
        approvalSection?.classList.add("hidden");

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

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

async function approvePlan(planId) {
    if (!planId) return;
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

    document.getElementById("tasksTableBody")?.addEventListener("click", (e) => {
        const btn = e.target.closest(".execute-task");
        if (!btn) return;
        const task = tasks.find((t) => String(t.id) === String(btn.dataset.id));
        if (task) openModal(task);
    });

    setupFilters();

    try {
        await loadCurrentUser();
        await loadTasks();
    } catch (err) {
        showAlert(err.message || "Gagal memuat tugas audit.", "error");
    }
});
