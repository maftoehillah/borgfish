<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Ikan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminViewModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_toggle_from_buyer_mode_redirects_to_seller_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'sabiqmaftu@gmail.com',
            'role' => 'pembeli',
            'is_admin' => true,
            'user_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'otp_verified_user_id' => $admin->id,
                'superadmin_view_mode' => 'PEMBELI',
            ])
            ->postJson(route('admin.toggle_view_mode'));

        $response->assertOk()
            ->assertJson([
                'mode' => 'PENJUAL',
                'redirect' => route('penjual.dashboard'),
            ]);

        $this->assertSame('PENJUAL', session('superadmin_view_mode'));
    }

    public function test_superadmin_toggle_from_seller_mode_redirects_to_marketplace(): void
    {
        $admin = User::factory()->create([
            'email' => 'sabiqmaftu@gmail.com',
            'role' => 'pembeli',
            'is_admin' => true,
            'user_status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'otp_verified_user_id' => $admin->id,
                'superadmin_view_mode' => 'PENJUAL',
            ])
            ->postJson(route('admin.toggle_view_mode'));

        $response->assertOk()
            ->assertJson([
                'mode' => 'PEMBELI',
                'redirect' => route('ikans.index'),
            ]);

        $this->assertSame('PEMBELI', session('superadmin_view_mode'));
    }

    public function test_superadmin_toggle_from_form_redirects_with_feedback(): void
    {
        $admin = $this->makeSuperAdmin('pembeli');

        $response = $this->actingAs($admin)
            ->withSession([
                'otp_verified_user_id' => $admin->id,
                'superadmin_view_mode' => 'PEMBELI',
            ])
            ->post(route('admin.toggle_view_mode'));

        $response->assertRedirect(route('penjual.dashboard'));
        $response->assertSessionHas('sukses', 'Mode tampilan admin diganti ke mode Penjual.');
        $this->assertSame('PENJUAL', session('superadmin_view_mode'));
    }

    public function test_non_admin_cannot_toggle_superadmin_view_mode(): void
    {
        $buyer = User::factory()->create([
            'role' => 'pembeli',
            'is_admin' => false,
            'user_status' => 'active',
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->postJson(route('admin.toggle_view_mode'));

        $response->assertForbidden();
    }

    public function test_non_whitelisted_admin_flag_cannot_toggle_superadmin_view_mode(): void
    {
        $buyer = User::factory()->create([
            'email' => 'not-admin@example.test',
            'role' => 'pembeli',
            'is_admin' => true,
            'user_status' => 'active',
        ]);

        $response = $this->actingAs($buyer)
            ->withSession(['otp_verified_user_id' => $buyer->id])
            ->postJson(route('admin.toggle_view_mode'));

        $response->assertForbidden();
        $this->assertFalse($buyer->fresh()->isPanelAdmin());
    }

    public function test_admin_roles_have_scoped_panel_permissions(): void
    {
        config([
            'marketplace.admin_whitelist' => [
                'finance@example.test',
                'ops@example.test',
                'support@example.test',
                'sabiqmaftu@gmail.com',
            ],
        ]);

        $finance = User::factory()->create([
            'email' => 'finance@example.test',
            'is_admin' => true,
            'admin_role' => User::ADMIN_ROLE_FINANCE,
        ]);
        $ops = User::factory()->create([
            'email' => 'ops@example.test',
            'is_admin' => true,
            'admin_role' => User::ADMIN_ROLE_OPS,
        ]);
        $support = User::factory()->create([
            'email' => 'support@example.test',
            'is_admin' => true,
            'admin_role' => User::ADMIN_ROLE_SUPPORT,
        ]);
        $superadmin = User::factory()->create([
            'email' => 'sabiqmaftu@gmail.com',
            'is_admin' => true,
            'admin_role' => User::ADMIN_ROLE_SUPPORT,
        ]);

        $this->assertTrue($finance->canAdmin('finance'));
        $this->assertTrue($finance->canAdmin('support'));
        $this->assertFalse($finance->canAdmin('ops'));
        $this->assertFalse($finance->canAdmin('settings'));

        $this->assertTrue($ops->canAdmin('ops'));
        $this->assertTrue($ops->canAdmin('support'));
        $this->assertFalse($ops->canAdmin('finance'));
        $this->assertFalse($ops->canAdmin('settings'));

        $this->assertTrue($support->canAdmin('support'));
        $this->assertFalse($support->canAdmin('ops'));
        $this->assertFalse($support->canAdmin('finance'));
        $this->assertFalse($support->canAdmin('settings'));

        $this->assertTrue($superadmin->canAdmin('finance'));
        $this->assertTrue($superadmin->canAdmin('ops'));
        $this->assertTrue($superadmin->canAdmin('support'));
        $this->assertTrue($superadmin->canAdmin('settings'));
        $this->assertTrue($superadmin->canAdmin('admin_users'));
    }

    public function test_superadmin_buyer_mode_sees_bid_form_even_when_base_role_is_seller(): void
    {
        $admin = $this->makeSuperAdmin('penjual');
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
        $lot = $this->makeLot($seller, [
            'nama_ikan' => 'Lot Milik Seller Lain',
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'otp_verified_user_id' => $admin->id,
                'superadmin_view_mode' => 'PEMBELI',
            ])
            ->get(route('ikans.show', $lot));

        $response->assertOk();
        $response->assertSee('Pasang Bid');
        $response->assertDontSee('Akun penjual tidak bisa melakukan bid.');
    }

    public function test_superadmin_buyer_mode_can_bid_lot_owned_by_other_seller(): void
    {
        $admin = $this->makeSuperAdmin('penjual');
        $seller = User::factory()->create([
            'role' => 'penjual',
            'is_admin' => false,
        ]);
        $lot = $this->makeLot($seller);

        $response = $this->actingAs($admin)
            ->withSession([
                'otp_verified_user_id' => $admin->id,
                'superadmin_view_mode' => 'PEMBELI',
            ])
            ->post(route('bid.store', $lot), [
                'jumlah_bid' => 110_000,
                'return_url' => route('ikans.show', $lot),
            ]);

        $response->assertRedirect(route('ikans.show', $lot));

        $this->assertDatabaseHas('bids', [
            'ikan_id' => $lot->id,
            'user_id' => $admin->id,
            'jumlah_bid' => 110_000,
        ]);
    }

    public function test_superadmin_buyer_mode_cannot_bid_own_uploaded_lot(): void
    {
        $admin = $this->makeSuperAdmin('penjual');
        $lot = $this->makeLot($admin, [
            'nama_ikan' => 'Lot Upload Admin Sendiri',
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'otp_verified_user_id' => $admin->id,
                'superadmin_view_mode' => 'PEMBELI',
            ])
            ->get(route('ikans.show', $lot));

        $response->assertOk();
        $response->assertSee('Lot ini dibuat oleh akun admin yang sama.');
        $response->assertDontSee('Pasang Bid');

        $response = $this->actingAs($admin)
            ->withSession([
                'otp_verified_user_id' => $admin->id,
                'superadmin_view_mode' => 'PEMBELI',
            ])
            ->post(route('bid.store', $lot), [
                'jumlah_bid' => 110_000,
                'return_url' => route('ikans.show', $lot),
            ]);

        $response->assertRedirect(route('ikans.show', $lot));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('akun admin yang sama', (string) session('error'));

        $this->assertSame(0, Bid::query()->where('ikan_id', $lot->id)->count());
    }

    private function makeSuperAdmin(string $role = 'pembeli'): User
    {
        return User::factory()->create([
            'email' => 'sabiqmaftu@gmail.com',
            'role' => $role,
            'is_admin' => true,
            'user_status' => 'active',
        ]);
    }

    private function makeLot(User $seller, array $overrides = []): Ikan
    {
        return Ikan::create(array_merge([
            'user_id' => $seller->id,
            'nama_ikan' => 'Lot Uji Mode Admin',
            'berat' => 10,
            'estimasi_jumlah_ekor' => 20,
            'jenis_kemasan' => 'keranjang',
            'kondisi' => 'segar',
            'deskripsi' => 'Lot untuk test mode super admin',
            'tipe_lelang' => 'naik',
            'harga_awal' => 100_000,
            'harga_tertinggi' => 100_000,
            'minimal_increment' => 5_000,
            'buy_now_enabled' => false,
            'buy_now_price' => null,
            'anti_sniping_enabled' => true,
            'anti_sniping_window_seconds' => 120,
            'anti_sniping_extend_seconds' => 120,
            'anti_sniping_max_extensions' => 3,
            'anti_sniping_extensions_used' => 0,
            'waktu_mulai' => now()->subMinutes(10),
            'waktu_selesai' => now()->addHours(2),
            'status' => 'aktif',
            'auction_state' => 'AKTIF',
            'state_version' => 1,
        ], $overrides));
    }
}
