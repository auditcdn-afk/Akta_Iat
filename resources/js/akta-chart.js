// Chart.js bersama untuk halaman Dashboard.
//
// Sebelumnya tiap modul grafik meng-import "chart.js/auto", yang mendaftarkan
// SELURUH isi Chart.js — termasuk jenis grafik yang tidak pernah dipakai
// aplikasi ini (radar, polarArea, pie, bubble, scatter) beserta skala
// radial/logaritmik/waktu-nya. Dashboard hanya memakai tiga jenis: bar,
// doughnut, dan line.
//
// Di sini komponennya didaftarkan satu kali secara eksplisit, lalu ketiga modul
// grafik (Audit Mandiri, Beban SK, Realisasi Dinas) meng-import Chart dari file
// ini. Karena file ini jadi chunk bersama, Chart.js tetap diunduh sekali saja —
// bedanya sekarang isinya hanya yang benar-benar dipakai.
//
// Kalau nanti ada grafik jenis baru, daftarkan controller + element + scale-nya
// di sini juga, kalau tidak Chart.js akan melempar error "not a registered
// controller" saat grafik itu dibuat.

import {
    Chart,
    // Jenis grafik yang dipakai
    BarController,
    DoughnutController,
    LineController,
    // Elemen penyusun ketiga jenis di atas
    ArcElement,
    BarElement,
    LineElement,
    PointElement,
    // Sumbu: kategori (label) + linear (nilai). Grafik batang horizontal
    // (indexAxis: "y") memakai pasangan skala yang sama, hanya bertukar posisi.
    CategoryScale,
    LinearScale,
    // Plugin yang dipakai lewat opsi `plugins: { legend, tooltip }` dan
    // `fill: true` pada dataset garis.
    Filler,
    Legend,
    Tooltip,
} from "chart.js";

Chart.register(
    BarController,
    DoughnutController,
    LineController,
    ArcElement,
    BarElement,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Filler,
    Legend,
    Tooltip,
);

export { Chart };
export default Chart;
