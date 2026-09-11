<?php

namespace App\Console\Commands;

use App\Services\WebPush\Vapid;
use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    /**
     * Membuat sepasang kunci VAPID untuk notifikasi push.
     *
     * Sengaja hanya MENCETAK, tidak menulis .env: .env produksi ada di server
     * dan tidak pernah ikut ter-deploy, jadi menulisnya di sini cuma akan
     * mengubah berkas lokal dan memberi rasa aman palsu.
     */
    protected $signature = 'akta:vapid-keys';

    protected $description = 'Buat sepasang kunci VAPID untuk notifikasi push (salin hasilnya ke .env server).';

    public function handle(): int
    {
        $kunci = Vapid::pasanganKunciBaru();

        $this->newLine();
        $this->info('Salin tiga baris ini ke .env pada SERVER, lalu panggil /deploy/clear-cache:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$kunci['publik']);
        $this->line('VAPID_PRIVATE_KEY='.$kunci['privat']);
        $this->line('VAPID_SUBJECT=mailto:auditcdn@gmail.com');
        $this->newLine();
        $this->warn('Simpan kunci privat seperti kata sandi, dan JANGAN mengganti pasangan ini');
        $this->warn('setelah ada yang berlangganan — semua langganan lama akan tertolak dan');
        $this->warn('pengguna harus mengaktifkan notifikasinya ulang.');
        $this->newLine();

        return self::SUCCESS;
    }
}
