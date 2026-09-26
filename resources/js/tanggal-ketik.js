// ── Isian tanggal yang bisa DIKETIK ──────────────────────────────────────────
//
// <input type="date"> bawaan peramban sebenarnya bisa diketik, tapi tiap kali
// disentuh kalendernya ikut terbuka dan menutupi baris-baris di bawahnya.
// Auditor yang menyalin puluhan tanggal dari portal jadi merasa harus mencari
// tanggal di kalender satu per satu.
//
// Di sini isiannya jadi kotak teks biasa: ketik angkanya saja, garis miringnya
// disisipkan sendiri. Yang diterima:
//
//     25072026      25/07/2026      25-07-2026      25.7.2026
//     250726        25/7/26         2026-07-25
//
// Kalendernya tidak dihilangkan — tetap ada tombol 📅 di sebelah kanan untuk
// yang lebih suka menunjuk. Enter menyimpan lalu lompat ke isian tanggal
// berikutnya, supaya satu kolom bisa dihabiskan tanpa menyentuh tetikus.

const KELAS_SALAH = ["border-red-500", "text-red-300"];

// "2026-07-25" -> "25/07/2026". Yang bukan tanggal dikembalikan apa adanya.
export function tglIsoKeTeks(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? "").trim());
    return m ? `${m[3]}/${m[2]}/${m[1]}` : "";
}

function tanggalNyata(h, b, t) {
    if (b < 1 || b > 12 || h < 1 || h > 31) return null;
    const d = new Date(Date.UTC(t, b - 1, h));
    // Menangkap 31 April / 29 Februari di tahun biasa: Date menggesernya ke
    // bulan berikutnya, jadi kalau tidak kembali utuh berarti tanggalnya palsu.
    if (d.getUTCFullYear() !== t || d.getUTCMonth() !== b - 1 || d.getUTCDate() !== h) return null;
    return `${String(t).padStart(4, "0")}-${String(b).padStart(2, "0")}-${String(h).padStart(2, "0")}`;
}

// Tahun 2 digit: 00-69 dibaca 2000-an, 70-99 dibaca 1900-an.
function tahunPenuh(t) {
    return t >= 100 ? t : (t < 70 ? 2000 + t : 1900 + t);
}

// Apa pun yang diketik -> "2026-07-25". Kosong -> "". Tidak terbaca -> null.
export function tglTeksKeIso(teks) {
    const s = String(teks ?? "").trim();
    if (s === "") return "";

    // Sudah bentuk ISO (mis. hasil dari kalender).
    const iso = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(s);
    if (iso) return tanggalNyata(+iso[3], +iso[2], +iso[1]);

    // Angka polos: 25072026 atau 250726.
    if (/^\d+$/.test(s)) {
        if (s.length === 8) return tanggalNyata(+s.slice(0, 2), +s.slice(2, 4), +s.slice(4));
        if (s.length === 6) return tanggalNyata(+s.slice(0, 2), +s.slice(2, 4), tahunPenuh(+s.slice(4)));
        return null;
    }

    // Dipisah garis miring / strip / titik / spasi.
    const p = s.split(/[/\-. ]+/).filter(Boolean);
    if (p.length !== 3 || p.some(x => !/^\d{1,4}$/.test(x))) return null;
    return tanggalNyata(+p[0], +p[1], tahunPenuh(+p[2]));
}

