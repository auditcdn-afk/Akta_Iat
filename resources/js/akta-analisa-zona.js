const SESSION_KEY = "akta_session";

function getSession() {
    try {
        return JSON.parse(sessionStorage.getItem(SESSION_KEY));
    } catch {
        return null;
    }
}

function authHeaders(extra = {}) {
    const session = getSession();
    const headers = { Accept: "application/json", ...extra };
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
        const err = new Error(body?.message || `Gagal memuat ${url} (status ${res.status})`);
        err.status = res.status;
        throw err;
    }
    return body;
}

function showAlert(message, type = "success") {
    const el = document.getElementById("azAlert");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden", "border-emerald-500/40", "bg-emerald-500/10", "text-emerald-300", "border-red-500/40", "bg-red-500/10", "text-red-300");
    el.classList.add(type === "error" ? "border-red-500/40" : "border-emerald-500/40", type === "error" ? "bg-red-500/10" : "bg-emerald-500/10", type === "error" ? "text-red-300" : "text-emerald-300");
    el.classList.remove("hidden");
    window.clearTimeout(showAlert._t);
    showAlert._t = window.setTimeout(() => el.classList.add("hidden"), 5000);
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    }[c]));
}

function fmtRp(v) {
    const n = Number(v) || 0;
    return "Rp " + Math.round(n).toLocaleString("id-ID");
}

function fmtDate(v) {
    if (!v) return "-";
    // Backend mengirim tanggal sebagai ISO datetime (mis. "2026-08-20T00:00:00.000Z")
    // — cukup ambil bagian tanggalnya saja, tidak perlu tampilkan jam.
    return String(v).slice(0, 10);
}

function skorBadgeClass(skor) {
    if (skor >= 70) return "bg-red-500/20 text-red-300";
    if (skor >= 40) return "bg-amber-500/20 text-amber-300";
    return "bg-emerald-500/20 text-emerald-300";
}

const SEVERITY_GAYA = {
    tinggi: { badge: "bg-red-500/20 text-red-300", garis: "border-l-red-500", label: "TINGGI" },
    sedang: { badge: "bg-amber-500/20 text-amber-300", garis: "border-l-amber-500", label: "SEDANG" },
    rendah: { badge: "bg-slate-500/20 text-slate-300", garis: "border-l-slate-500", label: "RENDAH" },
};

/**
 * Rincian temuan ditampilkan sebagai tabel kecil yang kolomnya mengikuti isi
 * item — tiap aturan punya bentuk rincian sendiri (piutang punya umur_hari,
 * kontrak punya harga_otr, dst), jadi kolomnya diturunkan dari datanya
 * daripada dihardcode per aturan.
 */
function renderDetailTemuan(items) {
    if (!Array.isArray(items) || items.length === 0) return "";
    const kolom = Object.keys(items[0]);
    const judulKolom = {
        kode_konsumen: "Kode Konsumen", no_bukti: "No. Bukti", tanggal: "Tanggal",
        tanggal_transaksi: "Tgl Transaksi", umur_hari: "Umur (hari)", nominal: "Nominal",
        harga_otr: "Harga OTR", dp: "DP", dp_ratio: "Rasio DP", kode_sales: "Sales",
        cara_bayar: "Cara Bayar", status_kredit: "Status", saldo_akhir_kas: "Saldo Akhir Kas",
        jumlah_baris: "Jml Baris",
    };
    const isUang = (k) => ["nominal", "harga_otr", "dp", "saldo_akhir_kas"].includes(k);

    return `
        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-700/60">
            <table class="w-full text-[11px]">
                <thead class="bg-slate-800/60">
                    <tr>${kolom.map(k => `<th class="px-2.5 py-1.5 text-left font-semibold uppercase text-slate-400">${escapeHtml(judulKolom[k] || k)}</th>`).join("")}</tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    ${items.map(it => `<tr>${kolom.map(k => {
                        let v = it[k];
                        if (isUang(k)) v = fmtRp(v);
                        else if (k === "dp_ratio") v = (Number(v) * 100).toFixed(1) + "%";
                        return `<td class="px-2.5 py-1.5 text-slate-300">${escapeHtml(v ?? "-")}</td>`;
                    }).join("")}</tr>`).join("")}
                </tbody>
            </table>
        </div>`;
}

