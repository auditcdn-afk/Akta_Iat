// Pemuatan data per tab Dashboard, ditunda sampai tabnya benar-benar dibuka.
//
// Dashboard punya tiga tab (Audit Mandiri, Beban SK, Realisasi Dinas) tapi hanya
// satu yang terlihat. Sebelumnya ketiga modul grafik menembak endpoint rekap
// masing-masing sekaligus saat halaman dimuat, jadi dua dari tiga request itu
// mengisi panel yang sedang disembunyikan. Padahal endpoint rekap adalah yang
// paling berat di aplikasi ini: ia memindai seluruh tabel untuk menghasilkan
// data grafik. Tiga request berat berbarengan juga saling berebut worker PHP
// yang jumlahnya terbatas di hosting bersama, sehingga halaman lain ikut
// tertahan.
//
// Sekarang tab yang terlihat saat halaman dibuka memuat datanya seperti biasa,
// dan dua tab lainnya baru memuat ketika dibuka — atau sedikit lebih awal,
// begitu kursor menyentuh tombol tabnya, supaya datanya sudah siap saat diklik.

/**
 * Jalankan `load` satu kali: segera bila panel sudah terlihat, atau nanti saat
 * panel ditampilkan / tombol tabnya disentuh kursor.
 *
 * @param {string} panelId  id elemen panel tab (mis. "dashTabBebanSk")
 * @param {() => any} load  pemuat data tab tersebut
 */
export function whenTabVisible(panelId, load) {
    const panel = document.getElementById(panelId);

    if (!panel) {
        return;
    }

    let sudahDimuat = false;
    const muat = () => {
        if (sudahDimuat) {
            return;
        }
        sudahDimuat = true;
        observer.disconnect();
        load();
    };

    // Mengamati atribut class, bukan menempel ke tombol tab tertentu: panel bisa
    // saja ditampilkan lewat jalur lain (deep link, tombol lain), dan cara ini
    // tetap menangkapnya.
    const observer = new MutationObserver(() => {
        if (!panel.classList.contains("hidden")) {
            muat();
        }
    });

    if (!panel.classList.contains("hidden")) {
        muat();
        return;
    }

    observer.observe(panel, { attributes: true, attributeFilter: ["class"] });

    // Ambil data sejak kursor menyentuh tombol tabnya, supaya isinya sudah siap
    // begitu tab diklik. Nama tab pada tombol adalah id panel tanpa awalan
    // "dashTab" dan huruf pertamanya kecil (dashTabBebanSk -> bebanSk).
    const namaTab = panelId.replace(/^dashTab/, "");
    const kunci = namaTab.charAt(0).toLowerCase() + namaTab.slice(1);

    document
        .querySelector(`.dashTabBtn[data-dash-tab="${kunci}"]`)
        ?.addEventListener("pointerenter", muat, { once: true });
}
