const SESSION_KEY = "akta_session";

let reportItems = [];
let reportSummary = null;
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
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
    };
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

        throw new Error(firstError || payload.message || "Request gagal.");
    }

    return payload;
}

async function loadCurrentUser() {
    const payload = await fetchJson("/api/auth/me");
    currentUser = payload.user;
}

async function loadReportSummary() {
    const payload = await fetchJson("/api/report-audit/summary");

    reportSummary = payload.data || {};

    renderGlobalStats();
}

async function loadReportItems() {
    const q = document.getElementById("reportAuditSearch")?.value || "";
    const status =
        document.getElementById("reportAuditStatusFilter")?.value || "";

    const params = new URLSearchParams();

    if (q) {
        params.set("q", q);
    }

    if (status) {
        params.set("status", status);
    }

    const url = params.toString()
        ? `/api/report-audit?${params.toString()}`
        : "/api/report-audit";

    const payload = await fetchJson(url);

    reportItems = normalizeListPayload(payload);

    renderReportItems();
}

function renderGlobalStats() {
    const data = reportSummary || {};

    setText("reportPlanTotalStat", data.plan_total || 0);
    setText("reportTaskTotalStat", data.task_total || 0);
    setText("reportRecommendationTotalStat", data.recommendation_total || 0);
    setText("reportPicaTotalStat", data.pica_total || 0);
    setText("reportPicaClosedStat", data.pica_closed || 0);
    setText("reportSkTotalStat", data.sk_total || 0);
    setText("reportSkSelesaiStat", data.sk_selesai || 0);
    setText("reportGeneratedAtStat", formatDateTime(data.generated_at));
}

