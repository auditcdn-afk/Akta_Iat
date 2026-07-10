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

function showAlert(message, type = "success") {
    const el = document.getElementById("mdAlert");
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

const STATUS_LABEL = {
    diajukan: { text: "Menunggu Manajer", cls: "bg-amber-500/10 text-amber-300 border-amber-500/30" },
    disetujui: { text: "Menunggu MRR", cls: "bg-blue-500/10 text-blue-300 border-blue-500/30" },
    ditolak: { text: "Ditolak", cls: "bg-red-500/10 text-red-300 border-red-500/30" },
    selesai: { text: "Selesai", cls: "bg-emerald-500/10 text-emerald-300 border-emerald-500/30" },
};

let currentUser = null;
let mdItems = [];
let activeDecideId = null;
let activeCompleteId = null;

function formatTanggal(value) {
    if (!value) return "-";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
}

async function loadPicOptions() {
    const container = document.getElementById("mdPicOptions");
    if (!container) return;
    try {
        const result = await fetchJson("/api/mobil-dinas/pic-options", { headers: authHeaders() });
        const options = result.data || [];
        if (!options.length) {
            container.innerHTML = '<p class="text-sm text-slate-500">Belum ada data auditor.</p>';
            return;
        }
        container.innerHTML = options.map((u) => `
            <label class="flex items-center gap-2 text-sm text-slate-200">
                <input type="checkbox" value="${escapeHtml(u.nama)}" class="mdPicCheckbox rounded border-slate-600 bg-slate-950 text-blue-500 focus:ring-0">
                ${escapeHtml(u.nama)}
            </label>
        `).join("");
    } catch (err) {
        container.innerHTML = '<p class="text-sm text-red-400">Gagal memuat daftar PIC.</p>';
        showAlert(err.message || "Gagal memuat daftar PIC.", "error");
    }
}

function getSelectedPic() {
    return Array.from(document.querySelectorAll(".mdPicCheckbox:checked")).map((el) => el.value);
}

function handleFileChange() {
    const fileInput = document.getElementById("mdFileInput");
    const fileName = document.getElementById("mdFileName");
    if (!fileInput || !fileName) return;
    fileName.textContent = fileInput.files?.[0]?.name || "Belum ada file";
}

function renderTable() {
    const tbody = document.getElementById("mdTableBody");
    if (!tbody) return;

    if (!mdItems.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada pengajuan mobil dinas.</td></tr>';
        return;
    }

    tbody.innerHTML = mdItems.map((item, idx) => {
        const status = STATUS_LABEL[item.status] || STATUS_LABEL.diajukan;
        const spdLink = item.spdFile?.url
            ? `<a href="${escapeHtml(item.spdFile.url)}" target="_blank" rel="noopener" class="text-blue-400 hover:underline">Lihat</a>`
            : '<span class="text-slate-500">-</span>';
        const kendaraan = item.namaSupir
            ? `${escapeHtml(item.namaSupir)}<br><span class="text-slate-400">${escapeHtml(item.platMobil)} • ${escapeHtml(item.jenisMobil)}</span>`
            : '<span class="text-slate-500">-</span>';

        const actions = [];
        const canDecide = (currentUser?.role === "admin" || currentUser?.role === "manajer") && item.status === "diajukan";
        const canComplete = (currentUser?.role === "admin" || currentUser?.role === "mrr") && item.status === "disetujui";
        const canDelete = item.status === "diajukan" && (currentUser?.role === "admin" || currentUser?.username === item.createdBy);

        if (canDecide) {
            actions.push(`<button data-id="${item.id}" class="mdDecideBtn rounded-lg border border-blue-500/40 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/10 transition">Setujui/Tolak</button>`);
        }
        if (canComplete) {
            actions.push(`<button data-id="${item.id}" class="mdCompleteBtn rounded-lg border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/10 transition">Lengkapi Form</button>`);
        }
        if (canDelete) {
            actions.push(`<button data-id="${item.id}" class="mdDeleteBtn rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/10 transition">Hapus</button>`);
        }
        if (!actions.length) actions.push('<span class="text-slate-600">-</span>');

        const catatan = item.catatanManajer
            ? `<div class="mt-1 text-xs italic text-slate-500">"${escapeHtml(item.catatanManajer)}"</div>`
            : "";

        return `
            <tr>
                <td class="px-4 py-3">${idx + 1}</td>
                <td class="px-4 py-3">${escapeHtml(item.supirRequest)}${catatan}</td>
                <td class="px-4 py-3">${escapeHtml(formatTanggal(item.tanggalBerangkat))} - ${escapeHtml(formatTanggal(item.tanggalPulang))}</td>
                <td class="px-4 py-3">${(item.picMobil || []).map(escapeHtml).join(", ") || "-"}</td>
                <td class="px-4 py-3 text-center">${spdLink}</td>
                <td class="px-4 py-3">${kendaraan}</td>
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${status.cls}">${status.text}</span>
                </td>
                <td class="px-4 py-3 text-right"><div class="flex justify-end gap-2">${actions.join("")}</div></td>
            </tr>`;
    }).join("");

    tbody.querySelectorAll(".mdDecideBtn").forEach((btn) => {
        btn.addEventListener("click", () => openDecideModal(btn.dataset.id));
    });
    tbody.querySelectorAll(".mdCompleteBtn").forEach((btn) => {
        btn.addEventListener("click", () => openCompleteModal(btn.dataset.id));
    });
    tbody.querySelectorAll(".mdDeleteBtn").forEach((btn) => {
        btn.addEventListener("click", () => deleteItem(btn.dataset.id));
    });
}

async function loadData() {
    try {
        const result = await fetchJson("/api/mobil-dinas", { headers: authHeaders() });
        mdItems = result.data || [];
        renderTable();
    } catch (err) {
        showAlert(err.message || "Gagal memuat data mobil dinas.", "error");
    }
}

async function handleSubmit(e) {
    e.preventDefault();
    const saveBtn = document.getElementById("mdSaveBtn");
    const fileInput = document.getElementById("mdFileInput");
    const picMobil = getSelectedPic();

    if (!picMobil.length) {
        showAlert("Pilih minimal satu PIC Mobil.", "error");
        return;
    }
    if (!fileInput?.files?.[0]) {
        showAlert("Upload SPD PIC wajib diisi.", "error");
        return;
    }

    const formData = new FormData();
    formData.append("supir_request", document.getElementById("mdSupirRequest")?.value || "");
    formData.append("tanggal_berangkat", document.getElementById("mdTglBerangkat")?.value || "");
    formData.append("tanggal_pulang", document.getElementById("mdTglPulang")?.value || "");
    picMobil.forEach((nama) => formData.append("pic_mobil[]", nama));
    formData.append("file", fileInput.files[0]);

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = "Mengirim...";
    }
    try {
        const result = await fetchJson("/api/mobil-dinas", {
            method: "POST",
            headers: authHeaders(),
            body: formData,
        });
        showAlert(result.message || "Pengajuan berhasil dikirim.");
        document.getElementById("mdForm")?.reset();
        document.getElementById("mdFileName").textContent = "Belum ada file";
        document.querySelectorAll(".mdPicCheckbox").forEach((el) => { el.checked = false; });
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal mengirim pengajuan.", "error");
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = "📨 Ajukan";
        }
    }
}

