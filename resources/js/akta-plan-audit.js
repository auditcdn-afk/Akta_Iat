const SESSION_KEY = "akta_session";

let plans = [];
let currentUser = null;
let usersList = [];
let unitUsahaList = [];

// Role kantor pusat (HO). Role di luar daftar ini = role cabang.
const HO_ROLES = ["admin", "manajer", "auditor", "koordinator", "coo"];

const STATUS_LABELS = {
    draft:                "Draft",
    pending_koordinator:  "Menunggu Koordinator",
    pending_manajer:      "Menunggu Manajer",
    pending_coo:          "Menunggu COO",
    scheduled:            "Disetujui",
    running:              "Audit Berjalan",
    cabang_active:        "Cabang Aktif",
    done:                 "Selesai",
    cancelled:            "Dibatalkan",
};

const STATUS_BADGE = {
    draft:                "bg-slate-500/10 text-slate-300 border-slate-500/20",
    pending_koordinator:  "bg-yellow-500/10 text-yellow-300 border-yellow-500/20",
    pending_manajer:      "bg-orange-500/10 text-orange-300 border-orange-500/20",
    pending_coo:          "bg-purple-500/10 text-purple-300 border-purple-500/20",
    scheduled:            "bg-blue-500/10 text-blue-300 border-blue-500/20",
    running:              "bg-amber-500/10 text-amber-300 border-amber-500/20",
    cabang_active:        "bg-teal-500/10 text-teal-300 border-teal-500/20",
    done:                 "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
    cancelled:            "bg-red-500/10 text-red-300 border-red-500/20",
};

// Daftar transisi: dari status → [next, siapa yang boleh advance]
// '__branch__' berarti role selain HO_ROLES.
const TRANSITIONS = {
    draft:               { next: "pending_koordinator", roles: ["auditor", "admin"],           label: "Ajukan",       color: "blue"    },
    pending_koordinator: { next: "pending_manajer",     roles: ["koordinator", "admin"],        label: "Setujui",      color: "emerald" },
    pending_manajer:     { next: "pending_coo",         roles: ["manajer", "admin"],            label: "Setujui",      color: "emerald" },
    pending_coo:         { next: "scheduled",           roles: ["coo", "admin"],               label: "Setujui",      color: "emerald" },
    scheduled:           { next: "running",             roles: ["auditor", "admin"],            label: "Mulai Audit",  color: "amber"   },
    running:             { next: "cabang_active",       roles: ["__branch__", "admin"],         label: "Mulai Cabang", color: "teal"    },
    cabang_active:       { next: "done",                roles: ["auditor", "manajer", "admin"], label: "Selesai",      color: "slate"   },
};

const REJECTABLE = {
    pending_koordinator: ["koordinator", "admin"],
    pending_manajer:     ["manajer", "admin"],
    pending_coo:         ["coo", "admin"],
};

function getSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function authHeaders() {
    const session = getSession();
    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
    };
}

function isBranchUser() {
    return !HO_ROLES.includes(currentUser?.role);
}

function canCreatePlan() {
    return ["admin", "manajer"].includes(currentUser?.role);
}

function canEditPlan() {
    return currentUser?.role === "admin";
}

function canAdvancePlan(plan) {
    const role = currentUser?.role;
    const t = TRANSITIONS[plan.status];
    if (!t) return false;
    return t.roles.includes(role) || (t.roles.includes("__branch__") && isBranchUser());
}

function canRejectPlan(plan) {
    const role = currentUser?.role;
    return (REJECTABLE[plan.status] || []).includes(role);
}

function showAlert(message, type = "success") {
    const el = document.getElementById("planAlert");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden", "border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200", "border-red-500/30", "bg-red-500/10", "text-red-200");
    if (type === "error") {
        el.classList.add("border-red-500/30", "bg-red-500/10", "text-red-200");
    } else {
        el.classList.add("border-emerald-500/30", "bg-emerald-500/10", "text-emerald-200");
    }
    el.classList.remove("hidden");
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

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { ...authHeaders(), ...(options.headers || {}) },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(firstError || payload.message || "Request gagal.");
    }
    return payload;
}

async function loadCurrentUser() {
    const payload = await fetchJson("/api/auth/me");
    currentUser = payload.user;
}

async function loadPlanUsers() {
    const payload = await fetchJson("/api/plan-users");
    usersList = payload.data || [];
}