function renderReportItems() {
    const tbody = document.getElementById("reportAuditTableBody");

    if (!tbody) {
        return;
    }

    if (!reportItems.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada data Report Audit.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = reportItems
        .map((item) => {
            const plan = item.plan || {};
            const summary = item.summary || {};
            const progress = Number(summary.completion_percent || 0);

            return `
                <tr class="hover:bg-slate-950/50">
                    <td class="px-4 py-4">
                        <div class="font-semibold text-slate-100">${escapeHtml(plan.no_spt || "-")}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(plan.cabang || plan.unit_usaha || "-")}</div>
                        <div class="mt-1">
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusBadge(plan.status)}">
                                ${escapeHtml(plan.status || "-")}
                            </span>
                        </div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.task_total || 0)}</div>
                        <div class="text-xs text-slate-500">Done: ${escapeHtml(summary.task_done || 0)}</div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.recommendation_total || 0)}</div>
                        <div class="text-xs text-slate-500">Approved: ${escapeHtml(summary.recommendation_approved || 0)}</div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.pica_total || 0)}</div>
                        <div class="text-xs text-slate-500">Closed: ${escapeHtml(summary.pica_closed || 0)}</div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.sk_total || 0)}</div>
                        <div class="text-xs text-slate-500">Selesai: ${escapeHtml(summary.sk_selesai || 0)}</div>
                    </td>

                    <td class="px-4 py-4">
                        <div class="w-40 rounded-full bg-slate-800">
                            <div class="h-2 rounded-full bg-blue-500" style="width: ${safePercent(progress)}%"></div>
                        </div>
                        <div class="mt-1 text-xs font-semibold text-slate-300">${escapeHtml(progress)}%</div>
                    </td>

                    <td class="px-4 py-4 text-right">
                        <button type="button" class="view-report-detail rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-plan-id="${plan.id}">
                            Detail
                        </button>
                    </td>
                </tr>
            `;
        })
        .join("");
}

async function openDetail(planId) {
    const payload = await fetchJson(`/api/report-audit/plans/${planId}`);
    const data = payload.data || {};

    renderDetail(data);

    const modal = document.getElementById("reportAuditDetailModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeDetail() {
    const modal = document.getElementById("reportAuditDetailModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function renderDetail(data) {
    const plan = data.plan || {};
    const summary = data.summary || {};
    const tasks = data.tasks || [];
    const recommendations = data.recommendations || [];
    const picas = data.picas || [];
    const suratKeputusan = data.surat_keputusan || [];

    setText("reportAuditDetailTitle", `Report Audit ${plan.no_spt || "-"}`);
    setText(
        "reportAuditDetailSubtitle",
        `${plan.cabang || plan.unit_usaha || "-"} • ${plan.status || "-"} • Generated ${formatDateTime(data.generated_at)}`,
    );

    setText("detailCompletionPercent", `${summary.completion_percent || 0}%`);
    setText(
        "detailTaskDone",
        `${summary.task_done || 0}/${summary.task_total || 0}`,
    );
    setText(
        "detailPicaClosed",
        `${summary.pica_closed || 0}/${summary.pica_total || 0}`,
    );
    setText(
        "detailSkSelesai",
        `${summary.sk_selesai || 0}/${summary.sk_total || 0}`,
    );

    renderPlanInfo(plan);
    renderTasks(tasks);
    renderRecommendations(recommendations);
    renderPicas(picas);
    renderSuratKeputusan(suratKeputusan);
}

function renderPlanInfo(plan) {
    const wrapper = document.getElementById("detailPlanInfo");

    if (!wrapper) {
        return;
    }

    wrapper.innerHTML = `
        ${infoItem("No SPT", plan.no_spt)}
        ${infoItem("Cabang", plan.cabang)}
        ${infoItem("Unit Usaha", plan.unit_usaha)}
        ${infoItem("Jenis Audit", plan.jenis_audit)}
        ${infoItem("Auditor", plan.auditor)}
        ${infoItem("Status", plan.status)}
        ${infoItem("Tanggal Mulai", formatDate(plan.tanggal_mulai))}
        ${infoItem("Tanggal Selesai", formatDate(plan.tanggal_selesai))}
        ${infoItem("Plan ID", plan.id)}
    `;
}

function renderTasks(tasks) {
    const wrapper = document.getElementById("detailTasks");

    if (!wrapper) {
        return;
    }

    if (!tasks.length) {
        wrapper.innerHTML = emptyText("Belum ada task.");
        return;
    }

    wrapper.innerHTML = tasks
        .map((item) => {
            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.judul || item.title || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Priority: ${escapeHtml(item.priority || item.prioritas || "-")}</div>
                </div>
            `;
        })
        .join("");
}

function renderRecommendations(recommendations) {
    const wrapper = document.getElementById("detailRecommendations");

    if (!wrapper) {
        return;
    }

    if (!recommendations.length) {
        wrapper.innerHTML = emptyText("Belum ada rekomendasi.");
        return;
    }

    wrapper.innerHTML = recommendations
        .map((item) => {
            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.judul || item.title || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">PIC: ${escapeHtml(item.pic || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Deadline: ${escapeHtml(formatDate(item.deadline))}</div>
                </div>
            `;
        })
        .join("");
}

function renderPicas(picas) {
    const wrapper = document.getElementById("detailPicas");

    if (!wrapper) {
        return;
    }

    if (!picas.length) {
        wrapper.innerHTML = emptyText("Belum ada PICA.");
        return;
    }

    wrapper.innerHTML = picas
        .map((item) => {
            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.pica_no || item.title || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">PIC: ${escapeHtml(item.pic || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-2 text-xs text-slate-400">
                        <div>Problem: ${escapeHtml(item.problem || "-")}</div>
                        <div>Corrective: ${escapeHtml(item.corrective_action || "-")}</div>
                    </div>
                </div>
            `;
        })
        .join("");
}

function renderSuratKeputusan(items) {
    const wrapper = document.getElementById("detailSuratKeputusan");

    if (!wrapper) {
        return;
    }

    if (!items.length) {
        wrapper.innerHTML = emptyText("Belum ada SK.");
        return;
    }

    wrapper.innerHTML = items
        .map((item) => {
            const file = item.file_sk || {};

            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.no_sk || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Uploaded: ${escapeHtml(item.uploaded_by_name || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">File: ${escapeHtml(file.name || "-")}</div>
                </div>
            `;
        })
        .join("");
}

function setupFilters() {
    let timer = null;

    document
        .getElementById("reportAuditSearch")
        ?.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(
                () =>
                    loadReportItems().catch((error) =>
                        showAlert(error.message, "error"),
                    ),
                300,
            );
        });

    document
        .getElementById("reportAuditStatusFilter")
        ?.addEventListener("change", () => {
            loadReportItems().catch((error) =>
                showAlert(error.message, "error"),
            );
        });

    document
        .getElementById("reloadReportAuditButton")
        ?.addEventListener("click", async () => {
            try {
                await loadReportSummary();
                await loadReportItems();
                showAlert("Report Audit berhasil dimuat ulang.");
            } catch (error) {
                showAlert(
                    error.message || "Gagal memuat ulang Report Audit.",
                    "error",
                );
            }
        });
}

function setupTableActions() {
    document
        .getElementById("reportAuditTableBody")
        ?.addEventListener("click", async (event) => {
            const detailButton = event.target.closest(".view-report-detail");

            if (!detailButton) {
                return;
            }

            try {
                await openDetail(detailButton.dataset.planId);
            } catch (error) {
                showAlert(
                    error.message || "Gagal membuka detail Report Audit.",
                    "error",
                );
            }
        });
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value ?? "-";
    }
}