function openDecideModal(id) {
    activeDecideId = id;
    document.getElementById("mdDecideCatatan").value = "";
    document.getElementById("mdDecideModal")?.classList.remove("hidden");
}

function closeDecideModal() {
    activeDecideId = null;
    document.getElementById("mdDecideModal")?.classList.add("hidden");
}

async function submitDecision(status) {
    if (!activeDecideId) return;
    const catatan = document.getElementById("mdDecideCatatan")?.value || "";
    try {
        const result = await fetchJson(`/api/mobil-dinas/${activeDecideId}/decide`, {
            method: "POST",
            headers: { "Content-Type": "application/json", ...authHeaders() },
            body: JSON.stringify({ status, catatan_manajer: catatan }),
        });
        showAlert(result.message || "Keputusan berhasil disimpan.");
        closeDecideModal();
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal menyimpan keputusan.", "error");
    }
}

function openCompleteModal(id) {
    activeCompleteId = id;
    document.getElementById("mdNamaSupir").value = "";
    document.getElementById("mdPlatMobil").value = "";
    document.getElementById("mdJenisMobil").value = "";
    document.getElementById("mdCompleteModal")?.classList.remove("hidden");
}

function closeCompleteModal() {
    activeCompleteId = null;
    document.getElementById("mdCompleteModal")?.classList.add("hidden");
}

async function submitComplete() {
    if (!activeCompleteId) return;
    const namaSupir = document.getElementById("mdNamaSupir")?.value.trim();
    const platMobil = document.getElementById("mdPlatMobil")?.value.trim();
    const jenisMobil = document.getElementById("mdJenisMobil")?.value.trim();

    if (!namaSupir || !platMobil || !jenisMobil) {
        showAlert("Nama supir, plat mobil, dan jenis mobil wajib diisi.", "error");
        return;
    }

    try {
        const result = await fetchJson(`/api/mobil-dinas/${activeCompleteId}/complete`, {
            method: "POST",
            headers: { "Content-Type": "application/json", ...authHeaders() },
            body: JSON.stringify({ nama_supir: namaSupir, plat_mobil: platMobil, jenis_mobil: jenisMobil }),
        });
        showAlert(result.message || "Form berhasil dikirim.");
        closeCompleteModal();
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal mengirim form.", "error");
    }
}

async function deleteItem(id) {
    if (!window.confirm("Hapus pengajuan mobil dinas ini?")) return;
    try {
        const result = await fetchJson(`/api/mobil-dinas/${id}`, {
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

    if (["admin", "auditor"].includes(currentUser.role)) {
        document.getElementById("mdFormCard")?.classList.remove("hidden");
        await loadPicOptions();
    }

    document.getElementById("mdForm")?.addEventListener("submit", handleSubmit);
    document.getElementById("mdFileInput")?.addEventListener("change", handleFileChange);
    document.getElementById("mdDecideCancelBtn")?.addEventListener("click", closeDecideModal);
    document.getElementById("mdApproveBtn")?.addEventListener("click", () => submitDecision("disetujui"));
    document.getElementById("mdRejectBtn")?.addEventListener("click", () => submitDecision("ditolak"));
    document.getElementById("mdCompleteCancelBtn")?.addEventListener("click", closeCompleteModal);
    document.getElementById("mdCompleteSubmitBtn")?.addEventListener("click", submitComplete);

    await loadData();
});