async function azLoadTemuan() {
    const periode = document.getElementById("azPeriode")?.value || "";
    const url = periode
        ? `/api/analisa-zona/temuan?periode=${encodeURIComponent(periode)}`
        : "/api/analisa-zona/temuan";
    const res = await fetchJson(url, { headers: authHeaders() });
    const items = res.data || [];
    const perSeverity = res.meta?.per_severity || {};

    const ringkas = document.getElementById("azTemuanRingkas");
    ringkas.innerHTML = items.length === 0
        ? `<span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-300">Tidak ada temuan</span>`
        : ["tinggi", "sedang", "rendah"]
            .filter(s => perSeverity[s])
            .map(s => `<span class="rounded-full px-2.5 py-1 text-xs font-bold ${SEVERITY_GAYA[s].badge}">${perSeverity[s]} ${SEVERITY_GAYA[s].label}</span>`)
            .join("");

    const list = document.getElementById("azTemuanList");
    if (items.length === 0) {
        list.innerHTML = `<p class="px-5 py-8 text-center text-xs text-slate-400">
            Belum ada temuan untuk periode ini. Kalau data sudah diupload, klik "Hitung Ulang Skor" untuk menjalankan pemeriksaan.
        </p>`;
        return;
    }

    list.innerHTML = items.map(t => {
        const gaya = SEVERITY_GAYA[t.severity] || SEVERITY_GAYA.rendah;
        return `
        <div class="border-l-4 ${gaya.garis} px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="flex-1 min-w-[240px]">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold ${gaya.badge}">${gaya.label}</span>
                        <span class="text-xs font-semibold text-slate-100">${escapeHtml(t.unitUsahaCode)}</span>
                        ${t.tanggal ? `<span class="text-[11px] text-slate-500">${escapeHtml(t.tanggal)}</span>` : ""}
                    </div>
                    <p class="mt-1.5 text-sm font-semibold text-slate-200">${escapeHtml(t.judul)}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-400">
                        <span class="font-semibold text-blue-300">Tindakan:</span> ${escapeHtml(t.rekomendasi)}
                    </p>
                </div>
                ${t.nominal ? `<span class="text-sm font-bold text-slate-200 whitespace-nowrap">${fmtRp(t.nominal)}</span>` : ""}
            </div>
            ${renderDetailTemuan(t.detail?.items)}
        </div>`;
    }).join("");
}

let _azSelectedZona = null;
let _azSelectedJenis = "rkk";
let _azDrillDownPage = 1;

async function azLoadScores() {
    const periodeInput = document.getElementById("azPeriode");
    const periode = periodeInput?.value || "";
    const url = periode ? `/api/analisa-zona/scores?periode=${encodeURIComponent(periode)}` : "/api/analisa-zona/scores";
    const res = await fetchJson(url, { headers: authHeaders() });
    const scores = res.data || [];

    document.getElementById("azScoreCount").textContent = `${scores.length} Zona`;

    const tbody = document.getElementById("azScoreTableBody");
    if (scores.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-4 py-8 text-center text-slate-400 text-xs">Belum ada skor. Upload data lalu klik "Hitung Ulang Skor".</td></tr>`;
        return;
    }

    tbody.innerHTML = scores.map((s, i) => `
        <tr class="hover:bg-slate-800/40">
            <td class="px-3 py-2 text-slate-400">${i + 1}</td>
            <td class="px-3 py-2 text-slate-100 font-semibold">${escapeHtml(s.unitUsahaCode)}</td>
            <td class="px-3 py-2 text-right text-slate-300">${s.skorKasKecil.toFixed(1)}</td>
            <td class="px-3 py-2 text-right text-slate-300">${s.skorPembiayaan.toFixed(1)}</td>
            <td class="px-3 py-2 text-right text-slate-300">${s.skorPenjualanPiutang.toFixed(1)}</td>
            <td class="px-3 py-2 text-right text-slate-300">${s.skorAnomali.toFixed(1)}</td>
            <td class="px-3 py-2 text-right text-slate-300">${(s.skorPosisiKas ?? 0).toFixed(1)}</td>
            <td class="px-3 py-2 text-right">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${skorBadgeClass(s.skorTotal)}">${s.skorTotal.toFixed(1)}</span>
            </td>
            <td class="px-3 py-2 text-center">
                <button type="button" class="az-detail-btn rounded-lg border border-slate-600 px-2.5 py-1 text-xs text-slate-300 hover:bg-slate-800" data-zona="${escapeHtml(s.unitUsahaCode)}">
                    Lihat
                </button>
            </td>
        </tr>`).join("");

    tbody.querySelectorAll(".az-detail-btn").forEach(btn => {
        btn.addEventListener("click", () => azOpenDrillDown(btn.dataset.zona));
    });
}

