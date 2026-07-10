import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/akta-shell.js',
                'resources/js/akta-auth.js',
                'resources/js/akta-database.js',
                'resources/js/akta-plan-audit.js',
                'resources/js/akta-task.js',
                'resources/js/akta-audit.js',
                'resources/js/akta-grading.js',
                'resources/js/akta-bu-performance.js',
                'resources/js/akta-pulsa.js',
                'resources/js/akta-mobil-dinas.js',
                'resources/js/akta-dashboard-audit-mandiri.js',
                'resources/js/akta-grafik-beban-sk.js',
                'resources/js/akta-sk.js',
                'resources/js/akta-pica.js',
                'resources/js/akta-rekomendasi.js',
                'resources/js/akta-audit-mandiri.js',
                'resources/js/akta-audit-detail-kas.js',
                'resources/js/akta-report-audit.js',
                'resources/js/akta-users.js',
                'resources/js/akta-monitoring.js',
                'resources/js/akta-menu-management.js',
                'resources/js/akta-profile.js',
            ],
            refresh: true,
        }),
    ],
});
