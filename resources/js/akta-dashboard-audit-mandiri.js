import Chart from "chart.js/auto";

const SESSION_KEY = "akta_session";

function getSession() {
    try {
        return JSON.parse(sessionStorage.getItem(SESSION_KEY));
    } catch {
        return null;
    }
}

function authHeaders() {
    const session = getSession();
    const headers = { Accept: "application/json" };
    if (session?.token) {
        headers.Authorization = `${session.tokenType || "Bearer"} ${session.token}`;
    }
    return headers;
}

async function fetchJson(url, options = {}) {
    const res = await fetch(url, options);
    let body = null;
    try {
        body = await res.json();
    } catch {
        body = null;
    }
    if (!res.ok || body === null) {
        throw new Error(body?.message || `Gagal memuat ${url} (status ${res.status})`);
    }
    return body;
}

function showAlert(message) {
    const el = document.getElementById("amdAlert");
    if (!el) return;
    if (!message) {
        el.classList.add("hidden");
        return;
    }
    el.textContent = message;
    el.classList.remove("hidden");
}

const BULAN_LABEL = [
    "", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

let summaryChart = null;
let unitChart = null;
let latestSummary = [];
let latestDetail = [];
let wilayahOptionsPopulated = false;

function populateFilters() {
    const bulanEl = document.getElementById("amdBulanFilter");
    const tahunEl = document.getElementById("amdTahunFilter");
    if (!bulanEl || !tahunEl) return;

    const now = new Date();
    bulanEl.innerHTML = "";
    BULAN_LABEL.forEach((label, idx) => {
        if (idx === 0) return;
        const opt = document.createElement("option");
        opt.value = String(idx);
        opt.textContent = label;
        bulanEl.appendChild(opt);
    });
    bulanEl.value = String(now.getMonth() + 1);

    const currentYear = now.getFullYear();
    tahunEl.innerHTML = "";
    for (let y = currentYear + 1; y >= currentYear - 3; y--) {
        const opt = document.createElement("option");
        opt.value = String(y);
        opt.textContent = String(y);
        tahunEl.appendChild(opt);
    }
    tahunEl.value = String(currentYear);
}

function populateWilayahOptions(options) {
    if (wilayahOptionsPopulated) return;
    const el = document.getElementById("amdWilayahFilter");
    if (!el || !options?.length) return;

    options.forEach((w) => {
        const opt = document.createElement("option");
        opt.value = w;
        opt.textContent = w;
        el.appendChild(opt);
    });
    wilayahOptionsPopulated = true;
}

function capaianBadge(capaian) {
    if (capaian === null || capaian === undefined) {
        return '<span class="text-slate-500">-</span>';
    }
    let cls = "bg-red-500/10 text-red-300 border-red-500/30";
    if (capaian >= 100) cls = "bg-emerald-500/10 text-emerald-300 border-emerald-500/30";
    else if (capaian >= 70) cls = "bg-amber-500/10 text-amber-300 border-amber-500/30";
    return `<span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${cls}">${capaian}%</span>`;
}

function renderSummaryChart(summary) {
    const canvas = document.getElementById("amdSummaryChart");
    if (!canvas) return;

    const labels = summary.map((it) => `${it.jenisAudit} (${it.unitType})`);
    const target = summary.map((it) => it.target);
    const realisasi = summary.map((it) => it.realisasi);

    if (summaryChart) summaryChart.destroy();
    summaryChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels,
            datasets: [
                { label: "Target", data: target, backgroundColor: "rgba(59,130,246,0.5)", borderRadius: 6 },
                { label: "Realisasi", data: realisasi, backgroundColor: "rgba(16,185,129,0.7)", borderRadius: 6 },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: "#d3d9e6" } } },
            scales: {
                x: { ticks: { color: "#aab2c5" }, grid: { color: "rgba(148,163,184,0.1)" } },
                y: { beginAtZero: true, ticks: { color: "#aab2c5", precision: 0 }, grid: { color: "rgba(148,163,184,0.1)" } },
            },
        },
    });
}

