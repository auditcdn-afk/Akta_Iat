const SESSION_KEY = "akta_session";

function getSession() {
    try {
        return JSON.parse(localStorage.getItem(SESSION_KEY) || "null");
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
    const el = document.getElementById("pulsaAlert");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden", "border-emerald-500/40", "bg-emerald-500/10", "text-emerald-300", "border-red-500/40", "bg-red-500/10", "text-red-300");
    if (type === "error") {
        el.classList.add("border-red-500/40", "bg-red-500/10", "text-red-300");
    } else {
        el.classList.add("border-emerald-500/40", "bg-emerald-500/10", "text-emerald-300");
    }
    el.classList.remove("hidden");
    window.clearTimeout(showAlert._t);
    showAlert._t = window.setTimeout(() => el.classList.add("hidden"), 4000);
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
    }[c]));
}

const BULAN_LABEL = [
    "", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

const STATUS_LABEL = {
    diajukan: { text: "Diajukan", cls: "bg-amber-500/10 text-amber-300 border-amber-500/30" },
    disetujui: { text: "Disetujui", cls: "bg-emerald-500/10 text-emerald-300 border-emerald-500/30" },
    ditolak: { text: "Ditolak", cls: "bg-red-500/10 text-red-300 border-red-500/30" },
};

let currentUser = null;
let userOptions = [];
let pulsaItems = [];
let currentPeriode = null;

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

function populateFilterOptions() {
    const tahunEl = document.getElementById("pulsaTahunFilter");
    const bulanEl = document.getElementById("pulsaBulanFilter");
    if (!tahunEl || !bulanEl) return;

    const now = new Date();
    const currentYear = now.getFullYear();
    tahunEl.innerHTML = "";
    for (let y = currentYear + 1; y >= currentYear - 4; y--) {
        const opt = document.createElement("option");
        opt.value = String(y);
        opt.textContent = String(y);
        tahunEl.appendChild(opt);
    }
    tahunEl.value = String(currentYear);

    bulanEl.innerHTML = "";
    BULAN_LABEL.forEach((label, idx) => {
        if (idx === 0) return;
        const opt = document.createElement("option");
        opt.value = String(idx);
        opt.textContent = label;
        bulanEl.appendChild(opt);
    });
    bulanEl.value = String(now.getMonth() + 1);
}

async function loadUserOptions() {
    try {
        const result = await fetchJson("/api/pulsa/users", { headers: authHeaders() });
        userOptions = result.data || [];
        const namaEl = document.getElementById("pulsaNama");
        if (!namaEl) return;
        namaEl.innerHTML = '<option value="">— Pilih Nama —</option>';
        userOptions.forEach((u) => {
            const opt = document.createElement("option");
            opt.value = u.nama;
            opt.dataset.jabatan = u.jabatan || "";
            opt.textContent = u.nama;
            namaEl.appendChild(opt);
        });
    } catch (err) {
        showAlert(err.message || "Gagal memuat daftar nama.", "error");
    }
}

async function loadOperatorOptions() {
    try {
        const result = await fetchJson("/api/pulsa/operators", { headers: authHeaders() });
        const operators = result.data || [];
        const el = document.getElementById("pulsaOperator");
        if (!el) return;
        el.innerHTML = '<option value="">— Pilih —</option>';
        operators.forEach((op) => {
            const opt = document.createElement("option");
            opt.value = op;
            opt.textContent = op;
            el.appendChild(opt);
        });
    } catch (err) {
        showAlert(err.message || "Gagal memuat daftar operator.", "error");
    }
}

function updatePeriodeUi() {
    const badge = document.getElementById("pulsaPeriodeBadge");
    const text = document.getElementById("pulsaPeriodeText");
    const toggleBtn = document.getElementById("pulsaTogglePeriodeBtn");
    if (!badge || !text || !toggleBtn) return;

    const tahun = document.getElementById("pulsaTahunFilter")?.value;
    const bulan = Number(document.getElementById("pulsaBulanFilter")?.value);
    const label = `${BULAN_LABEL[bulan] || ""} ${tahun || ""}`.trim();
    const status = currentPeriode?.status || "terbuka";
    const isTerbuka = status === "terbuka";

    text.textContent = `Periode ${isTerbuka ? "Terbuka" : "Tertutup"} — ${label}`;
    badge.classList.remove(
        "border-emerald-500/30", "bg-emerald-500/10", "text-emerald-300",
        "border-red-500/30", "bg-red-500/10", "text-red-300"
    );
    const dot = badge.querySelector("span.h-2");
    if (isTerbuka) {
        badge.classList.add("border-emerald-500/30", "bg-emerald-500/10", "text-emerald-300");
        dot?.classList.remove("bg-red-400");
        dot?.classList.add("bg-emerald-400");
    } else {
        badge.classList.add("border-red-500/30", "bg-red-500/10", "text-red-300");
        dot?.classList.remove("bg-emerald-400");
        dot?.classList.add("bg-red-400");
    }

    if (currentUser?.role === "admin") {
        toggleBtn.classList.remove("hidden");
        toggleBtn.textContent = isTerbuka ? "Tutup Periode" : "Buka Periode";
    } else {
        toggleBtn.classList.add("hidden");
    }
}

function renderTable() {
    const tbody = document.getElementById("pulsaTableBody");
    if (!tbody) return;

    if (!pulsaItems.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada data realisasi pulsa pada periode ini.</td></tr>';
    } else {
        tbody.innerHTML = pulsaItems.map((item, idx) => {
            const status = STATUS_LABEL[item.status] || STATUS_LABEL.diajukan;
            const canDelete = currentUser?.role === "admin" || currentUser?.username === item.createdBy;
            const bonLink = item.bonFile?.url
                ? `<a href="${escapeHtml(item.bonFile.url)}" target="_blank" rel="noopener" class="text-blue-400 hover:underline">Lihat</a>`
                : '<span class="text-slate-500">-</span>';
            return `
                <tr>
                    <td class="px-4 py-3">${idx + 1}</td>
                    <td class="px-4 py-3">${escapeHtml(formatTanggal(item.tanggal))}</td>
                    <td class="px-4 py-3">${escapeHtml(item.nama)}</td>
                    <td class="px-4 py-3">${escapeHtml(item.jabatan || "-")}</td>
                    <td class="px-4 py-3">${escapeHtml(item.operator || "-")}</td>
                    <td class="px-4 py-3">${escapeHtml(item.nomorHp)}</td>
                    <td class="px-4 py-3 text-right">${formatRupiah(item.nominal)}</td>
                    <td class="px-4 py-3 text-center">${bonLink}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${status.cls}">${status.text}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        ${canDelete ? `<button data-id="${item.id}" class="pulsaDeleteBtn rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10 transition">Hapus</button>` : '<span class="text-slate-600">-</span>'}
                    </td>
                </tr>`;
        }).join("");

        tbody.querySelectorAll(".pulsaDeleteBtn").forEach((btn) => {
            btn.addEventListener("click", () => deleteItem(btn.dataset.id));
        });
    }

    const totalCount = document.getElementById("pulsaTotalCount");
    const totalNominal = document.getElementById("pulsaTotalNominal");
    if (totalCount) totalCount.textContent = `${pulsaItems.length} record`;
    if (totalNominal) {
        const sum = pulsaItems.reduce((acc, item) => acc + (Number(item.nominal) || 0), 0);
        totalNominal.textContent = formatRupiah(sum);
    }
}

async function loadData() {
    const tahun = document.getElementById("pulsaTahunFilter")?.value;
    const bulan = document.getElementById("pulsaBulanFilter")?.value;
    try {
        const result = await fetchJson(`/api/pulsa?tahun=${tahun}&bulan=${bulan}`, { headers: authHeaders() });
        pulsaItems = result.data || [];
        currentPeriode = result.periode || null;
        renderTable();
        updatePeriodeUi();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data realisasi pulsa.", "error");
    }
}

async function deleteItem(id) {
    if (!window.confirm("Hapus data realisasi pulsa ini?")) return;
    try {
        const result = await fetchJson(`/api/pulsa/${id}`, {
            method: "DELETE",
            headers: authHeaders(),
        });
        showAlert(result.message || "Data berhasil dihapus.");
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal menghapus data.", "error");
    }
}

async function handleTogglePeriode() {
    const tahun = Number(document.getElementById("pulsaTahunFilter")?.value);
    const bulan = Number(document.getElementById("pulsaBulanFilter")?.value);
    const nextStatus = currentPeriode?.status === "terbuka" ? "tertutup" : "terbuka";
    try {
        const result = await fetchJson("/api/pulsa/toggle-periode", {
            method: "POST",
            headers: { "Content-Type": "application/json", ...authHeaders() },
            body: JSON.stringify({ tahun, bulan, status: nextStatus }),
        });
        showAlert(result.message || "Status periode diperbarui.");
        currentPeriode = result.data || null;
        updatePeriodeUi();
    } catch (err) {
        showAlert(err.message || "Gagal mengubah status periode.", "error");
    }
}

function handleNamaChange() {
    const namaEl = document.getElementById("pulsaNama");
    const jabatanEl = document.getElementById("pulsaJabatan");
    if (!namaEl || !jabatanEl) return;
    const selected = namaEl.selectedOptions[0];
    jabatanEl.value = selected?.dataset.jabatan || "";
}

function handleFileChange() {
    const fileInput = document.getElementById("pulsaFileInput");
    const fileName = document.getElementById("pulsaFileName");
    if (!fileInput || !fileName) return;
    fileName.textContent = fileInput.files?.[0]?.name || "Belum ada file";
}

function formatNominalInput() {
    const el = document.getElementById("pulsaNominal");
    if (!el) return;
    const digits = el.value.replace(/\D/g, "");
    el.value = digits ? Number(digits).toLocaleString("id-ID") : "";
}

async function handleSubmit(e) {
    e.preventDefault();
    const saveBtn = document.getElementById("pulsaSaveBtn");
    const fileInput = document.getElementById("pulsaFileInput");
    const nominalRaw = document.getElementById("pulsaNominal")?.value.replace(/\D/g, "") || "0";

    const formData = new FormData();
    formData.append("username", getSession()?.username || "");
    formData.append("nama", document.getElementById("pulsaNama")?.value || "");
    formData.append("jabatan", document.getElementById("pulsaJabatan")?.value || "");
    formData.append("tanggal", document.getElementById("pulsaTanggal")?.value || "");
    formData.append("nomor_hp", document.getElementById("pulsaNomorHp")?.value || "");
    formData.append("operator", document.getElementById("pulsaOperator")?.value || "");
    formData.append("nominal", nominalRaw);
    if (fileInput?.files?.[0]) {
        formData.append("file", fileInput.files[0]);
    }

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = "Menyimpan...";
    }
    try {
        const result = await fetchJson("/api/pulsa", {
            method: "POST",
            headers: authHeaders(),
            body: formData,
        });
        showAlert(result.message || "Realisasi pulsa berhasil disimpan.");
        document.getElementById("pulsaForm")?.reset();
        document.getElementById("pulsaFileName").textContent = "Belum ada file";
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal menyimpan realisasi pulsa.", "error");
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = "💾 Simpan Realisasi";
        }
    }
}

