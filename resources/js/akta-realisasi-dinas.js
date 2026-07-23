const SESSION_KEY = "akta_session";
const EDIT_ROLES = ["admin", "manajer", "auditor", "koordinator"];

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
let listItems = [];
let personilChips = [];
let currentDetail = null; // header realisasi dinas yang sedang dibuka
let currentPlanId = null;

// ── Personil chip picker (dipakai di panel detail) ──

function renderPersonilChips() {
    const el = document.getElementById("rdPersonilChips");
    if (!el) return;
    const locked = !!currentDetail?.isLocked;
    el.innerHTML = personilChips.map((nama, idx) => `
        <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-500/40 bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-300">
            ${escapeHtml(nama)}
            ${locked ? "" : `<button type="button" data-idx="${idx}" class="rdChipRemove text-blue-400 hover:text-blue-200">✕</button>`}
        </span>
    `).join("");
    el.querySelectorAll(".rdChipRemove").forEach((btn) => {
        btn.addEventListener("click", async () => {
            personilChips.splice(Number(btn.dataset.idx), 1);
            renderPersonilChips();
            await savePersonil();
        });
    });
}

async function savePersonil() {
    if (!currentDetail) return;
    try {
        const result = await fetchJson(`/api/realisasi-dinas/${currentDetail.id}/personil`, {
            method: "PUT",
            headers: { "Content-Type": "application/json", ...authHeaders() },
            body: JSON.stringify({ personil: personilChips }),
        });
        currentDetail = result.data;
    } catch (err) {
        showAlert(err.message || "Gagal menyimpan personil.", "error");
    }
}

async function addPersonilFromInput() {
    const input = document.getElementById("rdPersonilInput");
    const nama = (input?.value || "").trim();
    if (!nama) return;
    if (!personilChips.includes(nama)) personilChips.push(nama);
    if (input) input.value = "";
    renderPersonilChips();
    await savePersonil();
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
    } catch (err) {
        showAlert(err.message || "Gagal memuat daftar plan audit.", "error");
    }
}