function unitRowsForFilter(jenisAuditFilter) {
    const rows = [];
    latestDetail.forEach((unit) => {
        unit.items
            .filter((it) => !jenisAuditFilter || it.jenisAudit === jenisAuditFilter)
            .forEach((it) => {
                rows.push({
                    unitUsaha: unit.unitUsaha,
                    wilayah: unit.wilayah,
                    jenis: unit.jenis,
                    jenisAudit: it.jenisAudit,
                    target: it.target,
                    realisasi: it.realisasi,
                    capaian: it.capaian,
                });
            });
    });
    return rows;
}

function renderUnitChart(rows) {
    const canvas = document.getElementById("amdUnitChart");
    if (!canvas) return;

    const labels = rows.map((r) => rows.length && r.jenisAudit && !document.getElementById("amdJenisAuditFilter").value
        ? `${r.unitUsaha} - ${r.jenisAudit}`
        : r.unitUsaha);
    const target = rows.map((r) => r.target);
    const realisasi = rows.map((r) => r.realisasi);

    canvas.parentElement.style.height = `${Math.max(240, rows.length * 28)}px`;

    if (unitChart) unitChart.destroy();
    unitChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels,
            datasets: [
                { label: "Target", data: target, backgroundColor: "rgba(59,130,246,0.5)", borderRadius: 4 },
                { label: "Realisasi", data: realisasi, backgroundColor: "rgba(16,185,129,0.7)", borderRadius: 4 },
            ],
        },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: "#d3d9e6" } } },
            scales: {
                x: { beginAtZero: true, ticks: { color: "#aab2c5", precision: 0 }, grid: { color: "rgba(148,163,184,0.1)" } },
                y: { ticks: { color: "#aab2c5" }, grid: { color: "rgba(148,163,184,0.05)" } },
            },
        },
    });
}

function renderTable(rows) {
    const tbody = document.getElementById("amdTableBody");
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Tidak ada unit usaha H1/H2 untuk filter ini.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((r) => `
        <tr>
            <td class="px-3 py-2 font-semibold">${r.unitUsaha}${!document.getElementById("amdJenisAuditFilter").value ? ` <span class="text-slate-500 font-normal">(${r.jenisAudit})</span>` : ""}</td>
            <td class="px-3 py-2 text-center text-slate-400">${r.wilayah || "-"}</td>
            <td class="px-3 py-2 text-center">${r.jenis}</td>
            <td class="px-3 py-2 text-center">${r.target}</td>
            <td class="px-3 py-2 text-center">${r.realisasi}</td>
            <td class="px-3 py-2 text-center">${capaianBadge(r.capaian)}</td>
        </tr>
    `).join("");
}

function renderUnitSection() {
    const jenisAuditFilter = document.getElementById("amdJenisAuditFilter")?.value || "";

    const filteredSummary = jenisAuditFilter
        ? latestSummary.filter((it) => it.jenisAudit === jenisAuditFilter)
        : latestSummary;
    renderSummaryChart(filteredSummary);

    const rows = unitRowsForFilter(jenisAuditFilter);
    renderUnitChart(rows);
    renderTable(rows);
}

async function loadPencapaian() {
    const bulan = document.getElementById("amdBulanFilter")?.value;
    const tahun = document.getElementById("amdTahunFilter")?.value;
    const wilayah = document.getElementById("amdWilayahFilter")?.value || "";
    try {
        showAlert(null);
        const params = new URLSearchParams({ tahun, bulan });
        if (wilayah) params.set("wilayah", wilayah);
        const result = await fetchJson(`/api/plan-audit-mandiri/pencapaian?${params.toString()}`, {
            headers: authHeaders(),
        });
        latestSummary = result.summary || [];
        latestDetail = result.detail || [];
        populateWilayahOptions(result.wilayahOptions);
        renderUnitSection();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data pencapaian audit mandiri.");
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (!getSession()) return;

    populateFilters();
    document.getElementById("amdBulanFilter")?.addEventListener("change", loadPencapaian);
    document.getElementById("amdTahunFilter")?.addEventListener("change", loadPencapaian);
    document.getElementById("amdWilayahFilter")?.addEventListener("change", loadPencapaian);
    document.getElementById("amdJenisAuditFilter")?.addEventListener("change", renderUnitSection);
    loadPencapaian();
});
