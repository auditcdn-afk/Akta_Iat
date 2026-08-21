import * as pdfjsLib from "pdfjs-dist/build/pdf.mjs";

// PDF lampiran sebelumnya dirender lewat <embed> (plugin viewer native
// browser) — browser membatasi jumlah plugin PDF yang bisa aktif render
// bersamaan di satu halaman, jadi lampiran ke-5/6 dst tampil kosong. Lalu
// sempat diganti jadi tombol "buka di tab baru" saja, tapi auditor perlu
// isinya langsung terlihat di laporan tanpa harus klik keluar satu-satu.
// Di sini isi setiap PDF dirender ke <canvas> lewat pdf.js — bukan plugin
// native — sehingga tidak kena batas jumlah tadi dan tetap tampil sebagai
// gambar halaman yang bisa dicetak/di-scroll seperti bagian laporan lain.
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    "pdfjs-dist/build/pdf.worker.min.mjs",
    import.meta.url,
).href;

function buildFallback(container, src, name) {
    container.innerHTML = "";

    const wrap = document.createElement("div");
    wrap.style.cssText =
        "display:flex;flex-direction:column;align-items:center;gap:6px;padding:24px 20px;text-align:center;background:#f9fafb;";

    const icon = document.createElement("div");
    icon.style.fontSize = "32px";
    icon.textContent = "📄";

    const nameEl = document.createElement("div");
    nameEl.style.cssText = "font-size:11px;color:#374151;font-weight:600;";
    nameEl.textContent = name;

    const note = document.createElement("div");
    note.style.cssText = "font-size:9px;color:#9ca3af;";
    note.textContent = "Gagal menampilkan pratinjau otomatis.";

    const link = document.createElement("a");
    link.href = src;
    link.target = "_blank";
    link.rel = "noopener";
    link.style.cssText =
        "margin-top:4px;background:#1e40af;color:#fff;font-size:9.5px;font-weight:700;padding:5px 14px;border-radius:6px;text-decoration:none;";
    link.textContent = "Buka Pratinjau PDF ↗";

    wrap.append(icon, nameEl, note, link);
    container.appendChild(wrap);
}

async function renderOne(container) {
    const src = container.getAttribute("data-pdf-src");
    const name = container.getAttribute("data-pdf-name") || "file";
    if (!src) return;

    try {
        const pdf = await pdfjsLib.getDocument({ url: src }).promise;
        container.innerHTML = "";

        const targetWidth = container.clientWidth || 760;

        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const baseViewport = page.getViewport({ scale: 1 });
            const scale = (targetWidth / baseViewport.width) * 1.5;
            const viewport = page.getViewport({ scale });

            const canvas = document.createElement("canvas");
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.display = "block";
            canvas.style.width = "100%";
            canvas.style.height = "auto";
            if (pageNum > 1) {
                canvas.style.borderTop = "1px dashed #e5e7eb";
            }

            const ctx = canvas.getContext("2d");
            await page.render({ canvasContext: ctx, viewport }).promise;

            container.appendChild(canvas);
        }
    } catch (err) {
        console.error("Gagal render lampiran PDF:", name, err);
        buildFallback(container, src, name);
    }
}

function initLampiranPdf() {
    const containers = Array.from(
        document.querySelectorAll(".lampiran-pdf-pages"),
    );

    if (!containers.length) {
        window.__lampiranPdfReady = Promise.resolve();
        return;
    }

    window.__lampiranPdfReady = Promise.all(containers.map(renderOne));
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initLampiranPdf);
} else {
    initLampiranPdf();
}
