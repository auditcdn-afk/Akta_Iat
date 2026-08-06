// Cache sesi/user bersama untuk semua halaman AKTA.
//
// Objek user lengkap sudah disimpan saat login (akta-auth.js), jadi halaman
// tidak perlu memanggil /api/auth/me hanya untuk tahu role/nama user. Sebelum
// ini, tiap halaman menembak /api/auth/me dua kali (sekali dari akta-shell.js,
// sekali dari skrip halaman) dan semua pemuatan data lain menunggu request itu
// selesai — satu round-trip penuh terbuang sebelum tabel mulai dimuat.
//
// Kebenaran data tetap dijaga: akta-shell.js memvalidasi sesi ke server di
// latar belakang tiap halaman dibuka, lalu menyegarkan cache ini bila data
// user di server ternyata berbeda (mis. role diubah admin).

export const SESSION_KEY = "akta_session";

export function readSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

/** User hasil login, atau null bila sesi belum ada / formatnya lama. */
export function cachedUser() {
    return readSession()?.user ?? null;
}

/** Simpan ulang user ke sesi — dipakai setelah validasi ke server. */
export function updateCachedUser(user) {
    const session = readSession();

    if (!session) {
        return;
    }

    session.user = user;

    try {
        sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
    } catch {
        /* Kuota sessionStorage penuh — cache tidak wajib, abaikan. */
    }
}
