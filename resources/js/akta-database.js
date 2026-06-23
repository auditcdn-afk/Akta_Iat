// ── Helpers ──────────────────────────────────────────────────────
const SESSION_KEY = "akta_session";

function getSession() {
    try {
        return JSON.parse(sessionStorage.getItem(SESSION_KEY) || "null");
    } catch {
        return null;
    }
}

function authHeaders() {
    const s = getSession();
    return {
        Accept: "application/json",
        Authorization: `${s?.tokenType || "Bearer"} ${s?.token}`,
    };
}

function jsonHeaders() {
    return { ...authHeaders(), "Content-Type": "application/json" };
}

function escHtml(v) {
    return String(v ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function fmtRupiah(v) {
    const n = parseFloat(v);
    if (isNaN(n)) return "-";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(n);
}

async function fetchJson(url, opts = {}) {
    const res = await fetch(url, opts);
    const payload = await res.json().catch(() => ({}));
    if (!res.ok) {
        const firstErr = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : null;
        throw new Error(firstErr || payload.message || "Request gagal.");
    }
    return payload;
}

function showAlert(msg, type = "success") {
    const el = document.getElementById("dbAlert");
    if (!el) return;
    el.textContent = msg;
    el.className = "rounded-xl border px-4 py-3 text-sm";
    if (type === "error") {
        el.classList.add("border-red-500/30", "bg-red-500/10", "text-red-200");
    } else {
        el.classList.add(
            "border-emerald-500/30",
            "bg-emerald-500/10",
            "text-emerald-200",
        );
    }
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.add("hidden"), 6000);
}

// ── Tab Configuration ─────────────────────────────────────────────
const TABS = {
    "harga-smh": {
        label: "Harga SMH",
        fields: [
            { key: "kode_model", label: "Kode Model", type: "text", span: 1 },
            { key: "nama_smh", label: "Nama SMH", type: "text", required: true, span: 1 },
            { key: "harga", label: "Harga (Rp)", type: "number", required: true, span: 2 },
        ],
        renderRow(row, no, isAdmin) {
            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-500">${no}</td>
                <td class="px-4 py-3 text-sm font-mono text-slate-300">${escHtml(row.kodeModel)}</td>
                <td class="px-4 py-3 text-sm text-slate-100">${escHtml(row.namaSmh)}</td>
                <td class="px-4 py-3 text-sm font-semibold text-emerald-300">${fmtRupiah(row.harga)}</td>
                ${adminActions(row.id, isAdmin)}
            </tr>`;
        },
        getFormData(row) {
            return {
                kode_model: row?.kodeModel || "",
                nama_smh: row?.namaSmh || "",
                harga: row?.harga || "",
            };
        },
    },
    plafon: {
        label: "Plafon",
        fields: [
            { key: "kode", label: "Kode", type: "text", span: 1 },
            { key: "nama", label: "Nama", type: "text", required: true, span: 1 },
            { key: "nilai", label: "Nilai Plafon (Rp)", type: "number", required: true, span: 2 },
            { key: "keterangan", label: "Keterangan", type: "textarea", span: 2 },
        ],
        renderRow(row, no, isAdmin) {
            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-500">${no}</td>
                <td class="px-4 py-3 text-sm font-mono text-slate-300">${escHtml(row.kode)}</td>
                <td class="px-4 py-3 text-sm text-slate-100">${escHtml(row.nama)}</td>
                <td class="px-4 py-3 text-sm font-semibold text-emerald-300">${fmtRupiah(row.nilai)}</td>
                ${adminActions(row.id, isAdmin)}
            </tr>`;
        },
        getFormData(row) {
            return { kode: row?.kode || "", nama: row?.nama || "", nilai: row?.nilai || "", keterangan: row?.keterangan || "" };
        },
    },
    perlengkapan: {
        label: "Perlengkapan",
        fields: [
            { key: "tipe",  label: "Tipe",  type: "text", span: 1 },
            { key: "nosin", label: "NOSIN", type: "text", span: 1 },
            { key: "aceh",  label: "ACEH",  type: "textarea", span: 2 },
            { key: "riau",  label: "RIAU",  type: "textarea", span: 2 },
            { key: "kepri", label: "KEPRI", type: "textarea", span: 2 },
            { key: "type",  label: "Type",  type: "text", span: 1 },
        ],
        renderRow(row, no, isAdmin) {
            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-500">${no}</td>
                <td class="px-4 py-3 text-sm font-semibold text-slate-100">${escHtml(row.tipe)}</td>
                <td class="px-4 py-3 text-sm font-mono text-slate-300">${escHtml(row.nosin)}</td>
                <td class="px-4 py-3 text-xs text-slate-400 max-w-xs truncate" title="${escHtml(row.aceh)}">${escHtml(row.aceh)}</td>
                ${adminActions(row.id, isAdmin)}
            </tr>`;
        },
        getFormData(row) {
            return { tipe: row?.tipe || "", nosin: row?.nosin || "", aceh: row?.aceh || "", riau: row?.riau || "", kepri: row?.kepri || "", type: row?.type || "" };
        },
    },
    "unit-usaha": {
        label: "Unit Usaha",
        fields: [
            { key: "unit_usaha", label: "Unit Usaha", type: "text", required: true, span: 1 },
            { key: "wilayah", label: "Wilayah", type: "text", span: 1 },
            { key: "jenis", label: "Jenis", type: "text", span: 1 },
        ],
        renderRow(row, no, isAdmin) {
            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-500">${no}</td>
                <td class="px-4 py-3 text-sm text-slate-100">${escHtml(row.unitUsaha)}</td>
                <td class="px-4 py-3 text-sm text-slate-300">${escHtml(row.wilayah)}</td>
                <td class="px-4 py-3 text-sm text-slate-400">${escHtml(row.jenis)}</td>
                ${adminActions(row.id, isAdmin)}
            </tr>`;
        },
        getFormData(row) {
            return { unit_usaha: row?.unitUsaha || "", wilayah: row?.wilayah || "", jenis: row?.jenis || "" };
        },
    },
    grading: {
        label: "Database Grading",
        fields: [
            { key: "id_grading",        label: "ID Grading",         type: "text",   span: 1 },
            { key: "jenis",             label: "Jenis",               type: "text",   span: 1 },
            { key: "wilayah",           label: "Wilayah",             type: "text",   span: 1 },
            { key: "nama_pemeriksaan",  label: "Nama Pemeriksaan",    type: "textarea", span: 2 },
            { key: "hasil_pemeriksaan", label: "Hasil Pemeriksaan",   type: "textarea", span: 2 },
            { key: "nilai",             label: "Nilai",               type: "number", span: 1 },
            { key: "bknf",              label: "BKNF",                type: "text",   span: 1 },
            { key: "pknf",              label: "PKNF",                type: "number", span: 1 },
            { key: "bkf",              label: "BKF",                  type: "text",   span: 1 },
            { key: "pkf",              label: "PKF",                   type: "number", span: 1 },
            { key: "bnknf",            label: "BNKNF",                type: "text",   span: 1 },
            { key: "pnknf",            label: "PNKNF",                type: "number", span: 1 },
            { key: "bnkf",             label: "BNKF",                 type: "text",   span: 1 },
            { key: "pnkf",             label: "PNKF",                 type: "number", span: 1 },
        ],
        renderRow(row, no, isAdmin) {
            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-500">${no}</td>
                <td class="px-4 py-3 text-sm font-mono text-blue-300">${escHtml(row.idGrading)}</td>
                <td class="px-4 py-3 text-sm text-slate-300">${escHtml(row.jenis)} <span class="text-xs text-slate-500">/ ${escHtml(row.wilayah)}</span></td>
                <td class="px-4 py-3 text-xs text-slate-400 max-w-xs">
                    <div class="truncate" title="${escHtml(row.namaPemeriksaan)}">${escHtml(row.namaPemeriksaan)}</div>
                    <div class="mt-0.5 flex gap-2 text-slate-500">
                        <span>Nilai: <b class="text-slate-300">${row.nilai ?? "-"}</b></span>
                        <span>BKNF: <b class="text-green-400">${escHtml(row.bknf)}</b></span>
                        <span>BKF: <b class="text-blue-400">${escHtml(row.bkf)}</b></span>
                    </div>
                </td>
                ${adminActions(row.id, isAdmin)}
            </tr>`;
        },
        getFormData(row) {
            return {
                id_grading: row?.idGrading || "", jenis: row?.jenis || "", wilayah: row?.wilayah || "",
                nama_pemeriksaan: row?.namaPemeriksaan || "", hasil_pemeriksaan: row?.hasilPemeriksaan || "",
                nilai: row?.nilai ?? "", bknf: row?.bknf || "", pknf: row?.pknf ?? "",
                bkf: row?.bkf || "", pkf: row?.pkf ?? "", bnknf: row?.bnknf || "",
                pnknf: row?.pnknf ?? "", bnkf: row?.bnkf || "", pnkf: row?.pnkf ?? "",
            };
        },
    },
    mt: {
        label: "Database MT",
        fields: [
            { key: "nomor",          label: "No.",               type: "text",     span: 1 },
            { key: "nama_singkat",   label: "Nama Singkat",      type: "text",     span: 1 },
            { key: "nama_peralatan", label: "Nama Peralatan (IND)", type: "textarea", span: 2 },
            { key: "kode_peralatan", label: "Kode Peralatan",    type: "text",     span: 1 },
            { key: "jenis",          label: "Jenis (MT FI / MT Lama / MT Baru)", type: "text", span: 1 },
        ],
        renderRow(row, no, isAdmin) {
            const jenisBadge = row.jenis
                ? `<span class="ml-1 inline-flex rounded-full border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-xs font-semibold text-blue-300">${escHtml(row.jenis)}</span>`
                : "";
            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-500">${no}</td>
                <td class="px-4 py-3 text-sm font-semibold text-slate-100">
                    ${escHtml(row.namaSingkat)}${jenisBadge}
                </td>
                <td class="px-4 py-3 text-xs text-slate-400 max-w-xs truncate" title="${escHtml(row.namaPeralatan)}">${escHtml(row.namaPeralatan)}</td>
                <td class="px-4 py-3 text-sm font-mono text-slate-300">${escHtml(row.kodePeralatan)}</td>
                ${adminActions(row.id, isAdmin)}
            </tr>`;
        },
        getFormData(row) {
            return { nomor: row?.nomor || "", nama_singkat: row?.namaSingkat || "", nama_peralatan: row?.namaPeralatan || "", kode_peralatan: row?.kodePeralatan || "", jenis: row?.jenis || "" };
        },
    },
    het: {
        label: "Database HET",
        fields: [
            { key: "kode", label: "Kode", type: "text", span: 1 },
            { key: "nama", label: "Nama Produk", type: "text", required: true, span: 1 },
            { key: "harga_het", label: "Harga HET (Rp)", type: "number", required: true, span: 1 },
            { key: "satuan", label: "Satuan", type: "text", span: 1 },
            { key: "keterangan", label: "Keterangan", type: "textarea", span: 2 },
        ],
        renderRow(row, no, isAdmin) {
            return `
            <tr class="hover:bg-slate-950/50">
                <td class="px-4 py-3 text-sm text-slate-500">${no}</td>
                <td class="px-4 py-3 text-sm font-mono text-slate-300">${escHtml(row.kode)}</td>
                <td class="px-4 py-3 text-sm text-slate-100">${escHtml(row.nama)}</td>
                <td class="px-4 py-3 text-sm font-semibold text-emerald-300">${fmtRupiah(row.hargaHet)}${row.satuan ? ` <span class="text-slate-500 text-xs font-normal">/${escHtml(row.satuan)}</span>` : ""}</td>
                ${adminActions(row.id, isAdmin)}
            </tr>`;
        },
        getFormData(row) {
            return { kode: row?.kode || "", nama: row?.nama || "", harga_het: row?.hargaHet ?? "", satuan: row?.satuan || "", keterangan: row?.keterangan || "" };
        },
    },
};

function adminActions(id, isAdmin) {
    if (!isAdmin) return '<td class="admin-col hidden"></td>';
    return `
    <td class="admin-col px-4 py-3 text-right hidden">
        <button type="button" class="edit-row rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${id}">Edit</button>
        <button type="button" class="delete-row ml-2 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${id}">Hapus</button>
    </td>`;
}

// ── State ─────────────────────────────────────────────────────────
let currentUser = null;
let activeTab = "harga-smh";
const tabData = {};
const tabPage = {};
const tabSearchTimer = {};

Object.keys(TABS).forEach((t) => {
    tabData[t] = [];
    tabPage[t] = 1;
});

function isAdmin() {
    return currentUser?.role === "admin";
}

// ── Tab Rendering ─────────────────────────────────────────────────
function activateTab(type) {
    activeTab = type;

    document.querySelectorAll(".db-tab-btn").forEach((btn) => {
        const active = btn.dataset.tab === type;
        btn.className = `db-tab-btn flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition-all ${
            active
                ? "bg-blue-600 text-white shadow-lg"
                : "text-slate-400 hover:bg-slate-800 hover:text-slate-200"
        }`;
    });

    document.querySelectorAll(".db-panel").forEach((panel) => {
        panel.classList.add("hidden");
    });
    document.getElementById(`tab-${type}`)?.classList.remove("hidden");

    if (!tabData[type].length) {
        loadTab(type);
    }
}

// ── Data Loading ──────────────────────────────────────────────────
async function loadTab(type, page = 1) {
    tabPage[type] = page;
    const q = document.getElementById(`search-${type}`)?.value || "";
    const params = new URLSearchParams({ page });
    if (q) params.set("q", q);

    const tbody = document.getElementById(`tbody-${type}`);
    if (tbody && page === 1) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Memuat data...</td></tr>`;
    }

    try {
        const payload = await fetchJson(`/api/database/${type}?${params}`, {
            headers: authHeaders(),
        });

        if (page === 1) tabData[type] = payload.data || [];
        else tabData[type] = [...tabData[type], ...(payload.data || [])];

        renderTab(type, payload);
    } catch (err) {
        showAlert(err.message || "Gagal memuat data.", "error");
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-red-400">${escHtml(err.message)}</td></tr>`;
        }
    }
}