async function loadUnitUsahaOptions() {
    const payload = await fetchJson("/api/database/unit-usaha-options");
    unitUsahaList = payload.data || [];
}

async function loadPlans() {
    const q = document.getElementById("planSearch")?.value || "";
    const status = document.getElementById("planStatusFilter")?.value || "";
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (status) params.set("status", status);
    const url = params.toString() ? `/api/plans?${params}` : "/api/plans";
    const payload = await fetchJson(url);
    plans = payload.data || [];
    renderPlans();
}

function actionButtons(plan) {
    const buttons = [];

    // Advance button
    if (canAdvancePlan(plan)) {
        const t = TRANSITIONS[plan.status];
        const c = t.color;
        const colors = {
            blue:    "border-blue-500/40 text-blue-300 hover:bg-blue-500/10",
            emerald: "border-emerald-500/40 text-emerald-300 hover:bg-emerald-500/10",
            amber:   "border-amber-500/40 text-amber-300 hover:bg-amber-500/10",
            teal:    "border-teal-500/40 text-teal-300 hover:bg-teal-500/10",
            slate:   "border-slate-500/40 text-slate-300 hover:bg-slate-800",
        };

        // Untuk transisi cabang_active -> done, syarat BU Performance & Rekomendasi wajib lengkap.
        const belumLengkap = plan.status === "cabang_active" && !plan.canMarkSelesai;
        const btnClass = belumLengkap
            ? "border-slate-700 text-slate-600 cursor-not-allowed opacity-50"
            : colors[c] || colors.slate;
        const title = belumLengkap
            ? 'title="Belum bisa: BU Performance dan Rekomendasi (isi cabang) belum lengkap"'
            : "";

        buttons.push(
            `<button type="button" class="advance-plan rounded-lg border px-3 py-1.5 text-xs font-semibold ${btnClass}" data-id="${plan.id}" ${belumLengkap ? "disabled" : ""} ${title}>${escapeHtml(t.label)}</button>`
        );
    }

    // Reject button
    if (canRejectPlan(plan)) {
        buttons.push(
            `<button type="button" class="reject-plan rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${plan.id}">Tolak</button>`
        );
    }

    // Edit (admin only)
    if (canEditPlan()) {
        buttons.push(
            `<button type="button" class="edit-plan rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${plan.id}">Edit</button>`
        );
        buttons.push(
            `<button type="button" class="delete-plan rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${plan.id}">Hapus</button>`
        );
    }

    return buttons.length
        ? `<div class="flex flex-wrap justify-end gap-1.5">${buttons.join("")}</div>`
        : `<span class="text-xs text-slate-500">-</span>`;
}

function renderPlans() {
    const tbody = document.getElementById("plansTableBody");
    if (!tbody) return;

    if (!plans.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">Belum ada plan audit.</td></tr>`;
        return;
    }

    tbody.innerHTML = plans.map((plan) => {
        const badge = STATUS_BADGE[plan.status] || STATUS_BADGE.draft;
        const label = STATUS_LABELS[plan.status] || plan.status;

        return `
        <tr class="hover:bg-slate-950/50">
            <td class="px-4 py-4">
                <div class="font-semibold text-slate-100">${escapeHtml(plan.noSpt || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(plan.jenisAudit || "-")}</div>
                <div class="text-xs text-slate-600">${escapeHtml(plan.tglPlan || "")}</div>
            </td>
            <td class="px-4 py-4">
                <div class="text-sm font-semibold text-slate-200">${escapeHtml(plan.cabang || "-")}</div>
            </td>
            <td class="px-4 py-4">
                <div class="text-sm font-semibold text-slate-200">${escapeHtml(plan.kepalaTim || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml((plan.tim || []).join(", ") || "-")}</div>
            </td>
            <td class="px-4 py-4">
                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${badge}">
                    ${escapeHtml(label)}
                </span>
            </td>
            <td class="px-4 py-4 text-right">${actionButtons(plan)}</td>
        </tr>`;
    }).join("");
}

// ── Selects & checkboxes ──────────────────────────────────────────────────────

function renderCabangSelect(selected = "") {
    const el = document.getElementById("cabang");
    if (!el) return;
    const options = unitUsahaList.map(
        (u) => `<option value="${escapeHtml(u.unitUsaha)}" ${u.unitUsaha === selected ? "selected" : ""}>${escapeHtml(u.unitUsaha)}</option>`
    ).join("");
    el.innerHTML = `<option value="">-- Pilih Cabang --</option>${options}`;
}

