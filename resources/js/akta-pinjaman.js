import { cachedUser } from "./akta-session.js";
import { idDariUrl, sorotItem } from "./akta-sorot.js";

const SESSION_KEY = "akta_session";

let currentUser = null;
let rows = [];
let bolehLihatSemua = false;
let tahapSaya = null;
// Urutan tahap tiap jenis, dikirim server (lihat PinjamanCabangController::daftar).
let alur = { BPK: [], BPB: [] };

const STATUS_LABEL = {
    pending_koordinator: "Menunggu Koordinator",
    pending_manajer: "Menunggu Manajer Audit",
    pending_coo: "Menunggu COO",
    pending_unit: "Menunggu Unit Usaha",
    pending_bpk: "Menunggu Role BPK",
    approved: "Disetujui",
    rejected: "Ditolak",
};

const STATUS_BADGE = {
    approved: "border-emerald-500/20 bg-emerald-500/10 text-emerald-300",
    rejected: "border-red-500/20 bg-red-500/10 text-red-300",
};
const BADGE_MENUNGGU = "border-amber-500/20 bg-amber-500/10 text-amber-300";

const AKSI_LABEL = {
    submit: "Diajukan",
    resubmit: "Diperbaiki & diajukan ulang",
    approve: "Disetujui",
    reject: "Ditolak",
    admin_reset: "Koreksi admin",
};

function getSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function authHeaders(extra = {}) {
    const session = getSession();
    return {
        Accept: "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
        ...extra,
    };
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, { ...options, headers: { ...authHeaders(), ...(options.headers || {}) } });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.message || "Permintaan gagal.");
    }
    return payload;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function rupiah(n) {
    try {
        return Number(n || 0).toLocaleString("id-ID");
    } catch {
        return String(Number(n || 0));
    }
}

function showAlert(message, type = "success") {
    const el = document.getElementById("pinjamanAlert");
    if (!el) return;
    el.textContent = message;
    el.className = "rounded-xl border px-4 py-3 text-sm " + (type === "error"
        ? "border-red-500/30 bg-red-500/10 text-red-200"
        : "border-emerald-500/30 bg-emerald-500/10 text-emerald-200");
    window.scrollTo({ top: 0, behavior: "smooth" });
    setTimeout(() => el.classList.add("hidden"), 6000);
}

function cabangDari(p) {
    const c = Array.isArray(p.cabangRealisasi) ? p.cabangRealisasi.join(", ") : String(p.cabangRealisasi ?? "");
    return c || p.plan?.cabang || "-";
}

/** Baris yang lolos kotak cari — filter lain sudah dikerjakan server. */
function terlihat() {
    const q = (document.getElementById("pinjamanCari")?.value || "").trim().toLowerCase();
    if (!q) return rows;

    return rows.filter((p) => [
        cabangDari(p), p.noSpd, p.createdBy, p.plan?.noSpt, p.jenis, p.catatan,
    ].some((v) => String(v ?? "").toLowerCase().includes(q)));
}

function badgeStatus(status) {
    const cls = STATUS_BADGE[status] || BADGE_MENUNGGU;
    const label = STATUS_LABEL[status] || String(status || "-").replace(/_/g, " ");
    return `<span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ${cls}">${escapeHtml(label)}</span>`;
}

