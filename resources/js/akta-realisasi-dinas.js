const SESSION_KEY = "akta_session";
const CREATE_ROLES = ["admin", "manajer", "auditor", "koordinator"];

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

function showAlert(message, type = "success") {
    const el = document.getElementById("rdAlert");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden", "border-emerald-500/40", "bg-emerald-500/10", "text-emerald-300", "border-red-500/40", "bg-red-500/10", "text-red-300");
    el.classList.add(...(type === "error"
        ? ["border-red-500/40", "bg-red-500/10", "text-red-300"]
        : ["border-emerald-500/40", "bg-emerald-500/10", "text-emerald-300"]));
    window.clearTimeout(showAlert._t);
    showAlert._t = window.setTimeout(() => el.classList.add("hidden"), 4000);
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    }[c]));
}

function formatRupiah(value) {
    const n = Number(value) || 0;
    return "Rp " + n.toLocaleString("id-ID", { maximumFractionDigits: 0 });
}

function formatTanggal(value) {
    if (!value) return "-";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
}

const BULAN_LABEL = [
    "", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

let currentUser = null;
let rdItems = [];
let personilChips = [];
let preselectPlanId = null;

// ── Personil chip picker ──

function renderPersonilChips() {
    const el = document.getElementById("rdPersonilChips");
    if (!el) return;
    el.innerHTML = personilChips.map((nama, idx) => `
        <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-500/40 bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-300">
            ${escapeHtml(nama)}
            <button type="button" data-idx="${idx}" class="rdChipRemove text-blue-400 hover:text-blue-200">✕</button>
        </span>
    `).join("");
    el.querySelectorAll(".rdChipRemove").forEach((btn) => {
        btn.addEventListener("click", () => {
            personilChips.splice(Number(btn.dataset.idx), 1);
            renderPersonilChips();
        });
    });
}

function addPersonilFromInput() {
    const input = document.getElementById("rdPersonilInput");
    const nama = (input?.value || "").trim();
    if (!nama) return;
    if (!personilChips.includes(nama)) personilChips.push(nama);
    if (input) input.value = "";
    renderPersonilChips();
}

// ── Load reference data ──

async function loadPersonilOptions() {
    const el = document.getElementById("rdPersonilOptions");
    if (!el) return;
    try {
        const result = await fetchJson("/api/plan-users", { headers: authHeaders() });
        const options = result.data || [];
        el.innerHTML = options
            .map((u) => u.displayName || u.name || u.username)
            .filter(Boolean)
            .map((nama) => `<option value="${escapeHtml(nama)}"></option>`)
            .join("");
    } catch {
        // Datalist opsional — abaikan jika gagal
    }
}

async function loadPlanOptions() {
    const select = document.getElementById("rdPlanSelect");
    if (!select) return;
    try {
        const result = await fetchJson("/api/realisasi-dinas/plan-options", { headers: authHeaders() });
        const options = result.data || [];
        select.innerHTML = '<option value="">Pilih plan audit...</option>' + options.map((p) => `
            <option value="${p.id}">${escapeHtml(p.cabang)} — ${escapeHtml(p.noSpt || "")} (${escapeHtml(formatTanggal(p.tglSelesai))})</option>
        `).join("");
        if (preselectPlanId && options.some((p) => String(p.id) === String(preselectPlanId))) {
            select.value = String(preselectPlanId);
        }
    } catch (err) {
        showAlert(err.message || "Gagal memuat daftar plan audit.", "error");
    }
}

function populateJenisPengeluaranSelects(options) {
    const formSelect = document.getElementById("rdJenisPengeluaran");
    const filterSelect = document.getElementById("rdFilterJenis");
    if (formSelect && !formSelect.dataset.populated) {
        formSelect.innerHTML = options.map((j) => `<option value="${escapeHtml(j)}">${escapeHtml(j)}</option>`).join("");
        formSelect.dataset.populated = "1";
    }
    if (filterSelect && !filterSelect.dataset.populated) {
        filterSelect.innerHTML = '<option value="">Semua Jenis Pengeluaran</option>' +
            options.map((j) => `<option value="${escapeHtml(j)}">${escapeHtml(j)}</option>`).join("");
        filterSelect.dataset.populated = "1";
    }
}

function populateBulanFilter() {
    const el = document.getElementById("rdFilterBulan");
    if (!el || el.dataset.populated) return;
    el.innerHTML = '<option value="">Semua Bulan</option>' +
        BULAN_LABEL.map((label, idx) => idx === 0 ? "" : `<option value="${idx}">${label}</option>`).join("");
    el.dataset.populated = "1";
}

// ── Table ──

function renderTable() {
    const tbody = document.getElementById("rdTableBody");
    if (!tbody) return;

    if (!rdItems.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada data realisasi dinas.</td></tr>';
        return;
    }

    tbody.innerHTML = rdItems.map((item, idx) => {
        const buktiLink = item.buktiFile?.url
            ? `<a href="${escapeHtml(item.buktiFile.url)}" target="_blank" rel="noopener" class="text-blue-400 hover:underline">Lihat</a>`
            : '<span class="text-slate-500">-</span>';
        const canDelete = currentUser?.role === "admin" || currentUser?.username === item.createdBy;
        const deleteBtn = canDelete
            ? `<button data-id="${item.id}" class="rdDeleteBtn rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10 transition">Hapus</button>`
            : '<span class="text-slate-600">-</span>';

        return `
            <tr>
                <td class="px-4 py-3">${idx + 1}</td>
                <td class="px-4 py-3">${escapeHtml(item.cabang || "-")}<br><span class="text-xs text-slate-500">${escapeHtml(item.noSpt || "")}</span></td>
                <td class="px-4 py-3">${escapeHtml(formatTanggal(item.tanggalSettlement))}</td>
                <td class="px-4 py-3">${(item.personil || []).map(escapeHtml).join(", ") || "-"}</td>
                <td class="px-4 py-3">${escapeHtml(item.jenisPengeluaran)}${item.catatan ? `<div class="mt-1 text-xs italic text-slate-500">"${escapeHtml(item.catatan)}"</div>` : ""}</td>
                <td class="px-4 py-3 text-right font-semibold">${formatRupiah(item.nominal)}</td>
                <td class="px-4 py-3 text-center">${buktiLink}</td>
                <td class="px-4 py-3 text-right">${deleteBtn}</td>
            </tr>`;
    }).join("");

    tbody.querySelectorAll(".rdDeleteBtn").forEach((btn) => {
        btn.addEventListener("click", () => deleteItem(btn.dataset.id));
    });
}

function updateStatCards(stats) {
    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText("rdStatTotal", formatRupiah(stats.totalNominal));
    setText("rdStatCount", (stats.jumlahEntri ?? rdItems.length).toLocaleString("id-ID"));
    setText("rdStatPlan", (stats.jumlahPlan ?? new Set(rdItems.map((r) => r.planAuditId)).size).toLocaleString("id-ID"));
}

async function loadData() {
    const params = new URLSearchParams();
    const jenis = document.getElementById("rdFilterJenis")?.value;
    const tahun = document.getElementById("rdFilterTahun")?.value;
    const bulan = document.getElementById("rdFilterBulan")?.value;
    if (jenis) params.set("jenis_pengeluaran", jenis);
    if (tahun) params.set("tahun", tahun);
    if (bulan) params.set("bulan", bulan);

    try {
        const result = await fetchJson(`/api/realisasi-dinas?${params.toString()}`, { headers: authHeaders() });
        rdItems = result.data || [];
        populateJenisPengeluaranSelects(result.jenisPengeluaranOptions || []);
        renderTable();
        const total = rdItems.reduce((sum, r) => sum + (Number(r.nominal) || 0), 0);
        updateStatCards({ totalNominal: total, jumlahEntri: rdItems.length });
    } catch (err) {
        showAlert(err.message || "Gagal memuat data realisasi dinas.", "error");
    }
}

// ── Form ──

function handleFileChange() {
    const fileInput = document.getElementById("rdFileInput");
    const fileName = document.getElementById("rdFileName");
    if (!fileInput || !fileName) return;
    fileName.textContent = fileInput.files?.[0]?.name || "Belum ada file";
}

function resetForm() {
    document.getElementById("rdForm")?.reset();
    document.getElementById("rdFileName").textContent = "Belum ada file";
    document.getElementById("rdNominal").value = "0";
    personilChips = [];
    renderPersonilChips();
}

async function handleSubmit(e) {
    e.preventDefault();
    const saveBtn = document.getElementById("rdSaveBtn");
    const planId = document.getElementById("rdPlanSelect")?.value;
    const tanggal = document.getElementById("rdTanggal")?.value;
    const jenis = document.getElementById("rdJenisPengeluaran")?.value;
    const catatan = document.getElementById("rdCatatan")?.value || "";
    const nominal = document.getElementById("rdNominal")?.value || "0";
    const fileInput = document.getElementById("rdFileInput");

    if (!planId) {
        showAlert("Pilih plan audit terlebih dahulu.", "error");
        return;
    }
    if (!personilChips.length) {
        showAlert("Tambahkan minimal satu personil.", "error");
        return;
    }

    const formData = new FormData();
    formData.append("plan_audit_id", planId);
    formData.append("tanggal_settlement", tanggal);
    personilChips.forEach((nama) => formData.append("personil[]", nama));
    formData.append("jenis_pengeluaran", jenis);
    formData.append("catatan", catatan);
    formData.append("nominal", nominal);
    if (fileInput?.files?.[0]) formData.append("file", fileInput.files[0]);

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = "Menyimpan...";
    }
    try {
        const result = await fetchJson("/api/realisasi-dinas", {
            method: "POST",
            headers: authHeaders(),
            body: formData,
        });
        showAlert(result.message || "Realisasi Dinas berhasil disimpan.");
        resetForm();
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal menyimpan realisasi dinas.", "error");
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = "💾 Simpan Realisasi Dinas";
        }
    }
}

