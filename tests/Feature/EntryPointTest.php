<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pintu masuk aplikasi setelah scaffolding Laravel Breeze dihapus.
 *
 * Breeze dulu menyediakan rute /login, /register, /dashboard, dan /profile yang
 * tidak dipakai sama sekali — aplikasi punya alur sendiri lewat /akta/login
 * dengan token Sanctum. Tes ini menjaga agar penghapusannya tidak diam-diam
 * memutus jalur masuk yang sebenarnya.
 */
class EntryPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_depan_mengarahkan_ke_login_akta(): void
    {
        $this->get('/')->assertRedirect('/akta/login');
    }

    public function test_halaman_login_akta_bisa_dibuka(): void
    {
        $this->get('/akta/login')->assertOk();
    }

    /**
     * Sebelum ini, pengunjung yang belum login diarahkan ke rute bernama 'login'
     * bawaan Breeze. Rute itu sudah tidak ada, jadi pengalihannya harus menunjuk
     * halaman login aplikasi — kalau tidak, Laravel melempar RouteNotFoundException
     * (error 500) alih-alih mengarahkan ke halaman login.
     */
    public function test_kunjungan_browser_tanpa_login_diarahkan_ke_login_akta(): void
    {
        $this->get('/api/plans')->assertRedirect('/akta/login');
    }

    /** Request API yang meminta JSON tetap dapat 401, bukan pengalihan. */
    public function test_request_api_tanpa_token_tetap_dapat_401(): void
    {
        $this->getJson('/api/plans')->assertUnauthorized();
    }

    /** Rute bawaan Breeze benar-benar sudah tidak terdaftar. */
    public function test_rute_bawaan_breeze_sudah_tidak_ada(): void
    {
        foreach (['/register', '/forgot-password', '/dashboard'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