function renderRingkasan(daftar) {
    const el = document.getElementById("pinjamanRingkas");
    if (!el) return;

    const total = daftar.reduce((a, p) => a + Number(p.nominal || 0), 0);
    const menunggu = daftar.filter((p) => String(p.status).startsWith("pending_"));
    const disetujui = daftar.filter((p) => p.status === "approved");
    const ditolak = daftar.filter((p) => p.status === "rejected");

    const kartu = (judul, isi, sub, warna) => `
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wide text-slate-500">${escapeHtml(judul)}</div>
            <div class="mt-1 text-xl font-bold ${warna}">${isi}</div>
            <div class="mt-0.5 text-xs text-slate-500">${escapeHtml(sub)}</div>
        </div>`;

    el.innerHTML = [
        kartu("Total Pengajuan", String(daftar.length), `Rp ${rupiah(total)}`, "text-slate-100"),
        kartu("Masih Berjalan", String(menunggu.length), `Rp ${rupiah(menunggu.reduce((a, p) => a + Number(p.nominal || 0), 0))}`, "text-amber-300"),
        kartu("Disetujui", String(disetujui.length), `Rp ${rupiah(disetujui.reduce((a, p) => a + Number(p.nominal || 0), 0))}`, "text-emerald-300"),
        kartu("Ditolak", String(ditolak.length), `Rp ${rupiah(ditolak.reduce((a, p) => a + Number(p.nominal || 0), 0))}`, "text-red-300"),
    ].join("");
}

/**
 * Kemajuan sebuah pengajuan menyusuri alur persetujuannya.
 *
 * Badge status hanya menyebut tahap SEKARANG ("Menunggu COO"). Yang tidak
 * terbaca dari situ: sudah lewat berapa tahap, dan tinggal berapa lagi. Itu
 * yang membedakan pengajuan yang baru diajukan kemarin dengan yang sudah
 * ditunggu berminggu-minggu dan tinggal satu langkah.
 */
function kemajuan(p) {
    // "approved" bukan tahap yang perlu disetujui siapa-siapa — itu garis
    // akhirnya, jadi tidak ikut dihitung.
    const urutan = (alur[p.jenis] || []).filter((t) => t !== "approved");
    const total = urutan.length;

    if (!total || p.status === "rejected") return null;
    if (p.status === "approved") return { urutan, lewat: total, total, selesai: true };

    const idx = urutan.indexOf(p.status);
    if (idx === -1) return null;

    // Yang dihitung adalah tahap yang SUDAH disetujui, bukan nomor tahap yang
    // sedang berjalan. Kalau yang dipakai nomor tahap, pengajuan BPB yang masih
    // menunggu persetujuan terakhir tampil "3/3" — persis sama dengan yang
    // sudah tuntas, padahal justru belum ada yang boleh mencairkannya.
    return { urutan, lewat: idx, total, selesai: false };
}

function titikKemajuan(p) {
    const k = kemajuan(p);
    if (!k) return "";

    const titik = k.urutan.map((tahap, i) => {
        const warna = i < k.lewat ? "bg-emerald-500" : (!k.selesai && i === k.lewat ? "bg-amber-400" : "bg-slate-700");
        return `<span class="inline-block h-1.5 w-1.5 rounded-full ${warna}" title="${escapeHtml(STATUS_LABEL[tahap] || tahap)}"></span>`;
    }).join("");

    const judul = k.selesai
        ? `Seluruh ${k.total} tahap sudah disetujui`
        : `${k.lewat} dari ${k.total} tahap sudah disetujui — sekarang ${STATUS_LABEL[p.status] || p.status}`;

    return `<div class="mt-1 flex items-center gap-1" title="${escapeHtml(judul)}">
        ${titik}<span class="ml-1 text-[11px] ${k.selesai ? "text-emerald-400" : "text-slate-500"}">${k.lewat}/${k.total}</span>
    </div>`;
}

function kartuGiliran(p) {
    return `
        <div class="rounded-xl border border-slate-700 bg-slate-900 p-3" data-sorot-id="giliran-${p.id}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="font-semibold text-slate-100">Rp ${rupiah(p.nominal)}</span>
                    <div class="mt-0.5 truncate text-xs text-slate-400">${escapeHtml(cabangDari(p))}${p.noSpd ? " · No SPD " + escapeHtml(p.noSpd) : ""}</div>
                    <div class="text-xs text-slate-500">Diajukan ${escapeHtml(p.createdBy || "-")} · ${escapeHtml(p.createdAt || "")}</div>
                    ${titikKemajuan(p)}
                </div>
                <a href="/akta/pinjaman/${p.id}/memo" target="_blank" rel="noopener"
                    class="shrink-0 text-xs text-blue-400 hover:underline">🖨️ Memo</a>
            </div>
            <div class="mt-3 flex gap-2">
                <button type="button" class="pinjaman-tolak flex-1 rounded-lg border border-red-500/40 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${p.id}">Tolak</button>
                <button type="button" class="pinjaman-setuju flex-1 rounded-lg bg-emerald-600 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500" data-id="${p.id}">Setujui</button>
            </div>
        </div>`;
}