async function azLoadUploads() {
    const res = await fetchJson("/api/analisa-zona/uploads", { headers: authHeaders() });
    const uploads = res.data || [];
    const tbody = document.getElementById("azUploadsTableBody");
    if (uploads.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400 text-xs">Belum ada riwayat upload.</td></tr>`;
        return;
    }
    tbody.innerHTML = uploads.map(u => `
        <tr class="hover:bg-slate-800/40">
            <td class="px-3 py-2 text-slate-300">${escapeHtml(u.source_filename)}</td>
            <td class="px-3 py-2 text-slate-400 uppercase">${escapeHtml(u.jenis)}</td>
            <td class="px-3 py-2 text-slate-300">${escapeHtml(u.unit_usaha_code)}</td>
            <td class="px-3 py-2 text-center text-slate-400">${fmtDate(u.tanggal)}</td>
            <td class="px-3 py-2 text-right text-slate-300">${u.row_count}</td>
            <td class="px-3 py-2 text-slate-400">${escapeHtml(u.uploaded_by || "-")}</td>
        </tr>`).join("");
}

function azDrillDownColumns(jenis) {
    switch (jenis) {
        case "rkk":
            return [
                ["tanggal", "Tanggal", fmtDate], ["no_voucher", "No. Voucher"], ["kode_akun", "Kode Akun"],
                ["nama_akun", "Nama Akun"], ["nominal", "Nominal", fmtRp], ["nama_supplier", "Supplier"], ["keterangan", "Keterangan"],
            ];
        case "acc-consumers":
            return [
                ["tanggal", "Tanggal", fmtDate], ["kode_konsumen", "Kode Konsumen"], ["nama", "Nama"],
                ["nik", "NIK"], ["no_hp", "No. HP"], ["kecamatan", "Kecamatan"], ["desa", "Desa"],
            ];
        case "acc-contracts":
            return [
                ["tanggal", "Tanggal", fmtDate], ["no_bukti", "No. Bukti"], ["kode_konsumen", "Kode Konsumen"],
                ["harga_otr", "Harga OTR", fmtRp], ["dp", "DP", fmtRp], ["status_kredit", "Status"], ["cara_bayar", "Cara Bayar"],
            ];
        case "acc-receivables":
            return [
                ["tanggal_laporan", "Tgl Laporan", fmtDate], ["kode_konsumen", "Kode Konsumen"], ["no_bukti", "No. Bukti"],
                ["tanggal_transaksi", "Tgl Transaksi", fmtDate], ["kode_sales", "Sales"], ["nominal", "Nominal Piutang", fmtRp],
            ];
        case "lpk":
            return [
                ["tanggal", "Tanggal", fmtDate], ["kode_konsumen", "Kode Konsumen"], ["nama_konsumen", "Nama"],
                ["kode_finance", "Finance"], ["no_bukti", "No. Bukti"], ["nominal", "Nominal", fmtRp], ["kode_transaksi", "Jenis"],
            ];
        case "posisi-kas":
            return [
                ["tanggal", "Tanggal", fmtDate], ["saldo_awal_kas", "Saldo Awal Kas", fmtRp], ["saldo_akhir_kas", "Saldo Akhir Kas", fmtRp],
                ["saldo_awal_bank", "Saldo Awal Bank", fmtRp], ["saldo_akhir_bank", "Saldo Akhir Bank", fmtRp],
            ];
        default:
            return [];
    }
}

async function azOpenDrillDown(zona, jenis = _azSelectedJenis, page = 1) {
    _azSelectedZona = zona;
    _azSelectedJenis = jenis;
    _azDrillDownPage = page;

    document.getElementById("azDrillDownCard").classList.remove("hidden");
    document.getElementById("azDrillDownZona").textContent = zona;

    document.querySelectorAll(".az-dd-tab").forEach(btn => {
        const active = btn.dataset.azJenis === jenis;
        btn.classList.toggle("bg-blue-600", active);
        btn.classList.toggle("text-white", active);
        btn.classList.toggle("text-slate-300", !active);
        btn.classList.toggle("hover:bg-slate-800", !active);
    });

    const cols = azDrillDownColumns(jenis);
    document.getElementById("azDrillDownHead").innerHTML = `<tr>${cols.map(([, label]) => `<th class="px-3 py-2 text-left font-semibold uppercase text-slate-400">${escapeHtml(label)}</th>`).join("")}</tr>`;

    const res = await fetchJson(`/api/analisa-zona/drill-down?unit_usaha_code=${encodeURIComponent(zona)}&jenis=${encodeURIComponent(jenis)}&page=${page}`, { headers: authHeaders() });
    const items = res.data || [];

    const tbody = document.getElementById("azDrillDownBody");
    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${cols.length}" class="px-4 py-8 text-center text-slate-400 text-xs">Tidak ada data.</td></tr>`;
    } else {
        tbody.innerHTML = items.map(it => `
            <tr class="hover:bg-slate-800/40">
                ${cols.map(([key, , fmt]) => `<td class="px-3 py-2 text-slate-300">${escapeHtml(fmt ? fmt(it[key]) : (it[key] ?? "-"))}</td>`).join("")}
            </tr>`).join("");
    }

    const paging = document.getElementById("azDrillDownPaging");
    const currentPage = res.current_page || 1;
    const lastPage = res.last_page || 1;
    const total = res.total ?? items.length;
    paging.innerHTML = `
        <span>${total} baris — halaman ${currentPage} dari ${lastPage}</span>
        <div class="flex items-center gap-2">
            <button type="button" id="azDdPrev" ${currentPage <= 1 ? "disabled" : ""} class="rounded-lg border border-slate-600 px-3 py-1 text-xs ${currentPage <= 1 ? "text-slate-600" : "text-slate-300 hover:bg-slate-800"}">‹ Sebelumnya</button>
            <button type="button" id="azDdNext" ${currentPage >= lastPage ? "disabled" : ""} class="rounded-lg border border-slate-600 px-3 py-1 text-xs ${currentPage >= lastPage ? "text-slate-600" : "text-slate-300 hover:bg-slate-800"}">Selanjutnya ›</button>
        </div>`;
    document.getElementById("azDdPrev")?.addEventListener("click", () => { if (currentPage > 1) azOpenDrillDown(zona, jenis, currentPage - 1); });
    document.getElementById("azDdNext")?.addEventListener("click", () => { if (currentPage < lastPage) azOpenDrillDown(zona, jenis, currentPage + 1); });

    document.getElementById("azDrillDownCard").scrollIntoView({ behavior: "smooth", block: "start" });
}

