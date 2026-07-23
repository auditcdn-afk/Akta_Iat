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
    const el = document.getElementById("rdgAlert");
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

let trendChart = null;
let jenisChart = null;
let cabangChart = null;
let personilChart = null;
let tahunOptionsPopulated = false;
let jenisOptionsPopulated = false;

// ── Dropdown checkbox multi-pilih (reusable) ──

function setupDropdownToggle(btnId, panelId) {
    const btn = document.getElementById(btnId);
    const panel = document.getElementById(panelId);
    if (!btn || !panel) return;

    btn.addEventListener("click", (e) => {
        e.stopPropagation();
        document.querySelectorAll(".rdg-filter-panel").forEach((p) => {
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
    return getSelectedValues("rdgTahunCheckbox");
}
function getSelectedBulan() {
    return getSelectedValues("rdgBulanCheckbox");
}
function getSelectedJenis() {
    return getSelectedValues("rdgJenisCheckbox");
}

function populateStaticFilters() {
    setupDropdownToggle("rdgTahunFilterBtn", "rdgTahunFilterPanel");
    setupDropdownToggle("rdgBulanFilterBtn", "rdgBulanFilterPanel");
    setupDropdownToggle("rdgJenisFilterBtn", "rdgJenisFilterPanel");

    const bulanItems = BULAN_LABEL
        .map((label, idx) => ({ value: idx, label, checked: false }))
        .filter((item) => item.value !== 0);
    renderCheckboxPanel("rdgBulanFilterPanel", "rdgBulanCheckbox", bulanItems, () => {
        updateMultiFilterLabel("rdgBulanFilterLabel", getSelectedBulan(), "Semua Bulan", (v) => BULAN_LABEL[v]);
        loadRekap();
    });
}

function populateTahunOptions(options) {
    if (tahunOptionsPopulated || !options?.length) return;
    const items = options.map((y) => ({ value: y, label: String(y), checked: false }));
    renderCheckboxPanel("rdgTahunFilterPanel", "rdgTahunCheckbox", items, () => {
        updateMultiFilterLabel("rdgTahunFilterLabel", getSelectedTahun(), "Semua Tahun", (v) => v);
        loadRekap();
    });
    tahunOptionsPopulated = true;
}

function populateJenisOptions(options) {
    if (jenisOptionsPopulated || !options?.length) return;
    const items = options.map((j) => ({ value: j, label: j, checked: false }));
    renderCheckboxPanel("rdgJenisFilterPanel", "rdgJenisCheckbox", items, () => {
        updateMultiFilterLabel("rdgJenisFilterLabel", getSelectedJenis(), "Semua Jenis Pengeluaran", (v) => v);
        loadRekap();
    });
    jenisOptionsPopulated = true;
}

function palette(idx, alpha = 0.9) {
    const colors = [
        `rgba(96,165,250,${alpha})`,
        `rgba(45,212,191,${alpha})`,
        `rgba(250,204,21,${alpha})`,
        `rgba(248,113,113,${alpha})`,
        `rgba(167,139,250,${alpha})`,
        `rgba(52,211,153,${alpha})`,
    ];
    return colors[idx % colors.length];
}

function renderTrendChart(byBulan) {
    const canvas = document.getElementById("rdgTrendChart");
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
                label: "Total Realisasi (Rp)",
                data: totals,
                borderColor: "rgba(167,139,250,1)",
                backgroundColor: "rgba(167,139,250,0.15)",
                fill: true,
                tension: 0.3,
                pointBackgroundColor: "rgba(167,139,250,1)",
                pointRadius: 4,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (item) => `Total: ${formatRupiah(item.parsed.y)}` } },
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

function renderJenisChart(byJenis) {
    const canvas = document.getElementById("rdgJenisChart");
    if (!canvas) return;

    const labels = byJenis.map((it) => it.jenisPengeluaran);
    const totals = byJenis.map((it) => it.total);
    const colors = byJenis.map((_, idx) => palette(idx));

    if (jenisChart) jenisChart.destroy();
    jenisChart = new Chart(canvas, {
        type: "doughnut",
        data: { labels, datasets: [{ data: totals, backgroundColor: colors, borderWidth: 0 }] },
        options: {
            responsive: true,
            plugins: {
                legend: { position: "bottom", labels: { color: "#d3d9e6", usePointStyle: true, pointStyle: "circle" } },
                tooltip: { callbacks: { label: (item) => `${item.label}: ${formatRupiah(item.parsed)}` } },
            },
        },
    });
}

function renderCabangChart(byCabang) {
    const canvas = document.getElementById("rdgCabangChart");
    if (!canvas) return;

    const labels = byCabang.map((it) => it.cabang);
    const totals = byCabang.map((it) => it.total);
    const colors = byCabang.map((_, idx) => palette(idx));

    const wrap = document.getElementById("rdgCabangChartWrap");
    if (wrap) wrap.style.height = `${Math.max(260, byCabang.length * 30)}px`;

    if (cabangChart) cabangChart.destroy();
    cabangChart = new Chart(canvas, {
        type: "bar",
        data: { labels, datasets: [{ label: "Nominal", data: totals, backgroundColor: colors, borderRadius: 4, maxBarThickness: 18 }] },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (item) => formatRupiah(item.parsed.x) } },
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

function renderPersonilChart(byPersonil) {
    const canvas = document.getElementById("rdgPersonilChart");
    if (!canvas) return;

    const top = byPersonil.slice(0, 15);
    const labels = top.map((it) => it.nama);
    const totals = top.map((it) => it.total);
    const colors = top.map((_, idx) => palette(idx));

    const wrap = document.getElementById("rdgPersonilChartWrap");
    if (wrap) wrap.style.height = `${Math.max(260, top.length * 28)}px`;

    if (personilChart) personilChart.destroy();
    personilChart = new Chart(canvas, {
        type: "bar",
        data: { labels, datasets: [{ label: "Nominal", data: totals, backgroundColor: colors, borderRadius: 4, maxBarThickness: 18 }] },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (item) => formatRupiah(item.parsed.x) } },
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

function updateStatCards(stats) {
    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText("rdgStatTotal", formatRupiah(stats.totalNominal));
    setText("rdgStatCount", (stats.jumlahEntri ?? 0).toLocaleString("id-ID"));
    setText("rdgStatPlan", (stats.jumlahPlan ?? 0).toLocaleString("id-ID"));
}

async function loadRekap() {
    try {
        showAlert(null);
        const params = new URLSearchParams();
        getSelectedTahun().forEach((t) => params.append("tahun[]", t));
        getSelectedBulan().forEach((b) => params.append("bulan[]", b));
        getSelectedJenis().forEach((j) => params.append("jenis_pengeluaran[]", j));

        const result = await fetchJson(`/api/realisasi-dinas/rekap?${params.toString()}`, { headers: authHeaders() });

        populateTahunOptions(result.tahunOptions);
        populateJenisOptions(result.jenisPengeluaranOptions);
        updateStatCards(result.stats || {});
        renderTrendChart(result.byBulan || []);
        renderJenisChart(result.byJenis || []);
        renderCabangChart(result.byCabang || []);
        renderPersonilChart(result.byPersonil || []);
    } catch (err) {
        showAlert(err.message || "Gagal memuat rekap realisasi dinas.");
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (!getSession()) return;

    populateStaticFilters();
    loadRekap();
});