function renderGiliran(daftar) {
    const box = document.getElementById("pinjamanGiliranBox");
    if (!box) return;

    const giliran = daftar.filter((p) => p.bisaDiproses);

    if (!giliran.length) {
        box.classList.add("hidden");
        return;
    }

    box.classList.remove("hidden");
    document.getElementById("pinjamanGiliranJumlah").textContent = String(giliran.length);
    document.getElementById("pinjamanGiliranInfo").textContent =
        `${giliran.length} pengajuan menunggu tindakan Anda, senilai Rp ${rupiah(giliran.reduce((a, p) => a + Number(p.nominal || 0), 0))}.`;

    for (const [jenis, sufiks] of [["BPB", "Bpb"], ["BPK", "Bpk"]]) {
        const punya = giliran.filter((p) => p.jenis === jenis);
        const kolom = document.getElementById("pinjamanKolom" + sufiks);
        const list = document.getElementById("pinjamanGiliranList" + sufiks);
        const jumlah = document.getElementById("pinjamanGiliranJumlah" + sufiks);

        if (jumlah) jumlah.textContent = String(punya.length);
        // Kolomnya tetap ada walau kosong, supaya BPB selalu di kiri dan BPK
        // selalu di kanan — kalau salah satunya dihilangkan, yang tersisa
        // melompat ke kiri dan letaknya jadi berubah-ubah.
        if (kolom) kolom.classList.toggle("opacity-50", punya.length === 0);
        if (list) {
            list.innerHTML = punya.length
                ? punya.map(kartuGiliran).join("")
                : `<p class="rounded-xl border border-dashed border-slate-700 px-3 py-4 text-center text-xs text-slate-500">
                       Tidak ada ${jenis} yang menunggu Anda.
                   </p>`;
        }
    }
}

function barisTabel(p) {
    return `
        <tr class="hover:bg-slate-950/50" data-sorot-id="${p.id}">
            <td class="px-4 py-4">
                <div class="font-bold ${p.jenis === "BPK" ? "text-blue-300" : "text-purple-300"}">${escapeHtml(p.jenis || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(p.plan?.noSpt || "-")}</div>
            </td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(cabangDari(p))}</td>
            <td class="px-4 py-4 text-sm text-slate-300">${escapeHtml(p.noSpd || "-")}</td>
            <td class="px-4 py-4 text-right text-sm font-semibold text-slate-100">Rp ${rupiah(p.nominal)}</td>
            <td class="px-4 py-4 text-sm text-slate-300">
                <div>${escapeHtml(p.createdBy || "-")}</div>
                <div class="text-xs text-slate-500">${escapeHtml(p.createdAt || "")}</div>
            </td>
            <td class="px-4 py-4">${badgeStatus(p.status)}${titikKemajuan(p)}</td>
            <td class="px-4 py-4 text-right">
                <div class="flex flex-wrap justify-end gap-1.5">
                    <button type="button" class="pinjaman-jejak rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-id="${p.id}">Riwayat</button>
                    <a href="/akta/pinjaman/${p.id}/memo" target="_blank" rel="noopener"
                        class="rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10">🖨️ Memo</a>
                    ${p.bisaDiproses ? `
                    <button type="button" class="pinjaman-tolak rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10" data-id="${p.id}">Tolak</button>
                    <button type="button" class="pinjaman-setuju rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500" data-id="${p.id}">Setujui</button>` : ""}
                </div>
            </td>
        </tr>`;
}