async function deleteItem(id) {
    if (!window.confirm("Hapus data realisasi dinas ini?")) return;
    try {
        const result = await fetchJson(`/api/realisasi-dinas/${id}`, {
            method: "DELETE",
            headers: authHeaders(),
        });
        showAlert(result.message || "Data berhasil dihapus.");
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal menghapus data.", "error");
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    currentUser = getSession()?.user || null;
    if (!currentUser) {
        window.location.href = "/akta/login";
        return;
    }

    preselectPlanId = new URLSearchParams(window.location.search).get("plan_id");

    if (CREATE_ROLES.includes(currentUser.role)) {
        document.getElementById("rdFormCard")?.classList.remove("hidden");
        await Promise.all([loadPlanOptions(), loadPersonilOptions()]);
        if (preselectPlanId) {
            document.getElementById("rdFormCard")?.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }

    populateBulanFilter();

    document.getElementById("rdForm")?.addEventListener("submit", handleSubmit);
    document.getElementById("rdFileInput")?.addEventListener("change", handleFileChange);
    document.getElementById("rdPersonilAddBtn")?.addEventListener("click", addPersonilFromInput);
    document.getElementById("rdPersonilInput")?.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            addPersonilFromInput();
        }
    });
    document.getElementById("rdNominalPlus")?.addEventListener("click", () => {
        const el = document.getElementById("rdNominal");
        el.value = (Number(el.value) || 0) + 1000;
    });
    document.getElementById("rdNominalMinus")?.addEventListener("click", () => {
        const el = document.getElementById("rdNominal");
        el.value = Math.max(0, (Number(el.value) || 0) - 1000);
    });
    document.getElementById("rdFilterApplyBtn")?.addEventListener("click", loadData);
    document.getElementById("rdFilterResetBtn")?.addEventListener("click", () => {
        document.getElementById("rdFilterJenis").value = "";
        document.getElementById("rdFilterTahun").value = "";
        document.getElementById("rdFilterBulan").value = "";
        loadData();
    });

    await loadData();
});
