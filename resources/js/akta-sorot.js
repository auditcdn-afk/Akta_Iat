/**
 * Membuka item yang dituju sebuah notifikasi.
 *
 * Notifikasi mengarah ke alamat seperti /akta/plan-audit?id=123, tetapi sampai
 * sekarang parameter itu tidak pernah dibaca halaman mana pun. Akibatnya
 * pengguna yang mengetuk notifikasi mendarat di daftar biasa dan harus mencari
 * sendiri baris mana yang dimaksud di antara ratusan baris — notifikasinya
 * memberi tahu ada sesuatu, tapi tidak menunjukkan yang mana.
 *
 * Daftar dimuat setelah halaman siap, jadi barisnya belum tentu ada saat fungsi
 * ini dipanggil. Karena itu pencariannya diulang beberapa kali sebentar,
 * bukan sekali lalu menyerah.
 */

export function idDariUrl(nama = "id") {
    try {
        return new URLSearchParams(window.location.search).get(nama);
    } catch {
        return null;
    }
}

export function sorotItem(id, { attr = "data-sorot-id", percobaan = 16, jeda = 250 } = {}) {
    if (!id) return;

    let sisa = percobaan;
    const cari = () => {
        const aman = window.CSS?.escape ? CSS.escape(String(id)) : String(id).replace(/"/g, "");
        const el = document.querySelector(`[${attr}="${aman}"]`);

        if (!el) {
            if (--sisa > 0) setTimeout(cari, jeda);
            return;
        }

        el.scrollIntoView({ behavior: "smooth", block: "center" });
        el.classList.add("akta-sorot");
        // Sorotan sengaja hilang sendiri: penanda permanen akan menyesatkan
        // kalau halamannya nanti dibuka lagi tanpa lewat notifikasi.
        setTimeout(() => el.classList.remove("akta-sorot"), 3600);
    };

    cari();
}

/** Jalan pintas: baca ?id= dari alamat halaman, lalu soroti barisnya. */
export function sorotDariUrl(opsi) {
    sorotItem(idDariUrl(), opsi);
}