function grupTabel(judul, keterangan, warna, daftar) {
    const total = daftar.reduce((a, p) => a + Number(p.nominal || 0), 0);

    return `
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 px-4 py-3">
                <div>
                    <h3 class="font-bold ${warna}">${escapeHtml(judul)}</h3>
                    <p class="text-xs text-slate-500">${escapeHtml(keterangan)}</p>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold text-slate-100">${daftar.length} pengajuan</div>
                    <div class="text-xs text-slate-500">Rp ${rupiah(total)}</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Jenis / Plan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Cabang Realisasi</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">No SPD</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Nominal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Pengaju</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">${daftar.map(barisTabel).join("")}</tbody>
                </table>
            </div>
        </div>`;
}

function renderTabel() {
    const wadah = document.getElementById("pinjamanGrup");
    if (!wadah) return;

    const daftar = terlihat();
    renderRingkasan(daftar);
    renderGiliran(daftar);

    if (!daftar.length) {
        wadah.innerHTML = `<p class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-6 text-center text-sm text-slate-400">
            ${rows.length ? "Tidak ada pengajuan yang cocok dengan pencarian/filter." : "Belum ada pengajuan pinjaman."}
        </p>`;
        return;
    }

    // Tiga keadaan yang memerlukan perhatian berbeda: yang masih berjalan perlu
    // didorong, yang tuntas tinggal arsip, yang ditolak menunggu pengajunya
    // memperbaiki. Ditolak sengaja TIDAK digabung ke "selesai" — birokrasinya
    // justru belum tuntas dan pengajuannya masih bisa diajukan ulang.
    const proses  = daftar.filter((p) => String(p.status).startsWith("pending_"));
    const selesai = daftar.filter((p) => p.status === "approved");
    const ditolak = daftar.filter((p) => p.status === "rejected");

    wadah.innerHTML = [
        proses.length  ? grupTabel("Sedang Proses Approval", "Masih berjalan di alur birokrasi — titik hijau menandai tahap yang sudah dilewati.", "text-amber-300", proses) : "",
        selesai.length ? grupTabel("Sudah Selesai Semua Birokrasi", "Disetujui sampai tahap terakhir; tidak ada lagi yang perlu dikerjakan.", "text-emerald-300", selesai) : "",
        ditolak.length ? grupTabel("Ditolak", "Dikembalikan ke pengaju untuk diperbaiki lalu diajukan ulang dari tahap pertama.", "text-red-300", ditolak) : "",
    ].join("");
}

function bukaJejak(id) {
    const p = rows.find((r) => String(r.id) === String(id));
    if (!p) return;

    document.getElementById("pinjamanJejakJudul").textContent = `Riwayat ${p.jenis || "Pinjaman"} — Rp ${rupiah(p.nominal)}`;
    document.getElementById("pinjamanJejakSub").textContent =
        [cabangDari(p), p.noSpd ? `No SPD ${p.noSpd}` : "", p.plan?.noSpt].filter(Boolean).join(" · ");

    const jejak = Array.isArray(p.approvals) ? p.approvals : [];
    document.getElementById("pinjamanJejakIsi").innerHTML = jejak.length
        ? jejak.map((a) => {
            const aksi = AKSI_LABEL[a.action] || a.action || "-";
            const warna = a.action === "reject" ? "bg-red-500" : a.action === "approve" ? "bg-emerald-500" : "bg-slate-500";
            return `<div class="flex gap-3">
                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${warna}"></span>
                <div class="flex-1">
                    <div class="font-semibold text-slate-100">${escapeHtml(aksi)}</div>
                    ${a.note ? `<div class="text-sm text-slate-400">${escapeHtml(a.note)}</div>` : ""}
                    <div class="text-xs text-slate-500">${escapeHtml(a.user || "-")} (${escapeHtml(a.role || "-")}) • ${escapeHtml(a.at || "")}</div>
                </div>
            </div>`;
        }).join("")
        : `<p class="text-sm text-slate-500">Belum ada riwayat.</p>`;

    const modal = document.getElementById("pinjamanJejakModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function tutupJejak() {
    const modal = document.getElementById("pinjamanJejakModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

async function proses(id, action) {
    const p = rows.find((r) => String(r.id) === String(id));
    if (!p) return;

    let note = "";
    if (action === "reject") {
        const jawab = prompt(`Tolak pengajuan ${p.jenis} Rp ${rupiah(p.nominal)}.\n\nApa yang harus diperbaiki pengaju?`);
        if (jawab === null) return;
        note = jawab.trim();
        if (!note) {
            showAlert("Alasan penolakan wajib diisi supaya pengaju tahu apa yang harus diperbaiki.", "error");
            return;
        }
    } else if (!confirm(`Setujui pengajuan ${p.jenis} Rp ${rupiah(p.nominal)} untuk ${cabangDari(p)}?`)) {
        return;
    }

    const res = await fetchJson(`/api/pinjaman-cabang/${id}/approve`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action, note }),
    });

    showAlert(res.message || "Status pengajuan diperbarui.");
    await muat();
}

