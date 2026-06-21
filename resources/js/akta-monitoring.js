const SESSION_KEY = "akta_session";

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
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
    };
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value ?? "-";
    }
}

function showAlert(message, type = "success") {
    const alert = document.getElementById("monitoringAlert");

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

async function fetchJson(url) {
    const response = await fetch(url, {
        headers: authHeaders(),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.message || "Request gagal.");
    }

    return payload;
}

function renderStats(stats) {
    const users = stats.users || {};
    const appData = stats.appData || {};
    const activity = stats.activity || {};
    const system = stats.system || {};

    setText("statTotalUsers", users.total);
    setText(
        "statUserDetail",
        `${users.active || 0} aktif • ${users.disabled || 0} nonaktif`,
    );
    setText("statAdmins", users.admins);
    setText("statDataKeys", appData.totalKeys);
    setText(
        "statDataUpdated",
        appData.lastUpdatedAt
            ? `Update terakhir: ${appData.lastUpdatedAt}`
            : "Belum ada update",
    );
    setText("statActivityLogs", activity.totalLogs);
    setText(
        "statLastActivity",
        activity.lastAt
            ? `${activity.lastAction || "-"} • ${activity.lastAt}`
            : "Belum ada aktivitas",
    );

    setText("sysApp", system.app);
    setText(
        "sysEnvironment",
        `${system.environment || "-"}${system.debug ? " • debug on" : ""}`,
    );
    setText("sysPhp", system.php);
    setText("sysLaravel", system.laravel);
    setText("sysDatabase", system.database);
    setText("sysCacheQueue", `${system.cache || "-"} / ${system.queue || "-"}`);
}

function renderHealth(payload) {
    const container = document.getElementById("healthCheckList");

    if (!container) {
        return;
    }

    const checks = payload.checks || {};

    container.innerHTML = Object.entries(checks)
        .map(([key, check]) => {
            const okClass = check.ok
                ? "border-emerald-500/20 bg-emerald-500/10 text-emerald-200"
                : "border-red-500/20 bg-red-500/10 text-red-200";

            const label = key.charAt(0).toUpperCase() + key.slice(1);

            return `
            <div class="rounded-xl border ${okClass} p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-bold">${label}</div>
                        <div class="mt-1 text-xs opacity-80">${check.message || "-"}</div>
                    </div>
                    <div class="shrink-0 rounded-full bg-black/20 px-2.5 py-1 text-xs font-bold">
                        ${check.ok ? "OK" : "ERROR"}
                    </div>
                </div>
            </div>
        `;
        })
        .join("");
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function renderActivityLogs(logs) {
    const tbody = document.getElementById("activityLogBody");

    if (!tbody) {
        return;
    }

    if (!logs.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada activity log.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = logs
        .map(
            (log) => `
        <tr class="hover:bg-slate-950/50">
            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-300">
                ${escapeHtml(log.timestamp || "-")}
            </td>
            <td class="px-4 py-3">
                <div class="text-sm font-semibold text-slate-200">${escapeHtml(log.displayName || log.username || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(log.role || "-")} • ${escapeHtml(log.ip || "-")}</div>
            </td>
            <td class="whitespace-nowrap px-4 py-3">
                <span class="rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-300">
                    ${escapeHtml(log.action || "-")}
                </span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-300">
                ${escapeHtml(log.resource || "-")}
            </td>
            <td class="max-w-xl px-4 py-3 text-sm text-slate-400">
                ${escapeHtml(log.detail || "-")}
            </td>
        </tr>
    `,
        )
        .join("");
}

async function loadMonitoring() {
    const limit = document.getElementById("activityLimit")?.value || 25;

    const [statsPayload, healthPayload, logsPayload] = await Promise.all([
        fetchJson("/api/admin/monitoring/stats"),
        fetchJson("/api/admin/monitoring/health"),
        fetchJson(`/api/admin/monitoring/activity-log?limit=${limit}`),
    ]);

    renderStats(statsPayload.data || {});
    renderHealth(healthPayload);
    renderActivityLogs(logsPayload.data || []);
}

document.addEventListener("DOMContentLoaded", async () => {
    document
        .getElementById("refreshMonitoringButton")
        ?.addEventListener("click", async () => {
            try {
                await loadMonitoring();
                showAlert("Monitoring berhasil diperbarui.");
            } catch (error) {
                showAlert(
                    error.message || "Gagal refresh monitoring.",
                    "error",
                );
            }
        });

    document
        .getElementById("activityLimit")
        ?.addEventListener("change", async () => {
            try {
                await loadMonitoring();
            } catch (error) {
                showAlert(
                    error.message || "Gagal memuat activity log.",
                    "error",
                );
            }
        });

    try {
        await loadMonitoring();
    } catch (error) {
        showAlert(error.message || "Gagal memuat monitoring.", "error");
    }
});