function renderKepalaTimSelect(selected = "") {
    const el = document.getElementById("kepalaTim");
    if (!el) return;
    let lastWilayah = null;
    let options = "";
    for (const u of usersList) {
        if (u.wilayah !== lastWilayah) {
            if (lastWilayah !== null) options += "</optgroup>";
            options += `<optgroup label="${escapeHtml(u.wilayah || "Lainnya")}">`;
            lastWilayah = u.wilayah;
        }
        const label = escapeHtml((u.displayName || u.name || u.username) + (u.unitUsaha ? ` — ${u.unitUsaha}` : ""));
        const val = escapeHtml(u.displayName || u.name || u.username);
        options += `<option value="${val}" ${val === selected ? "selected" : ""}>${label}</option>`;
    }
    if (lastWilayah !== null) options += "</optgroup>";
    el.innerHTML = `<option value="">-- Pilih Kepala Tim --</option>${options}`;
}

function renderTimCheckboxes(selectedTim = []) {
    const container = document.getElementById("timContainer");
    if (!container) return;

    if (!usersList.length) {
        container.innerHTML = '<p class="py-2 text-center text-slate-500">Tidak ada auditor tersedia.</p>';
        return;
    }

    let lastWilayah = null;
    let html = "";
    for (const u of usersList) {
        const label = (u.displayName || u.name || u.username) + (u.unitUsaha ? ` — ${u.unitUsaha}` : "");
        const val = u.displayName || u.name || u.username;
        const isChecked = selectedTim.some((v) => v === val || v === u.name || v === u.username);

        if (u.wilayah !== lastWilayah) {
            if (lastWilayah !== null) html += "</div></div>";
            html += `<div class="mb-2"><p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(u.wilayah || "Lainnya")}</p><div class="space-y-1">`;
            lastWilayah = u.wilayah;
        }
        html += `
            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-800/60">
                <input type="checkbox" class="tim-checkbox h-4 w-4 rounded border-slate-600 bg-slate-900 accent-blue-500"
                    value="${escapeHtml(val)}" ${isChecked ? "checked" : ""}>
                <span class="text-sm text-slate-300">${escapeHtml(label)}</span>
            </label>`;
    }
    if (lastWilayah !== null) html += "</div></div>";
    container.innerHTML = html;
}

// ── Modal ─────────────────────────────────────────────────────────────────────

function pad(v, n = 2) { return String(v).padStart(n, "0"); }