async function muat() {
    const params = new URLSearchParams();
    for (const [id, nama] of [
        ["pinjamanFilterJenis", "jenis"],
        ["pinjamanFilterStatus", "status"],
        ["pinjamanDari", "dari"],
        ["pinjamanSampai", "sampai"],
    ]) {
        const v = document.getElementById(id)?.value;
        if (v) params.set(nama, v);
    }

    const payload = await fetchJson("/api/pinjaman-cabang/daftar?" + params.toString());
    rows = payload.data || [];
    bolehLihatSemua = !!payload.bolehLihatSemua;
    tahapSaya = payload.tahapSaya || null;
    alur = payload.alur || { BPK: [], BPB: [] };

    const cakupan = document.getElementById("pinjamanCakupan");
    if (cakupan) {
        cakupan.textContent = bolehLihatSemua
            ? "Seluruh pengajuan BPK & BPB dari semua plan audit."
            : `Pengajuan yang menjadi kewenangan Anda: yang menunggu giliran Anda${
                tahapSaya ? ` (${STATUS_LABEL[tahapSaya] || tahapSaya})` : ""
            }, ditambah yang pernah Anda setujui atau tolak.`;
    }

    renderTabel();
}

document.addEventListener("DOMContentLoaded", async () => {
    currentUser = cachedUser();

    document.getElementById("pinjamanJejakTutup")?.addEventListener("click", tutupJejak);
    document.getElementById("pinjamanJejakModal")?.addEventListener("click", (e) => {
        if (e.target.id === "pinjamanJejakModal") tutupJejak();
    });

    let timer = null;
    document.getElementById("pinjamanCari")?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(renderTabel, 120);
    });

    // Filter jenis/status/tanggal dikerjakan server supaya rekap nominalnya
    // ikut menyesuaikan, bukan cuma menyembunyikan baris di browser.
    ["pinjamanFilterJenis", "pinjamanFilterStatus", "pinjamanDari", "pinjamanSampai"].forEach((id) => {
        document.getElementById(id)?.addEventListener("change", () => {
            muat().catch((e) => showAlert(e.message, "error"));
        });
    });

    document.body.addEventListener("click", (e) => {
        const jejak = e.target.closest(".pinjaman-jejak");
        const tolak = e.target.closest(".pinjaman-tolak");
        const setuju = e.target.closest(".pinjaman-setuju");

        if (jejak) { bukaJejak(jejak.dataset.id); return; }
        if (tolak) { proses(tolak.dataset.id, "reject").catch((err) => showAlert(err.message, "error")); return; }
        if (setuju) { proses(setuju.dataset.id, "approve").catch((err) => showAlert(err.message, "error")); }
    });

    try {
        await muat();
        // Datang dari notifikasi penolakan (/akta/pinjaman?id=12).
        sorotItem(idDariUrl());
    } catch (err) {
        showAlert(err.message || "Gagal memuat daftar pengajuan.", "error");
        const tbody = document.getElementById("pinjamanTableBody");
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-red-300">${escapeHtml(err.message || "Gagal memuat.")}</td></tr>`;
        }
    }
});
