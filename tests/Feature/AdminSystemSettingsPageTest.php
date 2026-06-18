<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_friendly_system_settings_page(): void
    {
        $admin = User::factory()->create([
            'email' => User::SUPERADMIN_EMAIL,
            'is_admin' => true,
            'role' => 'pembeli',
        ]);

        $this
            ->actingAs($admin)
            ->get('/admin/setting-sistem')
            ->assertOk()
            ->assertSee('Identitas Website')
            ->assertSee('Social Media')
            ->assertSee('Halaman Informasi')
            ->assertSee('Metode Pembayaran Default')
            ->assertSee('Settlement Seller')
            ->assertSee('Notifikasi & OTP')
            ->assertSee('Template Chat Hubungi Admin')
            ->assertSee('Maintenance')
            ->assertSee('Nomor WhatsApp Admin')
            ->assertDontSee('Key')
            ->assertDontSee('Tipe');
    }

    public function test_legacy_raw_system_setting_resource_redirects_to_friendly_page(): void
    {
        $admin = User::factory()->create([
            'email' => User::SUPERADMIN_EMAIL,
            'is_admin' => true,
            'role' => 'pembeli',
        ]);

        $this
            ->actingAs($admin)
            ->get('/admin/system-settings')
            ->assertRedirect('/admin/setting-sistem');
    }
}