function infoItem(label, value) {
    return `
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(label)}</div>
            <div class="mt-1 text-sm font-semibold text-slate-200">${escapeHtml(value || "-")}</div>
        </div>
    `;
}

function emptyText(message) {
    return `
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm text-slate-500">
            ${escapeHtml(message)}
        </div>
    `;
}

function showAlert(message, type = "success") {
    const alert = document.getElementById("reportAuditAlert");

    if (!alert) {
        return;
    }

    alert.textContent = message;
    alert.classList.remove(
        "hidden",
        "border-emerald-500/30",
        "bg-emerald-500/10",
        "text-emerald-200",
        "border-red-500/30",
        "bg-red-500/10",
        "text-red-200",
    );

    if (type === "error") {
        alert.classList.add(
            "border-red-500/30",
            "bg-red-500/10",
            "text-red-200",
        );
    } else {
        alert.classList.add(
            "border-emerald-500/30",
            "bg-emerald-500/10",
            "text-emerald-200",
        );
    }
}

function statusBadge(status) {
    const map = {
        draft: "bg-slate-500/10 text-slate-300 border-slate-500/20",
        open: "bg-blue-500/10 text-blue-300 border-blue-500/20",
        progress: "bg-amber-500/10 text-amber-300 border-amber-500/20",
        in_progress: "bg-amber-500/10 text-amber-300 border-amber-500/20",
        done: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
        selesai: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
        cancelled: "bg-red-500/10 text-red-300 border-red-500/20",
    };

    return map[status] || map.draft;
}

function safePercent(value) {
    const number = Number(value || 0);

    if (number < 0) {
        return 0;
    }

    if (number > 100) {
        return 100;
    }

    return number;
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    return String(value).slice(0, 10);
}

function formatDateTime(value) {
    if (!value) {
        return "-";
    }

    return String(value).replace("T", " ").slice(0, 19);
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

document.addEventListener("DOMContentLoaded", async () => {
    document
        .getElementById("closeReportAuditDetailButton")
        ?.addEventListener("click", closeDetail);

    setupFilters();
    setupTableActions();

    try {
        await loadCurrentUser();
        await loadReportSummary();
        await loadReportItems();
    } catch (error) {
        showAlert(error.message || "Gagal memuat Report Audit.", "error");
    }
});
