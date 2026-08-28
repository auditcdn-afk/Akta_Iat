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
        err.body = body;
        throw err;
    }
    return body;
}

function showAlert(message, type = "success") {
    const el = document.getElementById("uaAlert");
    if (!el) return;
    el.textContent = message;
    el.classList.remove("hidden", "border-emerald-500/40", "bg-emerald-500/10", "text-emerald-300", "border-red-500/40", "bg-red-500/10", "text-red-300");
    el.classList.add(type === "error" ? "border-red-500/40" : "border-emerald-500/40", type === "error" ? "bg-red-500/10" : "bg-emerald-500/10", type === "error" ? "text-red-300" : "text-emerald-300");
    el.classList.remove("hidden");
    window.clearTimeout(showAlert._t);
    showAlert._t = window.setTimeout(() => el.classList.add("hidden"), 6000);
}

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    }[c]));
}

function fmtDate(v) {
    if (!v) return "-";
    return String(v).slice(0, 10);
}

async function loadHistory() {
    try {
        const res = await fetchJson("/api/analisa-zona/my-uploads", { headers: authHeaders() });
        const uploads = res.data || [];
        document.getElementById("uaHistoryCount").textContent = `${uploads.length} File`;

        const tbody = document.getElementById("uaHistoryTableBody");
        if (uploads.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-6 text-center text-slate-400 text-xs">Belum ada file yang diupload.</td></tr>`;
            return;
        }
        tbody.innerHTML = uploads.map(u => `
            <tr class="hover:bg-slate-800/40">
                <td class="px-3 py-2 text-slate-300">${escapeHtml(u.source_filename)}</td>
                <td class="px-3 py-2 text-slate-400 uppercase">${escapeHtml(u.jenis)}</td>
                <td class="px-3 py-2 text-center text-slate-400">${fmtDate(u.tanggal)}</td>
                <td class="px-3 py-2 text-right text-slate-300">${u.row_count}</td>
                <td class="px-3 py-2 text-slate-400">${escapeHtml(u.uploaded_by || "-")}</td>
            </tr>`).join("");
    } catch (e) {
        // Riwayat gagal dimuat bukan blocker untuk upload — cukup diamkan di tabel.
        document.getElementById("uaHistoryTableBody").innerHTML =
            `<tr><td colspan="5" class="px-4 py-6 text-center text-red-400 text-xs">Gagal memuat riwayat: ${escapeHtml(e.message || "")}</td></tr>`;
    }
}

function renderList(title, items, colorClass) {
    if (!items || items.length === 0) return "";
    return `
        <div>
            <p class="font-semibold ${colorClass} mb-1">${escapeHtml(title)} (${items.length})</p>
            <ul class="list-disc list-inside text-slate-400 space-y-0.5">
                ${items.map(it => `<li>${escapeHtml(it)}</li>`).join("")}
            </ul>
        </div>`;
}

async function handleZipUpload(file) {
    const msg = document.getElementById("uaUploadMsg");
    msg.classList.remove("hidden");
    msg.className = "text-sm font-medium text-blue-300";
    msg.textContent = "Mengupload & memproses...";

    const form = new FormData();
    form.append("file", file);

    try {
        const res = await fetchJson("/api/analisa-zona/upload-self", {
            method: "POST",
            headers: authHeaders(),
            body: form,
        });
        msg.className = "text-sm font-medium text-emerald-300";
        msg.textContent = res.message || "Berhasil diproses.";
        showAlert(res.message || "Upload selesai.", "success");

        const data = res.data || {};
        const resultCard = document.getElementById("uaResultCard");
        const resultBody = document.getElementById("uaResultBody");
        resultCard.classList.remove("hidden");
        resultBody.innerHTML = [
            renderList("✓ Berhasil diproses", data.processed, "text-emerald-400"),
            renderList("↻ Duplikat (dilewati)", data.skipped_duplicate, "text-slate-400"),
            renderList("✗ Ditolak — kode unit usaha tidak cocok", data.rejected_unit_usaha, "text-red-400"),
            renderList("? Format tidak dikenali", data.unsupported, "text-amber-400"),
        ].join("") || `<p class="text-slate-500">Tidak ada file yang diproses.</p>`;

        loadHistory().catch(() => {});
    } catch (e) {
        msg.className = "text-sm font-medium text-red-300";
        msg.textContent = e.message || "Gagal upload.";
        showAlert(e.message || "Gagal upload.", "error");
    }
}

document.addEventListener("DOMContentLoaded", () => {
    loadHistory().catch(() => {});

    const fileInput = document.getElementById("uaFileInput");
    const dropzone = document.getElementById("uaDropzone");
    fileInput?.addEventListener("change", () => { if (fileInput.files[0]) handleZipUpload(fileInput.files[0]); fileInput.value = ""; });
    dropzone?.addEventListener("dragover", e => { e.preventDefault(); dropzone.classList.add("border-blue-400"); });
    dropzone?.addEventListener("dragleave", () => dropzone.classList.remove("border-blue-400"));
    dropzone?.addEventListener("drop", e => {
        e.preventDefault();
        dropzone.classList.remove("border-blue-400");
        const f = e.dataTransfer.files[0];
        if (f) handleZipUpload(f);
    });
});
