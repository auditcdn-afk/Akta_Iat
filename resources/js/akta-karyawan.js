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
    const el = document.getElementById("kryAlert");
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

// Role kantor pusat yang boleh melihat semua unit usaha (read-only) --
// harus konsisten dengan KaryawanController::HO_ROLES di backend.
const HO_ROLES = ["admin", "manajer", "auditor", "koordinator", "coo"];
const BRANCH_ROLES = ["h1", "h2", "unit", "bpk"];

let currentUser = null;
let kryItems = [];

function canWrite(item) {
    if (!currentUser) return false;
    if (currentUser.role === "admin") return true;
    return currentUser.unitUsaha && currentUser.unitUsaha === item.unitUsaha;
}

function renderGrid() {
    const grid = document.getElementById("kryGrid");
    if (!grid) return;

    if (!kryItems.length) {
        grid.innerHTML = '<p class="col-span-full text-center text-sm text-slate-500 py-8">Belum ada data karyawan.</p>';
        return;
    }

    grid.innerHTML = kryItems.map((item) => {
        const foto = item.photoUrl
            ? `<img src="${escapeHtml(item.photoUrl)}" alt="${escapeHtml(item.nama)}" class="h-24 w-24 rounded-xl object-cover border border-slate-700 mx-auto">`
            : `<div class="h-24 w-24 rounded-xl bg-slate-800 border border-slate-700 mx-auto flex items-center justify-center text-2xl font-bold text-slate-500">${escapeHtml((item.nama || "?").charAt(0).toUpperCase())}</div>`;

        const deleteBtn = canWrite(item)
            ? `<button data-id="${item.id}" class="kryDeleteBtn mt-2 w-full rounded-lg border border-red-500/40 px-2 py-1 text-xs font-semibold text-red-300 hover:bg-red-500/10 transition">Hapus</button>`
            : "";

        return `
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-3 text-center">
                ${foto}
                <div class="mt-2 text-sm font-semibold text-slate-200 truncate" title="${escapeHtml(item.nama)}">${escapeHtml(item.nama)}</div>
                <div class="text-xs text-slate-400 truncate" title="${escapeHtml(item.jabatan)}">${escapeHtml(item.jabatan)}</div>
                ${HO_ROLES.includes(currentUser?.role) ? `<div class="mt-1 text-[10px] uppercase tracking-wide text-slate-500 truncate">${escapeHtml(item.unitUsaha)}</div>` : ""}
                ${deleteBtn}
            </div>`;
    }).join("");

    grid.querySelectorAll(".kryDeleteBtn").forEach((btn) => {
        btn.addEventListener("click", () => deleteItem(btn.dataset.id));
    });
}

async function loadData() {
    const grid = document.getElementById("kryGrid");
    try {
        const filterSelect = document.getElementById("kryFilterUnitUsaha");
        const filter = filterSelect && !filterSelect.classList.contains("hidden") ? filterSelect.value : "";
        const url = filter ? `/api/karyawan?unit_usaha=${encodeURIComponent(filter)}` : "/api/karyawan";
        const result = await fetchJson(url, { headers: authHeaders() });
        kryItems = result.data || [];
        renderGrid();
    } catch (err) {
        if (grid) grid.innerHTML = '<p class="col-span-full text-center text-sm text-red-400 py-8">Gagal memuat data karyawan.</p>';
        showAlert(err.message || "Gagal memuat data karyawan.", "error");
    }
}

async function loadUnitUsahaOptions(selectEl, includeEmpty) {
    if (!selectEl) return;
    try {
        const result = await fetchJson("/api/database/unit-usaha-options", { headers: authHeaders() });
        const options = result.data || [];
        const emptyOption = includeEmpty ? '<option value="">Semua Unit Usaha</option>' : '<option value="">Pilih unit usaha...</option>';
        selectEl.innerHTML = emptyOption + options.map((u) => `<option value="${escapeHtml(u.unitUsaha)}">${escapeHtml(u.unitUsaha)}</option>`).join("");
    } catch (err) {
        selectEl.innerHTML = '<option value="">Gagal memuat daftar unit usaha</option>';
    }
}

const KRY_MAX_FOTO_BYTES = 2 * 1024 * 1024; // samakan dengan validasi 'max:2048' di KaryawanController

