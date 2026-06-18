<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_setting_cache_is_cleared_after_admin_style_update(): void
    {
        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => 'site_address'],
            [
                'group' => 'site',
                'value' => 'Alamat Lama',
                'type' => 'text',
            ]
        );

        $service = app(SystemSettingService::class);

        $this->assertSame('Alamat Lama', $service->get('site_address'));

        $setting->update([
            'value' => 'Alamat Baru',
        ]);

        $this->assertSame('Alamat Baru', $service->get('site_address'));
    }

    public function test_contact_page_uses_contact_button_without_showing_legacy_admin_number(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'site_admin_contact'],
            ['group' => 'site', 'value' => '+62 812-3456-7890', 'type' => 'string']
        );

        $this->get(route('pages.contact'))
            ->assertOk()
            ->assertSee('Chat Admin')
            ->assertDontSee('+62 812-3456-7890');
    }

    public function test_contact_page_hides_whatsapp_button_when_admin_number_is_empty(): void
    {
        SystemSetting::query()->whereIn('key', ['site_admin_contact', 'admin_contact'])->delete();

        $this->get(route('pages.contact'))
            ->assertOk()
            ->assertDontSee('Hubungi Admin Borgfish');
    }

    public function test_social_media_links_can_be_loaded_from_system_settings(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'social_facebook_url'],
            ['group' => 'site', 'value' => 'https://facebook.com/borgfish', 'type' => 'string']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'social_instagram_url'],
            ['group' => 'site', 'value' => 'https://instagram.com/borgfish', 'type' => 'string']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'social_tiktok_url'],
            ['group' => 'site', 'value' => 'https://www.tiktok.com/@borgfish', 'type' => 'string']
        );

        $this->get(route('ikans.index'))
            ->assertOk()
            ->assertSee('https://facebook.com/borgfish', false)
            ->assertSee('https://instagram.com/borgfish', false)
            ->assertSee('https://www.tiktok.com/@borgfish', false);
    }

    public function test_social_media_icons_show_placeholders_when_links_are_empty(): void
    {
        SystemSetting::query()->whereIn('key', [
            'social_facebook_url',
            'social_instagram_url',
            'social_tiktok_url',
        ])->delete();

        $this->get(route('ikans.index'))
            ->assertOk()
            ->assertSee('Facebook segera hadir')
            ->assertSee('Instagram segera hadir')
            ->assertSee('TikTok segera hadir')
            ->assertSee('Akun resmi sedang disiapkan.');
    }

    public function test_system_setting_put_creates_audit_log_for_changed_value(): void
    {
        $admin = User::factory()->create([
            'email' => User::SUPERADMIN_EMAIL,
            'is_admin' => true,
            'role' => 'pembeli',
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'site_name'],
            ['group' => 'site', 'value' => 'Borgfish', 'type' => 'string']
        );

        $this->actingAs($admin);

        app(SystemSettingService::class)->put('site_name', 'Borgfish Baru', 'site', 'string');

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'action' => 'system_setting.updated',
            'resource_type' => 'system_settings',
        ]);

        $log = AuditLog::query()->where('action', 'system_setting.updated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('site_name', data_get($log->payload, 'key'));
        $this->assertSame('Borgfish', data_get($log->payload, 'old'));
        $this->assertSame('Borgfish Baru', data_get($log->payload, 'new'));
    }

    public function test_whatsapp_admin_contact_template_can_be_rendered_with_context(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'whatsapp_admin_contact_template'],
            [
                'group' => 'notification',
                'value' => 'Halo {site_name} | {user_role} | {user_name} | {transaction_id}',
                'type' => 'longtext',
            ]
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'site_name'],
            [
                'group' => 'site',
                'value' => 'Borgfish',
                'type' => 'string',
            ]
        );

        $message = app(SystemSettingService::class)->renderWhatsappAdminContactMessage([
            'user_role' => 'Penjual',
            'user_name' => 'Sabiq',
            'transaction_id' => 'TRX-001',
        ]);

        $this->assertSame('Halo Borgfish | Penjual | Sabiq | TRX-001', $message);
    }

    public function test_whatsapp_admin_contact_template_omits_empty_label_lines(): void
    {
        $message = app(SystemSettingService::class)->renderWhatsappAdminContactMessage([
            'user_role' => 'Pembeli',
            'user_name' => 'buyer',
            'user_email' => 'buyer@example.com',
            'current_url' => 'http://127.0.0.1:8000/syarat-ketentuan',
        ]);

        $this->assertStringContainsString('Role: Pembeli', $message);
        $this->assertStringContainsString('Nama: buyer', $message);
        $this->assertStringContainsString('Email: buyer@example.com', $message);
        $this->assertStringContainsString('Halaman: http://127.0.0.1:8000/syarat-ketentuan', $message);
        $this->assertStringNotContainsString('No. Telepon:', $message);
        $this->assertStringNotContainsString('ID Transaksi:', $message);
    }
}