function populateJenisPengeluaranSelects(options) {
    const itemSelect = document.getElementById("rdItemJenis");
    const filterSelect = document.getElementById("rdFilterJenis");
    if (itemSelect && !itemSelect.dataset.populated) {
        itemSelect.innerHTML = options.map((j) => `<option value="${escapeHtml(j)}">${escapeHtml(j)}</option>`).join("");
        itemSelect.dataset.populated = "1";
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

// ── Panel detail (satu plan yang dikunci) ──

function setEditingDisabled(disabled) {
    document.getElementById("rdPersonilInput").disabled = disabled;
    document.getElementById("rdPersonilAddBtn").disabled = disabled;
    document.getElementById("rdFileInput").disabled = disabled;
    document.getElementById("rdItemFormWrap").classList.toggle("opacity-50", disabled);
    document.getElementById("rdItemFormWrap").classList.toggle("pointer-events-none", disabled);
}

function renderItemsTable() {
    const tbody = document.getElementById("rdItemsTableBody");
    const items = currentDetail?.items || [];
    const locked = !!currentDetail?.isLocked;

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">Belum ada item pengeluaran.</td></tr>';
    } else {
        tbody.innerHTML = items.map((it) => `
            <tr>
                <td class="px-3 py-2">${escapeHtml(it.jenisPengeluaran)}</td>
                <td class="px-3 py-2 text-slate-400">${escapeHtml(it.catatan || "-")}</td>
                <td class="px-3 py-2 text-right font-semibold">${formatRupiah(it.nominal)}</td>
                <td class="px-3 py-2 text-right">
                    ${locked ? '<span class="text-slate-600">-</span>' : `<button data-id="${it.id}" class="rdItemDeleteBtn rounded-lg border border-red-500/40 px-2.5 py-1 text-xs font-semibold text-red-300 hover:bg-red-500/10 transition">Hapus</button>`}
                </td>
            </tr>
        `).join("");
        tbody.querySelectorAll(".rdItemDeleteBtn").forEach((btn) => {
            btn.addEventListener("click", () => deleteItem(btn.dataset.id));
        });
    }

    document.getElementById("rdItemsTotal").textContent = formatRupiah(currentDetail?.totalNominal || 0);
}

function renderDetail() {
    if (!currentDetail) return;
    const locked = currentDetail.isLocked;

    document.getElementById("rdDetailPlanLabel").textContent = currentDetail.cabang || "-";
    document.getElementById("rdDetailNoSpt").textContent = currentDetail.noSpt || "-";

    const badge = document.getElementById("rdStatusBadge");
    if (locked) {
        badge.textContent = "🔒 Selesai (Dikunci)";
        badge.className = "inline-flex rounded-full border px-3 py-1 text-xs font-bold border-emerald-500/40 bg-emerald-500/10 text-emerald-300";
    } else {
        badge.textContent = "📝 Draft";
        badge.className = "inline-flex rounded-full border px-3 py-1 text-xs font-bold border-amber-500/40 bg-amber-500/10 text-amber-300";
    }

    const noticeEl = document.getElementById("rdLockedNotice");
    if (locked) {
        noticeEl.textContent = `Realisasi Dinas ini sudah dikunci oleh ${currentDetail.lockedBy || "-"} pada ${formatTanggal(currentDetail.lockedAt)}. Tidak bisa diubah lagi kecuali admin membuka kunci.`;
        noticeEl.classList.remove("hidden");
    } else {
        noticeEl.classList.add("hidden");
    }

    document.getElementById("rdSelesaiBtn").classList.toggle("hidden", locked || !EDIT_ROLES.includes(currentUser?.role));
    document.getElementById("rdBukaKunciBtn").classList.toggle("hidden", !locked || currentUser?.role !== "admin");

    personilChips = [...(currentDetail.personil || [])];
    renderPersonilChips();

    const buktiWrap = document.getElementById("rdBuktiExisting");
    const buktiLink = document.getElementById("rdBuktiExistingLink");
    if (currentDetail.buktiFile?.url) {
        buktiLink.href = currentDetail.buktiFile.url;
        buktiWrap.classList.remove("hidden");
    } else {
        buktiWrap.classList.add("hidden");
    }

    setEditingDisabled(locked);
    renderItemsTable();
}

async function loadDetailForPlan(planId) {
    try {
        const result = await fetchJson(`/api/realisasi-dinas/plan/${planId}`, { headers: authHeaders() });
        currentDetail = result.data;
        populateJenisPengeluaranSelects(result.jenisPengeluaranOptions || []);
        document.getElementById("rdPlanPickerCard")?.classList.add("hidden");
        document.getElementById("rdDetailCard")?.classList.remove("hidden");
        renderDetail();
    } catch (err) {
        showAlert(err.message || "Gagal memuat realisasi dinas untuk plan ini.", "error");
    }
}

async function handleFileChange() {
    const fileInput = document.getElementById("rdFileInput");
    const fileName = document.getElementById("rdFileName");
    if (!fileInput || !fileName || !currentDetail) return;
    const file = fileInput.files?.[0];
    fileName.textContent = file?.name || "Belum ada file";
    if (!file) return;

    const formData = new FormData();
    formData.append("file", file);
    try {
        const result = await fetchJson(`/api/realisasi-dinas/${currentDetail.id}/bukti`, {
            method: "POST",
            headers: authHeaders(),
            body: formData,
        });
        currentDetail = result.data;
        showAlert(result.message || "Bukti berhasil diunggah.");
        renderDetail();
    } catch (err) {
        showAlert(err.message || "Gagal mengunggah bukti.", "error");
    }
}

async function addItem() {
    if (!currentDetail) return;
    const jenis = document.getElementById("rdItemJenis")?.value;
    const catatan = document.getElementById("rdItemCatatan")?.value || "";
    const nominal = document.getElementById("rdItemNominal")?.value || "0";

    try {
        const result = await fetchJson(`/api/realisasi-dinas/${currentDetail.id}/items`, {
            method: "POST",
            headers: { "Content-Type": "application/json", ...authHeaders() },
            body: JSON.stringify({ jenis_pengeluaran: jenis, catatan, nominal }),
        });
        currentDetail = result.data;
        document.getElementById("rdItemCatatan").value = "";
        document.getElementById("rdItemNominal").value = "0";
        renderItemsTable();
        showAlert(result.message || "Item berhasil ditambahkan.");
    } catch (err) {
        showAlert(err.message || "Gagal menambahkan item.", "error");
    }
}

async function deleteItem(itemId) {
    if (!window.confirm("Hapus item pengeluaran ini?")) return;
    try {
        const result = await fetchJson(`/api/realisasi-dinas/items/${itemId}`, {
            method: "DELETE",
            headers: authHeaders(),
        });
        currentDetail = result.data;
        renderItemsTable();
        showAlert(result.message || "Item berhasil dihapus.");
    } catch (err) {
        showAlert(err.message || "Gagal menghapus item.", "error");
    }
}

async function markSelesai() {
    if (!currentDetail) return;
    if (!window.confirm("Kunci Realisasi Dinas ini? Setelah dikunci, tidak bisa diubah lagi kecuali dibuka oleh admin.")) return;
    try {
        const result = await fetchJson(`/api/realisasi-dinas/${currentDetail.id}/selesai`, {
            method: "POST",
            headers: authHeaders(),
        });
        currentDetail = result.data;
        showAlert(result.message || "Realisasi Dinas berhasil dikunci.");
        renderDetail();
        await loadListing();
    } catch (err) {
        showAlert(err.message || "Gagal mengunci realisasi dinas.", "error");
    }
}

async function bukaKunci() {
    if (!currentDetail) return;
    if (!window.confirm("Buka kunci Realisasi Dinas ini?")) return;
    try {
        const result = await fetchJson(`/api/realisasi-dinas/${currentDetail.id}/buka-kunci`, {
            method: "POST",
            headers: authHeaders(),
        });
        currentDetail = result.data;
        showAlert(result.message || "Realisasi Dinas berhasil dibuka kembali.");
        renderDetail();
        await loadListing();
    } catch (err) {
        showAlert(err.message || "Gagal membuka kunci.", "error");
    }
}

// ── Listing / analisa (semua plan) ──

function renderListing() {
    const tbody = document.getElementById("rdTableBody");
    if (!tbody) return;

    if (!listItems.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada data realisasi dinas.</td></tr>';
        return;
    }

    tbody.innerHTML = listItems.map((item, idx) => {
        const statusBadge = item.isLocked
            ? '<span class="inline-flex rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">Selesai</span>'
            : '<span class="inline-flex rounded-full border border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-300">Draft</span>';

        return `
            <tr>
                <td class="px-4 py-3">${idx + 1}</td>
                <td class="px-4 py-3">${escapeHtml(item.cabang || "-")}<br><span class="text-xs text-slate-500">${escapeHtml(item.noSpt || "")}</span></td>
                <td class="px-4 py-3">${escapeHtml(formatTanggal(item.createdAt))}</td>
                <td class="px-4 py-3">${(item.personil || []).map(escapeHtml).join(", ") || "-"}</td>
                <td class="px-4 py-3 text-center">${item.items?.length || 0}</td>
                <td class="px-4 py-3 text-right font-semibold">${formatRupiah(item.totalNominal)}</td>
                <td class="px-4 py-3 text-center">${statusBadge}</td>
                <td class="px-4 py-3 text-right">
                    <a href="/akta/realisasi-dinas?plan_id=${item.planAuditId}" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition">Buka</a>
                </td>
            </tr>`;
    }).join("");
}

function updateStatCards() {
    const total = listItems.reduce((sum, r) => sum + (Number(r.totalNominal) || 0), 0);
    const selesaiCount = listItems.filter((r) => r.isLocked).length;
    document.getElementById("rdStatTotal").textContent = formatRupiah(total);
    document.getElementById("rdStatSelesai").textContent = selesaiCount.toLocaleString("id-ID");
    document.getElementById("rdStatPlan").textContent = listItems.length.toLocaleString("id-ID");
}

async function loadListing() {
    const params = new URLSearchParams();
    const jenis = document.getElementById("rdFilterJenis")?.value;
    const tahun = document.getElementById("rdFilterTahun")?.value;
    const bulan = document.getElementById("rdFilterBulan")?.value;
    if (jenis) params.set("jenis_pengeluaran", jenis);
    if (tahun) params.set("tahun", tahun);
    if (bulan) params.set("bulan", bulan);

    try {
        const result = await fetchJson(`/api/realisasi-dinas?${params.toString()}`, { headers: authHeaders() });
        listItems = result.data || [];
        populateJenisPengeluaranSelects(result.jenisPengeluaranOptions || []);
        renderListing();
        updateStatCards();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data realisasi dinas.", "error");
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    currentUser = getSession()?.user || null;
    if (!currentUser) {
        window.location.href = "/akta/login";
        return;
    }

    currentPlanId = new URLSearchParams(window.location.search).get("plan_id");
    populateBulanFilter();

    if (currentPlanId) {
        await Promise.all([loadPersonilOptions(), loadDetailForPlan(currentPlanId)]);
        document.getElementById("rdDetailCard")?.scrollIntoView({ behavior: "smooth", block: "start" });
    } else if (EDIT_ROLES.includes(currentUser.role)) {
        document.getElementById("rdPlanPickerCard")?.classList.remove("hidden");
        await loadPlanOptions();
    }

    document.getElementById("rdPlanStartBtn")?.addEventListener("click", () => {
        const planId = document.getElementById("rdPlanSelect")?.value;
        if (!planId) {
            showAlert("Pilih plan audit terlebih dahulu.", "error");
            return;
        }
        window.location.href = `/akta/realisasi-dinas?plan_id=${planId}`;
    });

    document.getElementById("rdFileInput")?.addEventListener("change", handleFileChange);
    document.getElementById("rdPersonilAddBtn")?.addEventListener("click", addPersonilFromInput);
    document.getElementById("rdPersonilInput")?.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            addPersonilFromInput();
        }
    });
    document.getElementById("rdItemNominalPlus")?.addEventListener("click", () => {
        const el = document.getElementById("rdItemNominal");
        el.value = (Number(el.value) || 0) + 1000;
    });
    document.getElementById("rdItemNominalMinus")?.addEventListener("click", () => {
        const el = document.getElementById("rdItemNominal");
        el.value = Math.max(0, (Number(el.value) || 0) - 1000);
    });
    document.getElementById("rdItemAddBtn")?.addEventListener("click", addItem);
    document.getElementById("rdSelesaiBtn")?.addEventListener("click", markSelesai);
    document.getElementById("rdBukaKunciBtn")?.addEventListener("click", bukaKunci);

    document.getElementById("rdFilterApplyBtn")?.addEventListener("click", loadListing);
    document.getElementById("rdFilterResetBtn")?.addEventListener("click", () => {
        document.getElementById("rdFilterJenis").value = "";
        document.getElementById("rdFilterTahun").value = "";
        document.getElementById("rdFilterBulan").value = "";
        loadListing();
    });

    await loadListing();
});
