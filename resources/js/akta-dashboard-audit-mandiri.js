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

function capaianColor(capaian, alpha = 0.9) {
    if (capaian === null || capaian === undefined) return `rgba(148,163,184,${alpha})`;
    if (capaian >= 100) return `rgba(34,197,94,${alpha})`;
    if (capaian >= 70) return `rgba(250,204,21,${alpha})`;
    return `rgba(248,113,113,${alpha})`;
}

function updateStatCards(summary, rows) {
    const totalTarget = summary.reduce((acc, it) => acc + it.target, 0);
    const totalRealisasi = summary.reduce((acc, it) => acc + it.realisasi, 0);
    const capaianRata = totalTarget > 0 ? Math.round((totalRealisasi / totalTarget) * 1000) / 10 : 0;
    const belum = rows.filter((r) => r.realisasi === 0).length;

    const ccOk = rows.reduce((acc, r) => acc + (r.crosscheckOk || 0), 0);
    const ccNotOk = rows.reduce((acc, r) => acc + (r.crosscheckNotOk || 0), 0);
    const ccSelisih = rows.reduce((acc, r) => acc + (r.crosscheckSelisih || 0), 0);
    const ccPending = rows.reduce((acc, r) => acc + (r.crosscheckPending || 0), 0);

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText("amdStatTarget", totalTarget.toLocaleString("id-ID"));
    setText("amdStatRealisasi", totalRealisasi.toLocaleString("id-ID"));
    setText("amdStatCapaian", `${capaianRata}%`);
    setText("amdStatBelum", belum.toLocaleString("id-ID"));
    setText("amdStatCcOk", ccOk.toLocaleString("id-ID"));
    setText("amdStatCcNotOk", ccNotOk.toLocaleString("id-ID"));
    setText("amdStatCcSelisih", ccSelisih.toLocaleString("id-ID"));
    setText("amdStatCcPending", ccPending.toLocaleString("id-ID"));
}

function renderSummaryChart(summary) {
    const canvas = document.getElementById("amdSummaryChart");
    if (!canvas) return;

    const labels = summary.map((it) => [it.jenisAudit, `Unit ${it.unitType}`]);
    const target = summary.map((it) => it.target);
    const realisasi = summary.map((it) => it.realisasi);

    if (summaryChart) summaryChart.destroy();
    summaryChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels,
            datasets: [
                {
                    label: "Target",
                    data: target,
                    backgroundColor: "rgba(96,165,250,0.85)",
                    hoverBackgroundColor: "rgba(96,165,250,1)",
                    borderRadius: 8,
                    maxBarThickness: 46,
                },
                {
                    label: "Realisasi",
                    data: realisasi,
                    backgroundColor: "rgba(45,212,191,0.9)",
                    hoverBackgroundColor: "rgba(45,212,191,1)",
                    borderRadius: 8,
                    maxBarThickness: 46,
                },
            ],
        },
        options: {
            responsive: true,
            categoryPercentage: 0.6,
            barPercentage: 0.85,
            plugins: {
                legend: { labels: { color: "#d3d9e6", usePointStyle: true, pointStyle: "circle" } },
                tooltip: {
                    callbacks: {
                        afterBody: (items) => {
                            const idx = items[0]?.dataIndex;
                            const capaian = summary[idx]?.capaian;
                            return capaian !== null && capaian !== undefined ? `Capaian: ${capaian}%` : "";
                        },
                    },
                },
            },
            scales: {
                x: { ticks: { color: "#aab2c5" }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: "#aab2c5", precision: 0 }, grid: { color: "rgba(148,163,184,0.08)" } },
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
                    crosscheckOk: it.crosscheckOk || 0,
                    crosscheckNotOk: it.crosscheckNotOk || 0,
                    crosscheckSelisih: it.crosscheckSelisih || 0,
                    crosscheckPending: it.crosscheckPending || 0,
                });
            });
    });
    return rows;
}

function renderUnitChart(rows) {
    const canvas = document.getElementById("amdUnitChart");
    if (!canvas) return;

    const showJenisAudit = !document.getElementById("amdJenisAuditFilter")?.value;
    const sorted = [...rows].sort((a, b) => (a.capaian ?? -1) - (b.capaian ?? -1));

    const labels = sorted.map((r) => (showJenisAudit ? `${r.unitUsaha} · ${r.jenisAudit}` : r.unitUsaha));
    const target = sorted.map((r) => r.target);
    const realisasi = sorted.map((r) => r.realisasi);
    const realisasiColors = sorted.map((r) => capaianColor(r.capaian));

    const wrap = document.getElementById("amdUnitChartWrap");
    if (wrap) wrap.style.height = `${Math.max(260, sorted.length * 30)}px`;

    if (unitChart) unitChart.destroy();
    unitChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels,
            datasets: [
                { label: "Target", data: target, backgroundColor: "rgba(96,165,250,0.55)", borderRadius: 4, maxBarThickness: 16 },
                { label: "Realisasi", data: realisasi, backgroundColor: realisasiColors, borderRadius: 4, maxBarThickness: 16 },
            ],
        },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            categoryPercentage: 0.7,
            barPercentage: 0.9,
            plugins: {
                legend: { labels: { color: "#d3d9e6", usePointStyle: true, pointStyle: "circle" } },
                tooltip: {
                    callbacks: {
                        afterBody: (items) => {
                            const idx = items[0]?.dataIndex;
                            const capaian = sorted[idx]?.capaian;
                            return capaian !== null && capaian !== undefined ? `Capaian: ${capaian}%` : "";
                        },
                    },
                },
            },
            scales: {
                x: { beginAtZero: true, ticks: { color: "#aab2c5", precision: 0 }, grid: { color: "rgba(148,163,184,0.08)" } },
                y: { ticks: { color: "#aab2c5", font: { size: 11 } }, grid: { display: false } },
            },
        },
    });
}

function crosscheckSummaryBadges(r) {
    const parts = [];
    if (r.crosscheckOk) parts.push(`<span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">OK ${r.crosscheckOk}</span>`);
    if (r.crosscheckNotOk) parts.push(`<span class="inline-flex items-center gap-1 rounded-full border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-semibold text-red-300">Not OK ${r.crosscheckNotOk}</span>`);
    if (r.crosscheckSelisih) parts.push(`<span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-semibold text-amber-300">Selisih ${r.crosscheckSelisih}</span>`);
    if (r.crosscheckPending) parts.push(`<span class="inline-flex items-center gap-1 rounded-full border border-slate-600 bg-slate-800/60 px-2 py-0.5 text-[11px] font-semibold text-slate-400">Belum Cross-check ${r.crosscheckPending}</span>`);
    return parts.length ? `<div class="flex flex-wrap justify-center gap-1">${parts.join("")}</div>` : '<span class="text-slate-500">-</span>';
}

function renderTable(rows) {
    const tbody = document.getElementById("amdTableBody");
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">Tidak ada unit usaha H1/H2 untuk filter ini.</td></tr>';
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
            <td class="px-3 py-2 text-center">${crosscheckSummaryBadges(r)}</td>
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
    updateStatCards(filteredSummary, rows);
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
