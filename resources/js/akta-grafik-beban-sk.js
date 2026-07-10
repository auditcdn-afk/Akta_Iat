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
    const el = document.getElementById("gbAlert");
    if (!el) return;
    if (!message) {
        el.classList.add("hidden");
        return;
    }
    el.textContent = message;
    el.classList.remove("hidden");
}

function formatRupiah(value) {
    const n = Number(value) || 0;
    return "Rp " + n.toLocaleString("id-ID", { maximumFractionDigits: 0 });
}

const BULAN_LABEL = [
    "", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];
const JENIS_UNIT_LABEL = { h1: "H1", h2_whs: "H2 / WHS" };
const STATUS_LABEL = { draft: "Draft", final: "Final" };

let trendChart = null;
let jenisUnitChart = null;
let unitChart = null;
let tahunChart = null;
let itemChart = null;
let jabatanChart = null;
let personilChart = null;
let latestByUnit = [];
let tahunOptionsPopulated = false;

// ── Dropdown checkbox multi-pilih (reusable) ──

function setupDropdownToggle(btnId, panelId) {
    const btn = document.getElementById(btnId);
    const panel = document.getElementById(panelId);
    if (!btn || !panel) return;

    btn.addEventListener("click", (e) => {
        e.stopPropagation();
        document.querySelectorAll(".gb-filter-panel").forEach((p) => {
            if (p !== panel) p.classList.add("hidden");
        });
        panel.classList.toggle("hidden");
    });
    document.addEventListener("click", (e) => {
        if (!panel.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
            panel.classList.add("hidden");
        }
    });
}

function renderCheckboxPanel(panelId, checkboxClass, items, onChange) {
    const panel = document.getElementById(panelId);
    if (!panel) return;
    panel.innerHTML = items.map(({ value, label, checked }) => `
        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-200 hover:bg-slate-800 cursor-pointer">
            <input type="checkbox" value="${value}" class="${checkboxClass} rounded border-slate-600 bg-slate-950 text-blue-500 focus:ring-0" ${checked ? "checked" : ""}>
            ${label}
        </label>
    `).join("");
    panel.querySelectorAll(`.${checkboxClass}`).forEach((cb) => cb.addEventListener("change", onChange));
}

function getSelectedValues(checkboxClass) {
    return Array.from(document.querySelectorAll(`.${checkboxClass}:checked`)).map((el) => el.value);
}

function updateMultiFilterLabel(labelId, selected, allLabel, resolveLabel) {
    const el = document.getElementById(labelId);
    if (!el) return;
    if (!selected.length) {
        el.textContent = allLabel;
    } else if (selected.length === 1) {
        el.textContent = resolveLabel(selected[0]);
    } else {
        el.textContent = `${selected.length} Dipilih`;
    }
}

function getSelectedTahun() {
    return getSelectedValues("gbTahunCheckbox");
}
function getSelectedBulan() {
    return getSelectedValues("gbBulanCheckbox");
}
function getSelectedJenisUnit() {
    return getSelectedValues("gbJenisUnitCheckbox");
}
function getSelectedStatus() {
    return getSelectedValues("gbStatusCheckbox");
}
function getSelectedUnitUsahaFilter() {
    return getSelectedValues("gbUnitUsahaCheckbox");
}

let unitUsahaOptionsPopulated = false;
let allUnitUsahaOptions = [];

function renderUnitUsahaFilterOptions(searchTerm = "") {
    const term = searchTerm.trim().toLowerCase();
    const previouslyChecked = new Set(getSelectedUnitUsahaFilter());
    const jenisUnitFilter = getSelectedJenisUnit();

    const filtered = allUnitUsahaOptions.filter((u) => {
        const matchesSearch = !term || u.unitUsaha.toLowerCase().includes(term);
        const matchesJenis = !jenisUnitFilter.length || jenisUnitFilter.includes(u.jenisUnit);
        return matchesSearch && matchesJenis;
    });
    const items = filtered.map((u) => ({ value: u.unitUsaha, label: u.unitUsaha, checked: previouslyChecked.has(u.unitUsaha) }));
    renderCheckboxPanel("gbUnitUsahaFilterOptions", "gbUnitUsahaCheckbox", items, () => {
        updateMultiFilterLabel("gbUnitUsahaFilterLabel", getSelectedUnitUsahaFilter(), "Semua Unit Usaha", (v) => v);
        loadRekap();
    });
}

function populateUnitUsahaOptions(options) {
    if (unitUsahaOptionsPopulated || !options?.length) return;
    allUnitUsahaOptions = options;
    renderUnitUsahaFilterOptions();
    document.getElementById("gbUnitUsahaFilterSearch")?.addEventListener("input", (e) => {
        renderUnitUsahaFilterOptions(e.target.value);
    });
    unitUsahaOptionsPopulated = true;
}

