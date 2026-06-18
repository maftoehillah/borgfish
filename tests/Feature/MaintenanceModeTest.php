<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_show_maintenance_html_when_toggle_is_enabled(): void
    {
        $htmlPath = public_path('maintenance-test.html');

        file_put_contents($htmlPath, '<html><body><h1>Maintenance Borgfish</h1></body></html>');

        config([
            'maintenance-page.enabled' => true,
            'maintenance-page.html_path' => $htmlPath,
            'maintenance-page.status' => 503,
            'maintenance-page.except' => ['up', 'api/tripay/callback'],
        ]);

        $response = $this->get(route('ikans.index'));

        @unlink($htmlPath);

        $response->assertStatus(503);
        $response->assertSee('Maintenance Borgfish');
    }

    public function test_tripay_callback_is_not_blocked_by_maintenance_toggle(): void
    {
        config([
            'maintenance-page.enabled' => true,
            'maintenance-page.html_path' => public_path('maintenance-test.html'),
            'maintenance-page.status' => 503,
            'maintenance-page.except' => ['up', 'api/tripay/callback'],
        ]);

        $response = $this->postJson(route('tripay.callback'), []);

        $this->assertNotSame(503, $response->getStatusCode());
    }

    public function test_database_setting_can_enable_maintenance_without_env_toggle(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'maintenance_enabled'],
            ['group' => 'maintenance', 'value' => '1', 'type' => 'boolean']
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'maintenance_message'],
            ['group' => 'maintenance', 'value' => 'Maintenance dari admin panel.', 'type' => 'longtext']
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'maintenance_except'],
            ['group' => 'maintenance', 'value' => json_encode(['up', 'api/tripay/callback']), 'type' => 'json']
        );

        config([
            'maintenance-page.enabled' => false,
            'maintenance-page.html_path' => public_path('maintenance-test-missing.html'),
        ]);

        $response = $this->get(route('ikans.index'));

        $response->assertStatus(503);
        $response->assertSee('Maintenance dari admin panel.');
    }

    public function test_admin_panel_is_not_blocked_when_maintenance_toggle_is_enabled(): void
    {
        $admin = User::factory()->create([
            'email' => User::SUPERADMIN_EMAIL,
            'is_admin' => true,
            'role' => 'pembeli',
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'maintenance_enabled'],
            ['group' => 'maintenance', 'value' => '1', 'type' => 'boolean']
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'maintenance_message'],
            ['group' => 'maintenance', 'value' => 'Maintenance dari admin panel.', 'type' => 'longtext']
        );

        $this
            ->actingAs($admin)
            ->get('/admin/setting-sistem')
            ->assertOk()
            ->assertSee('Setting Sistem');
    }

    public function test_admin_livewire_requests_are_not_blocked_when_maintenance_toggle_is_enabled(): void
    {
        $admin = User::factory()->create([
            'email' => User::SUPERADMIN_EMAIL,
            'is_admin' => true,
            'role' => 'pembeli',
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'maintenance_enabled'],
            ['group' => 'maintenance', 'value' => '1', 'type' => 'boolean']
        );

        $response = $this
            ->actingAs($admin)
            ->withHeader('referer', url('/admin/setting-sistem'))
            ->post('/livewire-c743e067/update', []);

        $this->assertNotSame(503, $response->getStatusCode());
    }

    public function test_public_storage_routes_are_not_blocked_when_maintenance_toggle_is_enabled(): void
    {
        Storage::disk('public')->put('maintenance-check/test.txt', 'ok');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'maintenance_enabled'],
            ['group' => 'maintenance', 'value' => '1', 'type' => 'boolean']
        );

        $response = $this->get(route('media.fallback', ['path' => 'maintenance-check/test.txt']));

        $response->assertOk();
        $this->assertNotSame(503, $response->getStatusCode());
    }
}
