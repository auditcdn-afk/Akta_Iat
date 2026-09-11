<?php

namespace Tests\Feature;

use App\Jobs\KirimPushNotification;
use App\Models\AppNotification;
use App\Models\PlanAudit;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\WebPush\Base64Url;
use App\Services\WebPush\P256;
use App\Services\WebPush\PushEncryptor;
use App\Services\WebPush\Vapid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Notifikasi push ke layar HP (Web Push, RFC 8291/8292).
 *
 * Yang dijaga di sini ada tiga lapis:
 *
 * 1. KRIPTOGRAFI — isi notifikasi harus benar-benar bisa dibuka penerimanya.
 *    Salah satu byte saja pada urutan HKDF atau susunan rekaman, dan browser
 *    menolak diam-diam tanpa pesan kesalahan apa pun.
 * 2. PERFORMA — pengiriman TIDAK BOLEH ikut menumpang di dalam permintaan
 *    pengguna. Satu approve bisa memberi tahu belasan perangkat, dan tiap
 *    perangkat berarti satu permintaan HTTPS; kalau inline, tombol Approve
 *    menggantung berdetik-detik.
 * 3. KEBERSIHAN DATA — langganan yang sudah mati harus hilang sendiri, dan
 *    langganan milik orang lain tidak boleh bisa disentuh.
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI_PUBLIK_UJI = 'BIK_8QpcQo5OB-A1xCowAWav078WDbyHqvhlOlekiiZuiKAtyx9mx4ZGv9oeeKQvi-BPEElxMYRT9uALsPXA7LU';
    private const KUNCI_PRIVAT_UJI = '5NoNjnuaYBIAHCo47mIyOUwhIpwGassFODxpZzOqleA';

    private function pasangKunciVapid(): void
    {
        config([
            'webpush.public_key' => self::KUNCI_PUBLIK_UJI,
            'webpush.private_key' => self::KUNCI_PRIVAT_UJI,
            'webpush.subject' => 'mailto:auditcdn@gmail.com',
        ]);
    }

    private function buatPlan(string $noSpt): PlanAudit
    {
        return PlanAudit::query()->create([
            'no_spt' => $noSpt,
            'cabang' => 'CSC TEST',
            'cabang_area' => 'AREA TEST',
            'jenis_audit' => 'Audit Full SO',
            'kepala_tim' => 'Abdul Aziz',
            'tim' => ['Abdul Aziz'],
            'status' => 'draft',
        ]);
    }

    /** Perankan browser: pasangan kunci + auth secret seperti hasil PushManager.subscribe(). */
    private function langgananPalsu(User $user, string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        $ua = P256::pasanganKunciBaru();
        $auth = random_bytes(16);

        $baris = PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => Base64Url::encode($ua['publik']),
            'auth' => Base64Url::encode($auth),
        ]);

        return ['baris' => $baris, 'privat' => $ua['privat'], 'publik' => $ua['publik'], 'auth' => $auth];
    }

    /**
     * Pembuka independen, ditulis ulang dari RFC 8291 — bukan memanggil kode
     * produksi — supaya tes ini benar-benar membuktikan formatnya, bukan sekadar
     * bahwa kode itu konsisten dengan dirinya sendiri.
     */
    private function bukaRekaman(string $badan, string $privatUa, string $publikUa, string $authUa): string
    {
        $salt = substr($badan, 0, 16);
        $panjangKunci = ord($badan[20]);
        $kunciPengirim = substr($badan, 21, $panjangKunci);
        $muatan = substr($badan, 21 + $panjangKunci);

        $rahasia = openssl_pkey_derive(
            P256::kunciPublik($kunciPengirim),
            P256::kunciPrivat($privatUa, $publikUa)
        );

        $ikm = hash_hkdf('sha256', $rahasia, 32, "WebPush: info\x00".$publikUa.$kunciPengirim, $authUa);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        $tag = substr($muatan, -16);
        $ciphertext = substr($muatan, 0, -16);

        $terbuka = openssl_decrypt($ciphertext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        $this->assertNotFalse($terbuka, 'Rekaman gagal dibuka — kunci atau nonce tidak cocok.');

        // Byte terakhir adalah penanda rekaman terakhir (0x02), bukan bagian isi.
        $this->assertSame("\x02", substr($terbuka, -1));

        return substr($terbuka, 0, -1);
    }

    // ── Kriptografi ──────────────────────────────────────────────────────────

    public function test_isi_notifikasi_bisa_dibuka_kembali_oleh_penerimanya(): void
    {
        $ua = P256::pasanganKunciBaru();
        $auth = random_bytes(16);
        $pesan = json_encode(['title' => 'Giliran approve', 'body' => 'Plan 0125 menunggu Anda']);

        $badan = PushEncryptor::enkripsi($pesan, $ua['publik'], $auth);

        $this->assertSame($pesan, $this->bukaRekaman($badan, $ua['privat'], $ua['publik'], $auth));
    }

    public function test_susunan_rekaman_sesuai_rfc_8188(): void
    {
        $ua = P256::pasanganKunciBaru();
        $badan = PushEncryptor::enkripsi('halo', $ua['publik'], random_bytes(16));

        $this->assertSame(16, strlen(substr($badan, 0, 16)), 'salt harus 16 byte');
        $this->assertSame(4096, unpack('N', substr($badan, 16, 4))[1], 'ukuran rekaman diumumkan di 4 byte big-endian');
        $this->assertSame(65, ord($badan[20]), 'panjang kunci publik pengirim');
        $this->assertSame("\x04", $badan[21], 'kunci publik harus bentuk tak-terkompresi');
    }

    public function test_dua_kiriman_tidak_pernah_memakai_salt_yang_sama(): void
    {
        $ua = P256::pasanganKunciBaru();
        $auth = random_bytes(16);

        $satu = PushEncryptor::enkripsi('halo', $ua['publik'], $auth);
        $dua = PushEncryptor::enkripsi('halo', $ua['publik'], $auth);

        $this->assertNotSame(substr($satu, 0, 16), substr($dua, 0, 16));
        $this->assertNotSame($satu, $dua, 'Isi sama tidak boleh menghasilkan ciphertext yang sama.');
    }

    public function test_token_vapid_ditandatangani_dan_menyebut_server_push_yang_dituju(): void
    {
        $this->pasangKunciVapid();

        $header = Vapid::dariKonfigurasi()->headerAuthorization('https://fcm.googleapis.com/fcm/send/abc123');

        $this->assertMatchesRegularExpression('/^vapid t=[\w\-]+\.[\w\-]+\.[\w\-]+, k=[\w\-]+$/', $header);

        preg_match('/t=([^,]+)/', $header, $cocok);
        [$h, $klaim, $tandaTangan] = explode('.', $cocok[1]);

        $isiKlaim = json_decode(Base64Url::decode($klaim), true);
        $this->assertSame('https://fcm.googleapis.com', $isiKlaim['aud'], 'aud harus origin, bukan URL endpoint penuh');
        $this->assertSame('mailto:auditcdn@gmail.com', $isiKlaim['sub']);
        $this->assertGreaterThan(time(), $isiKlaim['exp']);
        $this->assertLessThanOrEqual(time() + 24 * 3600, $isiKlaim['exp'], 'RFC 8292 membatasi umur token 24 jam');

        $mentah = Base64Url::decode($tandaTangan);
        $this->assertSame(64, strlen($mentah), 'ES256 menuntut R||S tepat 64 byte');

        $this->assertSame(
            1,
            openssl_verify(
                "{$h}.{$klaim}",
                $this->mentahKeDer($mentah),
                P256::kunciPublik(Base64Url::decode(self::KUNCI_PUBLIK_UJI)),
                OPENSSL_ALGO_SHA256
            ),
            'Tanda tangan tidak cocok dengan kunci publik VAPID yang diumumkan.'
        );
    }

    /** Kebalikan dari P256::derKeTandaTanganMentah(), ditulis terpisah untuk verifikasi. */
    private function mentahKeDer(string $mentah): string
    {
        $bungkus = function (string $angka): string {
            $angka = ltrim($angka, "\x00");
            if ($angka === '' || ord($angka[0]) >= 0x80) {
                $angka = "\x00".$angka;
            }

            return "\x02".chr(strlen($angka)).$angka;
        };

        $isi = $bungkus(substr($mentah, 0, 32)).$bungkus(substr($mentah, 32));

        return "\x30".chr(strlen($isi)).$isi;
    }

    // ── Performa: pengiriman tidak boleh menumpang permintaan pengguna ───────

    public function test_membuat_notifikasi_hanya_mengantrekan_bukan_mengirim_langsung(): void
    {
        $this->pasangKunciVapid();
        Queue::fake();
        Http::fake();

        $user = User::factory()->create(['role' => 'auditor']);
        $this->langgananPalsu($user);

        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'plan_audit_step',
            'title' => 'Giliran memproses plan audit',
        ]);
        // Notifikasi dibuat lewat dispatcher-lah yang mengantre; pembuatan
        // langsung seperti di atas sengaja TIDAK memicu apa pun.
        Queue::assertNothingPushed();

        NotificationDispatcher::notifyPlanAuditStep(
            $this->buatPlan('0001/TEST/SPT-IAT')
        );

        Queue::assertPushed(KirimPushNotification::class);
        Http::assertNothingSent();
    }

    public function test_tanpa_kunci_vapid_tidak_ada_yang_diantrekan(): void
    {
        config(['webpush.public_key' => '', 'webpush.private_key' => '']);
        Queue::fake();

        $user = User::factory()->create(['role' => 'auditor']);
        $this->langgananPalsu($user);

        NotificationDispatcher::notifyPlanAuditStep(
            $this->buatPlan('0002/TEST/SPT-IAT')
        );

        Queue::assertNothingPushed();
    }

    // ── Job pengirim ─────────────────────────────────────────────────────────

    public function test_job_mengirim_ke_semua_perangkat_milik_penerima(): void
    {
        $this->pasangKunciVapid();
        Http::fake(['*' => Http::response('', 201)]);

        $user = User::factory()->create(['role' => 'auditor']);
        $this->langgananPalsu($user, 'https://fcm.googleapis.com/fcm/send/hp');
        $this->langgananPalsu($user, 'https://updates.push.services.mozilla.com/wpush/v2/laptop');

        $notifikasi = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'plan_audit_step',
            'title' => 'Giliran memproses plan audit',
            'message' => 'Plan 0125 menunggu Anda.',
            'url' => '/akta/plan-audit?id=1',
        ]);

        (new KirimPushNotification($notifikasi->id))->handle();

        Http::assertSentCount(2);
        $this->assertNotNull($user->pushSubscriptions()->first()->last_success_at);
    }

    public function test_notifikasi_yang_sudah_terbaca_tidak_lagi_membunyikan_hp(): void
    {
        $this->pasangKunciVapid();
        Http::fake();

        $user = User::factory()->create(['role' => 'auditor']);
        $this->langgananPalsu($user);

        $notifikasi = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'plan_audit_step',
            'title' => 'Giliran memproses plan audit',
            'read_at' => now(),
        ]);

        (new KirimPushNotification($notifikasi->id))->handle();

        Http::assertNothingSent();
    }

    public function test_langganan_yang_sudah_mati_dihapus_sendiri(): void
    {
        $this->pasangKunciVapid();
        Http::fake(['*' => Http::response('', 410)]);

        $user = User::factory()->create(['role' => 'auditor']);
        $this->langgananPalsu($user);

        $notifikasi = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'plan_audit_step',
            'title' => 'Giliran memproses plan audit',
        ]);

        (new KirimPushNotification($notifikasi->id))->handle();

        $this->assertSame(0, PushSubscription::count(), 'Langganan yang dijawab 410 Gone harus dibuang.');
    }

    public function test_server_push_bermasalah_tidak_menghapus_langganan(): void
    {
        $this->pasangKunciVapid();
        Http::fake(['*' => Http::response('server sedang sibuk', 503)]);

        $user = User::factory()->create(['role' => 'auditor']);
        $this->langgananPalsu($user);

        $notifikasi = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'plan_audit_step',
            'title' => 'Giliran memproses plan audit',
        ]);

        (new KirimPushNotification($notifikasi->id))->handle();

        $this->assertSame(1, PushSubscription::count());
        $this->assertNotNull(PushSubscription::first()->last_failed_at);
    }

    // ── API langganan ────────────────────────────────────────────────────────

    public function test_kunci_publik_tidak_dibagikan_kalau_server_belum_disiapkan(): void
    {
        config(['webpush.public_key' => '', 'webpush.private_key' => '']);
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->getJson('/api/push/public-key')
            ->assertOk()
            ->assertJson(['enabled' => false, 'publicKey' => null]);
    }

    public function test_berlangganan_lalu_berlangganan_lagi_tidak_menggandakan_perangkat(): void
    {
        $this->pasangKunciVapid();
        $user = User::factory()->create(['role' => 'auditor']);
        Sanctum::actingAs($user);

        $muatan = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => Base64Url::encode(P256::pasanganKunciBaru()['publik']), 'auth' => Base64Url::encode(random_bytes(16))],
        ];

        $this->postJson('/api/push/subscribe', $muatan)->assertOk();
        $this->postJson('/api/push/subscribe', $muatan)->assertOk();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($user->id, PushSubscription::first()->user_id);
    }

    public function test_perangkat_yang_dipakai_akun_lain_berpindah_kepemilikan(): void
    {
        $this->pasangKunciVapid();
        $lama = User::factory()->create(['role' => 'auditor']);
        $baru = User::factory()->create(['role' => 'manajer']);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/hp-bersama';
        $this->langgananPalsu($lama, $endpoint);

        Sanctum::actingAs($baru);
        $this->postJson('/api/push/subscribe', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => Base64Url::encode(P256::pasanganKunciBaru()['publik']), 'auth' => Base64Url::encode(random_bytes(16))],
        ])->assertOk();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($baru->id, PushSubscription::first()->user_id,
            'Perangkat yang dipakai bergantian harus pindah pemilik, bukan mengirim notifikasi ke orang sebelumnya.');
    }

    public function test_tidak_bisa_mencabut_langganan_milik_orang_lain(): void
    {
        $this->pasangKunciVapid();
        $korban = User::factory()->create(['role' => 'auditor']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/punya-orang-lain';
        $this->langgananPalsu($korban, $endpoint);

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
        $this->postJson('/api/push/unsubscribe', ['endpoint' => $endpoint])->assertOk();

        $this->assertSame(1, PushSubscription::count(), 'Langganan orang lain tidak boleh ikut terhapus.');
    }

    public function test_notifikasi_percobaan_melaporkan_hasil_sebenarnya(): void
    {
        $this->pasangKunciVapid();
        Http::fake(['*' => Http::response('', 201)]);

        $user = User::factory()->create(['role' => 'auditor']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';
        $this->langgananPalsu($user, $endpoint);
        Sanctum::actingAs($user);

        $this->postJson('/api/push/test', ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_notifikasi_percobaan_untuk_perangkat_asing_ditolak(): void
    {
        $this->pasangKunciVapid();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->postJson('/api/push/test', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/entah'])
            ->assertStatus(404);
    }
}