function handleFileChange() {
    const fileInput = document.getElementById("kryFileInput");
    const fileName = document.getElementById("kryFileName");
    if (!fileInput || !fileName) return;
    const file = fileInput.files?.[0] || null;
    // Dicek di sini (bukan cuma menunggu server) supaya foto kamera HP yang
    // ukurannya beberapa MB langsung dapat pesan jelas — tanpa ini requestnya
    // bisa ditolak PHP SEBELUM sempat sampai ke validasi Laravel, muncul
    // sebagai error mentah "The POST data is too large." yang membingungkan.
    if (file && file.size > KRY_MAX_FOTO_BYTES) {
        showAlert(`Ukuran foto "${file.name}" (${(file.size / 1024 / 1024).toFixed(1)} MB) melebihi batas 2MB. Kompres atau pilih foto lain.`, "error");
        fileInput.value = "";
        fileName.textContent = "Belum ada foto";
        return;
    }
    fileName.textContent = file?.name || "Belum ada foto";
}

async function handleSubmit(e) {
    e.preventDefault();
    const saveBtn = document.getElementById("krySaveBtn");
    const isAdmin = currentUser?.role === "admin";
    const unitUsaha = isAdmin ? document.getElementById("kryUnitUsaha")?.value : null;

    if (isAdmin && !unitUsaha) {
        showAlert("Pilih unit usaha terlebih dahulu.", "error");
        return;
    }

    const fileInput = document.getElementById("kryFileInput");
    const foto = fileInput?.files?.[0] || null;
    if (foto && foto.size > KRY_MAX_FOTO_BYTES) {
        showAlert(`Ukuran foto "${foto.name}" (${(foto.size / 1024 / 1024).toFixed(1)} MB) melebihi batas 2MB. Kompres atau pilih foto lain.`, "error");
        return;
    }

    const formData = new FormData();
    if (isAdmin) formData.append("unit_usaha", unitUsaha);
    formData.append("nama", document.getElementById("kryNama")?.value || "");
    formData.append("jabatan", document.getElementById("kryJabatan")?.value || "");
    if (foto) formData.append("foto", foto);

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = "Menyimpan...";
    }
    try {
        const result = await fetchJson("/api/karyawan", {
            method: "POST",
            headers: authHeaders(),
            body: formData,
        });
        showAlert(result.message || "Data karyawan berhasil ditambahkan.");
        document.getElementById("kryForm")?.reset();
        document.getElementById("kryFileName").textContent = "Belum ada foto";
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal menambahkan data karyawan.", "error");
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = "💾 Simpan";
        }
    }
}

async function deleteItem(id) {
    if (!window.confirm("Hapus data karyawan ini?")) return;
    try {
        const result = await fetchJson(`/api/karyawan/${id}`, {
            method: "DELETE",
            headers: authHeaders(),
        });
        showAlert(result.message || "Data karyawan berhasil dihapus.");
        await loadData();
    } catch (err) {
        showAlert(err.message || "Gagal menghapus data karyawan.", "error");
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    currentUser = getSession()?.user || null;
    if (!currentUser) {
        window.location.href = "/akta/login";
        return;
    }

    const isAdmin = currentUser.role === "admin";
    const isBranch = BRANCH_ROLES.includes(currentUser.role);
    const isHo = HO_ROLES.includes(currentUser.role);

    if (isAdmin || isBranch) {
        document.getElementById("kryFormCard")?.classList.remove("hidden");
        if (isAdmin) {
            document.getElementById("kryUnitUsahaWrap")?.classList.remove("hidden");
            await loadUnitUsahaOptions(document.getElementById("kryUnitUsaha"), false);
        }
    }

    if (isHo) {
        const filterWrap = document.getElementById("kryFilterWrap");
        filterWrap?.classList.remove("hidden");
        const filterSelect = document.getElementById("kryFilterUnitUsaha");
        await loadUnitUsahaOptions(filterSelect, true);
        filterSelect?.addEventListener("change", loadData);
    }

    document.getElementById("kryForm")?.addEventListener("submit", handleSubmit);
    document.getElementById("kryFileInput")?.addEventListener("change", handleFileChange);

    await loadData();
});