function populateStaticFilters() {
    setupDropdownToggle("gbUnitUsahaFilterBtn", "gbUnitUsahaFilterPanel");
    setupDropdownToggle("gbTahunFilterBtn", "gbTahunFilterPanel");
    setupDropdownToggle("gbBulanFilterBtn", "gbBulanFilterPanel");
    setupDropdownToggle("gbJenisUnitFilterBtn", "gbJenisUnitFilterPanel");
    setupDropdownToggle("gbStatusFilterBtn", "gbStatusFilterPanel");

    const bulanItems = BULAN_LABEL
        .map((label, idx) => ({ value: idx, label, checked: false }))
        .filter((item) => item.value !== 0);
    renderCheckboxPanel("gbBulanFilterPanel", "gbBulanCheckbox", bulanItems, () => {
        updateMultiFilterLabel("gbBulanFilterLabel", getSelectedBulan(), "Semua Bulan", (v) => BULAN_LABEL[v]);
        loadRekap();
    });

    const jenisUnitItems = Object.entries(JENIS_UNIT_LABEL).map(([value, label]) => ({ value, label, checked: false }));
    renderCheckboxPanel("gbJenisUnitFilterPanel", "gbJenisUnitCheckbox", jenisUnitItems, () => {
        updateMultiFilterLabel("gbJenisUnitFilterLabel", getSelectedJenisUnit(), "Semua Jenis Unit", (v) => JENIS_UNIT_LABEL[v]);
        renderUnitUsahaFilterOptions(document.getElementById("gbUnitUsahaFilterSearch")?.value || "");
        loadRekap();
    });

    const statusItems = Object.entries(STATUS_LABEL).map(([value, label]) => ({ value, label, checked: false }));
    renderCheckboxPanel("gbStatusFilterPanel", "gbStatusCheckbox", statusItems, () => {
        updateMultiFilterLabel("gbStatusFilterLabel", getSelectedStatus(), "Semua Status", (v) => STATUS_LABEL[v]);
        loadRekap();
    });
}

function populateTahunOptions(options) {
    if (tahunOptionsPopulated || !options?.length) return;
    const items = options.map((y) => ({ value: y, label: String(y), checked: false }));
    renderCheckboxPanel("gbTahunFilterPanel", "gbTahunCheckbox", items, () => {
        updateMultiFilterLabel("gbTahunFilterLabel", getSelectedTahun(), "Semua Tahun", (v) => v);
        loadRekap();
    });
    tahunOptionsPopulated = true;
}

function capaianColor(idx, total, alpha = 0.9) {
    const palette = [
        `rgba(96,165,250,${alpha})`,
        `rgba(45,212,191,${alpha})`,
        `rgba(250,204,21,${alpha})`,
        `rgba(248,113,113,${alpha})`,
        `rgba(167,139,250,${alpha})`,
        `rgba(52,211,153,${alpha})`,
    ];
    return palette[idx % palette.length];
}