// Sisipkan garis miring sambil mengetik, tapi hanya kalau yang diketik memang
// angka polos dan kursornya di ujung — supaya tidak mengacaukan orang yang
// sedang menyunting di tengah atau menempel "2026-07-25".
function rapikanSambilKetik(inp) {
    if (inp.selectionStart !== inp.value.length) return;
    const angka = inp.value.replace(/\D/g, "");
    if (angka !== inp.value.replace(/\//g, "")) return;
    if (angka.length === 0 || angka.length > 8) return;
    let hasil = angka.slice(0, 2);
    if (angka.length > 2) hasil += "/" + angka.slice(2, 4);
    if (angka.length > 4) hasil += "/" + angka.slice(4, 8);
    if (hasil !== inp.value) {
        inp.value = hasil;
        inp.setSelectionRange(hasil.length, hasil.length);
    }
}

function tandaiSalah(inp, salah) {
    inp.classList.toggle("border-slate-700", !salah);
    KELAS_SALAH.forEach(k => inp.classList.toggle(k, salah));
    inp.title = salah ? "Tanggal tidak terbaca. Contoh: 25/07/2026 atau 25072026." : "";
}

/**
 * HTML satu isian tanggal yang bisa diketik.
 *
 * @param {string} iso    nilai awal, "2026-07-25" atau ""
 * @param {string} data   atribut data-* penanda baris, mis. 'data-tc-portal-idx="3"'
 * @param {string} lebar  kelas lebar kotak teksnya
 */
export function tglKetikHtml(iso, data, lebar = "w-28") {
    const teks = tglIsoKeTeks(iso).replace(/"/g, "&quot;");
    return `<span class="tgl-ketik relative inline-flex items-center gap-1" ${data}>
        <input type="text" inputmode="numeric" maxlength="10" placeholder="hh/bb/tttt"
            value="${teks}" data-tgl-teks
            class="${lebar} rounded border border-slate-700 bg-slate-800 px-2 py-1 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
        <input type="date" data-tgl-kalender tabindex="-1" aria-hidden="true"
            value="${iso || ""}" class="absolute right-0 h-0 w-0 opacity-0 pointer-events-none">
        <button type="button" data-tgl-buka title="Pilih dari kalender"
            class="rounded px-1 text-sm leading-none text-slate-400 hover:text-slate-200">&#128197;</button>
    </span>`;
}

/**
 * Hidupkan semua isian tanggal ketik di dalam sebuah wadah.
 *
 * @param {Element}  wadah
 * @param {Function} onSimpan  (iso, elemenSpan) => void — dipanggil saat nilainya
 *                             berubah dan sah (termasuk saat dikosongkan).
 */
export function pasangTanggalKetik(wadah, onSimpan) {
    if (!wadah) return;

    wadah.querySelectorAll(".tgl-ketik").forEach(span => {
        const teks = span.querySelector("[data-tgl-teks]");
        const kalender = span.querySelector("[data-tgl-kalender]");
        const tombol = span.querySelector("[data-tgl-buka]");
        if (!teks) return;

        let terakhir = tglTeksKeIso(teks.value) || "";

        // spanBerikut diteruskan ke onSimpan karena penyimpanan biasanya
        // menggambar ulang tabelnya: pemanggil yang tahu isian mana yang harus
        // dipegang fokusnya SESUDAH gambar ulang itu, bukan elemen ini yang
        // sebentar lagi sudah tidak ada.
        const simpan = (spanBerikut = null) => {
            const iso = tglTeksKeIso(teks.value);
            if (iso === null) { tandaiSalah(teks, true); return false; }
            tandaiSalah(teks, false);
            teks.value = tglIsoKeTeks(iso);
            if (kalender) kalender.value = iso;
            if (iso !== terakhir) { terakhir = iso; onSimpan(iso, span, spanBerikut); }
            return true;
        };

        const spanSesudahIni = () => {
            const semua = [...wadah.querySelectorAll(".tgl-ketik")];
            return semua[semua.indexOf(span) + 1] ?? null;
        };

        // Begitu isian disentuh, seluruh isinya disorot supaya langsung bisa
        // diketik timpa. Tanpa ini, kotak yang sudah berisi "25/07/2026" sudah
        // mentok maxlength, jadi mengetik di dalamnya TIDAK terjadi apa-apa dan
        // auditor harus menghapusnya dulu. mouseup ditahan sekali supaya klik
        // tetikus tidak langsung membatalkan sorotan itu.
        const sorotSemua = () => { try { teks.select(); } catch { /* diabaikan */ } };
        // Kotak tanggal isinya pendek dan bentuknya tetap, jadi menyentuhnya
        // berarti "mau menulis ulang": seluruh isinya selalu disorot supaya
        // langsung bisa diketik timpa. Tanpa ini kotak yang sudah berisi
        // "25/07/2026" mentok maxlength dan ketikan auditor tidak masuk sama
        // sekali. preventDefault menahan peramban mengubah sorotan jadi caret.
        teks.addEventListener("focus", sorotSemua);
        teks.addEventListener("mouseup", (e) => { e.preventDefault(); sorotSemua(); });

        teks.addEventListener("input", () => { rapikanSambilKetik(teks); tandaiSalah(teks, false); });
        teks.addEventListener("blur", () => simpan());
        teks.addEventListener("keydown", (e) => {
            if (e.key !== "Enter") return;
            e.preventDefault();
            // Enter = simpan lalu turun ke isian tanggal berikutnya, supaya satu
            // kolom bisa diisi berurutan tanpa menyentuh tetikus.
            const berikut = spanSesudahIni();
            if (!simpan(berikut)) return;
            // Kalau nilainya tidak berubah, tidak ada penyimpanan dan tidak ada
            // gambar ulang — fokusnya dipindah di sini.
            const sasaran = berikut?.querySelector("[data-tgl-teks]");
            if (sasaran) { sasaran.focus(); sasaran.select(); } else { teks.blur(); }
        });

        tombol?.addEventListener("click", () => {
            if (!kalender) return;
            kalender.classList.remove("pointer-events-none", "h-0", "w-0");
            kalender.classList.add("h-full", "w-full");
            try { kalender.showPicker(); } catch { kalender.focus(); kalender.click(); }
        });

        kalender?.addEventListener("change", () => {
            teks.value = tglIsoKeTeks(kalender.value);
            kalender.classList.add("pointer-events-none", "h-0", "w-0");
            kalender.classList.remove("h-full", "w-full");
            simpan();
        });
        kalender?.addEventListener("blur", () => {
            kalender.classList.add("pointer-events-none", "h-0", "w-0");
            kalender.classList.remove("h-full", "w-full");
        });
    });
}

// ── Memasang di isian tanggal yang SUDAH ADA ─────────────────────────────────
//
// Isian <input type="date"> tersebar di puluhan layar, masing-masing punya
// penyimpanannya sendiri (ada yang baca .value lewat id, ada yang lewat kelas,
// ada yang pakai delegasi event). Menulis ulang semuanya satu per satu berisiko
// besar dan tidak perlu.
//
// Jadi isian aslinya TIDAK dibuang: ia tetap di DOM, cuma disembunyikan, dan
// kotak teks ketik dipasang di depannya. Begitu yang diketik sah, nilainya
// ditulis ke isian asli lalu event 'input' + 'change' dilepas dari situ — jadi
// seluruh kode penyimpanan yang sudah ada tetap berjalan apa adanya, tanpa
// disentuh sama sekali. Tombol kalender memakai isian asli itu juga.

// Kelas yang boleh ikut ke kotak teks: HANYA yang mengatur tampilan.
//
// Ini bukan kerapian, ini keselamatan data. Kode lama mencari isiannya lewat
// kelas penanda, mis. tr.querySelector(".trx-tanggal").value. Kalau kelas itu
// ikut tersalin ke kotak teks, yang ketemu duluan justru kotak teksnya, dan
// yang tersimpan jadi "25/07/2026" alih-alih "2026-07-25". Jadi penanda
// semacam trx-tanggal / bank-rk-tgl ditinggal di isian aslinya.
const AWALAN_TAMPILAN = [
    "w-", "min-w-", "max-w-", "h-", "min-h-", "max-h-",
    "p-", "px-", "py-", "pt-", "pb-", "pl-", "pr-",
    "rounded", "border", "bg-", "text-", "font-", "leading-", "tracking-",
    "outline-", "ring-", "shadow", "placeholder-", "cursor-", "opacity-",
    "appearance-", "transition", "uppercase", "lowercase", "capitalize",
    "block", "inline", "flex", "grow", "shrink", "truncate", "tabular-",
];

function kelasTampilan(daftar) {
    return [...daftar]
        .filter(k => {
            const inti = k.includes(":") ? k.slice(k.lastIndexOf(":") + 1) : k;
            return AWALAN_TAMPILAN.some(a => (a.endsWith("-") ? inti.startsWith(a) : inti === a || inti.startsWith(a + "-")));
        })
        .join(" ");
}

function upgradeSatu(asli) {
    if (asli.dataset.tglSiap) return;
    // Isian kalender milik tglKetikHtml() — sudah punya kotak teksnya sendiri.
    if (asli.hasAttribute("data-tgl-kalender")) return;
    asli.dataset.tglSiap = "1";

    const penuh = /\bw-full\b/.test(asli.className);
    const span = document.createElement("span");
    span.className = "tgl-ketik relative inline-flex items-center gap-1" + (penuh ? " w-full" : "");

    const teks = document.createElement("input");
    teks.type = "text";
    teks.inputMode = "numeric";
    teks.maxLength = 10;
    teks.placeholder = "hh/bb/tttt";
    // Tampilannya mewarisi isian aslinya supaya bentuk layarnya tidak berubah,
    // tapi kelas penandanya TIDAK ikut (lihat kelasTampilan di atas).
    teks.className = kelasTampilan(asli.classList);
    teks.value = tglIsoKeTeks(asli.value);
    if (asli.disabled) teks.disabled = true;
    if (asli.readOnly) teks.readOnly = true;
    if (asli.title) teks.title = asli.title;
    const label = asli.getAttribute("aria-label");
    if (label) teks.setAttribute("aria-label", label);

    // 'required' dipindah ke kotak teks: isian asli yang tersembunyi dan wajib
    // membuat peramban menolak submit sambil mencoba menyorot elemen yang tidak
    // terlihat. Di kotak teks, wajibnya tetap berlaku DAN sekalian menolak
    // tanggal yang tidak terbaca.
    const wajib = asli.required;
    if (wajib) { asli.required = false; teks.required = true; }

    const tombol = document.createElement("button");
    tombol.type = "button";
    tombol.title = "Pilih dari kalender";
    tombol.tabIndex = -1;
    tombol.className = "shrink-0 rounded px-1 text-sm leading-none text-slate-400 hover:text-slate-200";
    tombol.innerHTML = "&#128197;";

    asli.replaceWith(span);
    span.append(teks, asli, tombol);

    // Isian asli disembunyikan dengan gaya inline supaya tidak bentrok dengan
    // kelas Tailwind-nya sendiri, tapi tetap di DOM (id, name, listener, dan
    // pembacaan .value oleh kode lain tidak berubah sedikit pun).
    Object.assign(asli.style, {
        position: "absolute", right: "0", width: "0", height: "0",
        opacity: "0", padding: "0", border: "0", pointerEvents: "none",
    });
    asli.tabIndex = -1;

    const validitas = () => {
        if (!teks.setCustomValidity) return;
        const iso = tglTeksKeIso(teks.value);
        teks.setCustomValidity(iso === null ? "Tanggal tidak terbaca. Contoh: 25/07/2026" : "");
    };

    let terakhir = asli.value || "";
    const simpan = () => {
        const iso = tglTeksKeIso(teks.value);
        validitas();
        if (iso === null) { tandaiSalah(teks, true); return false; }
        tandaiSalah(teks, false);
        teks.value = tglIsoKeTeks(iso);
        if (iso === terakhir) return true;
        terakhir = iso;
        asli.value = iso;
        // Inilah yang membuat kode penyimpanan lama ikut jalan.
        asli.dispatchEvent(new Event("input", { bubbles: true }));
        asli.dispatchEvent(new Event("change", { bubbles: true }));
        return true;
    };

    const sorotSemua = () => { try { teks.select(); } catch { /* diabaikan */ } };
    teks.addEventListener("focus", sorotSemua);
    teks.addEventListener("mouseup", (e) => { e.preventDefault(); sorotSemua(); });
    teks.addEventListener("input", () => { rapikanSambilKetik(teks); tandaiSalah(teks, false); validitas(); });
    teks.addEventListener("blur", simpan);
    teks.addEventListener("keydown", (e) => {
        if (e.key !== "Enter") return;
        // Di dalam <form>, Enter di kotak teks akan men-submit form. Isian ini
        // menggantikan isian tanggal, jadi perilakunya disamakan: simpan lalu
        // turun ke isian tanggal berikutnya.
        e.preventDefault();
        if (!simpan()) return;
        const semua = [...document.querySelectorAll(".tgl-ketik input[type=text]")];
        const berikut = semua[semua.indexOf(teks) + 1];
        if (berikut && !berikut.disabled) { berikut.focus(); berikut.select(); } else { teks.blur(); }
    });

    tombol.addEventListener("click", () => {
        Object.assign(asli.style, { width: "100%", height: "100%", pointerEvents: "auto" });
        try { asli.showPicker(); } catch { asli.focus(); asli.click(); }
    });
    const sembunyikanLagi = () => Object.assign(asli.style, { width: "0", height: "0", pointerEvents: "none" });
    asli.addEventListener("change", () => {
        // Hanya perubahan dari kalender yang perlu disalin balik; perubahan dari
        // simpan() sudah sinkron (terakhir === asli.value).
        if (asli.value !== terakhir) {
            terakhir = asli.value;
            teks.value = tglIsoKeTeks(asli.value);
            tandaiSalah(teks, false);
            validitas();
        }
        sembunyikanLagi();
    });
    asli.addEventListener("blur", sembunyikanLagi);

    validitas();
}

/** Pasang di semua isian tanggal di dalam sebuah wadah (aman dipanggil ulang). */
export function upgradeIsianTanggal(akar = document) {
    akar.querySelectorAll?.('input[type="date"]:not([data-tgl-siap])').forEach(upgradeSatu);
}

/**
 * Pasang sekarang, lalu ikuti isian tanggal yang muncul belakangan — banyak
 * tabel di aplikasi ini menggambar barisnya lewat JS sesudah halaman siap.
 */
export function amatiIsianTanggal() {
    upgradeIsianTanggal(document);
    new MutationObserver((rekaman) => {
        for (const r of rekaman) {
            for (const n of r.addedNodes) {
                if (n.nodeType !== 1) continue;
                if (n.matches?.('input[type="date"]:not([data-tgl-siap])')) upgradeSatu(n);
                else upgradeIsianTanggal(n);
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
}