function renderTab(type, payload) {
    const cfg = TABS[type];
    const tbody = document.getElementById(`tbody-${type}`);
    const countEl = document.getElementById(`count-${type}`);
    const pagEl = document.getElementById(`pagination-${type}`);

    if (!tbody || !cfg) return;

    const rows = payload.data || [];
    const total = payload.total || 0;
    const currentPage = payload.currentPage || 1;
    const lastPage = payload.lastPage || 1;
    const perPage = payload.perPage || 100;
    const admin = isAdmin();

    if (countEl) countEl.textContent = `${total.toLocaleString("id-ID")} data`;

    // Show/hide admin columns
    document.querySelectorAll(`#tab-${type} .admin-col`).forEach((el) => {
        el.classList.toggle("hidden", !admin);
    });

    if (rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada data.</td></tr>`;
    } else {
        const offset = (currentPage - 1) * perPage;
        tbody.innerHTML = rows
            .map((row, i) => cfg.renderRow(row, offset + i + 1, admin))
            .join("");
    }

    // Pagination
    if (pagEl && lastPage > 1) {
        pagEl.classList.remove("hidden");
        pagEl.classList.add("flex");
        const infoEl = document.getElementById(`pag-info-${type}`);
        if (infoEl) infoEl.textContent = `Halaman ${currentPage} dari ${lastPage} (${total} total)`;
        const prevBtn = document.getElementById(`pag-prev-${type}`);
        const nextBtn = document.getElementById(`pag-next-${type}`);
        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= lastPage;
    } else if (pagEl) {
        pagEl.classList.add("hidden");
        pagEl.classList.remove("flex");
    }
}

// ── Admin UI Setup ────────────────────────────────────────────────
function applyAdminUI() {
    const admin = isAdmin();

    Object.keys(TABS).forEach((type) => {
        const adminLabel = document.getElementById(`admin-label-${type}`);
        if (adminLabel) {
            adminLabel.textContent = admin ? "Admin: dapat import & hapus data" : "Hanya ADM yang dapat mengimport";
        }

        const importZone = document.getElementById(`import-zone-${type}`);
        if (importZone) importZone.classList.toggle("hidden", !admin);

        document.getElementById(`hapus-btn-${type}`)?.classList.toggle("hidden", !admin);
        document.getElementById(`import-btn-${type}`)?.classList.toggle("hidden", !admin);
        document.getElementById(`tambah-btn-${type}`)?.classList.toggle("hidden", !admin);
    });
}

// ── Modal ─────────────────────────────────────────────────────────
function buildFormFields(type) {
    const cfg = TABS[type];
    const container = document.getElementById("dbFormFields");
    if (!container || !cfg) return;

    container.innerHTML = cfg.fields
        .map((f) => {
            const span = f.span === 2 ? "sm:col-span-2" : "";
            const req = f.required ? "required" : "";
            const inputClass =
                "w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-blue-500";
            let input = "";
            if (f.type === "textarea") {
                input = `<textarea id="ff-${f.key}" name="${f.key}" rows="2" ${req} class="${inputClass}"></textarea>`;
            } else {
                input = `<input type="${f.type}" id="ff-${f.key}" name="${f.key}" ${req} class="${inputClass}">`;
            }
            return `<div class="${span}">
                <label class="mb-1 block text-sm font-medium text-slate-300">${f.label}${f.required ? ' <span class="text-red-400">*</span>' : ""}</label>
                ${input}
            </div>`;
        })
        .join("");
}

function openModal(type, row = null) {
    const cfg = TABS[type];
    document.getElementById("dbFormType").value = type;
    document.getElementById("dbFormId").value = row?.id || "";
    document.getElementById("dbModalTitle").textContent = row ? "Edit Data" : "Tambah Data";
    document.getElementById("dbModalSub").textContent = cfg?.label || "";

    buildFormFields(type);

    if (row) {
        const data = cfg.getFormData(row);
        cfg.fields.forEach((f) => {
            const el = document.getElementById(`ff-${f.key}`);
            if (el) el.value = data[f.key] ?? "";
        });
    }

    const modal = document.getElementById("dbModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeModal() {
    const modal = document.getElementById("dbModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function submitForm(e) {
    e.preventDefault();
    const type = document.getElementById("dbFormType").value;
    const id = document.getElementById("dbFormId").value;
    const cfg = TABS[type];
    if (!cfg) return;

    const body = {};
    cfg.fields.forEach((f) => {
        const el = document.getElementById(`ff-${f.key}`);
        body[f.key] = el ? el.value.trim() : "";
    });

    try {
        const url = id ? `/api/database/${type}/${id}` : `/api/database/${type}`;
        const method = id ? "PUT" : "POST";
        const payload = await fetchJson(url, {
            method,
            headers: jsonHeaders(),
            body: JSON.stringify(body),
        });
        closeModal();
        showAlert(payload.message || "Data berhasil disimpan.");
        await loadTab(type, 1);
    } catch (err) {
        showAlert(err.message || "Gagal menyimpan data.", "error");
    }
}

async function deleteRow(type, id) {
    if (!confirm("Hapus data ini?")) return;
    try {
        const payload = await fetchJson(`/api/database/${type}/${id}`, {
            method: "DELETE",
            headers: authHeaders(),
        });
        showAlert(payload.message || "Data berhasil dihapus.");
        await loadTab(type, tabPage[type]);
    } catch (err) {
        showAlert(err.message || "Gagal menghapus data.", "error");
    }
}

async function truncateData(type) {
    const label = TABS[type]?.label || type;
    if (!confirm(`Hapus SEMUA data ${label}? Tindakan ini tidak dapat dibatalkan.`)) return;
    try {
        const payload = await fetchJson(`/api/database/${type}/truncate`, {
            method: "DELETE",
            headers: authHeaders(),
        });
        showAlert(payload.message || "Data berhasil dihapus.");
        tabData[type] = [];
        await loadTab(type, 1);
    } catch (err) {
        showAlert(err.message || "Gagal menghapus data.", "error");
    }
}

// ── File Import ───────────────────────────────────────────────────
async function importFile(type, file) {
    const overlay = document.getElementById("uploadOverlay");
    if (overlay) {
        overlay.classList.remove("hidden");
        overlay.classList.add("flex");
    }

    try {
        const fd = new FormData();
        fd.append("file", file);

        // For MT: include selected jenis
        if (type === "mt") {
            const jenisSel = document.getElementById("mt-jenis-select");
            const jenis = jenisSel ? jenisSel.value : "";
            if (!jenis) {
                showAlert("Pilih Kategori MT (MT FI / MT Lama / MT Baru) sebelum import.", "error");
                if (overlay) { overlay.classList.add("hidden"); overlay.classList.remove("flex"); }
                return;
            }
            fd.append("mt_jenis", jenis);
        }

        const res = await fetch(`/api/database/${type}/import`, {
            method: "POST",
            headers: authHeaders(),
            body: fd,
        });

        const payload = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(payload.message || "Import gagal.");

        showAlert(payload.message || "Import berhasil.");
        tabData[type] = [];
        await loadTab(type, 1);
    } catch (err) {
        showAlert(err.message || "Gagal mengimport file.", "error");
    } finally {
        if (overlay) {
            overlay.classList.add("hidden");
            overlay.classList.remove("flex");
        }
    }
}

function setupDropzone(type) {
    const zone = document.getElementById(`dropzone-${type}`);
    const input = document.getElementById(`fileInput-${type}`);
    if (!zone || !input) return;

    zone.addEventListener("click", () => input.click());

    zone.addEventListener("dragover", (e) => {
        e.preventDefault();
        zone.classList.add("border-blue-500", "bg-blue-500/5");
    });

    zone.addEventListener("dragleave", () => {
        zone.classList.remove("border-blue-500", "bg-blue-500/5");
    });

    zone.addEventListener("drop", (e) => {
        e.preventDefault();
        zone.classList.remove("border-blue-500", "bg-blue-500/5");
        const file = e.dataTransfer.files[0];
        if (file) importFile(type, file);
    });

    input.addEventListener("change", () => {
        const file = input.files[0];
        if (file) {
            importFile(type, file);
            input.value = "";
        }
    });

    // "Import Excel" button triggers file input
    const importBtn = document.getElementById(`import-btn-${type}`);
    if (importBtn) {
        importBtn.addEventListener("click", () => input.click());
    }
}

// ── Event Setup ───────────────────────────────────────────────────
function setupEvents() {
    // Tab buttons
    document.querySelectorAll(".db-tab-btn").forEach((btn) => {
        btn.addEventListener("click", () => activateTab(btn.dataset.tab));
    });

    // Modal close
    document.getElementById("closeDbModal")?.addEventListener("click", closeModal);
    document.getElementById("cancelDbModal")?.addEventListener("click", closeModal);
    document.getElementById("dbModal")?.addEventListener("click", (e) => {
        if (e.target === e.currentTarget) closeModal();
    });

    // Form submit
    document.getElementById("dbForm")?.addEventListener("submit", async (e) => {
        try {
            await submitForm(e);
        } catch (err) {
            showAlert(err.message || "Gagal menyimpan.", "error");
        }
    });

    // Per-tab events
    Object.keys(TABS).forEach((type) => {
        // Search
        document.getElementById(`search-${type}`)?.addEventListener("input", () => {
            clearTimeout(tabSearchTimer[type]);
            tabSearchTimer[type] = setTimeout(() => loadTab(type, 1), 350);
        });

        // Hapus Data (truncate)
        document.getElementById(`hapus-btn-${type}`)?.addEventListener("click", () => truncateData(type));

        // Tambah
        document.getElementById(`tambah-btn-${type}`)?.addEventListener("click", () => openModal(type));

        // Table row actions (delegated)
        document.getElementById(`tbody-${type}`)?.addEventListener("click", async (e) => {
            const editBtn = e.target.closest(".edit-row");
            const delBtn = e.target.closest(".delete-row");

            if (editBtn) {
                const id = Number(editBtn.dataset.id);
                const row = tabData[type]?.find((r) => r.id === id);
                openModal(type, row);
            }
            if (delBtn) {
                try {
                    await deleteRow(type, delBtn.dataset.id);
                } catch (err) {
                    showAlert(err.message || "Gagal menghapus.", "error");
                }
            }
        });

        // Pagination
        document.getElementById(`pag-prev-${type}`)?.addEventListener("click", () => {
            if (tabPage[type] > 1) loadTab(type, tabPage[type] - 1);
        });
        document.getElementById(`pag-next-${type}`)?.addEventListener("click", () => {
            loadTab(type, tabPage[type] + 1);
        });

        // Dropzone
        setupDropzone(type);
    });
}

// ── Boot ──────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", async () => {
    setupEvents();

    try {
        const me = await fetchJson("/api/auth/me", { headers: authHeaders() });
        currentUser = me.user;
    } catch {
        // proceed without admin features
    }

    applyAdminUI();
    activateTab("harga-smh");
});