function exportExcel() {
    if (!pulsaItems.length) {
        showAlert("Tidak ada data untuk diexport.", "error");
        return;
    }
    const header = ["No", "Tanggal", "Nama", "Jabatan", "Operator", "No HP", "Nominal", "Status"];
    const rows = pulsaItems.map((item, idx) => [
        idx + 1,
        formatTanggal(item.tanggal),
        item.nama,
        item.jabatan || "-",
        item.operator || "-",
        item.nomorHp,
        item.nominal,
        STATUS_LABEL[item.status]?.text || item.status,
    ]);
    const csv = [header, ...rows]
        .map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(","))
        .join("\n");
    const blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    const tahun = document.getElementById("pulsaTahunFilter")?.value;
    const bulan = document.getElementById("pulsaBulanFilter")?.value;
    a.href = url;
    a.download = `realisasi-pulsa-${tahun}-${bulan}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

document.addEventListener("DOMContentLoaded", async () => {
    currentUser = getSession();

    populateFilterOptions();

    document.getElementById("pulsaNama")?.addEventListener("change", handleNamaChange);
    document.getElementById("pulsaFileInput")?.addEventListener("change", handleFileChange);
    document.getElementById("pulsaNominal")?.addEventListener("input", formatNominalInput);
    document.getElementById("pulsaForm")?.addEventListener("submit", handleSubmit);
    document.getElementById("pulsaExportBtn")?.addEventListener("click", exportExcel);
    document.getElementById("pulsaTogglePeriodeBtn")?.addEventListener("click", handleTogglePeriode);
    document.getElementById("pulsaTahunFilter")?.addEventListener("change", loadData);
    document.getElementById("pulsaBulanFilter")?.addEventListener("change", loadData);

    await Promise.all([loadUserOptions(), loadOperatorOptions(), loadData()]);
});