function todayIso() {
    const d = new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function nextNoSptPreview() {
    const seq = pad(plans.length + 1, 4);
    const d = new Date();
    return `${seq}/${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}/SPT-IAT`;
}

function openModal(plan = null) {
    const modal = document.getElementById("planModal");
    const title = document.getElementById("planModalTitle");

    document.getElementById("planForm").reset();
    renderCabangSelect();
    renderKepalaTimSelect();
    renderTimCheckboxes([]);

    if (plan) {
        title.textContent = "Edit Plan Audit";
        document.getElementById("planId").value = plan.id;
        document.getElementById("noSpt").value = plan.noSpt || "";
        document.getElementById("jenisAudit").value = plan.jenisAudit || "Audit";
        document.getElementById("tglPlan").value = plan.tglPlan || "";
        document.getElementById("keterangan").value = plan.keterangan || "";
        renderCabangSelect(plan.cabang || "");
        renderKepalaTimSelect(plan.kepalaTim || "");
        renderTimCheckboxes(plan.tim || []);
    } else {
        title.textContent = "Tambah Plan Audit";
        document.getElementById("planId").value = "";
        document.getElementById("jenisAudit").value = "Audit";
        document.getElementById("noSpt").value = nextNoSptPreview();
        document.getElementById("tglPlan").value = todayIso();
    }

    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeModal() {
    document.getElementById("planModal").classList.add("hidden");
    document.getElementById("planModal").classList.remove("flex");
}

function getFormPayload() {
    const tim = Array.from(document.querySelectorAll(".tim-checkbox:checked")).map((cb) => cb.value);
    return {
        no_spt:      document.getElementById("noSpt").value.trim(),
        jenis_audit: document.getElementById("jenisAudit").value,
        cabang:      document.getElementById("cabang").value,
        kepala_tim:  document.getElementById("kepalaTim").value,
        tim,
        keterangan:  document.getElementById("keterangan").value.trim(),
    };
}

async function savePlan(event) {
    event.preventDefault();

    if (!canCreatePlan() && !canEditPlan()) {
        showAlert("Role kamu tidak bisa menyimpan plan.", "error");
        return;
    }

    const id = document.getElementById("planId").value;
    const isEdit = Boolean(id);

    if (isEdit && !canEditPlan()) {
        showAlert("Hanya admin yang bisa mengedit plan.", "error");
        return;
    }

    const payload = await fetchJson(isEdit ? `/api/plans/${id}` : "/api/plans", {
        method: isEdit ? "PUT" : "POST",
        body: JSON.stringify(getFormPayload()),
    });

    closeModal();
    showAlert(payload.message || "Plan audit berhasil disimpan.");
    await loadPlans();
}

async function advancePlan(id) {
    const plan = plans.find((p) => String(p.id) === String(id));
    if (!plan) return;

    const t = TRANSITIONS[plan.status];
    const confirmed = confirm(`${t?.label || "Lanjutkan"} plan ${plan.noSpt}?`);
    if (!confirmed) return;

    const payload = await fetchJson(`/api/plans/${id}/advance`, { method: "POST" });
    showAlert(payload.message || "Status berhasil diperbarui.");
    await loadPlans();
}

async function rejectPlan(id) {
    const plan = plans.find((p) => String(p.id) === String(id));
    if (!plan) return;

    const confirmed = confirm(`Tolak dan kembalikan plan ${plan.noSpt} ke Draft?`);
    if (!confirmed) return;

    const payload = await fetchJson(`/api/plans/${id}/reject`, { method: "POST" });
    showAlert(payload.message || "Plan dikembalikan ke Draft.");
    await loadPlans();
}

async function deletePlan(id) {
    const plan = plans.find((p) => String(p.id) === String(id));
    if (!plan) return;

    const confirmed = confirm(`Hapus plan audit ${plan.noSpt || plan.cabang}?`);
    if (!confirmed) return;

    const payload = await fetchJson(`/api/plans/${id}`, { method: "DELETE" });
    showAlert(payload.message || "Plan audit berhasil dihapus.");
    await loadPlans();
}

function setupFilters() {
    let timer = null;
    document.getElementById("planSearch")?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadPlans().catch((e) => showAlert(e.message, "error")), 300);
    });
    document.getElementById("planStatusFilter")?.addEventListener("change", () => {
        loadPlans().catch((e) => showAlert(e.message, "error"));
    });
}

document.addEventListener("DOMContentLoaded", async () => {
    document.getElementById("openCreatePlanButton")?.addEventListener("click", () => openModal());
    document.getElementById("closePlanModalButton")?.addEventListener("click", closeModal);
    document.getElementById("cancelPlanFormButton")?.addEventListener("click", closeModal);

    document.getElementById("planForm")?.addEventListener("submit", async (e) => {
        try { await savePlan(e); }
        catch (err) { showAlert(err.message || "Gagal menyimpan plan audit.", "error"); }
    });

    document.getElementById("plansTableBody")?.addEventListener("click", async (e) => {
        const advance = e.target.closest(".advance-plan");
        const reject  = e.target.closest(".reject-plan");
        const edit    = e.target.closest(".edit-plan");
        const del     = e.target.closest(".delete-plan");

        try {
            if (advance) { await advancePlan(advance.dataset.id); return; }
            if (reject)  { await rejectPlan(reject.dataset.id);  return; }
            if (edit) {
                const plan = plans.find((p) => String(p.id) === String(edit.dataset.id));
                openModal(plan);
                return;
            }
            if (del) { await deletePlan(del.dataset.id); }
        } catch (err) {
            showAlert(err.message || "Terjadi kesalahan.", "error");
        }
    });

    setupFilters();

    try {
        await loadCurrentUser();

        if (!canCreatePlan()) {
            document.getElementById("openCreatePlanButton")?.classList.add("hidden");
        }

        await Promise.all([loadPlanUsers(), loadUnitUsahaOptions()]);
        await loadPlans();
    } catch (err) {
        showAlert(err.message || "Gagal memuat plan audit.", "error");
    }
});