function renderTrendChart(byBulan) {
    const canvas = document.getElementById("gbTrendChart");
    if (!canvas) return;

    const labels = byBulan.map((it) => {
        const [y, m] = (it.bulan || "").split("-");
        return `${BULAN_LABEL[Number(m)] || m} ${y || ""}`;
    });
    const totals = byBulan.map((it) => it.total);

    if (trendChart) trendChart.destroy();
    trendChart = new Chart(canvas, {
        type: "line",
        data: {
            labels,
            datasets: [{
                label: "Total Beban (Rp)",
                data: totals,
                borderColor: "rgba(45,212,191,1)",
                backgroundColor: "rgba(45,212,191,0.15)",
                fill: true,
                tension: 0.3,
                pointBackgroundColor: "rgba(45,212,191,1)",
                pointRadius: 4,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (item) => `Total: ${formatRupiah(item.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: { ticks: { color: "#aab2c5" }, grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { color: "#aab2c5", callback: (v) => formatRupiah(v) },
                    grid: { color: "rgba(148,163,184,0.08)" },
                },
            },
        },
    });
}

function renderJenisUnitChart(byJenisUnit) {
    const canvas = document.getElementById("gbJenisUnitChart");
    if (!canvas) return;

    const labels = byJenisUnit.map((it) => JENIS_UNIT_LABEL[it.jenisUnit] || it.jenisUnit);
    const totals = byJenisUnit.map((it) => it.total);
    const colors = byJenisUnit.map((_, idx) => capaianColor(idx));

    if (jenisUnitChart) jenisUnitChart.destroy();
    jenisUnitChart = new Chart(canvas, {
        type: "doughnut",
        data: {
            labels,
            datasets: [{ data: totals, backgroundColor: colors, borderWidth: 0 }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: "bottom", labels: { color: "#d3d9e6", usePointStyle: true, pointStyle: "circle" } },
                tooltip: {
                    callbacks: {
                        label: (item) => `${item.label}: ${formatRupiah(item.parsed)}`,
                    },
                },
            },
        },
    });
}

function renderTahunChart(byTahun) {
    const canvas = document.getElementById("gbTahunChart");
    if (!canvas) return;

    const labels = byTahun.map((it) => it.tahun);
    const totals = byTahun.map((it) => it.total);
    const jumlah = byTahun.map((it) => it.jumlahSk);

    if (tahunChart) tahunChart.destroy();
    tahunChart = new Chart(canvas, {
        data: {
            labels,
            datasets: [
                {
                    type: "bar",
                    label: "Nominal Beban (Rp)",
                    data: totals,
                    backgroundColor: "rgba(96,165,250,0.85)",
                    borderRadius: 8,
                    maxBarThickness: 60,
                    yAxisID: "y",
                },
                {
                    type: "line",
                    label: "Jumlah Kasus",
                    data: jumlah,
                    borderColor: "rgba(248,113,113,1)",
                    backgroundColor: "rgba(248,113,113,1)",
                    tension: 0.3,
                    yAxisID: "y1",
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: "#d3d9e6", usePointStyle: true, pointStyle: "circle" } },
                tooltip: {
                    callbacks: {
                        label: (item) => item.dataset.yAxisID === "y" ? `Nominal: ${formatRupiah(item.parsed.y)}` : `Jumlah Kasus: ${item.parsed.y}`,
                    },
                },
            },
            scales: {
                x: { ticks: { color: "#aab2c5" }, grid: { display: false } },
                y: {
                    beginAtZero: true,
                    position: "left",
                    ticks: { color: "#aab2c5", callback: (v) => formatRupiah(v) },
                    grid: { color: "rgba(148,163,184,0.08)" },
                },
                y1: {
                    beginAtZero: true,
                    position: "right",
                    ticks: { color: "#aab2c5", precision: 0 },
                    grid: { drawOnChartArea: false },
                },
            },
        },
    });
}

function renderItemChart(byItem) {
    const canvas = document.getElementById("gbItemChart");
    if (!canvas) return;

    const labels = byItem.map((it) => it.kategori);
    const totals = byItem.map((it) => it.total);
    const colors = byItem.map((_, idx) => capaianColor(idx));

    if (itemChart) itemChart.destroy();
    itemChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels,
            datasets: [{ label: "Nominal Beban", data: totals, backgroundColor: colors, borderRadius: 6, maxBarThickness: 40 }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (item) => formatRupiah(item.parsed.y) } },
            },
            scales: {
                x: { ticks: { color: "#aab2c5" }, grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { color: "#aab2c5", callback: (v) => formatRupiah(v) },
                    grid: { color: "rgba(148,163,184,0.08)" },
                },
            },
        },
    });
}

function renderJabatanChart(byJabatan) {
    const canvas = document.getElementById("gbJabatanChart");
    if (!canvas) return;

    const labels = byJabatan.map((it) => it.jabatan);
    const totals = byJabatan.map((it) => it.total);
    const colors = byJabatan.map((_, idx) => capaianColor(idx));

    if (jabatanChart) jabatanChart.destroy();
    jabatanChart = new Chart(canvas, {
        type: "doughnut",
        data: {
            labels,
            datasets: [{ data: totals, backgroundColor: colors, borderWidth: 0 }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: "bottom", labels: { color: "#d3d9e6", usePointStyle: true, pointStyle: "circle" } },
                tooltip: { callbacks: { label: (item) => `${item.label}: ${formatRupiah(item.parsed)}` } },
            },
        },
    });
}

function renderPersonilChart(byPersonil) {
    const canvas = document.getElementById("gbPersonilChart");
    if (!canvas) return;

    const top = byPersonil.slice(0, 15);
    const labels = top.map((it) => it.nama);
    const totals = top.map((it) => it.total);
    const colors = top.map((_, idx) => capaianColor(idx));

    const wrap = document.getElementById("gbPersonilChartWrap");
    if (wrap) wrap.style.height = `${Math.max(260, top.length * 28)}px`;

    if (personilChart) personilChart.destroy();
    personilChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels,
            datasets: [{ label: "Nominal Beban", data: totals, backgroundColor: colors, borderRadius: 4, maxBarThickness: 18 }],
        },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (item) => formatRupiah(item.parsed.x),
                        afterLabel: (item) => `Jabatan: ${top[item.dataIndex]?.jabatan || "-"}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { color: "#aab2c5", callback: (v) => formatRupiah(v) },
                    grid: { color: "rgba(148,163,184,0.08)" },
                },
                y: { ticks: { color: "#aab2c5", font: { size: 11 } }, grid: { display: false } },
            },
        },
    });
}

