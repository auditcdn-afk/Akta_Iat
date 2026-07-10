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

let chartInstance = null;

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

function renderChart(items) {
    const canvas = document.getElementById("amdChart");
    if (!canvas) return;

    const labels = items.map((it) => `${it.jenisAudit} (${it.unitType})`);
    const target = items.map((it) => it.target);
    const realisasi = items.map((it) => it.realisasi);

    if (chartInstance) {
        chartInstance.destroy();
    }

    chartInstance = new Chart(canvas, {
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
            plugins: {
                legend: { labels: { color: "#d3d9e6" } },
            },
            scales: {
                x: { ticks: { color: "#aab2c5" }, grid: { color: "rgba(148,163,184,0.1)" } },
                y: { beginAtZero: true, ticks: { color: "#aab2c5", precision: 0 }, grid: { color: "rgba(148,163,184,0.1)" } },
            },
        },
    });
}

function capaianBadge(capaian) {
    if (capaian === null) {
        return '<span class="text-slate-500">-</span>';
    }
    let cls = "bg-red-500/10 text-red-300 border-red-500/30";
    if (capaian >= 100) cls = "bg-emerald-500/10 text-emerald-300 border-emerald-500/30";
    else if (capaian >= 70) cls = "bg-amber-500/10 text-amber-300 border-amber-500/30";
    return `<span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${cls}">${capaian}%</span>`;
}

function renderTable(items) {
    const tbody = document.getElementById("amdTableBody");
    if (!tbody) return;

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">Belum ada data unit usaha H1/H2 untuk dihitung.</td></tr>';
        return;
    }

    tbody.innerHTML = items.map((it) => `
        <tr>
            <td class="px-3 py-2 font-semibold">${it.jenisAudit}</td>
            <td class="px-3 py-2 text-center">${it.unitType} <span class="text-slate-500">(${it.unitCount})</span></td>
            <td class="px-3 py-2 text-center">${it.target}</td>
            <td class="px-3 py-2 text-center">${it.realisasi}</td>
            <td class="px-3 py-2 text-center">${capaianBadge(it.capaian)}</td>
        </tr>
    `).join("");
}

async function loadPencapaian() {
    const bulan = document.getElementById("amdBulanFilter")?.value;
    const tahun = document.getElementById("amdTahunFilter")?.value;
    try {
        showAlert(null);
        const result = await fetchJson(`/api/plan-audit-mandiri/pencapaian?tahun=${tahun}&bulan=${bulan}`, {
            headers: authHeaders(),
        });
        const items = result.data || [];
        renderChart(items);
        renderTable(items);
    } catch (err) {
        showAlert(err.message || "Gagal memuat data pencapaian audit mandiri.");
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (!getSession()) return;

    populateFilters();
    document.getElementById("amdBulanFilter")?.addEventListener("change", loadPencapaian);
    document.getElementById("amdTahunFilter")?.addEventListener("change", loadPencapaian);
    loadPencapaian();
});