async function azHandleZipUpload(file) {
    const msg = document.getElementById("azUploadMsg");
    msg.classList.remove("hidden");
    msg.className = "text-sm font-medium text-blue-300";
    msg.textContent = "Mengupload & memproses...";

    const form = new FormData();
    form.append("file", file);

    try {
        const res = await fetchJson("/api/analisa-zona/import", {
            method: "POST",
            headers: authHeaders(),
            body: form,
        });
        msg.className = "text-sm font-medium text-emerald-300";
        msg.textContent = res.message || "Berhasil diproses.";
        showAlert(res.message || "Import selesai.", "success");
        await Promise.all([azLoadScores(), azLoadTemuan(), azLoadUploads()]);
    } catch (e) {
        msg.className = "text-sm font-medium text-red-300";
        msg.textContent = e.message || "Gagal upload.";
        showAlert(e.message || "Gagal upload.", "error");
    }
}

function initAnalisaZonaForm() {
    const periodeInput = document.getElementById("azPeriode");
    if (periodeInput && !periodeInput.value) {
        const now = new Date();
        periodeInput.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
    }
    periodeInput?.addEventListener("change", () => {
        Promise.all([azLoadScores(), azLoadTemuan()]).catch(err => showAlert(err.message, "error"));
    });

    document.getElementById("azRecomputeBtn")?.addEventListener("click", async () => {
        try {
            const res = await fetchJson("/api/analisa-zona/recompute", {
                method: "POST",
                headers: authHeaders({ "Content-Type": "application/json" }),
                body: JSON.stringify({ periode: periodeInput?.value || undefined }),
            });
            showAlert(res.message || "Skor dihitung ulang.", "success");
            await azLoadScores();
        } catch (e) {
            showAlert(e.message || "Gagal menghitung ulang skor.", "error");
        }
    });

    document.querySelectorAll(".az-dd-tab").forEach(btn => {
        btn.addEventListener("click", () => {
            if (_azSelectedZona) azOpenDrillDown(_azSelectedZona, btn.dataset.azJenis, 1);
        });
    });

    const fileInput = document.getElementById("azFileInput");
    const dropzone = document.getElementById("azDropzone");
    fileInput?.addEventListener("change", () => { if (fileInput.files[0]) azHandleZipUpload(fileInput.files[0]); fileInput.value = ""; });
    dropzone?.addEventListener("dragover", e => { e.preventDefault(); dropzone.classList.add("border-blue-400"); });
    dropzone?.addEventListener("dragleave", () => dropzone.classList.remove("border-blue-400"));
    dropzone?.addEventListener("drop", e => {
        e.preventDefault();
        dropzone.classList.remove("border-blue-400");
        const f = e.dataTransfer.files[0];
        if (f) azHandleZipUpload(f);
    });
}

document.addEventListener("DOMContentLoaded", async () => {
    const session = getSession();
    if (!session?.user?.analisaZonaAccess) {
        document.getElementById("azAccessDenied")?.classList.remove("hidden");
        return;
    }
    document.getElementById("azContent")?.classList.remove("hidden");

    initAnalisaZonaForm();
    try {
        await Promise.all([azLoadScores(), azLoadTemuan(), azLoadUploads()]);
    } catch (e) {
        if (e.status === 403) {
            document.getElementById("azContent")?.classList.add("hidden");
            document.getElementById("azAccessDenied")?.classList.remove("hidden");
        } else {
            showAlert(e.message || "Gagal memuat data.", "error");
        }
    }
});