function unitRowsForFilter() {
    const searchTerm = (document.getElementById("gbUnitSearch")?.value || "").trim().toLowerCase();
    return latestByUnit.filter((u) => !searchTerm || u.unitUsaha.toLowerCase().includes(searchTerm));
}

function renderUnitChart(rows) {
    const canvas = document.getElementById("gbUnitChart");
    if (!canvas) return;

    const labels = rows.map((r) => r.unitUsaha);
    const totals = rows.map((r) => r.total);
    const colors = rows.map((_, idx) => capaianColor(idx));

    const wrap = document.getElementById("gbUnitChartWrap");
    if (wrap) wrap.style.height = `${Math.max(260, rows.length * 30)}px`;

    if (unitChart) unitChart.destroy();
    unitChart = new Chart(canvas, {
        type: "bar",
        data: {
            labels,
            datasets: [{ label: "Total Beban", data: totals, backgroundColor: colors, borderRadius: 4, maxBarThickness: 18 }],
        },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            categoryPercentage: 0.7,
            barPercentage: 0.85,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (item) => formatRupiah(item.parsed.x),
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { color: "#aab2c5", callback: (v) => formatRupiah(v) },
                    grid: { color: "rgba(148,163,184,0.08)" },
                },
                y: { ticks: { color: "#aab2c5", font: { size: 11 } }, grid: { display: false } },
            },
        },
    });
}

function renderTable(rows) {
    const tbody = document.getElementById("gbTableBody");
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">Tidak ada data untuk filter ini.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((r) => `
        <tr>
            <td class="px-3 py-2 font-semibold">${r.unitUsaha}</td>
            <td class="px-3 py-2 text-center">${JENIS_UNIT_LABEL[r.jenisUnit] || r.jenisUnit || "-"}</td>
            <td class="px-3 py-2 text-center">${r.jumlahSk}</td>
            <td class="px-3 py-2 text-right font-semibold">${formatRupiah(r.total)}</td>
        </tr>
    `).join("");
}

function renderUnitSection() {
    const rows = unitRowsForFilter();
    renderUnitChart(rows);
    renderTable(rows);

    const el = document.getElementById("gbStatUnitCount");
    if (el) el.textContent = rows.length.toLocaleString("id-ID");
}

function updateStatCards(stats) {
    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText("gbStatTotal", formatRupiah(stats.totalBeban));
    setText("gbStatFinal", formatRupiah(stats.totalFinal));
    setText("gbStatFinalCount", stats.jumlahFinal.toLocaleString("id-ID"));
    setText("gbStatDraft", formatRupiah(stats.totalDraft));
    setText("gbStatDraftCount", stats.jumlahDraft.toLocaleString("id-ID"));
}

async function loadRekap() {
    const tahunList = getSelectedTahun();
    const bulanList = getSelectedBulan();
    const jenisUnitList = getSelectedJenisUnit();
    const statusList = getSelectedStatus();
    const unitUsahaFilterList = getSelectedUnitUsahaFilter();

    try {
        showAlert(null);
        const params = new URLSearchParams();
        tahunList.forEach((t) => params.append("tahun[]", t));
        bulanList.forEach((b) => params.append("bulan[]", b));
        jenisUnitList.forEach((j) => params.append("jenis_unit[]", j));
        statusList.forEach((s) => params.append("status[]", s));
        unitUsahaFilterList.forEach((u) => params.append("unit_usaha[]", u));

        const result = await fetchJson(`/api/sk-pembebanan/rekap?${params.toString()}`, {
            headers: authHeaders(),
        });

        populateTahunOptions(result.tahunOptions);
        populateUnitUsahaOptions(result.unitUsahaOptions);
        updateStatCards(result.stats || {});
        renderTrendChart(result.byBulan || []);
        renderJenisUnitChart(result.byJenisUnit || []);
        renderTahunChart(result.byTahun || []);
        renderItemChart(result.byItemPembebanan || []);
        renderJabatanChart(result.byJabatan || []);
        renderPersonilChart(result.byPersonil || []);
        latestByUnit = result.byUnit || [];
        renderUnitSection();
    } catch (err) {
        showAlert(err.message || "Gagal memuat rekap beban SK.");
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (!getSession()) return;

    populateStaticFilters();
    document.getElementById("gbUnitSearch")?.addEventListener("input", renderUnitSection);
    loadRekap();
});
