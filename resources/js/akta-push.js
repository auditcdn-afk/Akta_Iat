/**
 * Notifikasi ke layar HP (Web Push).
 *
 * Alurnya: minta izin -> browser membuat "langganan" (endpoint + sepasang
 * kunci) -> langganan itu dititipkan ke server. Sejak itu server bisa
 * membunyikan HP walau aplikasinya tertutup.
 *
 * Tiga hal yang ditangani di sini karena sering jadi sumber kebingungan:
 * 1. iPhone hanya mengizinkan ini kalau aplikasinya sudah ditambahkan ke Home
 *    Screen — kalau belum, tombolnya menjelaskan itu, bukan diam saja.
 * 2. Izin yang sudah pernah diblokir tidak bisa diminta ulang lewat kode; yang
 *    bisa dilakukan hanya memberi tahu cara membukanya di setelan browser.
 * 3. Browser bisa memperbarui langganan sendiri kapan saja, jadi tiap kali
 *    aplikasi dibuka langganan yang ada disetorkan ulang (murah, sekali POST).
 */

function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

function didukung() {
    return "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
}

/**
 * Safari di iPhone hanya menyediakan Push untuk aplikasi yang sudah dipasang ke
 * Home Screen. Membedakannya penting: pesan "browser Anda tidak mendukung"
 * salah dan membuat pengguna menyerah, padahal cukup dipasang dulu.
 */
function iosBelumDipasang() {
    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const terpasang = window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
    return ios && !terpasang;
}

async function subscriptionSaatIni() {
    const registration = await navigator.serviceWorker.ready;
    return registration.pushManager.getSubscription();
}

export function initPushNotifikasi(authHeaders) {
    const tombol = document.getElementById("pushToggleBtn");
    const keterangan = document.getElementById("pushToggleHint");
    const tombolUji = document.getElementById("pushTestBtn");

    if (!tombol) return;

    let publicKey = null;
    let sibuk = false;

    const setKeterangan = (teks) => {
        if (keterangan) keterangan.textContent = teks;
    };

    const gambar = (aktif) => {
        tombol.textContent = aktif ? "Matikan notifikasi HP" : "Aktifkan notifikasi HP";
        tombol.dataset.aktif = aktif ? "1" : "0";
        tombol.disabled = sibuk;
        if (tombolUji) tombolUji.classList.toggle("hidden", !aktif);
    };

    const matikanTombol = (alasan) => {
        tombol.classList.add("hidden");
        if (tombolUji) tombolUji.classList.add("hidden");
        setKeterangan(alasan);
    };

    const simpanKeServer = (subscription) =>
        fetch("/api/push/subscribe", {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify(subscription.toJSON()),
        }).then((res) => {
            if (!res.ok) throw new Error("gagal menyimpan langganan");
        });

    const aktifkan = async () => {
        const izin = await Notification.requestPermission();

        if (izin === "denied") {
            setKeterangan("Notifikasi diblokir. Buka setelan browser untuk situs ini, lalu izinkan Notifikasi.");
            return false;
        }
        if (izin !== "granted") {
            setKeterangan("Izin belum diberikan.");
            return false;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            // Wajib true di semua browser modern: notifikasi harus selalu
            // terlihat pengguna, tidak boleh dipakai diam-diam di latar belakang.
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });

        await simpanKeServer(subscription);
        setKeterangan("Aktif di perangkat ini.");
        return true;
    };

    const matikan = async () => {
        const subscription = await subscriptionSaatIni();
        if (!subscription) return true;

        await fetch("/api/push/unsubscribe", {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ endpoint: subscription.endpoint }),
        }).catch(() => {
            /* Kalaupun server tidak terhubung, langganan lokal tetap dicabut. */
        });

        await subscription.unsubscribe().catch(() => {});
        setKeterangan("Notifikasi HP dimatikan untuk perangkat ini.");
        return true;
    };

    tombol.addEventListener("click", async (event) => {
        event.stopPropagation();
        if (sibuk) return;

        sibuk = true;
        const sedangAktif = tombol.dataset.aktif === "1";
        tombol.disabled = true;
        setKeterangan(sedangAktif ? "Mematikan..." : "Meminta izin...");

        try {
            const hasil = sedangAktif ? await matikan() : await aktifkan();
            gambar(sedangAktif ? false : hasil);
        } catch {
            setKeterangan("Gagal mengubah pengaturan notifikasi. Coba lagi.");
            gambar(sedangAktif);
        } finally {
            sibuk = false;
            tombol.disabled = false;
        }
    });

    if (tombolUji) {
        tombolUji.addEventListener("click", async (event) => {
            event.stopPropagation();
            const subscription = await subscriptionSaatIni();
            if (!subscription) {
                setKeterangan("Perangkat ini belum berlangganan.");
                return;
            }

            tombolUji.disabled = true;
            setKeterangan("Mengirim notifikasi percobaan...");

            fetch("/api/push/test", {
                method: "POST",
                headers: { ...authHeaders(), "Content-Type": "application/json" },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            })
                .then((res) => res.json().catch(() => ({})))
                .then((payload) => setKeterangan(payload.message || "Notifikasi percobaan dikirim."))
                .catch(() => setKeterangan("Gagal mengirim notifikasi percobaan."))
                .finally(() => {
                    tombolUji.disabled = false;
                });
        });
    }

    if (!didukung()) {
        matikanTombol(
            iosBelumDipasang()
                ? "Di iPhone, tambahkan dulu aplikasi ini ke Home Screen (Bagikan → Tambah ke Layar Utama), baru notifikasi bisa diaktifkan."
                : "Browser ini belum mendukung notifikasi ke layar HP."
        );
        return;
    }

    fetch("/api/push/public-key", { headers: authHeaders() })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error("gagal"))))
        .then(async (payload) => {
            if (!payload.enabled || !payload.publicKey) {
                matikanTombol("Notifikasi HP belum diaktifkan oleh administrator.");
                return;
            }

            publicKey = payload.publicKey;

            const subscription = await subscriptionSaatIni();
            const aktif = Boolean(subscription) && Notification.permission === "granted";
            gambar(aktif);

            if (aktif) {
                setKeterangan("Aktif di perangkat ini.");
                // Langganan bisa diperbarui browser tanpa memberi tahu siapa pun;
                // menyetorkannya ulang tiap kali aplikasi dibuka jauh lebih murah
                // daripada menemukan notifikasi berhenti mengalir tanpa sebab.
                simpanKeServer(subscription).catch(() => {});
            } else if (Notification.permission === "denied") {
                setKeterangan("Notifikasi diblokir di browser ini. Izinkan lewat setelan situs.");
            } else {
                setKeterangan("Dapatkan pemberitahuan di layar HP walau aplikasi tertutup.");
            }
        })
        .catch(() => {
            matikanTombol("Status notifikasi tidak bisa dimuat.");
        });
}
